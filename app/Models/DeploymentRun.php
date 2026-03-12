<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeploymentRun extends Model
{
protected $fillable = [
'site_id','action_type','mode','status','requested_by',
'confirm_phrase_used','started_at','finished_at','meta_json'
];

protected $casts = [
'started_at' => 'datetime',
'finished_at' => 'datetime',
'meta_json' => 'array',
];

public function site(): BelongsTo { return $this->belongsTo(Site::class); }
public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
public function logs(): HasMany { return $this->hasMany(DeploymentLog::class, 'run_id'); }
}
