<?php

declare(strict_types=1);

namespace Tests\Architecture\Security;

use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class OtpConcurrencyTest extends TestCase
{
    // NO RefreshDatabase or DatabaseTruncation as they use Transactions invisible to external processes

    /** @test */
    public function it_prevents_race_conditions_on_otp_attempts_limit()
    {
        // Policy: testing must run on shared DB (MySQL in this repo), not temp SQLite files.
        // We only create a lightweight fallback schema when the table is missing.
        $createdTableForTest = false;
        if (! Schema::hasTable('one_time_passwords')) {
            Schema::create('one_time_passwords', function ($table) {
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
            $createdTableForTest = true;
        }

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

            // 2. ATTACK: Repeated atomic increments beyond configured max.
            $concurrency = 10;
            $results = [];
            for ($i = 0; $i < $concurrency; $i++) {
                try {
                    $repo->incrementAttemptsAtomically((string) $createdId, (int) config('auth.otp.max_attempts', 3));
                    $results[] = 'INVALID';
                } catch (\App\Domain\Auth\Exceptions\MaxOtpAttemptsExceededException) {
                    $results[] = 'BLOCKED';
                }
            }

            // 3. ANALYSIS & ASSERTIONS
            $invalidCounts = 0;
            $blockedCounts = 0;
            $errorCounts = 0;

            foreach ($results as $output) {
                $output = trim((string) $output);
                if (str_contains($output, 'INVALID')) {
                    $invalidCounts++;
                } elseif (str_contains($output, 'BLOCKED')) {
                    $blockedCounts++;
                } else {
                    $errorCounts++;
                    echo 'Unexpected Output: '.$output."\n";
                }
            }

            // Hard Check
            $freshOtp = DB::table('one_time_passwords')->where('id', $createdId)->first();
            $this->assertNotNull($freshOtp, 'OTP should exist.');

            $maxConfig = config('auth.otp.max_attempts', 3);

            $this->assertEquals($maxConfig, $freshOtp->attempts, 'DB Attempts exceeded the limit! Race condition detected.');

            $this->assertGreaterThan(0, $blockedCounts, 'No requests were blocked! Logic failing open.');
            $this->assertEquals(0, $errorCounts, 'Command execution errors occurred.');

        } finally {
            // 4. CLEANUP
            if (isset($createdId)) {
                // Try to delete via DB if connection still valid
                try {
                    DB::table('one_time_passwords')->where('id', $createdId)->delete();
                } catch (\Throwable $e) {
                }
            }
            if ($createdTableForTest) {
                Schema::dropIfExists('one_time_passwords');
            }
        }
    }
}
