<?php

namespace App\Services\Themes;

use App\Actions\EmitSiteEventAction;
use App\Actions\UpdateSiteThemeVersionAction;
use App\Models\DeploymentLog;
use App\Models\DeploymentRun;
use App\Models\Site;
use App\Models\SiteTarget;
use App\Models\User;
use App\Services\Deployments\DeploymentRunGuardService;
use App\Services\Sites\SitePipelineMigrationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class DeployThemeService
{
    public function __construct(
        private readonly ThemeRefResolver $refs,
        private readonly ThemeBuildService $build,
        private readonly ThemeDeployUploadService $upload,
        private readonly SitePipelineMigrationService $migration,
        private readonly DeploymentRunGuardService $guard,
        private readonly UpdateSiteThemeVersionAction $updateThemeVersion,
        private readonly EmitSiteEventAction $emitSiteEvent,
    ) {}

    /**
     * Create a queued run and return it (job will process).
     *
     * @param  array{site_id:int,git_ref:string,targets?:string,force_rebuild?:bool,mode?:string,requested_by:int}  $data
     */
    public function queue(array $data): DeploymentRun
    {
        $user = User::query()->findOrFail($data['requested_by']);
        if (! in_array($user->role, ['owner', 'dev'], true) && ($data['mode'] ?? 'live') === 'live') {
            throw new AuthorizationException('Only owner/dev can run live DeployTheme.');
        }

        $site = Site::query()->findOrFail($data['site_id']);

        return DeploymentRun::create([
            'site_id' => $site->id,
            'action_type' => 'deploy_theme',
            'mode' => $data['mode'] ?? 'live',
            'status' => 'queued',
            'requested_by' => $user->id,
            'started_at' => null,
            'meta_json' => [
                'git_ref' => $data['git_ref'],
                'targets' => $data['targets'] ?? 'all_active',
                'force_rebuild' => (bool) ($data['force_rebuild'] ?? false),
                'skip_smoke' => (bool) ($data['skip_smoke'] ?? false),
                'pipeline' => 'v2',
            ],
        ]);
    }

    public function executeRun(DeploymentRun $run): DeploymentRun
    {
        $run->status = 'running';
        $run->started_at = $run->started_at ?? now();
        $run->save();

        $line = 1;
        $log = function (string $stream, string $msg) use ($run, &$line): void {
            DeploymentLog::create([
                'run_id' => $run->id,
                'stream' => $stream,
                'line_no' => $line++,
                'message' => $msg,
            ]);
        };

        $meta = $run->meta_json ?? [];
        $gitRef = (string) ($meta['git_ref'] ?? '');
        $targetPolicy = (string) ($meta['targets'] ?? 'all_active');
        $forceRebuild = (bool) ($meta['force_rebuild'] ?? false);
        $skipSmoke = (bool) ($meta['skip_smoke'] ?? false);

        try {
            $site = Site::query()->findOrFail($run->site_id);
            $site = $this->migration->ensureMigrated($site, $log);

            $resolved = $this->refs->resolve($gitRef);
            $log('stdout', 'Resolved ref '.$gitRef.' → '.$resolved['sha'].' (legacy='.($resolved['is_legacy'] ? 'yes' : 'no').')');

            if ($resolved['is_legacy']) {
                throw new InvalidArgumentException('DeployTheme is for theme major >= 2. Use legacy theme update for 1.x.');
            }

            $targets = $this->selectTargets($site, $targetPolicy);
            if ($targets->isEmpty()) {
                throw new RuntimeException('No matching active targets for policy: '.$targetPolicy);
            }

            $first = $targets->first();
            $scopeKey = $this->guard->buildThemeUpdateScopeKey(
                (string) $first->server_id,
                $first->domain,
            );
            $run->lock_key = $scopeKey;
            $run->save();
            $this->guard->acquireOrFail(
                $scopeKey,
                (int) $run->id,
                (int) $run->requested_by,
                (int) config('deployment.theme_update_lock_ttl', 900),
            );

            $this->migration->writePinsForTargets($site, $targets, $log);

            $artifact = $this->build->buildOrGet(
                $site,
                $resolved['ref'],
                $resolved['sha'],
                $forceRebuild,
                $log,
            );

            foreach ($targets as $target) {
                $target->loadMissing('server');
                $this->upload->upload($artifact, $site, $target, $log);
                $this->upload->activateTheme($site, $target, $log);
                $smoke = $this->upload->smokeCheck($site, $target);
                $log($smoke['ok'] ? 'stdout' : 'stderr', $smoke['message'].($skipSmoke && ! $smoke['ok'] ? ' (skip_smoke=1, non-fatal)' : ''));
                if (! $smoke['ok'] && ! $skipSmoke) {
                    throw new RuntimeException($smoke['message']);
                }
            }

            DB::transaction(function () use ($site, $resolved, $artifact, $run) {
                $site->theme_git_ref = $resolved['ref'];
                $site->last_build_meta = $artifact->build_meta;
                $site->profile_pipeline_enabled = true;
                $site->save();

                $this->updateThemeVersion->execute(
                    $site,
                    (string) ($resolved['tag'] ?? $resolved['sha']),
                    (int) $run->requested_by,
                );
            });

            $this->emitSiteEvent->execute(
                (int) $site->id,
                'theme_deployed',
                [
                    'git_ref' => $resolved['ref'],
                    'git_sha' => $resolved['sha'],
                    'cache_key' => $artifact->cache_key,
                    'run_id' => $run->id,
                ],
                (int) $run->requested_by,
                'panel',
            );

            $run->status = 'success';
            $run->finished_at = now();
            $run->meta_json = array_merge($meta, [
                'git_sha' => $resolved['sha'],
                'cache_key' => $artifact->cache_key,
                'resolved_ref' => $resolved['ref'],
            ]);
            $run->save();
            $log('stdout', 'DeployTheme success');
        } catch (Throwable $e) {
            $log('stderr', $e->getMessage());
            $run->status = 'failed';
            $run->finished_at = now();
            $run->meta_json = array_merge($meta, ['failure_reason' => $e->getMessage()]);
            $run->save();
        } finally {
            try {
                $this->guard->releaseByRunId((int) $run->id, 'terminal');
            } catch (Throwable) {
                // ignore
            }
        }

        return $run->fresh();
    }

    private function selectTargets(Site $site, string $policy)
    {
        $query = $site->targets()->with('server')->where('is_active', true);

        return match ($policy) {
            'staging' => $query->where('kind', SiteTarget::KIND_STAGING)->get(),
            'production' => $query->where('kind', SiteTarget::KIND_PRODUCTION)->get(),
            default => $query->get(),
        };
    }
}
