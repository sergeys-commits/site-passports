<?php

namespace App\Jobs;

use App\Models\DeploymentRun;
use App\Services\Themes\DeployThemeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeployThemeJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(
        public int $runId,
    ) {}

    public function handle(DeployThemeService $service): void
    {
        $run = DeploymentRun::query()->findOrFail($this->runId);
        $service->executeRun($run);
    }
}
