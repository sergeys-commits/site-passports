<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitePlugin extends Model {
    protected $fillable = ['site_id','plugin_slug','plugin_version','active'];
    protected $casts = ['active' => 'boolean'];
    public function site(): BelongsTo { return $this->belongsTo(Site::class); }
}
