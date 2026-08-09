<?php

declare(strict_types=1);

use App\Application\Ai\Contracts\AssistantResult;
use App\Application\Ai\Contracts\AssistantRuntime;
use App\Application\Ai\DTOs\AssistantExecutionContext;
use App\Jobs\GenerateBusinessProfileJob;
use App\Models\AiAssistantRun;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

describe('GenerateBusinessProfileJob', function () {

    it('can be dispatched to the queue', function () {
        Queue::fake();

        $userId = '00000000-0000-4000-8000-000000000001';
        $tenantId = '00000000-0000-4000-8000-000000000100';

        GenerateBusinessProfileJob::dispatch(
            businessName: 'Test Business',
            description: 'A sample business description',
            userId: $userId,
            tenantId: $tenantId
        );

        Queue::assertPushed(GenerateBusinessProfileJob::class, function ($job) use ($userId, $tenantId) {
            return $job->businessName === 'Test Business'
                && $job->description === 'A sample business description'
                && $job->userId === $userId
                && $job->tenantId === $tenantId;
        });
    });

    it('has correct timeout and tries configuration', function () {
        $job = new GenerateBusinessProfileJob(
            businessName: 'Test',
            description: 'Desc',
            userId: '00000000-0000-4000-8000-000000000001',
            tenantId: '00000000-0000-4000-8000-000000000100'
        );

        expect($job->timeout)->toBe(60);
        expect($job->tries)->toBe(1);
    });

    it('has scalar properties for safe serialization', function () {
        $job = new GenerateBusinessProfileJob(
            businessName: 'My Business',
            description: 'My Description',
            userId: '00000000-0000-4000-8000-000000000999',
            tenantId: '00000000-0000-4000-8000-000000000888'
        );

        // Verify all properties are scalars (safe for Redis/DB queue)
        expect($job->businessName)->toBeString();
        expect($job->description)->toBeString();
        expect($job->userId)->toBeString();
        expect($job->tenantId)->toBeString();
    });

    it('preserves tenant ID for isolation', function () {
        $tenantId = '00000000-0000-4000-8000-000000012345';

        $job = new GenerateBusinessProfileJob(
            businessName: 'Tenant Business',
            description: 'Description',
            userId: '00000000-0000-4000-8000-000000000001',
            tenantId: $tenantId
        );

        expect($job->tenantId)->toBe($tenantId);
    });

    it('persists an idempotent correlation reference without plaintext prompts', function () {
        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();
        $runtime = new class implements AssistantRuntime
        {
            public function execute(string $assistantKey, array $input, AssistantExecutionContext $context): AssistantResult
            {
                return AssistantResult::success([
                    'summary' => 'Generated profile',
                    'fields' => [['key' => 'seo_title', 'value' => 'Verified title']],
                    'confidence' => 0.9,
                ], 0.01, 5, ['status' => 'completed', 'correlation_id' => $context->correlationId]);
            }
        };
        $job = new GenerateBusinessProfileJob(
            businessName: 'Tenant Business',
            description: 'A sufficiently detailed business description.',
            userId: (string) $owner->id,
            tenantId: (string) $tenant->id,
            correlationId: '00000000-0000-4000-8000-000000012345',
        );

        $job->handle($runtime);
        $job->handle($runtime);

        expect(AiAssistantRun::query()->where('correlation_id', $job->correlationId)->count())->toBe(1);
        $run = AiAssistantRun::query()->where('correlation_id', $job->correlationId)->firstOrFail();
        expect($run->status)->toBe('completed')
            ->and($run->prompt)->toBeNull()
            ->and($run->response)->toBeNull();
    });

});
