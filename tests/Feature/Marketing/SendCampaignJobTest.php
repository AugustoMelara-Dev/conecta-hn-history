<?php

use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Jobs\Marketing\SendCampaignEmail;
use App\Mail\CampaignOfferMail;
use App\Models\Campaign;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\DatabaseTransactions::class);

test('send campaign email job accepts uuids and executes successfully', function () {
    Mail::fake();

    $tenantId = Str::uuid()->toString();
    $tenant = Tenant::forceCreate([
        'id' => $tenantId,
        'name' => 'Queue Test Tenant',
        'slug' => 'queue-test-'.Str::random(6),
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'test@queue.local',
    ]);

    app(TenantContext::class)->setTenant($tenant->id);

    $campaign = Campaign::create([
        'subject' => 'Queue Test Promo',
        'content' => 'Queue test content',
        'type' => 'email',
        'status' => 'draft',
        'recipients_count' => 0,
    ]);

    $job = new SendCampaignEmail($tenant->id, $campaign->id, $user->id);
    app(TenantContext::class)->setTenant($tenant->id);
    $job->handle();
    app(TenantContext::class)->reset();

    Mail::assertSent(CampaignOfferMail::class, function ($mail) use ($user, $campaign) {
        return $mail->hasTo($user->email) && $mail->campaign->id === $campaign->id;
    });
});
