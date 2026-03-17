<?php

namespace App\Services\Deployments;

use App\Models\DeploymentLog;
use App\Models\DeploymentRun;
use App\Models\Site;
use App\Models\SiteEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

class StageProvisionService
{
public function __construct(private readonly DeploymentRunGuardService $guard) {}

public function execute(array $data, User $user): DeploymentRun
{
$this->authorizeScope($user, $data);

$run = DeploymentRun::create([
'site_id' => null,
'action_type' => 'stage_provision',
'mode' => $data['mode'],
'status' => 'running',
'requested_by' => $user->id,
'confirm_phrase_used' => $data['mode'] === 'live' ? ($data['confirm_phrase'] ?? null) : null,
'started_at' => now(),
'meta_json' => $data,
]);

$line = 1;
$log = function (string $stream, string $msg) use ($run, &$line) {
DeploymentLog::create([
'run_id' => $run->id,
'stream' => $stream,
'line_no' => $line++,
'message' => $msg,
]);
};

if ($data['mode'] === 'live') {
$scopeKey = $this->guard->buildScopeKey((string)($data['server_host'] ?? 'local'), (string)($data['stage_domain'] ?? ''));
$run->lock_key = $scopeKey;
$run->save();
$this->guard->acquireOrFail($scopeKey, (int)$run->id, (int)$user->id, (int)config('deploy.stage_provision_lock_ttl', 900));
}

try {
$script = config('app.stage_provision_dry_run_script') ?: env('STAGE_PROVISION_DRY_RUN_SCRIPT');

if (!$script || !is_file($script) || !is_executable($script)) {
$log('stderr', 'Script missing or not executable: '.$script);
$run->status = 'failed';
$run->finished_at = now();
$run->save();
return $run->fresh();
}

$cmd = [
'bash',
$script,
'--mode='.$data['mode'],
'--site-name='.$data['name'],
'--domain='.($data['domain'] ?? ''),
'--stage-domain='.$data['stage_domain'],
'--cms='.($data['cms'] ?? 'wordpress'),
'--template='.($data['template'] ?? 'default'),
'--server-host='.($data['server_host'] ?? 'local'),
];

$result = Process::timeout(1800)->run($cmd);

foreach (preg_split("/\r\n|\n|\r/", trim($result->output())) as $row) {
if ($row !== '') $log('stdout', $row);
}
foreach (preg_split("/\r\n|\n|\r/", trim($result->errorOutput())) as $row) {
if ($row !== '') $log('stderr', $row);
}

if ($data['mode'] === 'dry_run') {
// dry_run invariant: no business writes
$run->status = $result->successful() ? 'success' : 'failed';
$run->finished_at = now();
$run->save();
return $run->fresh();
}

if ($result->successful()) {
DB::transaction(function () use ($data, $run, $user): void {
$site = Site::updateOrCreate(
['stage_domain' => $data['stage_domain']],
['name' => $data['name'], 'domain' => $data['domain'] ?? $data['name'], 'status' => 'stage']
);

$run->site_id = $site->id;
$run->status = 'success';
$run->finished_at = now();
$run->save();

SiteEvent::create([
'site_id' => $site->id,
'event_type' => 'stage_provision_live_ok',
'source' => 'deployment_console',
'payload' => ['run_id' => $run->id],
'created_by' => $user->id,
]);
});
} else {
$run->status = 'failed';
$run->finished_at = now();
$run->save();
}

return $run->fresh();
} finally {
if ($data['mode'] === 'live') {
$this->guard->releaseByRunId((int)$run->id, 'terminal');
}
}
}

private function authorizeScope(User $user, array $data): void
{
if (($data['mode'] ?? 'dry_run') === 'live' && !in_array($user->role, ['owner', 'dev'], true)) {
throw new AuthorizationException('Live provision is restricted to owner/dev.');
}
}
}
