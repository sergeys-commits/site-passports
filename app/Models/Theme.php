<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Theme extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'git_repo',
        'src_path',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function resolveSrcPath(): string
    {
        if (filled($this->src_path)) {
            return (string) $this->src_path;
        }

        $base = (string) config('deployment.theme_src_path', storage_path('app/theme-src'));

        // If base looks like a single-repo path ending in theme-src, use sibling by slug.
        return rtrim($base, '/').'-'.$this->slug;
    }

    public static function defaultTheme(): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->orderBy('id')
            ->first()
            ?? static::query()->where('is_active', true)->orderBy('id')->first();
    }

    public function markAsDefault(): void
    {
        static::query()->where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->is_default = true;
        $this->save();
    }

    protected static function booted(): void
    {
        static::saving(function (Theme $theme): void {
            if ($theme->is_default) {
                static::query()
                    ->when($theme->exists, fn ($q) => $q->where('id', '!=', $theme->id))
                    ->update(['is_default' => false]);
            }
        });
    }
}
