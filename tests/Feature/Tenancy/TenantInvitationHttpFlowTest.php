<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Application\Identity\TenantInvitationService;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantInvitationNotification;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TenantInvitationHttpFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_preview_requires_the_matching_authenticated_identity(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'member@example.test']);
        $wrongIdentity = User::factory()->create(['email' => 'other@example.test']);
        $tenant = Tenant::factory()->forUser($owner)->create(['name' => 'Dovra Studio']);
        $invitation = app(TenantInvitationService::class)->issue(
            tenantId: (string) $tenant->id,
            inviterUserId: (string) $owner->id,
            email: $invitee->email,
            role: 'operator',
        );
        $url = route('tenant-invitations.show', ['token' => $invitation->plainTextToken]);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($wrongIdentity)->get($url)->assertNotFound();
        $this->actingAs($wrongIdentity)
            ->get(route('tenant-invitations.show', ['token' => str_repeat('0', 64)]))
            ->assertNotFound();

        $this->actingAs($invitee)->get($url)
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/AcceptTenantInvitation')
                ->where('invitation.tenantName', 'Dovra Studio')
                ->where('invitation.role', 'operator')
                ->where('invitation.email', 'member@example.test')
                ->missing('invitation.token')
                ->has('invitation.expiresAt'));
    }

    public function test_matching_identity_can_accept_once_over_the_throttled_post_route(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'member@example.test']);
        $tenant = Tenant::factory()->forUser($owner)->create(['slug' => 'dovra-studio']);
        $invitation = app(TenantInvitationService::class)->issue(
            tenantId: (string) $tenant->id,
            inviterUserId: (string) $owner->id,
            email: $invitee->email,
            role: 'viewer',
        );
        $url = route('tenant-invitations.accept', ['token' => $invitation->plainTextToken]);

        $this->actingAs($invitee)->post($url)
            ->assertRedirect('/app/dovra-studio')
            ->assertSessionHas('status', 'tenant-invitation-accepted');

        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'user_id' => $invitee->id,
            'role' => 'viewer',
            'status' => 'active',
        ]);

        $this->actingAs($invitee)->post($url)
            ->assertRedirect()
            ->assertSessionHasErrors('invitation');
    }

    public function test_invitation_mail_is_queued_encrypted_and_contains_the_acceptance_url(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'member@example.test']);
        $tenant = Tenant::factory()->forUser($owner)->create(['name' => 'Dovra Studio']);
        $invitation = app(TenantInvitationService::class)->issue(
            tenantId: (string) $tenant->id,
            inviterUserId: (string) $owner->id,
            email: $invitee->email,
            role: 'manager',
        );

        Notification::route('mail', $invitation->email)->notify(
            new TenantInvitationNotification(
                tenantName: $tenant->name,
                inviterName: $owner->name,
                role: 'manager',
                plainTextToken: $invitation->plainTextToken,
                expiresAt: $invitation->expiresAt,
            ),
        );

        Notification::assertSentOnDemand(
            TenantInvitationNotification::class,
            function (TenantInvitationNotification $notification) use ($invitation): bool {
                self::assertInstanceOf(ShouldQueue::class, $notification);
                self::assertInstanceOf(ShouldBeEncrypted::class, $notification);
                $mail = $notification->toMail((object) []);
                self::assertSame(
                    route('tenant-invitations.show', ['token' => $invitation->plainTextToken]),
                    $mail->actionUrl,
                );
                self::assertStringContainsString('Dovra', (string) $mail->subject);

                return true;
            },
        );

        self::assertStringNotContainsString(
            $invitation->plainTextToken,
            json_encode(
                DB::table('tenant_invitations')->where('id', $invitation->invitationId)->first(),
                JSON_THROW_ON_ERROR,
            ),
        );
    }

    public function test_database_queue_payload_never_contains_the_plaintext_token(): void
    {
        config([
            'queue.default' => 'database',
            'queue.connections.database.table' => 'jobs',
        ]);
        $plainTextToken = bin2hex(random_bytes(32));

        Notification::route('mail', 'member@example.test')->notify(
            new TenantInvitationNotification(
                tenantName: 'Dovra Studio',
                inviterName: 'Dovra Owner',
                role: 'operator',
                plainTextToken: $plainTextToken,
                expiresAt: now()->addDays(7),
            ),
        );

        $payload = DB::table('jobs')->value('payload');

        self::assertIsString($payload);
        self::assertStringNotContainsString($plainTextToken, $payload);
        self::assertStringContainsString('SendQueuedNotifications', $payload);
    }

    public function test_new_identity_can_register_from_the_invitation_and_accept_atomically(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create([
            'name' => 'Dovra Studio',
            'slug' => 'dovra-studio',
        ]);
        $invitation = app(TenantInvitationService::class)->issue(
            tenantId: (string) $tenant->id,
            inviterUserId: (string) $owner->id,
            email: 'new.member@example.test',
            role: 'operator',
        );
        $registrationUrl = route('tenant-invitations.register', [
            'token' => $invitation->plainTextToken,
        ]);

        $this->get($registrationUrl)
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/RegisterTenantInvitation')
                ->where('invitation.tenantName', 'Dovra Studio')
                ->where('invitation.email', 'new.member@example.test')
                ->missing('invitation.token'));

        $this->post($registrationUrl, [
            'name' => 'New Member',
            'password' => 'A-secure-passphrase-2026!',
            'password_confirmation' => 'A-secure-passphrase-2026!',
        ])->assertRedirect('/app/dovra-studio');

        $invitee = User::query()->where('email', 'new.member@example.test')->firstOrFail();
        self::assertTrue(Hash::check('A-secure-passphrase-2026!', (string) $invitee->password));
        self::assertNotNull($invitee->email_verified_at);
        $this->assertAuthenticatedAs($invitee);
        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'user_id' => $invitee->id,
            'role' => 'operator',
            'status' => 'active',
        ]);
    }

    public function test_invitation_registration_is_enumeration_safe_and_rejects_existing_identity(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'member@example.test']);
        $tenant = Tenant::factory()->forUser($owner)->create();
        $invitation = app(TenantInvitationService::class)->issue(
            tenantId: (string) $tenant->id,
            inviterUserId: (string) $owner->id,
            email: $invitee->email,
            role: 'viewer',
        );

        $this->get(route('tenant-invitations.register', ['token' => str_repeat('f', 64)]))
            ->assertNotFound();

        $this->post(route('tenant-invitations.register.store', [
            'token' => $invitation->plainTextToken,
        ]), [
            'name' => 'Duplicate Identity',
            'password' => 'A-secure-passphrase-2026!',
            'password_confirmation' => 'A-secure-passphrase-2026!',
        ])->assertSessionHasErrors('invitation');

        self::assertSame(1, User::query()->where('email', $invitee->email)->count());
    }
}
