<?php

namespace App\Services\Deployments;

use App\Actions\EmitSiteEventAction;
use App\Actions\UpsertSiteFromDeploymentAction;
use App\DTO\PromoteToProductionData;
use App\Exceptions\DeploymentAlreadyRunningException;
use App\Exceptions\DeploymentScriptException;
use App\Models\DeploymentLog;
use App\Models\DeploymentRun;
use App\Models\Server;
use App\Models\Site;
use App\Models\SiteTarget;
use App\Models\User;
use App\Services\Themes\DeployThemeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PromoteToProductionService
{
    public function __construct(
        private readonly DeploymentRunGuardService $guard,
        private readonly UpsertSiteFromDeploymentAction $upsertSite,
        private readonly EmitSiteEventAction $emitSiteEvent,
        private readonly DeployThemeService $deployTheme,
    ) {}

    public function run(PromoteToProductionData $data): DeploymentRun
    {
        $user = User::query()->findOrFail($data->requestedBy);
        $this->authorizeLive($user, $data->mode);

        $site = Site::query()->with('targets.server')->findOrFail($data->siteId);
        if ($site->status !== Site::STATUS_STAGE) {
            throw new AuthorizationException('Only stage sites can be promoted.');
        }

        if ($site->profile_pipeline_enabled && $data->mode === 'live') {
            if (! filled($site->theme_git_ref)) {
                throw new InvalidArgumentException(
                    'Pipeline site has no theme_git_ref. Deploy theme to staging first (same ref will be promoted).'
                );
            }
        }

        $meta = [
            'site_id' => $data->siteId,
            'stage_domain' => $data->stageDomain,
            'prod_domain' => $data->prodDomain,
            'mode' => $data->mode,
            'pipeline' => $site->profile_pipeline_enabled ? 'v2' : 'v1_legacy',
        ];

        $run = DeploymentRun::create([
            'site_id' => $data->siteId,
            'action_type' => 'promote_to_prod',
            'mode' => $data->mode,
            'status' => 'running',
            'requested_by' => $data->requestedBy,
            'confirm_phrase_used' => $data->mode === 'live' ? $data->confirmPhrase : null,
            'started_at' => now(),
            'meta_json' => $meta,
        ]);

        $line = 1;
        $log = function (string $stream, string $msg) use ($run, &$line): void {
            DeploymentLog::create([
                'run_id' => $run->id,
                'stream' => $stream,
                'line_no' => $line++,
                'message' => $msg,
            ]);
        };

        $script = $data->mode === 'live'
            ? (string) config('deployment.promote_live_script')
            : (string) config('deployment.promote_dry_run_script');

        if ($script === '' || ! is_file($script) || ! is_executable($script)) {
            $log('stderr', 'Script missing or not executable: '.$script);
            $run->status = 'failed';
            $run->finished_at = now();
            $run->save();

            throw new DeploymentScriptException(
                'Promotion script is missing or not executable.',
                1,
                (int) $run->id,
            );
        }

        $serverHost = (string) env('PROMOTE_DEPLOYMENT_SERVER_HOST', 'local');
        $dbHost = (string) env('WP_DB_HOST', '127.0.0.1:3306');
        $dbRootPassword = (string) env('WP_DB_ROOT_PASSWORD', '');
        $wpSitesRoot = (string) env('WP_SITES_ROOT', '/var/www/www-root/data/www');
        $wpSiteUser = (string) env('WP_SITE_USER', 'www-root');

        $cmd = [
            'bash',
            $script,
            '--mode='.$data->mode,
            '--stage-domain='.$data->stageDomain,
            '--domain='.$data->prodDomain,
            '--db-host='.$dbHost,
            '--db-root-password='.$dbRootPassword,
            '--wp-sites-root='.$wpSitesRoot,
            '--wp-site-user='.$wpSiteUser,
        ];

        if ($data->mode === 'live') {
            $scopeKey = $this->guard->buildPromoteToProdScopeKey($serverHost, $data->stageDomain, $data->prodDomain);
            $run->lock_key = $scopeKey;
            $run->save();

            try {
                $this->guard->acquireOrFail(
                    $scopeKey,
                    (int) $run->id,
                    (int) $user->id,
                    (int) env('PROMOTE_TO_PROD_LOCK_TTL', 3600),
                );
            } catch (\RuntimeException $e) {
                $run->status = 'failed';
                $run->finished_at = now();
                $run->save();

                throw new DeploymentAlreadyRunningException($e->getMessage(), 0, $e);
            }
        }

        try {
            $stdoutLines = $this->executeScriptStreaming($cmd, $log);
            $exitCode = $stdoutLines['exit_code'];
            $jsonLine = $this->findLastJsonLine($stdoutLines['lines']);

            if ($data->mode === 'dry_run') {
                $run->status = ($exitCode === 0 && $this->jsonIndicatesSuccess($jsonLine, $data->prodDomain, true))
                    ? 'success'
                    : 'failed';
                $run->finished_at = now();
                $run->save();

                return $run->fresh();
            }

            if ($exitCode !== 0 || ! $this->jsonIndicatesSuccess($jsonLine, $data->prodDomain, false)) {
                $run->status = 'failed';
                $run->finished_at = now();
                $run->save();

                $this->emitSiteEvent->execute(
                    $data->siteId,
                    'promote_to_prod_failed',
                    ['run_id' => $run->id, 'exit_code' => $exitCode],
                    $data->requestedBy,
                );

                throw new DeploymentScriptException(
                    'Promotion script failed. See deployment logs for details.',
                    $exitCode !== 0 ? $exitCode : 1,
                    (int) $run->id,
                );
            }

            DB::transaction(function () use ($data, $run, $jsonLine): void {
                $this->upsertSite->execute($data->siteId, [
                    'domain' => $data->prodDomain,
                    'admin_url' => 'https://'.$data->prodDomain.'/wp-admin',
                    'status' => 'active',
                ]);

                $run->status = 'success';
                $run->finished_at = now();
                $run->save();

                $this->emitSiteEvent->execute(
                    $data->siteId,
                    'promote_to_prod_ok',
                    ['run_id' => $run->id, 'parsed' => $jsonLine],
                    $data->requestedBy,
                );
            });

            $site = Site::query()->with('targets.server')->findOrFail($data->siteId);
            if ($site->profile_pipeline_enabled) {
                $this->finalizePipelinePromote($site, $data, $run, $log);
            }

            return $run->fresh();
        } finally {
            if ($data->mode === 'live') {
                $this->guard->releaseByRunId((int) $run->id, 'terminal');
            }
        }
    }

    private function finalizePipelinePromote(Site $site, PromoteToProductionData $data, DeploymentRun $run, callable $log): void
    {
        $server = $site->stagingTarget()?->server
            ?? Server::query()->where('is_active', true)->orderBy('id')->first();

        if (! $server) {
            $log('stderr', 'No server for production target; skipping DeployTheme on promote.');

            return;
        }

        $prodTarget = $site->targets()
            ->where('kind', SiteTarget::KIND_PRODUCTION)
            ->where('domain', $data->prodDomain)
            ->first();

        if (! $prodTarget) {
            $docroot = $server->resolveDocroot($data->prodDomain);
            $prodTarget = SiteTarget::create([
                'site_id' => $site->id,
                'kind' => SiteTarget::KIND_PRODUCTION,
                'domain' => $data->prodDomain,
                'server_id' => $server->id,
                'docroot' => $docroot,
                'wp_path' => $docroot,
                'basic_auth' => false,
                'is_active' => true,
                'wp_config_pins_written' => false,
            ]);
            $log('stdout', 'Created production SiteTarget');
        // Promote script rewrites wp-config without FACTORY_SITE_* — force re-write pins.
        $prodTarget->wp_config_pins_written = false;
        $prodTarget->is_active = true;
        $prodTarget->save();

        // Deactivate staging optionally kept active for now
        $site->lifecycle = Site::LIFECYCLE_PRODUCTION;
        $site->scenario = $site->scenario ?: Site::SCENARIO_STAGE_THEN_PROD;
        $site->save();

        $themeRun = $this->deployTheme->queue([
            'site_id' => $site->id,
            'git_ref' => (string) $site->theme_git_ref,
            'targets' => 'production',
            'force_rebuild' => false,
            'mode' => 'live',
            'requested_by' => $data->requestedBy,
            'skip_smoke' => true, // prod DNS/TLS may lag; content+theme already rsynced
        ]);
        $log('stdout', 'DeployTheme to production run #'.$themeRun->id.' (same ref '.$site->theme_git_ref.')');
        $themeRun = $this->deployTheme->executeRun($themeRun);
        $meta = $run->meta_json ?? [];
        $meta['theme_run_id'] = $themeRun->id;
        $meta['theme_status'] = $themeRun->status;
        $themeFail = (string) (($themeRun->meta_json ?? [])['failure_reason'] ?? '');
        if ($themeFail !== '') {
            $meta['theme_failure_reason'] = $themeFail;
            $log('stderr', 'DeployTheme failure: '.$themeFail);
        }
        $run->meta_json = $meta;
        if ($themeRun->status !== 'success') {
            $run->status = 'failed';
            $log('stderr', 'Promote content OK but DeployTheme to production failed — see run #'.$themeRun->id);
        } else {
            $log('stdout', 'DeployTheme to production OK (smoke skipped on promote)');
        }
        $run->save();
    }

    /**
     * @param  list<string>  $cmd
     * @return array{lines: list<string>, exit_code: int}
     */
    private function executeScriptStreaming(array $cmd, callable $log): array
    {
        $escaped = array_map(static fn (string $part): string => escapeshellarg($part), $cmd);
        $commandLine = implode(' ', $escaped);

        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($commandLine, $descriptorspec, $pipes, base_path(), null);
        if (! is_resource($process)) {
            return ['lines' => [], 'exit_code' => -1];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdoutBuffer = '';
        $stderrBuffer = '';
        $linesOut = [];

        $flushLines = function (string &$buffer, string $stream) use ($log, &$linesOut): void {
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $line = rtrim($line, "\r");
                if ($line === '') {
                    continue;
                }
                $log($stream, $line);
                if ($stream === 'stdout') {
                    $linesOut[] = $line;
                }
            }
        };

        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            $changed = @stream_select($read, $write, $except, 1, 0);
            if ($changed === false) {
                $status = proc_get_status($process);
                if (! $status['running']) {
                    break;
                }

                continue;
            }
            foreach ($read as $pipe) {
                if ($pipe === $pipes[1]) {
                    $chunk = (string) fread($pipes[1], 8192);
                    if ($chunk !== '') {
                        $stdoutBuffer .= $chunk;
                        $flushLines($stdoutBuffer, 'stdout');
                    }
                } elseif ($pipe === $pipes[2]) {
                    $chunk = (string) fread($pipes[2], 8192);
                    if ($chunk !== '') {
                        $stderrBuffer .= $chunk;
                        $flushLines($stderrBuffer, 'stderr');
                    }
                }
            }

            $status = proc_get_status($process);
            if (! $status['running']) {
                break;
            }
        }

        $stdoutBuffer .= (string) stream_get_contents($pipes[1]);
        $stderrBuffer .= (string) stream_get_contents($pipes[2]);
        $flushLines($stdoutBuffer, 'stdout');
        $flushLines($stderrBuffer, 'stderr');

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return ['lines' => $linesOut, 'exit_code' => $exitCode];
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, mixed>|null
     */
    private function findLastJsonLine(array $lines): ?array
    {
        $last = null;
        foreach ($lines as $line) {
            $trim = ltrim($line);
            if (str_starts_with($trim, '{')) {
                $decoded = json_decode($trim, true);
                if (is_array($decoded)) {
                    $last = $decoded;
                }
            }
        }

        return $last;
    }

    private function jsonIndicatesSuccess(?array $json, string $prodDomain, bool $dryRun): bool
    {
        if ($json === null) {
            return false;
        }
        if (($json['status'] ?? null) !== 'success') {
            return false;
        }
        if (($json['domain'] ?? null) !== $prodDomain) {
            return false;
        }
        if ($dryRun && ($json['mode'] ?? null) !== 'dry_run') {
            return false;
        }

        return true;
    }

    private function authorizeLive(User $user, string $mode): void
    {
        if ($mode === 'live' && ! in_array($user->role, ['owner', 'dev'], true)) {
            throw new AuthorizationException('Live promotion is restricted to owner/dev.');
        }
    }
}
