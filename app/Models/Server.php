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
        'connection',
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
        // Column is named "connection"; must use getAttribute — $this->connection
        // is Eloquent's DB connection name property, not the column value.
        return $this->getAttribute('connection') === self::CONNECTION_LOCAL;
    }

    public function isSsh(): bool
    {
        return $this->getAttribute('connection') === self::CONNECTION_SSH;
    }

    public function resolveDocroot(string $domain): string
    {
        return rtrim($this->wp_sites_root, '/').'/'.$domain;
    }
}
