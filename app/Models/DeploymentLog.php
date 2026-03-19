<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentLog extends Model
{
protected $fillable = ['run_id','stream','line_no','message'];

public function run(): BelongsTo { return $this->belongsTo(DeploymentRun::class, 'run_id'); }
}
