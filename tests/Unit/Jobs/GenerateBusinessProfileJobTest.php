<?php

declare(strict_types=1);

use App\Jobs\GenerateBusinessProfileJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(TestCase::class);

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

});
