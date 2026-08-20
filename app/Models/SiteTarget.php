<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteTarget extends Model
{
    public const KIND_STAGING = 'staging';

    public const KIND_PRODUCTION = 'production';

    protected $fillable = [
        'site_id',
        'kind',
        'domain',
        'server_id',
        'docroot',
        'wp_path',
        'basic_auth',
        'is_active',
        'wp_config_pins_written',
    ];

    protected $casts = [
        'basic_auth' => 'boolean',
        'is_active' => 'boolean',
        'wp_config_pins_written' => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function resolvedWpPath(): string
    {
        if ($this->wp_path) {
            return rtrim($this->wp_path, '/');
        }
        if ($this->docroot) {
            return rtrim($this->docroot, '/');
        }

        return $this->server->resolveDocroot($this->domain);
    }
}
