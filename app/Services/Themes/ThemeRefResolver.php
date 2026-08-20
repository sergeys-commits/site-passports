<?php

namespace App\Services\Themes;

use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

class ThemeRefResolver
{
    public function themeSrcPath(): string
    {
        return (string) config('deployment.theme_src_path', storage_path('app/theme-src'));
    }

    public function themeRepo(): string
    {
        $repo = (string) config('deployment.theme_repo', env('THEME_REPO', ''));
        if ($repo === '') {
            throw new RuntimeException('THEME_REPO is not configured.');
        }

        return $repo;
    }

    /**
     * Ensure theme source is cloned and fetch latest refs.
     */
    public function ensureRepo(): string
    {
        $path = $this->themeSrcPath();
        $repo = $this->themeRepo();

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
     * @return array{ref: string, sha: string, tag: ?string, major: ?int, is_legacy: bool}
     */
    public function resolve(string $gitRef): array
    {
        $path = $this->ensureRepo();
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
            // Prefer exact tag match with or without v prefix
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
        ];
    }

    public function isLegacyRef(string $gitRef): bool
    {
        $resolved = $this->resolve($gitRef);

        return $resolved['is_legacy'];
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
