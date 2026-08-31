<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Seeder;

class ThemeSeeder extends Seeder
{
    public function run(): void
    {
        $repo = (string) config('deployment.theme_repo', env('THEME_REPO', 'git@github.com:sergeys-commits/wp-theme-core.git'));

        Theme::query()->firstOrCreate(
            ['slug' => 'wp-theme-core'],
            [
                'name' => 'wp-theme-core',
                'git_repo' => $repo !== '' ? $repo : 'git@github.com:sergeys-commits/wp-theme-core.git',
                'src_path' => (string) config('deployment.theme_src_path', storage_path('app/theme-src')),
                'is_active' => true,
                'is_default' => true,
            ]
        );
    }
}
