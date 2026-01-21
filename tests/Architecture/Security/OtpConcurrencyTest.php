<?php

declare(strict_types=1);

namespace Tests\Architecture\Security;

use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Process\Pool;
use Tests\TestCase;
use Ramsey\Uuid\Uuid;

class OtpConcurrencyTest extends TestCase
{
    // NO RefreshDatabase or DatabaseTruncation as they use Transactions invisible to external processes
    
    /** @test */
    public function it_prevents_race_conditions_on_otp_attempts_limit()
    {
        // 0. DB CONFIGURATION: Use a temp file-based SQLite DB so processes can share it
        $dbPath = database_path('testing_otp_concurrency_' . Uuid::uuid7()->toString() . '.sqlite');
        file_put_contents($dbPath, ''); // Create empty file
        
        // Override Config for THIS process
        config(['database.connections.sqlite_test_file' => [
            'driver' => 'sqlite',
            'database' => $dbPath,
            'foreign_key_constraints' => true,
        ]]);
        DB::purge();
        DB::setDefaultConnection('sqlite_test_file');
        
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
        $identifier = 'victim-' . Uuid::uuid7()->toString();
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

            // 2. ATTACK: Launch 10 Concurrent Processes trying WRONG code
            $cmdSignature = "php artisan security:attack-otp {$identifier} {$tenantId} 000000";
            $concurrency = 10;
            
            $results = Process::pool(function (Pool $pool) use ($cmdSignature, $concurrency, $dbPath) {
                for ($i = 0; $i < $concurrency; $i++) {
                    // Inject the SAME database config to subprocesses
                    $pool->command($cmdSignature)
                        ->env([
                            'DB_CONNECTION' => 'sqlite',
                            'DB_DATABASE' => $dbPath
                        ]);
                }
            })->wait();

            // 3. ANALYSIS & ASSERTIONS
            $invalidCounts = 0;
            $blockedCounts = 0;
            $errorCounts = 0;

            foreach ($results as $result) {
                $output = trim($result->output() . $result->errorOutput());
                if (str_contains($output, 'INVALID')) $invalidCounts++;
                elseif (str_contains($output, 'BLOCKED')) $blockedCounts++;
                else {
                    $errorCounts++;
                    echo "Unexpected Output: " . $output . "\n";
                }
            }
            
            // Hard Check
            $freshOtp = DB::table('one_time_passwords')->where('id', $createdId)->first();
            $this->assertNotNull($freshOtp, "OTP should exist.");
            
            $maxConfig = config('auth.otp.max_attempts', 3);
            
            $this->assertEquals($maxConfig, $freshOtp->attempts, "DB Attempts exceeded the limit! Race condition detected.");
            
            $this->assertGreaterThan(0, $blockedCounts, "No requests were blocked! Logic failing open.");
            $this->assertEquals(0, $errorCounts, "Command execution errors occurred.");

        } finally {
            // 4. CLEANUP
            if (isset($createdId)) {
                // Try to delete via DB if connection still valid
                try { DB::table('one_time_passwords')->where('id', $createdId)->delete(); } catch (\Throwable $e) {}
            }
            if (file_exists($dbPath)) {
                @unlink($dbPath);
            }
        }
    }
}
