<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeploymentRunGuard extends Model
{
protected $fillable = [
'scope_key', 'active_scope_key', 'deployment_run_id', 'owner_id',
'is_active', 'acquired_at', 'expires_at', 'released_at', 'release_reason',
];

protected $casts = [
'is_active' => 'boolean',
'acquired_at' => 'datetime',
'expires_at' => 'datetime',
'released_at' => 'datetime',
];
}
