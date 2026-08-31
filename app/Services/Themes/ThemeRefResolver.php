<?php

namespace App\Services\Themes;

use App\Models\Site;
use App\Models\Theme;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

class ThemeRefResolver
{
    public function resolveThemeForSite(Site $site): Theme
    {
        $theme = $site->resolveTheme();
        if ($theme === null) {
            throw new RuntimeException('No theme configured for site and no default theme in registry.');
        }

        return $theme;
    }

    public function themeSrcPath(?Theme $theme = null): string
    {
        if ($theme !== null) {
            return $theme->resolveSrcPath();
        }

        $default = Theme::defaultTheme();
        if ($default !== null) {
            return $default->resolveSrcPath();
        }

        return (string) config('deployment.theme_src_path', storage_path('app/theme-src'));
    }

    public function themeRepo(?Theme $theme = null): string
    {
        if ($theme !== null) {
            $repo = trim((string) $theme->git_repo);
            if ($repo === '') {
                throw new RuntimeException('Theme #'.$theme->id.' has empty git_repo.');
            }

            return $repo;
        }

        $default = Theme::defaultTheme();
        if ($default !== null) {
            return $this->themeRepo($default);
        }

        $repo = (string) config('deployment.theme_repo', env('THEME_REPO', ''));
        if ($repo === '') {
            throw new RuntimeException('THEME_REPO is not configured and no default theme exists.');
        }

        return $repo;
    }

    /**
     * Ensure theme source is cloned and fetch latest refs.
     */
    public function ensureRepo(?Theme $theme = null): string
    {
        $path = $this->themeSrcPath($theme);
        $repo = $this->themeRepo($theme);

        if (! is_dir($path.'/.git')) {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            $clone = Process::timeout(600)->run(['git', 'clone', $repo, $path]);
            if (! $clone->successful()) {
                throw new RuntimeException('git clone failed: '.$clone->errorOutput());
            }
        }

        $fetch = Process::timeout(300)->path($path)->run(['git', 'fetch', '--tags', '--prune', 'origin']);
        if (! $fetch->successful()) {
            throw new RuntimeException('git fetch failed: '.$fetch->errorOutput());
        }

        return $path;
    }

    /**
     * @return array{ref: string, sha: string, tag: ?string, major: ?int, is_legacy: bool, theme_id: ?int}
     */
    public function resolve(string $gitRef, ?Theme $theme = null): array
    {
        $path = $this->ensureRepo($theme);
        $ref = trim($gitRef);
        if ($ref === '') {
            throw new InvalidArgumentException('git_ref is required.');
        }

        $tag = null;
        $resolvedRef = $ref;

        if (strtolower($ref) === 'latest' || strtolower($ref) === 'latest_tag') {
            $tag = $this->latestSemverTag($path);
            if ($tag === null) {
                throw new RuntimeException('No semver tags found in theme repo.');
            }
            $resolvedRef = $tag;
        } elseif (preg_match('/^v?\d+\.\d+/', $ref)) {
            $tag = ltrim($ref, 'v');
            $check = Process::path($path)->run(['git', 'rev-parse', '--verify', 'refs/tags/'.$ref]);
            if (! $check->successful()) {
                $alt = str_starts_with($ref, 'v') ? substr($ref, 1) : 'v'.$ref;
                $check2 = Process::path($path)->run(['git', 'rev-parse', '--verify', 'refs/tags/'.$alt]);
                if ($check2->successful()) {
                    $resolvedRef = $alt;
                    $tag = ltrim($alt, 'v');
                }
            } else {
                $tag = ltrim($ref, 'v');
            }
        }

        $shaResult = Process::path($path)->run(['git', 'rev-parse', $resolvedRef]);
        if (! $shaResult->successful()) {
            $shaResult = Process::path($path)->run(['git', 'rev-parse', 'origin/'.$resolvedRef]);
        }
        if (! $shaResult->successful()) {
            throw new RuntimeException('Cannot resolve git ref: '.$ref);
        }

        $sha = trim($shaResult->output());
        $major = $this->majorFromTag($tag ?? $ref);

        return [
            'ref' => $resolvedRef,
            'sha' => $sha,
            'tag' => $tag,
            'major' => $major,
            'is_legacy' => $major !== null && $major < 2,
            'theme_id' => $theme?->id,
        ];
    }

    public function isLegacyRef(string $gitRef, ?Theme $theme = null): bool
    {
        $resolved = $this->resolve($gitRef, $theme);

        return $resolved['is_legacy'];
    }

    /**
     * @return list<string>
     */
    public function listSemverTags(?Theme $theme = null, int $limit = 40): array
    {
        try {
            $path = $this->ensureRepo($theme);
        } catch (\Throwable) {
            return [];
        }

        $result = Process::path($path)->run(['git', 'tag', '-l', '--sort=-v:refname']);
        if (! $result->successful()) {
            return [];
        }

        $tags = [];
        foreach (preg_split("/\r\n|\n|\r/", trim($result->output())) as $tag) {
            $tag = trim($tag);
            if ($tag === '') {
                continue;
            }
            if (preg_match('/^v?(\d+)\.(\d+)\.(\d+)/', $tag)) {
                $tags[] = $tag;
                if (count($tags) >= $limit) {
                    break;
                }
            }
        }

        return $tags;
    }

    public function latestSemverTag(string $path): ?string
    {
        $result = Process::path($path)->run(['git', 'tag', '-l', '--sort=-v:refname']);
        if (! $result->successful()) {
            return null;
        }

        foreach (preg_split("/\r\n|\n|\r/", trim($result->output())) as $tag) {
            $tag = trim($tag);
            if ($tag === '') {
                continue;
            }
            if (preg_match('/^v?(\d+)\.(\d+)\.(\d+)/', $tag)) {
                return $tag;
            }
        }

        return null;
    }

    private function majorFromTag(?string $tagOrRef): ?int
    {
        if ($tagOrRef === null) {
            return null;
        }
        if (preg_match('/^v?(\d+)\./', $tagOrRef, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
