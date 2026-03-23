<?php

namespace App\Http\Controllers\Deployments;

use App\DTO\ThemeUpdateData;
use App\Http\Controllers\Controller;
use App\Http\Requests\ThemeUpdateRequest;
use App\Models\DeploymentRun;
use App\Models\Site;
use App\Services\Deployments\ThemeUpdateService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class ThemeUpdateController extends Controller
{
    public function create(): View
    {
        $stageSites = Site::query()
            ->with('siteGroup')
            ->where('status', Site::STATUS_STAGE)
            ->orderBy('name')
            ->get();

        $prodSites = Site::query()
            ->with('siteGroup')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $tags = [];

        return view('deployments.theme-update.create', compact('stageSites', 'prodSites', 'tags'));
    }

    public function run(ThemeUpdateRequest $request, ThemeUpdateService $service): RedirectResponse
    {
        $validated = $request->validated();
        $userId = (int) $request->user()->id;

        $results = [];

        try {
            foreach ($validated['site_ids'] as $siteId) {
                $siteId = (int) $siteId;

                try {
                    $data = new ThemeUpdateData(
                        siteId: $siteId,
                        targetVersion: (string) $validated['target_version'],
                        environment: (string) $validated['environment'],
                        mode: (string) $validated['mode'],
                        requestedBy: $userId,
                    );

                    $run = $service->run($data);
                    $results[] = $this->resultRowFromRun($siteId, $run);
                } catch (InvalidArgumentException $e) {
                    $site = Site::query()->find($siteId);
                    $results[] = [
                        'site_id' => $siteId,
                        'site_name' => $site?->name,
                        'run_id' => null,
                        'status' => 'skipped',
                        'version' => null,
                        'message' => $e->getMessage(),
                    ];
                } catch (\Throwable $e) {
                    $site = Site::query()->find($siteId);
                    $results[] = [
                        'site_id' => $siteId,
                        'site_name' => $site?->name,
                        'run_id' => null,
                        'status' => 'failed',
                        'version' => null,
                        'message' => $e->getMessage(),
                    ];
                }
            }
        } catch (AuthorizationException $e) {
            return back()->withErrors(['deploy' => $e->getMessage()])->withInput();
        }

        $success = count(array_filter($results, fn (array $r): bool => $r['status'] === 'success'));
        $failed = count(array_filter($results, fn (array $r): bool => $r['status'] === 'failed'));
        $skipped = count(array_filter($results, fn (array $r): bool => $r['status'] === 'skipped'));
        $cancelled = count(array_filter($results, fn (array $r): bool => $r['status'] === 'cancelled'));

        $summary = "Theme update: {$success} succeeded, {$failed} failed, {$skipped} skipped, {$cancelled} cancelled.";

        return redirect()
            ->route('deployments.index')
            ->with('theme_update_results', $results)
            ->with('ok', $summary);
    }

    /**
     * @return array{site_id: int, site_name: ?string, run_id: ?int, status: string, version: ?string, message: ?string}
     */
    private function resultRowFromRun(int $siteId, DeploymentRun $run): array
    {
        $run->loadMissing('site');
        $site = $run->site;
        $meta = $run->meta_json ?? [];
        $parsed = is_array($meta['parsed'] ?? null) ? $meta['parsed'] : [];

        $status = $run->status === 'success' ? 'success' : 'failed';
        if (($meta['failure_reason'] ?? null) === 'lock_busy') {
            $status = 'cancelled';
        }

        $version = null;
        if ($run->status === 'success') {
            $version = $run->mode === 'live'
                ? (string) ($parsed['version'] ?? '')
                : (string) ($parsed['current_version'] ?? '');
        }

        $message = null;
        if ($run->status !== 'success') {
            if (($meta['failure_reason'] ?? null) === 'lock_busy') {
                $message = 'Another deployment is active for this site path.';
            } else {
                $message = (string) ($parsed['message'] ?? 'Run finished with status '.$run->status);
            }
        }

        return [
            'site_id' => $siteId,
            'site_name' => $site?->name,
            'run_id' => (int) $run->id,
            'status' => $status,
            'version' => $version !== '' ? $version : null,
            'message' => $message,
        ];
    }
}
