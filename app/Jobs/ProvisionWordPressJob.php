<?php

namespace App\Jobs;

use App\Models\DeploymentRun;
use App\Services\Deployments\ProvisionWordPressService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProvisionWordPressJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 2400;

    public function __construct(
        public int $runId,
    ) {}

    public function handle(ProvisionWordPressService $service): void
    {
        $run = DeploymentRun::query()->findOrFail($this->runId);
        $service->executeRun($run);
    }
}
