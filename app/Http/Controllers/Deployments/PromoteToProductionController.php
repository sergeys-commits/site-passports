<?php

namespace App\Http\Controllers\Deployments;

use App\DTO\PromoteToProductionData;
use App\Exceptions\DeploymentAlreadyRunningException;
use App\Exceptions\DeploymentScriptException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromoteToProductionRequest;
use App\Models\Server;
use App\Models\Site;
use App\Services\Deployments\PromoteToProductionService;
use Illuminate\Auth\Access\AuthorizationException;

class PromoteToProductionController extends Controller
{
    public function create()
    {
        $sites = Site::query()
            ->with(['targets.server'])
            ->where('status', Site::STATUS_STAGE)
            ->orderBy('name')
            ->get(['id', 'name', 'stage_domain']);

        $servers = Server::query()->where('is_active', true)->orderBy('name')->get();

        return view('deployments.promote.create', compact('sites', 'servers'));
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
            serverId: isset($validated['server_id']) ? (int) $validated['server_id'] : null,
        );

        try {
            $run = $service->run($data);
        } catch (DeploymentAlreadyRunningException $e) {
            return back()->withErrors(['deploy' => $e->getMessage()])->withInput();
        } catch (AuthorizationException $e) {
            return back()->withErrors(['deploy' => $e->getMessage()])->withInput();
        } catch (\InvalidArgumentException $e) {
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
