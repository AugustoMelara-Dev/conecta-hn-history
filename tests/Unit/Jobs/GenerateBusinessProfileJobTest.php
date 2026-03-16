<?php



declare(strict_types=1);

use App\Jobs\GenerateBusinessProfileJob;
use App\Services\AI\OpenAIService;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

// uses(TestCase::class);

describe('GenerateBusinessProfileJob', function () {

    it('can be dispatched to the queue', function () {
        Queue::fake();

        GenerateBusinessProfileJob::dispatch(
            businessName: 'Test Business',
            description: 'A sample business description',
            userId: '00000000-0000-0000-0000-000000000002',
            tenantId: '00000000-0000-0000-0000-000000000001'
        );

        Queue::assertPushed(GenerateBusinessProfileJob::class, function ($job) {
            return $job->businessName === 'Test Business'
                && $job->description === 'A sample business description'
                && $job->userId === '00000000-0000-0000-0000-000000000002'
                && $job->tenantId === '00000000-0000-0000-0000-000000000001';
        });
    });

    it('has correct timeout and tries configuration', function () {
        $job = new GenerateBusinessProfileJob(
            businessName: 'Test',
            description: 'Desc',
            userId: '00000000-0000-0000-0000-000000000002',
            tenantId: '00000000-0000-0000-0000-000000000001'
        );

        expect($job->timeout)->toBe(60);
        expect($job->tries)->toBe(1);
    });

    it('has scalar properties for safe serialization', function () {
        $job = new GenerateBusinessProfileJob(
            businessName: 'My Business',
            description: 'My Description',
            userId: '00000000-0000-0000-0000-000000000999',
            tenantId: '00000000-0000-0000-0000-000000000001'
        );

        // Verify all properties are scalars (safe for Redis/DB queue)
        expect($job->businessName)->toBeString();
        expect($job->description)->toBeString();
        expect($job->userId)->toBeString();
        expect($job->tenantId)->toBeString();
    });

    it('preserves tenant ID for isolation', function () {
        $tenantId = '00000000-0000-0000-0000-000000000002';
        
        $job = new GenerateBusinessProfileJob(
            businessName: 'Tenant Business',
            description: 'Description',
            userId: '00000000-0000-0000-0000-000000000002',
            tenantId: $tenantId
        );

        expect($job->tenantId)->toBe($tenantId);
    });

});
