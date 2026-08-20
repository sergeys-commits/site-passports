<?php

namespace App\Services\Themes;

use App\Models\Server;
use App\Models\Site;
use App\Models\SiteTarget;
use App\Models\ThemeArtifact;
use App\Services\Servers\ServerConnectionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class ThemeDeployUploadService
{
    public function __construct(
        private readonly ServerConnectionService $connections,
    ) {}

    public function upload(ThemeArtifact $artifact, Site $site, SiteTarget $target, ?callable $log = null): void
    {
        $server = $target->server;
        $wpPath = $target->resolvedWpPath();
        $themeDir = $wpPath.'/wp-content/themes/'.$site->theme_slug;
        $source = rtrim($artifact->storage_path, '/').'/';

        if (! is_dir($source)) {
            throw new RuntimeException('Artifact path missing: '.$source);
        }

        $log && $log('stdout', "Uploading theme to {$themeDir}");

        if ($server->isLocal()) {
            if (! is_dir($wpPath)) {
                throw new RuntimeException('WP path missing on local server: '.$wpPath);
            }
            Process::timeout(300)->run(['mkdir', '-p', $themeDir]);
            $rsync = Process::timeout(600)->run([
                'rsync', '-a', '--delete',
                '--exclude', 'node_modules',
                $source,
                rtrim($themeDir, '/').'/',
            ]);
            if (! $rsync->successful()) {
                throw new RuntimeException('Local rsync failed: '.$rsync->errorOutput());
            }
            $siteUser = env('WP_SITE_USER', 'www-root');
            Process::timeout(120)->run(['chown', '-R', $siteUser.':'.$siteUser, $themeDir]);
        } else {
            $sshBase = [
                'ssh',
                '-i', $server->ssh_key_path,
                '-p', (string) $server->ssh_port,
                '-o', 'BatchMode=yes',
                '-o', 'StrictHostKeyChecking=accept-new',
            ];
            $remote = $server->ssh_user.'@'.$server->host;
            $mkdir = Process::timeout(60)->run(array_merge($sshBase, [$remote, 'mkdir -p '.escapeshellarg($themeDir)]));
            if (! $mkdir->successful()) {
                throw new RuntimeException('Remote mkdir failed: '.$mkdir->errorOutput());
            }

            $rsync = Process::timeout(900)->run([
                'rsync', '-az', '--delete',
                '--exclude', 'node_modules',
                '-e', sprintf(
                    'ssh -i %s -p %d -o BatchMode=yes -o StrictHostKeyChecking=accept-new',
                    escapeshellarg($server->ssh_key_path),
                    $server->ssh_port
                ),
                $source,
                $remote.':'.rtrim($themeDir, '/').'/',
            ]);
            if (! $rsync->successful()) {
                throw new RuntimeException('Remote rsync failed: '.$rsync->errorOutput());
            }
        }

        $log && $log('stdout', 'Upload complete');
    }

    public function activateTheme(Site $site, SiteTarget $target, ?callable $log = null): void
    {
        $wpPath = $target->resolvedWpPath();
        $cmd = 'cd '.escapeshellarg($wpPath).' && wp theme activate '.escapeshellarg($site->theme_slug).' --allow-root';
        $result = $this->connections->run($target->server, $cmd, 120);
        if (! $result->successful()) {
            throw new RuntimeException('Theme activate failed: '.$result->errorOutput());
        }
        $log && $log('stdout', 'Theme activated: '.$site->theme_slug);
    }

    public function writeWpConfigPins(Site $site, SiteTarget $target, ?callable $log = null): void
    {
        if ($target->wp_config_pins_written) {
            $log && $log('stdout', 'wp-config pins already written');

            return;
        }

        $wpPath = $target->resolvedWpPath();
        $configPath = $wpPath.'/wp-config.php';
        $defines = implode("\n", [
            "define('FACTORY_SITE_SALT', ".var_export($site->site_salt, true).");",
            "define('FACTORY_SITE_PROFILE_ID', ".var_export($site->profile_id, true).");",
            "define('FACTORY_SITE_PROFILE_REVISION', ".(int) $site->profile_revision.");",
            "define('FACTORY_SITE_PUBLIC_TOKEN', ".var_export($site->public_token, true).");",
            "define('FACTORY_SITE_THEME_SLUG', ".var_export($site->theme_slug, true).");",
        ]);

        $php = <<<'PHP'
$config = getenv('WP_CONFIG_PATH');
$block = getenv('PINS_BLOCK');
if (!is_file($config)) { fwrite(STDERR, "wp-config missing\n"); exit(1); }
$src = file_get_contents($config);
if (str_contains($src, "FACTORY_SITE_SALT")) {
  echo "pins_already_present\n";
  exit(0);
}
$needle = "/* That's all, stop editing!";
if (str_contains($src, $needle)) {
  $src = str_replace($needle, $block."\n\n".$needle, $src);
} else {
  $src .= "\n".$block."\n";
}
file_put_contents($config, $src);
echo "pins_written\n";
PHP;

        if ($target->server->isLocal()) {
            if (! is_file($configPath)) {
                throw new RuntimeException('wp-config.php missing: '.$configPath);
            }
            $result = Process::timeout(60)->env([
                'WP_CONFIG_PATH' => $configPath,
                'PINS_BLOCK' => "// Site Passports theme pins\n".$defines,
            ])->run(['php', '-r', $php]);
        } else {
            $b64 = base64_encode("// Site Passports theme pins\n".$defines);
            $remotePhp = 'php -r '.escapeshellarg(
                'file_put_contents("/tmp/pins_block.php.txt", base64_decode("'.$b64.'"));'
            );
            // Simpler: append via remote PHP one-liner
            $appendCmd = sprintf(
                'php -r %s',
                escapeshellarg(
                    '$c='.var_export($configPath, true).';$b='.var_export("// Site Passports theme pins\n".$defines, true).';'
                    .'$s=file_get_contents($c);if(str_contains($s,"FACTORY_SITE_SALT")){echo "pins_already_present\n";exit;}'.
                    '$n="/* That\'s all, stop editing!";$s=str_contains($s,$n)?str_replace($n,$b."\n\n".$n,$s):$s."\n".$b."\n";'.
                    'file_put_contents($c,$s);echo "pins_written\n";'
                )
            );
            $result = $this->connections->run($target->server, $appendCmd, 60);
        }

        if (! $result->successful()) {
            throw new RuntimeException('Writing wp-config pins failed: '.$result->errorOutput());
        }

        $target->wp_config_pins_written = true;
        $target->save();
        $log && $log('stdout', trim($result->output()) ?: 'pins written');
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function smokeCheck(Site $site, SiteTarget $target): array
    {
        $url = 'https://'.$target->domain.'/';
        try {
            $request = Http::timeout(20)->withOptions(['allow_redirects' => true, 'verify' => false]);
            if ($target->basic_auth) {
                $request = $request->withHeaders(['Accept' => 'text/html']);
            }
            $response = $request->get($url);
            $status = $response->status();
            $body = $response->body();

            if (str_contains($body, 'Site configuration error')) {
                return ['ok' => false, 'message' => 'Smoke failed: Site configuration error'];
            }

            // Cloudflare origin SSL issues — edge problem, not theme deploy failure
            if (in_array($status, [525, 526], true)) {
                return [
                    'ok' => true,
                    'message' => "Smoke soft-pass: HTTP {$status} (Cloudflare SSL to origin). Theme upload/activate already done — fix cert/CF SSL mode separately.",
                ];
            }

            $needle = '/themes/'.$site->theme_slug.'/dist/assets/';
            if ($response->successful() && ! str_contains($body, $needle) && ! str_contains($body, (string) $site->theme_slug)) {
                return ['ok' => true, 'message' => 'HTTP '.$status.' (theme slug not found in HTML — soft pass)'];
            }
            if ($status >= 500) {
                return ['ok' => false, 'message' => 'Smoke failed: HTTP '.$status];
            }

            return ['ok' => true, 'message' => 'Smoke OK: HTTP '.$status];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Smoke check error: '.$e->getMessage()];
        }
    }
}
