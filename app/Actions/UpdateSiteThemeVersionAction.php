<?php

namespace App\Actions;

use App\Models\Site;

class UpdateSiteThemeVersionAction
{
    public function __construct(
        private readonly EmitSiteEventAction $emitSiteEvent,
    ) {}

    public function execute(Site $site, string $version, int $createdBy): void
    {
        $site->theme_version = $version;
        $site->theme_changed_at = now();
        $site->save();

        $this->emitSiteEvent->execute(
            $site->id,
            'theme_updated',
            ['version' => $version],
            $createdBy,
        );
    }
}
