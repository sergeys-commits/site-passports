<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemeArtifact extends Model
{
    protected $fillable = [
        'cache_key',
        'git_ref',
        'git_sha',
        'profile_id',
        'profile_revision',
        'salt_fingerprint',
        'storage_path',
        'build_meta',
        'built_at',
    ];

    protected $casts = [
        'build_meta' => 'array',
        'built_at' => 'datetime',
        'profile_revision' => 'integer',
    ];
}
