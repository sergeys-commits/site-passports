<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteEvent extends Model {
    protected $fillable = ['site_id','event_type','source','payload','created_by'];
    protected $casts = ['payload' => 'array'];
    public function site(): BelongsTo { return $this->belongsTo(Site::class); }
}
