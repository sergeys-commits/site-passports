<?php

namespace App\Services\Themes;

use App\Models\Site;
use App\Models\ThemeArtifact;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class ThemeBuildService
{
    public function __construct(
        private readonly ThemeRefResolver $refs,
    ) {}

    public function artifactsRoot(): string
    {
        return (string) config('deployment.theme_artifacts_path', storage_path('app/theme-artifacts'));
    }

    public function cacheKey(string $gitSha, Site $site): string
    {
        $fp = $site->saltFingerprint();
        if ($fp === null) {
            throw new RuntimeException('Site has no salt for cache key.');
        }

        return sprintf(
            '%s__%s@%d__%s',
            $gitSha,
            $site->profile_id,
            (int) $site->profile_revision,
            $fp,
        );
    }

    /**
     * Build or return cached artifact for site pins + git sha.
     */
    public function buildOrGet(Site $site, string $gitRef, string $gitSha, bool $forceRebuild = false, ?callable $log = null): ThemeArtifact
    {
        $key = $this->cacheKey($gitSha, $site);
        $existing = ThemeArtifact::query()->where('cache_key', $key)->first();
        if ($existing && ! $forceRebuild && is_dir($existing->storage_path)) {
            $log && $log('stdout', 'Using cached artifact: '.$key);

            return $existing;
        }

        $log && $log('stdout', 'Building theme artifact: '.$key);
        $src = $this->refs->ensureRepo();

        $checkout = Process::timeout(120)->path($src)->run(['git', 'checkout', '--force', $gitSha]);
        if (! $checkout->successful()) {
            throw new RuntimeException('git checkout failed: '.$checkout->errorOutput());
        }

        $env = [
            'SITE_SALT' => $site->site_salt,
            'SITE_PROFILE_ID' => $site->profile_id,
            'SITE_PROFILE_REVISION' => (string) $site->profile_revision,
            'SITE_PUBLIC_TOKEN' => $site->public_token,
        ];

        $ci = Process::timeout(900)->path($src)->env($env)->run(['npm', 'ci']);
        if (! $ci->successful()) {
            throw new RuntimeException('npm ci failed: '.$ci->errorOutput());
        }
        $log && $log('stdout', 'npm ci OK');

        $build = Process::timeout(900)->path($src)->env($env)->run(['npm', 'run', 'build']);
        if (! $build->successful()) {
            throw new RuntimeException('npm run build failed: '.$build->errorOutput());
        }
        $log && $log('stdout', 'npm run build OK');

        $metaPath = $src.'/dist/build-meta.json';
        if (! is_file($metaPath)) {
            throw new RuntimeException('dist/build-meta.json missing after build.');
        }
        $meta = json_decode((string) file_get_contents($metaPath), true);
        if (! is_array($meta)) {
            throw new RuntimeException('Invalid build-meta.json');
        }

        $this->assertMetaMatchesPins($meta, $site);

        $dest = rtrim($this->artifactsRoot(), '/').'/'.$key;
        if (is_dir($dest)) {
            File::deleteDirectory($dest);
        }
        File::ensureDirectoryExists($dest);

        $this->copyThemePackage($src, $dest);
        $log && $log('stdout', 'Artifact stored at '.$dest);

        return ThemeArtifact::query()->updateOrCreate(
            ['cache_key' => $key],
            [
                'git_ref' => $gitRef,
                'git_sha' => $gitSha,
                'profile_id' => $site->profile_id,
                'profile_revision' => (int) $site->profile_revision,
                'salt_fingerprint' => $site->saltFingerprint(),
                'storage_path' => $dest,
                'build_meta' => $meta,
                'built_at' => now(),
            ]
        );
    }

    public function assertMetaMatchesPins(array $meta, Site $site): void
    {
        $checks = [
            'profile_id' => $site->profile_id,
            'profile_revision' => (int) $site->profile_revision,
            'public_token' => $site->public_token,
            'salt_fingerprint' => $site->saltFingerprint(),
        ];

        foreach ($checks as $key => $expected) {
            $actual = $meta[$key] ?? null;
            if ($key === 'profile_revision') {
                $actual = (int) $actual;
            }
            if ((string) $actual !== (string) $expected) {
                throw new RuntimeException("build-meta mismatch on {$key}: expected {$expected}, got ".json_encode($actual));
            }
        }
    }

    private function copyThemePackage(string $src, string $dest): void
    {
        $include = [
            'style.css',
            'functions.php',
            'index.php',
            'header.php',
            'footer.php',
            'screenshot.png',
            'theme.json',
        ];

        foreach ($include as $file) {
            if (is_file($src.'/'.$file)) {
                File::copy($src.'/'.$file, $dest.'/'.$file);
            }
        }

        foreach (['inc', 'config', 'dist', 'templates', 'parts', 'patterns', 'assets'] as $dir) {
            if (is_dir($src.'/'.$dir)) {
                File::copyDirectory($src.'/'.$dir, $dest.'/'.$dir);
            }
        }

        // Copy remaining PHP templates at root
        foreach (File::files($src) as $file) {
            $name = $file->getFilename();
            if (str_ends_with($name, '.php') && ! is_file($dest.'/'.$name)) {
                File::copy($file->getPathname(), $dest.'/'.$name);
            }
        }
    }
}
