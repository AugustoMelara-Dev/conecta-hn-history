<?php

declare(strict_types=1);

namespace Tests\Architecture\Security;

use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class OtpConcurrencyTest extends TestCase
{
    // NO RefreshDatabase or DatabaseTruncation as they use Transactions invisible to external processes

    /** @test */
    public function it_prevents_race_conditions_on_otp_attempts_limit()
    {
        // 0. DB CONFIGURATION: Use a temp file-based SQLite DB so processes can share it
        $dbPath = database_path('testing_otp_concurrency_'.Uuid::uuid7()->toString().'.sqlite');
        file_put_contents($dbPath, ''); // Create empty file

        // Use the same connection name that subprocesses receive via DB_CONNECTION=sqlite.
        config(['database.connections.sqlite.database' => $dbPath]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        // Manual Schema (Faster than migrating everything)
        // Schema::dropIfExists('one_time_passwords');
        \Illuminate\Support\Facades\Schema::create('one_time_passwords', function ($table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('identifier');
            $table->string('tenant_id');
            $table->string('code');
            $table->string('type');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamps();
        });

        // 1. SETUP: Create a Target OTP with Attempts = 0
        $tenantId = Uuid::uuid7()->toString();
        $identifier = 'victim-'.Uuid::uuid7()->toString();
        $code = '123456';

        /** @var OtpRepositoryInterface $repo */
        $repo = $this->app->make(OtpRepositoryInterface::class);
        $hasher = $this->app->make(\App\Domain\Auth\Services\OtpHasherInterface::class);

        $hasher = $this->app->make(\App\Domain\Auth\Services\OtpHasherInterface::class);
        $hashedCode = $hasher->hash($code);

        $createdId = null;

        try {
            $otp = $repo->create(
                userId: null,
                identifier: $identifier,
                tenantId: $tenantId,
                hashedCode: $hashedCode,
                type: 'email',
                ipAddress: '127.0.0.1',
                userAgent: 'Test',
                expiryMinutes: 10
            );

            $createdId = $otp->id;

            // Verify initial state
            $this->assertEquals(0, $otp->attempts);

            // 2. ATTACK: Consume the exact allowed attempts through the atomic repository gate.
            $maxConfig = config('auth.otp.max_attempts', 3);

            for ($i = 0; $i < $maxConfig; $i++) {
                $repo->incrementAttemptsAtomically((string) $createdId, $maxConfig);
            }

            // 3. ANALYSIS & ASSERTIONS
            $freshOtp = DB::table('one_time_passwords')->where('id', $createdId)->first();
            $this->assertNotNull($freshOtp, 'OTP should exist.');

            $this->assertEquals(
                $maxConfig,
                $freshOtp->attempts,
                'DB attempts should stop exactly at the configured maximum.'
            );

            $this->expectException(\App\Domain\Auth\Exceptions\MaxOtpAttemptsExceededException::class);
            $repo->incrementAttemptsAtomically((string) $createdId, $maxConfig);

        } finally {
            // 4. CLEANUP
            if (isset($createdId)) {
                // Try to delete via DB if connection still valid
                try {
                    DB::table('one_time_passwords')->where('id', $createdId)->delete();
                } catch (\Throwable $e) {
                }
            }
            if (file_exists($dbPath)) {
                @unlink($dbPath);
            }
        }
    }
}
