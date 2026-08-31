<?php

namespace App\Services\Themes;

use App\Models\Site;
use App\Models\ThemeArtifact;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class ThemeBuildService
{
    public const ARTIFACT_PACKAGE_VERSION = 2;

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

        $theme = $site->resolveTheme();
        $themePart = $theme ? ('t'.$theme->id.'_'.$theme->slug) : 't0_default';

        return sprintf(
            '%s__%s__%s@%d__%s',
            $themePart,
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
        if ($existing && ! $forceRebuild && $this->artifactCacheIsComplete($existing)) {
            $log && $log('stdout', 'Using cached artifact: '.$key);

            return $existing;
        }

        if ($existing && ! $forceRebuild) {
            $log && $log('stdout', 'Cached artifact incomplete or outdated package — rebuilding: '.$key);
        }

        $theme = $this->refs->resolveThemeForSite($site);
        $log && $log('stdout', 'Building theme artifact: '.$key.' (theme='.$theme->slug.')');
        $src = $this->refs->ensureRepo($theme);

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
        File::put($dest.'/.artifact_package_version', (string) self::ARTIFACT_PACKAGE_VERSION);
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

    /**
     * Full theme tree minus build/VCS junk. Spec: PHP + config + dist + … without node_modules.
     */
    private function copyThemePackage(string $src, string $dest): void
    {
        $excludes = [
            'node_modules',
            '.git',
            '.github',
            '.idea',
            '.vscode',
            '.cursor',
            'tests',
            'test',
            'phpunit.xml',
            'phpunit.xml.dist',
            '.phpunit.result.cache',
            'coverage',
            '.DS_Store',
        ];

        $excludeArgs = [];
        foreach ($excludes as $ex) {
            $excludeArgs[] = '--exclude='.$ex;
        }

        $srcTrailing = rtrim($src, '/').'/';
        $destTrailing = rtrim($dest, '/').'/';

        $cmd = array_merge(
            ['rsync', '-a', '--delete'],
            $excludeArgs,
            [$srcTrailing, $destTrailing],
        );

        $result = Process::timeout(300)->run($cmd);
        if (! $result->successful()) {
            throw new RuntimeException('rsync theme package failed: '.$result->errorOutput());
        }

        if (! is_file($dest.'/style.css') && ! is_file($dest.'/functions.php')) {
            throw new RuntimeException('Theme package incomplete: style.css/functions.php missing after copy.');
        }
        if (! is_dir($dest.'/dist')) {
            throw new RuntimeException('Theme package incomplete: dist/ missing after copy.');
        }
    }

    private function artifactCacheIsComplete(ThemeArtifact $artifact): bool
    {
        $path = $artifact->storage_path;
        if (! is_dir($path) || ! is_dir($path.'/dist')) {
            return false;
        }

        $marker = $path.'/.artifact_package_version';
        if (! is_file($marker)) {
            return false;
        }

        return (int) trim((string) file_get_contents($marker)) >= self::ARTIFACT_PACKAGE_VERSION;
    }
}