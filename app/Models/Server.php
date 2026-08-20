<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    public const CONNECTION_LOCAL = 'local';

    public const CONNECTION_SSH = 'ssh';

    public const PANEL_ISP = 'isp';

    public const PANEL_HESTIA = 'hestia';

    public const PANEL_NONE = 'none';

    protected $fillable = [
        'name',
        'host',
        'ssh_port',
        'ssh_user',
        'ssh_key_path',
        'access_type',
        'panel_type',
        'wp_sites_root',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'ssh_port' => 'integer',
    ];

    public function targets(): HasMany
    {
        return $this->hasMany(SiteTarget::class);
    }

    public function isLocal(): bool
    {
        return $this->access_type === self::CONNECTION_LOCAL;
    }

    public function isSsh(): bool
    {
        return $this->access_type === self::CONNECTION_SSH;
    }

    public function resolveDocroot(string $domain): string
    {
        return rtrim($this->wp_sites_root, '/').'/'.$domain;
    }
}
