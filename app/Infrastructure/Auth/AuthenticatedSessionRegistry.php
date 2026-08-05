<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AuthenticatedSessionRegistry
{
    public function start(Request $request, User $user): void
    {
        $sessionId = $request->session()->getId();
        if ($sessionId === '') {
            throw new DomainException('Authenticated session identifier is missing.');
        }

        $now = CarbonImmutable::now();
        $sessionHash = $this->sessionHash($sessionId);
        $values = $this->metadata($request, $user) + [
            'last_authenticated_at' => $now,
            'last_seen_at' => $now,
            'revoked_at' => null,
            'revocation_reason' => null,
            'updated_at' => $now,
        ];

        DB::table('authenticated_sessions')->insertOrIgnore($values + [
            'id' => (string) Str::uuid(),
            'session_id_hash' => $sessionHash,
            'user_id' => (string) $user->getKey(),
            'created_at' => $now,
        ]);
        DB::table('authenticated_sessions')
            ->where('session_id_hash', $sessionHash)
            ->where('user_id', (string) $user->getKey())
            ->update($values);
    }

    public function touch(Request $request, User $user): bool
    {
        $sessionId = $request->session()->getId();
        if ($sessionId === '') {
            return true;
        }

        $sessionHash = $this->sessionHash($sessionId);
        $record = DB::table('authenticated_sessions')
            ->where('session_id_hash', $sessionHash)
            ->where('user_id', (string) $user->getKey())
            ->first();

        if ($record !== null && $record->revoked_at !== null) {
            return false;
        }

        if ($record === null) {
            $this->start($request, $user);

            return true;
        }

        DB::table('authenticated_sessions')->where('id', $record->id)->update(
            $this->metadata($request, $user) + [
                'last_seen_at' => CarbonImmutable::now(),
                'updated_at' => CarbonImmutable::now(),
            ],
        );

        return true;
    }

    /**
     * @return Collection<int, object>
     */
    public function forUser(User $user, Request $request): Collection
    {
        $currentHash = $this->sessionHash($request->session()->getId());

        return DB::table('authenticated_sessions')
            ->where('user_id', (string) $user->getKey())
            ->whereNull('revoked_at')
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(function (object $session) use ($currentHash): object {
                $session->is_current = hash_equals((string) $session->session_id_hash, $currentHash);
                unset($session->session_id_hash, $session->ip_address_hash);

                return $session;
            });
    }

    public function revokeOne(User $user, string $inventoryId, Request $request): void
    {
        DB::transaction(function () use ($user, $inventoryId, $request): void {
            $session = DB::table('authenticated_sessions')
                ->where('id', $inventoryId)
                ->where('user_id', (string) $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($session === null || $session->revoked_at !== null) {
                throw new DomainException('Authenticated session was not found.');
            }

            if (hash_equals(
                (string) $session->session_id_hash,
                $this->sessionHash($request->session()->getId()),
            )) {
                throw new DomainException('Use logout to revoke the current session.');
            }

            $this->markRevoked((string) $session->id, 'user_revoked_device');
            $this->deleteFrameworkSessions(
                userId: (string) $user->getKey(),
                targetHash: (string) $session->session_id_hash,
            );
            $this->audit('revoked', $user, (string) $session->id);
        });
    }

    public function revokeOthers(User $user, Request $request, string $reason): void
    {
        $currentHash = $this->sessionHash($request->session()->getId());

        DB::transaction(function () use ($user, $currentHash, $reason): void {
            DB::table('authenticated_sessions')
                ->where('user_id', (string) $user->getKey())
                ->where('session_id_hash', '!=', $currentHash)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => CarbonImmutable::now(),
                    'revocation_reason' => $reason,
                    'updated_at' => CarbonImmutable::now(),
                ]);
            $this->deleteFrameworkSessions(
                userId: (string) $user->getKey(),
                exceptHash: $currentHash,
            );
            $this->audit('others_revoked', $user, null);
        });
    }

    public function revokeAll(User $user, string $reason): void
    {
        DB::table('authenticated_sessions')
            ->where('user_id', (string) $user->getKey())
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => CarbonImmutable::now(),
                'revocation_reason' => $reason,
                'updated_at' => CarbonImmutable::now(),
            ]);
        $this->audit('all_revoked', $user, null);
    }

    public function markCurrentRevoked(Request $request, User $user, string $reason): void
    {
        $sessionHash = $this->sessionHash($request->session()->getId());
        $session = DB::table('authenticated_sessions')
            ->where('session_id_hash', $sessionHash)
            ->where('user_id', (string) $user->getKey())
            ->first();

        if ($session !== null && $session->revoked_at === null) {
            $this->markRevoked((string) $session->id, $reason);
            $this->audit('current_revoked', $user, (string) $session->id);
        }
    }

    public function rotateCurrent(Request $request, User $user): void
    {
        $this->markCurrentRevoked($request, $user, 'session_rotated');
        $request->session()->regenerate();
        $this->start($request, $user);
    }

    public function sessionHash(string $sessionId): string
    {
        return hash_hmac('sha256', $sessionId, (string) config('app.key'));
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(Request $request, User $user): array
    {
        $verification = $request->session()->get('auth.two_factor_verified');
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $currentTenantId = $tenant instanceof Tenant
            ? (string) $tenant->getKey()
            : ($user->tenant_id !== null ? (string) $user->tenant_id : null);
        $userAgent = mb_substr((string) $request->userAgent(), 0, 1000);

        return [
            'current_tenant_id' => $currentTenantId,
            'device_name' => $this->deviceName($userAgent),
            'ip_address_hash' => $request->ip() === null
                ? null
                : hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
            'user_agent' => $userAgent === '' ? null : $userAgent,
            'mfa_verified_at' => is_array($verification) && is_numeric($verification['verified_at'] ?? null)
                ? CarbonImmutable::createFromTimestamp((int) $verification['verified_at'])
                : null,
        ];
    }

    private function deviceName(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'Edg/') => 'Microsoft Edge',
            str_contains($userAgent, 'Chrome/') => 'Google Chrome',
            str_contains($userAgent, 'Firefox/') => 'Mozilla Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            $userAgent === '' => 'Dispositivo desconocido',
            default => mb_substr($userAgent, 0, 120),
        };
    }

    private function markRevoked(string $inventoryId, string $reason): void
    {
        DB::table('authenticated_sessions')->where('id', $inventoryId)->update([
            'revoked_at' => CarbonImmutable::now(),
            'revocation_reason' => $reason,
            'updated_at' => CarbonImmutable::now(),
        ]);
    }

    private function deleteFrameworkSessions(
        string $userId,
        ?string $targetHash = null,
        ?string $exceptHash = null,
    ): void {
        $sessions = DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $userId)
            ->pluck('id');

        foreach ($sessions as $sessionId) {
            $hash = $this->sessionHash((string) $sessionId);
            $shouldDelete = $targetHash !== null
                ? hash_equals($targetHash, $hash)
                : ($exceptHash === null || ! hash_equals($exceptHash, $hash));

            if ($shouldDelete) {
                DB::table((string) config('session.table', 'sessions'))
                    ->where('id', (string) $sessionId)
                    ->where('user_id', $userId)
                    ->delete();
            }
        }
    }

    private function audit(string $action, User $user, ?string $inventoryId): void
    {
        activity('identity-security')
            ->causedBy($user)
            ->withProperties([
                'event_type' => "identity.session.{$action}",
                'user_id' => (string) $user->getKey(),
                'authenticated_session_id' => $inventoryId,
            ])
            ->event("identity.session.{$action}")
            ->log("authenticated_session_{$action}");
    }
}
