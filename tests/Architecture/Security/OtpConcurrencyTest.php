<?php

declare(strict_types=1);

namespace Tests\Architecture\Security;

use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use App\Domain\Auth\Services\OtpHasherInterface;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class OtpConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prevents_race_conditions_on_otp_attempts_limit()
    {
        $this->assertSame(
            'mysql',
            DB::connection()->getDriverName(),
            'This concurrency test must run against the MySQL-compatible testing database.'
        );

        $tenantId = Uuid::uuid7()->toString();
        $identifier = 'victim-'.Uuid::uuid7()->toString().'@example.com';
        $code = '123456';

        /** @var OtpRepositoryInterface $repo */
        $repo = $this->app->make(OtpRepositoryInterface::class);
        /** @var OtpHasherInterface $hasher */
        $hasher = $this->app->make(OtpHasherInterface::class);

        $otp = $repo->create(
            userId: null,
            identifier: $identifier,
            tenantId: $tenantId,
            hashedCode: $hasher->hash($code),
            type: 'email',
            ipAddress: '127.0.0.1',
            userAgent: 'Test',
            expiryMinutes: 10
        );

        $this->assertSame(0, $otp->attempts);

        $command = "php artisan security:attack-otp {$identifier} {$tenantId} 000000";
        $concurrency = 10;

        try {
            $results = Process::pool(function (Pool $pool) use ($command, $concurrency) {
                for ($i = 0; $i < $concurrency; $i++) {
                    $pool->command($command)
                        ->timeout(180)
                        ->env($this->testing_environment());
                }
            })->wait();

            $invalidCounts = 0;
            $blockedCounts = 0;
            $errorCounts = 0;

            foreach ($results->collect() as $result) {
                $output = trim($result->output().$result->errorOutput());

                if (str_contains($output, 'INVALID')) {
                    $invalidCounts++;

                    continue;
                }

                if (str_contains($output, 'BLOCKED')) {
                    $blockedCounts++;

                    continue;
                }

                $errorCounts++;
                fwrite(STDOUT, 'Unexpected Output: '.$output.PHP_EOL);
            }

            $freshOtp = DB::table('one_time_passwords')->where('id', $otp->id)->first();
            $this->assertNotNull($freshOtp, 'OTP should exist.');

            $maxAttempts = (int) config('auth.otp.max_attempts', 3);

            $this->assertSame(
                $maxAttempts,
                (int) $freshOtp->attempts,
                "DB attempts mismatch. invalid={$invalidCounts} blocked={$blockedCounts} errors={$errorCounts}"
            );
            $this->assertGreaterThan(0, $blockedCounts, 'Expected blocked attempts after the threshold was reached.');
            $this->assertSame(0, $errorCounts, 'Unexpected command execution errors occurred.');
            $this->assertSame(
                $concurrency,
                $invalidCounts + $blockedCounts,
                'Every concurrent attempt should resolve as INVALID or BLOCKED.'
            );
        } finally {
            DB::table('one_time_passwords')->where('id', $otp->id)->delete();
        }
    }

    /**
     * Ensure subprocesses hit the same MySQL testing database as the parent test process.
     *
     * @return array<string, string>
     */
    private function testing_environment(): array
    {
        $defaultConnection = (string) config('database.default');
        $connection = (array) config("database.connections.{$defaultConnection}", []);

        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => $defaultConnection,
            'DB_HOST' => (string) ($connection['host'] ?? ''),
            'DB_PORT' => (string) ($connection['port'] ?? ''),
            'DB_DATABASE' => (string) ($connection['database'] ?? ''),
            'DB_USERNAME' => (string) ($connection['username'] ?? ''),
            'DB_PASSWORD' => (string) ($connection['password'] ?? ''),
            'AUTH_OTP_MAX_ATTEMPTS' => (string) config('auth.otp.max_attempts', 5),
        ];

        foreach (['LARAVEL_PARALLEL_TESTING', 'TEST_TOKEN'] as $key) {
            $value = $_SERVER[$key] ?? $_ENV[$key] ?? null;

            if (is_string($value) && $value !== '') {
                $env[$key] = $value;
            }
        }

        return array_filter($env, static fn (string $value): bool => $value !== '');
    }
}
