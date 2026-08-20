<?php

namespace App\Services\Servers;

use App\Models\Server;
use Illuminate\Support\Facades\Process;

class ServerConnectionService
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function check(Server $server): array
    {
        if ($server->isLocal()) {
            if (! is_dir($server->wp_sites_root)) {
                return [
                    'ok' => false,
                    'message' => 'Local wp_sites_root does not exist: '.$server->wp_sites_root,
                ];
            }

            return [
                'ok' => true,
                'message' => 'Local server OK. wp_sites_root is readable.',
            ];
        }

        if (! filled($server->host) || ! filled($server->ssh_user) || ! filled($server->ssh_key_path)) {
            return ['ok' => false, 'message' => 'SSH host, user and key path are required.'];
        }

        if (! is_file($server->ssh_key_path)) {
            return ['ok' => false, 'message' => 'SSH key file not found: '.$server->ssh_key_path];
        }

        $sshTarget = $server->ssh_user.'@'.$server->host;
        $cmd = [
            'ssh',
            '-i', $server->ssh_key_path,
            '-p', (string) $server->ssh_port,
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'ConnectTimeout=10',
            $sshTarget,
            'test -d '.escapeshellarg($server->wp_sites_root).' && echo OK',
        ];

        $result = Process::timeout(30)->run($cmd);

        if (! $result->successful() || ! str_contains($result->output(), 'OK')) {
            $err = trim($result->errorOutput() ?: $result->output()) ?: 'SSH check failed';

            return ['ok' => false, 'message' => $err];
        }

        return ['ok' => true, 'message' => 'SSH OK. wp_sites_root exists on remote.'];
    }

    /**
     * Run a shell command on the server (local bash -lc or remote ssh).
     */
    public function run(Server $server, string $remoteCommand, int $timeout = 600): \Illuminate\Contracts\Process\ProcessResult
    {
        if ($server->isLocal()) {
            return Process::timeout($timeout)->run(['bash', '-lc', $remoteCommand]);
        }

        $sshTarget = $server->ssh_user.'@'.$server->host;

        return Process::timeout($timeout)->run([
            'ssh',
            '-i', $server->ssh_key_path,
            '-p', (string) $server->ssh_port,
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=accept-new',
            $sshTarget,
            $remoteCommand,
        ]);
    }
}
