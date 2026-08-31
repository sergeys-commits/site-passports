<?php

namespace App\Services\Sites;

use App\Models\Server;
use App\Models\Site;
use App\Models\SiteEvent;
use App\Models\SiteTarget;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateSiteService
{
    public function __construct(
        private readonly SitePinGenerator $pins,
    ) {}

    /**
     * @param  array{
     *   name: string,
     *   scenario: string,
     *   server_id?: int,
     *   staging_server_id?: int,
     *   production_server_id?: int,
     *   theme_id?: int|null,
     *   staging_domain?: ?string,
     *   production_domain?: ?string,
     *   basic_auth?: bool,
     *   profile_id?: ?string,
     *   group_id?: ?int,
     *   git_ref?: ?string
     * }  $data
     */
    public function create(array $data, User $user): Site
    {
        $scenario = $data['scenario'];
        if (! in_array($scenario, [Site::SCENARIO_STAGE_THEN_PROD, Site::SCENARIO_PROD_BASIC_AUTH], true)) {
            throw new InvalidArgumentException('Invalid scenario.');
        }

        $theme = $this->resolveTheme($data['theme_id'] ?? null);
        $stagingServer = null;
        $productionServer = null;

        if ($scenario === Site::SCENARIO_STAGE_THEN_PROD) {
            $stagingServerId = (int) ($data['staging_server_id'] ?? $data['server_id'] ?? 0);
            $productionServerId = (int) ($data['production_server_id'] ?? $data['server_id'] ?? $stagingServerId);
            $stagingServer = Server::query()->where('is_active', true)->findOrFail($stagingServerId);
            $productionServer = Server::query()->where('is_active', true)->findOrFail($productionServerId);
        } else {
            $serverId = (int) ($data['server_id'] ?? $data['production_server_id'] ?? 0);
            $productionServer = Server::query()->where('is_active', true)->findOrFail($serverId);
        }

        $pins = $this->pins->generate($data['name'], $data['profile_id'] ?? null);

        return DB::transaction(function () use ($data, $user, $stagingServer, $productionServer, $pins, $scenario, $theme) {
            $stagingDomain = $data['staging_domain'] ?? null;
            $productionDomain = $data['production_domain'] ?? null;

            if ($scenario === Site::SCENARIO_STAGE_THEN_PROD) {
                if (! filled($stagingDomain)) {
                    throw new InvalidArgumentException('staging_domain is required for stage_then_prod.');
                }
            } else {
                if (! filled($productionDomain)) {
                    throw new InvalidArgumentException('production_domain is required for prod_basic_auth.');
                }
            }

            $site = Site::create([
                'name' => $data['name'],
                'brand_key' => $pins['brand_key'],
                'domain' => $productionDomain,
                'stage_domain' => $stagingDomain,
                'group_id' => $data['group_id'] ?? null,
                'site_salt' => $pins['site_salt'],
                'profile_id' => $pins['profile_id'],
                'profile_revision' => $pins['profile_revision'],
                'public_token' => $pins['public_token'],
                'theme_slug' => $pins['theme_slug'],
                'theme_id' => $theme->id,
                'theme_name' => $theme->name,
                'theme_git_ref' => $data['git_ref'] ?? null,
                'scenario' => $scenario,
                'lifecycle' => $scenario === Site::SCENARIO_STAGE_THEN_PROD
                    ? Site::LIFECYCLE_STAGING
                    : Site::LIFECYCLE_PRODUCTION,
                'status' => $scenario === Site::SCENARIO_STAGE_THEN_PROD ? 'stage' : 'active',
                'profile_pipeline_enabled' => true,
            ]);

            if ($scenario === Site::SCENARIO_STAGE_THEN_PROD) {
                $this->createTarget($site, $stagingServer, SiteTarget::KIND_STAGING, $stagingDomain, false);
            } else {
                $this->createTarget(
                    $site,
                    $productionServer,
                    SiteTarget::KIND_PRODUCTION,
                    $productionDomain,
                    (bool) ($data['basic_auth'] ?? true),
                );
            }

            SiteEvent::create([
                'site_id' => $site->id,
                'event_type' => 'site_created_pipeline',
                'source' => 'panel',
                'payload' => [
                    'scenario' => $scenario,
                    'theme_slug' => $site->theme_slug,
                    'theme_id' => $theme->id,
                    'profile_id' => $site->profile_id,
                    'staging_server_id' => $stagingServer?->id,
                    'production_server_id' => $productionServer?->id,
                ],
                'created_by' => $user->id,
            ]);

            return $site->fresh(['targets.server', 'theme']);
        });
    }

    private function resolveTheme(mixed $themeId): Theme
    {
        if (filled($themeId)) {
            return Theme::query()->where('is_active', true)->findOrFail((int) $themeId);
        }

        $default = Theme::defaultTheme();
        if ($default === null) {
            throw new InvalidArgumentException('No active theme in registry. Create a theme first.');
        }

        return $default;
    }

    private function createTarget(
        Site $site,
        Server $server,
        string $kind,
        string $domain,
        bool $basicAuth,
    ): SiteTarget {
        $docroot = $server->resolveDocroot($domain);

        return SiteTarget::create([
            'site_id' => $site->id,
            'kind' => $kind,
            'domain' => $domain,
            'server_id' => $server->id,
            'docroot' => $docroot,
            'wp_path' => $docroot,
            'basic_auth' => $basicAuth,
            'is_active' => true,
            'wp_config_pins_written' => false,
        ]);
    }
}
