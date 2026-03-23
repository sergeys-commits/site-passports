<?php

namespace App\Services\Deployments;

use App\Models\DeploymentRunGuard;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeploymentRunGuardService
{
    public function buildScopeKey(string $serverHost, string $domain): string
    {
        return hash('sha256', implode('|', ['stage_provision', mb_strtolower(trim($serverHost)), mb_strtolower(trim($domain))]));
    }

    public function buildPromoteToProdScopeKey(string $serverHost, string $stageDomain, string $prodDomain): string
    {
        return hash('sha256', implode('|', [
            'promote_to_prod',
            mb_strtolower(trim($serverHost)),
            mb_strtolower(trim($stageDomain)),
            mb_strtolower(trim($prodDomain)),
        ]));
    }

    public function buildThemeUpdateScopeKey(string $serverHost, string $siteDomain): string
    {
        return hash('sha256', implode('|', [
            'theme_update',
            mb_strtolower(trim($serverHost)),
            mb_strtolower(trim($siteDomain)),
        ]));
    }

    public function acquireOrFail(string $scopeKey, int $runId, int $ownerId, int $ttlSeconds = 900): void
    {
        $now = now();
        $expiresAt = $now->copy()->addSeconds($ttlSeconds);

        DB::transaction(function () use ($scopeKey, $runId, $ownerId, $now, $expiresAt) {
            DeploymentRunGuard::where('scope_key', $scopeKey)
                ->where('is_active', true)
                ->where('expires_at', '<=', $now)
                ->update([
                    'is_active' => false,
                    'active_scope_key' => null,
                    'released_at' => $now,
                    'release_reason' => 'ttl_expired',
                ]);

            $exists = DeploymentRunGuard::where('active_scope_key', $scopeKey)
                ->where('is_active', true)
                ->exists();

            if ($exists) {
                throw new RuntimeException('Live submission already active for this scope.');
            }

            DeploymentRunGuard::create([
                'scope_key' => $scopeKey,
                'active_scope_key' => $scopeKey,
                'deployment_run_id' => $runId,
                'owner_id' => $ownerId,
                'is_active' => true,
                'acquired_at' => $now,
                'expires_at' => $expiresAt,
            ]);
        });
    }

    public function releaseByRunId(int $runId, string $reason = 'terminal'): void
    {
        DeploymentRunGuard::where('deployment_run_id', $runId)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'active_scope_key' => null,
                'released_at' => now(),
                'release_reason' => $reason,
            ]);
    }
}
