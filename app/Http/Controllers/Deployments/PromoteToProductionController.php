<?php

namespace App\Http\Controllers\Deployments;

use App\DTO\PromoteToProductionData;
use App\Exceptions\DeploymentAlreadyRunningException;
use App\Exceptions\DeploymentScriptException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromoteToProductionRequest;
use App\Models\Site;
use App\Services\Deployments\PromoteToProductionService;
use Illuminate\Auth\Access\AuthorizationException;

class PromoteToProductionController extends Controller
{
    public function create()
    {
        $sites = Site::query()
            ->where('status', Site::STATUS_STAGE)
            ->orderBy('name')
            ->get(['id', 'name', 'stage_domain']);

        return view('deployments.promote.create', compact('sites'));
    }

    public function store(PromoteToProductionRequest $request, PromoteToProductionService $service)
    {
        $validated = $request->validated();

        $data = new PromoteToProductionData(
            siteId: (int) $validated['site_id'],
            stageDomain: (string) $validated['stage_domain'],
            prodDomain: (string) $validated['prod_domain'],
            mode: (string) $validated['mode'],
            requestedBy: (int) $request->user()->id,
            confirmPhrase: (string) ($validated['confirm_phrase'] ?? ''),
        );

        try {
            $run = $service->run($data);
        } catch (DeploymentAlreadyRunningException $e) {
            return back()->withErrors(['deploy' => $e->getMessage()])->withInput();
        } catch (AuthorizationException $e) {
            return back()->withErrors(['deploy' => $e->getMessage()])->withInput();
        } catch (DeploymentScriptException $e) {
            if ($e->deploymentRunId !== null) {
                return redirect()
                    ->route('deployments.runs.show', $e->deploymentRunId)
                    ->withErrors(['deploy' => $e->getMessage()]);
            }

            return back()->withErrors(['deploy' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('deployments.runs.show', $run->id)
            ->with('ok', 'Promotion run finished: '.$run->status);
    }
}
