<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SiteEvent;
use App\Models\DeploymentRun;
use App\Models\DeploymentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;


class DeploymentConsoleController extends Controller
{
public function onboardProdForm()
{
return view('deployments.onboard-prod-new');
}

public function stageProvisionForm()
{
return view('deployments.stage-provision-new');
}

public function onboardProdStore(Request $request)
{
$data = $request->validate([
'name' => 'required|string|max:190',
'domain' => 'required|string|max:190|unique:sites,domain',
'stage_domain' => 'nullable|string|max:190',
'theme_name' => 'nullable|string|max:190',
'theme_version' => 'nullable|string|max:50',
'status' => 'required|in:active,stage,archived',
'launch_date' => 'nullable|date',
'transfer_date' => 'nullable|date',
'notes' => 'nullable|string',
]);

$site = Site::create($data);

SiteEvent::create([
'site_id' => $site->id,
'event_type' => 'site_onboarded',
'source' => 'manual',
'payload' => $data,
'created_by' => auth()->id(),
]);

return redirect()->route('sites.show', $site->id)->with('ok', 'Prod site onboarded');
}

    public function stageProvisionStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:190',
            'stage_domain' => 'required|string|max:190',
            'mode' => 'required|in:dry_run,live',
            'confirm_phrase' => 'nullable|string|max:100',
        ]);

// Live пока защищён
        if ($data['mode'] === 'live') {
            if (($data['confirm_phrase'] ?? null) !== 'CONFIRM STAGE LIVE') {
                return back()->withErrors(['confirm_phrase' => 'Нужна точная фраза CONFIRM STAGE LIVE'])->withInput();
            }
            return back()->withErrors(['mode' => 'Live пока отключен на этом шаге'])->withInput();
        }

        $run = DeploymentRun::create([
            'site_id' => null,
            'action_type' => 'stage_provision',
            'mode' => 'dry_run',
            'status' => 'running',
            'requested_by' => auth()->id(),
            'started_at' => now(),
            'meta_json' => [
                'name' => $data['name'],
                'stage_domain' => $data['stage_domain'],
            ],
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

        $log('system', 'Deployment Console started');
        $log('stdout', 'Action: stage_provision');
        $log('stdout', 'Mode: dry_run');
        $log('stdout', 'Target stage domain: '.$data['stage_domain']);

// TODO: замени путь на твой реальный dry-run entrypoint
        $cmd = [
            'bash',
            '-lc',
            'echo "[dry-run] stage provision start"; echo "name='.$data['name'].'"; echo "stage_domain='.$data['stage_domain'].'"; echo "[dry-run] done"'
        ];

        $result = Process::timeout(120)->run($cmd);

        foreach (preg_split("/\\r\\n|\\n|\\r/", trim($result->output())) as $row) {
            if ($row !== '') $log('stdout', $row);
        }
        foreach (preg_split("/\\r\\n|\\n|\\r/", trim($result->errorOutput())) as $row) {
            if ($row !== '') $log('stderr', $row);
        }

        $run->update([
            'status' => $result->successful() ? 'success' : 'failed',
            'finished_at' => now(),
        ]);

        return redirect()->route('deployments.runs.show', $run->id)
            ->with('ok', $result->successful() ? 'Dry-run completed' : 'Dry-run failed');
    }


public function runShow(DeploymentRun $run)
{
$run->load(['requester','logs' => fn($q) => $q->orderBy('line_no')]);
return view('deployments.run-show', compact('run'));
}
}
