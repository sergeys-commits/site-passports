<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model {
    protected $fillable = [
        'name','domain','stage_domain','group_id','theme_name','theme_version',
        'theme_changed_at','php_version','wp_version','status','launch_date','transfer_date','notes'
    ];
    protected $casts = ['theme_changed_at' => 'datetime','launch_date' => 'date','transfer_date' => 'date'];
    public function group(): BelongsTo { return $this->belongsTo(SiteGroup::class, 'group_id'); }
    public function plugins(): HasMany { return $this->hasMany(SitePlugin::class); }
    public function events(): HasMany { return $this->hasMany(SiteEvent::class); }
}
