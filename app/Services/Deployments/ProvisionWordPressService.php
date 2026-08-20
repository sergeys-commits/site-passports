<?php

namespace App\Services\Deployments;

use App\Jobs\ProvisionWordPressJob;
use App\Models\DeploymentLog;
use App\Models\DeploymentRun;
use App\Models\Site;
use App\Models\SiteEvent;
use App\Models\SiteTarget;
use App\Models\User;
use App\Services\Themes\DeployThemeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ProvisionWordPressService
{
    public function __construct(
        private readonly DeploymentRunGuardService $guard,
        private readonly DeployThemeService $deployTheme,
    ) {}

    /**
     * Queue live v2 provision for a pipeline site (scenario A or B).
     *
     * @param  array{site_id:int,git_ref:string,mode?:string,requested_by:int}  $data
     */
    public function queue(array $data): DeploymentRun
    {
        $user = User::query()->findOrFail($data['requested_by']);
        if (($data['mode'] ?? 'live') === 'live' && ! in_array($user->role, ['owner', 'dev'], true)) {
            throw new AuthorizationException('Live provision restricted to owner/dev.');
        }

        $site = Site::query()->with('targets')->findOrFail($data['site_id']);
        if (! $site->profile_pipeline_enabled) {
            throw new InvalidArgumentException('Site is not on profile pipeline. Use legacy stage provision.');
        }

        $run = DeploymentRun::create([
            'site_id' => $site->id,
            'action_type' => 'provision_wordpress',
            'mode' => $data['mode'] ?? 'live',
            'status' => 'queued',
            'requested_by' => $user->id,
            'meta_json' => [
                'git_ref' => $data['git_ref'] ?? 'latest',
                'scenario' => $site->scenario,
                'pipeline' => 'v2',
            ],
        ]);

        if (($data['mode'] ?? 'live') === 'live') {
            ProvisionWordPressJob::dispatch($run->id);
        }

        return $run;
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

        try {
            $site = Site::query()->with(['targets.server'])->findOrFail($run->site_id);
            $target = $this->primaryTarget($site);
            $server = $target->server;

            $scopeKey = $this->guard->buildScopeKey((string) $server->id, $target->domain);
            $run->lock_key = $scopeKey;
            $run->save();
            $this->guard->acquireOrFail($scopeKey, (int) $run->id, (int) $run->requested_by, 1800);

            if ($server->isLocal()) {
                $this->provisionLocalWp($site, $target, $log);
            } else {
                throw new RuntimeException(
                    'Remote SSH WordPress provision is not fully automated yet. '.
                    'Create WP on remote, then DeployTheme. Local Server is supported for provision v2. '.
                    '(server_id='.$server->id.', connection='.$server->getAttribute('connection').')'
                );
            }

            // Deploy theme via nested run
            $gitRef = (string) ($meta['git_ref'] ?? $site->theme_git_ref ?? 'latest');
            $themeRun = $this->deployTheme->queue([
                'site_id' => $site->id,
                'git_ref' => $gitRef,
                'targets' => $target->kind === SiteTarget::KIND_STAGING ? 'staging' : 'production',
                'mode' => 'live',
                'requested_by' => (int) $run->requested_by,
            ]);
            $log('stdout', 'Starting DeployTheme run #'.$themeRun->id);
            $themeRun = $this->deployTheme->executeRun($themeRun);
            if ($themeRun->status !== 'success') {
                throw new RuntimeException('DeployTheme failed during provision (run #'.$themeRun->id.')');
            }

            SiteEvent::create([
                'site_id' => $site->id,
                'event_type' => 'provision_wordpress_success',
                'source' => 'panel',
                'payload' => [
                    'run_id' => $run->id,
                    'theme_run_id' => $themeRun->id,
                    'domain' => $target->domain,
                ],
                'created_by' => $run->requested_by,
            ]);

            $run->status = 'success';
            $run->finished_at = now();
            $run->meta_json = array_merge($meta, [
                'theme_run_id' => $themeRun->id,
                'domain' => $target->domain,
            ]);
            $run->save();
            $log('stdout', 'ProvisionWordPress success');
        } catch (Throwable $e) {
            $log('stderr', $e->getMessage());
            $run->status = 'failed';
            $run->finished_at = now();
            $run->meta_json = array_merge($meta, ['failure_reason' => $e->getMessage()]);
            $run->save();
        } finally {
            $this->guard->releaseByRunId((int) $run->id, 'terminal');
        }

        return $run->fresh();
    }

    private function primaryTarget(Site $site): SiteTarget
    {
        if ($site->scenario === Site::SCENARIO_PROD_BASIC_AUTH) {
            $t = $site->productionTarget();
        } else {
            $t = $site->stagingTarget() ?? $site->productionTarget();
        }

        if (! $t) {
            throw new RuntimeException('Site has no active target for provision.');
        }

        return $t->loadMissing('server');
    }

    private function provisionLocalWp(Site $site, SiteTarget $target, callable $log): void
    {
        $script = (string) (env('STAGE_PROVISION_LIVE_SCRIPT') ?: base_path('scripts/provision_stage_live.sh'));
        if (! is_file($script) || ! is_executable($script)) {
            throw new RuntimeException('Stage provision script missing: '.$script);
        }

        $domain = $target->domain;
        $cmd = [
            'bash',
            $script,
            '--mode=live',
            '--site-name='.$site->name,
            '--domain='.($site->domain ?? $domain),
            '--stage-domain='.$domain,
            '--cms=wordpress',
            '--template=default',
            '--server-host=local',
            '--skip-theme=1',
        ];

        // For scenario B, stage-domain arg is still the WP docroot domain
        if ($target->kind === SiteTarget::KIND_PRODUCTION) {
            $cmd = [
                'bash',
                $script,
                '--mode=live',
                '--site-name='.$site->name,
                '--domain='.$domain,
                '--stage-domain='.$domain,
                '--cms=wordpress',
                '--template=default',
                '--server-host=local',
                '--skip-theme=1',
            ];
        }

        $log('stdout', 'Running provision script with --skip-theme=1');
        $result = Process::timeout(1800)->run($cmd);
        foreach (preg_split("/\r\n|\n|\r/", trim($result->output())) as $row) {
            if ($row !== '') {
                $log('stdout', $row);
            }
        }
        foreach (preg_split("/\r\n|\n|\r/", trim($result->errorOutput())) as $row) {
            if ($row !== '') {
                $log('stderr', $row);
            }
        }

        if (! $result->successful()) {
            throw new RuntimeException('Provision script failed.');
        }

        $lines = preg_split('/\r\n|\n|\r/', trim($result->output())) ?: [];
        $parsed = json_decode((string) end($lines), true) ?? [];
        if (($parsed['admin_password'] ?? null)) {
            $site->wp_admin_password = $parsed['admin_password'];
        }
        if ($target->kind === SiteTarget::KIND_STAGING) {
            $site->stage_admin_url = 'https://'.$domain.'/wp-admin';
            $site->status = 'stage';
            $site->lifecycle = Site::LIFECYCLE_STAGING;
        } else {
            $site->admin_url = 'https://'.$domain.'/wp-admin';
            $site->status = 'active';
            $site->lifecycle = Site::LIFECYCLE_PRODUCTION;
        }
        $site->save();
    }
}
