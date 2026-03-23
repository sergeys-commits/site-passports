<?php

namespace App\Actions;

use App\Models\Site;

class UpsertSiteFromDeploymentAction
{
    public function execute(int $siteId, array $attributes): Site
    {
        $site = Site::query()->findOrFail($siteId);
        $site->update($attributes);

        return $site->fresh();
    }
}
