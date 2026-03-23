<?php

namespace App\Services\Deployments;

use App\Actions\EmitSiteEventAction;
use App\Actions\UpdateSiteThemeVersionAction;
use App\DTO\ThemeUpdateData;
use App\Models\DeploymentLog;
use App\Models\DeploymentRun;
use App\Models\Site;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

class ThemeUpdateService
{
    public function __construct(
        private readonly DeploymentRunGuardService $guard,
        private readonly UpdateSiteThemeVersionAction $updateThemeVersion,
        private readonly EmitSiteEventAction $emitSiteEvent,
    ) {}

    public function run(ThemeUpdateData $data): DeploymentRun
    {
        $user = User::query()->findOrFail($data->requestedBy);
        $this->authorizeLive($user, $data->mode);

        $site = Site::query()->with('siteGroup')->findOrFail($data->siteId);

        if ($data->environment === 'stage' && $site->status !== Site::STATUS_STAGE) {
            throw new InvalidArgumentException('Site status must be stage for environment=stage.');
        }
        if ($data->environment === 'prod' && $site->status !== 'active') {
            throw new InvalidArgumentException('Site status must be active for environment=prod.');
        }

        $siteDomain = $data->environment === 'stage'
            ? (string) ($site->stage_domain ?? '')
            : (string) ($site->domain ?? '');

        if ($siteDomain === '') {
            throw new InvalidArgumentException(
                $data->environment === 'stage'
                    ? 'Site has no stage_domain.'
                    : 'Site has no domain.',
            );
        }

        $themeName = $site->siteGroup?->theme_name ?? 'wp-theme-core';

        $meta = [
            'site_id' => $site->id,
            'target_version' => $data->targetVersion,
            'environment' => $data->environment,
        ];

        $run = DeploymentRun::create([
            'site_id' => $site->id,
            'action_type' => 'theme_update',
            'mode' => $data->mode,
            'status' => 'running',
            'requested_by' => $data->requestedBy,
            'confirm_phrase_used' => null,
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

        $serverHost = (string) config('deployment.theme_update_server_host', 'local');

        if ($data->mode === 'live') {
            $scopeKey = $this->guard->buildThemeUpdateScopeKey($serverHost, $siteDomain);
            $run->lock_key = $scopeKey;
            $run->save();

            try {
                $this->guard->acquireOrFail(
                    $scopeKey,
                    (int) $run->id,
                    (int) $user->id,
                    (int) config('deployment.theme_update_lock_ttl', 900),
                );
            } catch (RuntimeException $e) {
                $log('stderr', $e->getMessage());
                $run->status = 'failed';
                $run->finished_at = now();
                $run->meta_json = array_merge($meta, [
                    'failure_reason' => 'lock_busy',
                    'parsed' => null,
                ]);
                $run->save();

                return $run->fresh();
            }
        }

        try {
            $script = $data->mode === 'live'
                ? (string) config('deployment.theme_update_live_script', '')
                : (string) config('deployment.theme_update_dry_run_script', '');

            if ($script === '' || ! is_file($script) || ! is_executable($script)) {
                $log('stderr', 'Script missing or not executable: '.$script);
                $run->status = 'failed';
                $run->finished_at = now();
                $run->save();
                $this->emitFailureIfLive($data, $site, $run, 'Script missing or not executable.');

                return $run->fresh();
            }

            $cmd = [
                'bash',
                $script,
                '--site-domain='.$siteDomain,
                '--theme-name='.$themeName,
                '--target-version='.$data->targetVersion,
            ];

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

            $parsed = $this->parseLastJsonObject(trim($result->output()));
            $run->meta_json = array_merge($meta, ['parsed' => $parsed]);
            $run->save();

            $ok = $result->successful() && is_array($parsed) && ($parsed['status'] ?? '') === 'success';

            if ($data->mode === 'dry_run') {
                $run->status = $ok ? 'success' : 'failed';
                $run->finished_at = now();
                $run->save();

                return $run->fresh();
            }

            if (! $ok) {
                $run->status = 'failed';
                $run->finished_at = now();
                $run->save();
                $msg = is_array($parsed) ? (string) ($parsed['message'] ?? 'Theme update script failed.') : 'Theme update script failed.';
                $this->emitSiteEvent->execute(
                    $site->id,
                    'theme_update_failed',
                    ['run_id' => $run->id, 'message' => $msg],
                    $data->requestedBy,
                );

                return $run->fresh();
            }

            $version = (string) ($parsed['version'] ?? '');
            if ($version === '') {
                $run->status = 'failed';
                $run->finished_at = now();
                $run->save();
                $this->emitSiteEvent->execute(
                    $site->id,
                    'theme_update_failed',
                    ['run_id' => $run->id, 'message' => 'Script success JSON missing version.'],
                    $data->requestedBy,
                );

                return $run->fresh();
            }

            DB::transaction(function () use ($site, $version, $data, $run): void {
                $this->updateThemeVersion->execute($site->fresh(), $version, $data->requestedBy);
                $run->status = 'success';
                $run->finished_at = now();
                $run->save();
            });

            return $run->fresh();
        } finally {
            if ($data->mode === 'live') {
                $this->guard->releaseByRunId((int) $run->id, 'terminal');
            }
        }
    }

    private function emitFailureIfLive(ThemeUpdateData $data, Site $site, DeploymentRun $run, string $message): void
    {
        if ($data->mode !== 'live') {
            return;
        }

        $this->emitSiteEvent->execute(
            $site->id,
            'theme_update_failed',
            ['run_id' => $run->id, 'message' => $message],
            $data->requestedBy,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseLastJsonObject(string $output): ?array
    {
        $lines = preg_split("/\r\n|\n|\r/", $output) ?: [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $trim = ltrim((string) $lines[$i]);
            if ($trim === '' || ! str_starts_with($trim, '{')) {
                continue;
            }
            $decoded = json_decode($trim, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function authorizeLive(User $user, string $mode): void
    {
        if ($mode === 'live' && ! in_array($user->role, ['owner', 'dev'], true)) {
            throw new AuthorizationException('Live theme update is restricted to owner/dev.');
        }
    }
}
