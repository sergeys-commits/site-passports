<?php

namespace App\Services\Sites;

use App\Models\Site;
use Illuminate\Support\Str;

class SitePinGenerator
{
    private const PROFILES = ['p01', 'p02', 'p03', 'p04', 'p05', 'p06', 'p07', 'p08'];

    /**
     * @return array{
     *   site_salt: string,
     *   profile_id: string,
     *   profile_revision: int,
     *   public_token: string,
     *   theme_slug: string,
     *   brand_key: string
     * }
     */
    public function generate(string $name, ?string $preferredProfile = null): array
    {
        $profile = $preferredProfile && in_array($preferredProfile, self::PROFILES, true)
            ? $preferredProfile
            : self::PROFILES[random_int(0, count(self::PROFILES) - 1)];

        $brandKey = Str::slug($name) ?: 'site';
        $themeSlug = 'factory-'.Str::lower(Str::random(6));

        return [
            'site_salt' => bin2hex(random_bytes(16)),
            'profile_id' => $profile,
            'profile_revision' => 1,
            'public_token' => Str::lower(Str::random(12)),
            'theme_slug' => $themeSlug,
            'brand_key' => $brandKey,
        ];
    }

    public function applyToSite(Site $site, ?string $preferredProfile = null): Site
    {
        if ($site->hasPins()) {
            return $site;
        }

        $pins = $this->generate($site->name ?: ($site->domain ?? 'site'), $preferredProfile);
        $site->fill($pins);
        $site->save();

        return $site->fresh();
    }
}
