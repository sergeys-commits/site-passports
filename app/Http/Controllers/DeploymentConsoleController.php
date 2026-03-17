<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SiteEvent;
use App\Models\DeploymentRun;
use Illuminate\Http\Request;
use App\Http\Requests\Deployments\StageProvisionRunRequest;
use App\Services\Deployments\StageProvisionService;

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

public function stageProvisionStore(StageProvisionRunRequest $request, StageProvisionService $service)
{
$run = $service->execute($request->validated(), $request->user());

if ($run->mode === 'live' && $run->status === 'success' && $run->site_id) {
return redirect()->route('sites.show', $run->site_id)->with('ok', 'Stage live provision completed');
}

return redirect()->route('deployments.runs.show', $run->id)->with('ok', 'Run finished: '.$run->status);
}

public function runShow(DeploymentRun $run)
{
$run->load(['requester','logs' => fn($q) => $q->orderBy('line_no')]);
return view('deployments.run-show', compact('run'));
}
}
