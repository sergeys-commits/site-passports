<?php

namespace App\Services\Sites;

use App\Models\Server;
use App\Models\Site;
use App\Models\SiteTarget;
use App\Services\Themes\ThemeDeployUploadService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SitePipelineMigrationService
{
    public function __construct(
        private readonly SitePinGenerator $pins,
        private readonly ThemeDeployUploadService $upload,
    ) {}

    /**
     * Ensure pins + targets exist for an existing site before first v2 deploy.
     */
    public function ensureMigrated(Site $site, ?callable $log = null): Site
    {
        return DB::transaction(function () use ($site, $log) {
            $site = $site->fresh(['targets']);
            if (! $site->hasPins()) {
                $log && $log('stdout', 'Generating pins for existing site');
                $this->pins->applyToSite($site);
                $site = $site->fresh();
            }

            $server = Server::query()->where('connection', Server::CONNECTION_LOCAL)->where('is_active', true)->first()
                ?? Server::query()->where('is_active', true)->first();

            if (! $server) {
                throw new RuntimeException('No active server found for migration.');
            }

            if ($site->stage_domain && ! $site->targets()->where('kind', SiteTarget::KIND_STAGING)->exists()) {
                $docroot = $server->resolveDocroot($site->stage_domain);
                SiteTarget::create([
                    'site_id' => $site->id,
                    'kind' => SiteTarget::KIND_STAGING,
                    'domain' => $site->stage_domain,
                    'server_id' => $server->id,
                    'docroot' => $docroot,
                    'wp_path' => $docroot,
                    'basic_auth' => false,
                    'is_active' => true,
                    'wp_config_pins_written' => false,
                ]);
                $log && $log('stdout', 'Created staging target from stage_domain');
            }

            if ($site->domain && ! $site->targets()->where('kind', SiteTarget::KIND_PRODUCTION)->exists()) {
                $docroot = $server->resolveDocroot($site->domain);
                SiteTarget::create([
                    'site_id' => $site->id,
                    'kind' => SiteTarget::KIND_PRODUCTION,
                    'domain' => $site->domain,
                    'server_id' => $server->id,
                    'docroot' => $docroot,
                    'wp_path' => $docroot,
                    'basic_auth' => false,
                    'is_active' => $site->status === 'active',
                    'wp_config_pins_written' => false,
                ]);
                $log && $log('stdout', 'Created production target from domain');
            }

            if (! $site->scenario) {
                $site->scenario = $site->stage_domain
                    ? Site::SCENARIO_STAGE_THEN_PROD
                    : Site::SCENARIO_PROD_BASIC_AUTH;
            }
            if (! $site->lifecycle) {
                $site->lifecycle = $site->status === 'stage'
                    ? Site::LIFECYCLE_STAGING
                    : Site::LIFECYCLE_PRODUCTION;
            }
            $site->profile_pipeline_enabled = true;
            $site->save();

            return $site->fresh(['targets.server']);
        });
    }

    public function writePinsForTargets(Site $site, iterable $targets, ?callable $log = null): void
    {
        foreach ($targets as $target) {
            /** @var SiteTarget $target */
            $this->upload->writeWpConfigPins($site, $target->loadMissing('server'), $log);
        }
    }
}
