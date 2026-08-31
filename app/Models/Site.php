<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    public const STATUS_DEFAULT = 'default';

    public const STATUS_STAGE = 'stage';

    public const STATUS_PROD = 'prod';

    public const LIFECYCLE_STAGING = 'staging';

    public const LIFECYCLE_PRODUCTION = 'production';

    public const LIFECYCLE_ARCHIVED = 'archived';

    public const SCENARIO_STAGE_THEN_PROD = 'stage_then_prod';

    public const SCENARIO_PROD_BASIC_AUTH = 'prod_basic_auth';

    protected $fillable = [
        'name', 'brand_key', 'domain', 'stage_domain', 'group_id', 'admin_url', 'stage_admin_url', 'wp_admin_password',
        'theme_name', 'theme_version', 'theme_changed_at', 'php_version', 'wp_version',
        'site_salt', 'profile_id', 'profile_revision', 'public_token', 'theme_slug', 'theme_id',
        'theme_git_ref', 'last_build_meta', 'lifecycle', 'scenario', 'profile_pipeline_enabled',
        'status', 'launch_date', 'transfer_date', 'notes',
    ];

    protected $casts = [
        'theme_changed_at' => 'datetime',
        'launch_date' => 'date',
        'transfer_date' => 'date',
        'last_build_meta' => 'array',
        'profile_pipeline_enabled' => 'boolean',
        'profile_revision' => 'integer',
    ];

    protected $hidden = [
        'site_salt',
    ];

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function resolveTheme(): ?Theme
    {
        if ($this->theme_id) {
            $this->loadMissing('theme');

            return $this->theme;
        }

        return Theme::defaultTheme();
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SiteGroup::class, 'group_id');
    }

    public function siteGroup(): BelongsTo
    {
        return $this->belongsTo(SiteGroup::class, 'group_id');
    }

    public function plugins(): HasMany
    {
        return $this->hasMany(SitePlugin::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SiteEvent::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(SiteTarget::class);
    }

    public function hasPins(): bool
    {
        return filled($this->site_salt)
            && filled($this->profile_id)
            && filled($this->public_token)
            && filled($this->theme_slug);
    }

    public function saltFingerprint(): ?string
    {
        if (! filled($this->site_salt)) {
            return null;
        }

        return substr(hash('sha256', $this->site_salt), 0, 16);
    }

    public function activeTargets()
    {
        return $this->targets()->where('is_active', true);
    }

    public function stagingTarget(): ?SiteTarget
    {
        return $this->targets()
            ->where('kind', SiteTarget::KIND_STAGING)
            ->where('is_active', true)
            ->first();
    }

    public function productionTarget(): ?SiteTarget
    {
        return $this->targets()
            ->where('kind', SiteTarget::KIND_PRODUCTION)
            ->where('is_active', true)
            ->first();
    }
}
