<?php

namespace App\Actions;

use App\Models\SiteEvent;

class EmitSiteEventAction
{
    public function execute(
        int $siteId,
        string $eventType,
        array $payload,
        int $createdBy,
        string $source = 'deployment_console',
    ): void {
        SiteEvent::create([
            'site_id' => $siteId,
            'event_type' => $eventType,
            'source' => $source,
            'payload' => $payload,
            'created_by' => $createdBy,
        ]);
    }
}
