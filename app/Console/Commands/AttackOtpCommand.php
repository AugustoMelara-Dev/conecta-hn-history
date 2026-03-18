<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Handlers\VerifyOtpHandler;
use App\Domain\Auth\Exceptions\InvalidOtpException;
use App\Domain\Auth\Exceptions\MaxOtpAttemptsExceededException;
use Illuminate\Console\Command;
use Ramsey\Uuid\Uuid;

class AttackOtpCommand extends Command
{
    protected $signature = 'security:attack-otp {identifier} {tenantId} {code}';

    protected $description = 'Simulate an OTP attack attempt';

    public function handle(VerifyOtpHandler $handler): int
    {
        $identifier = $this->argument('identifier');
        $tenantId = $this->argument('tenantId');
        $code = $this->argument('code');

        $command = new VerifyOtpCommand(
            identifier: $identifier,
            code: $code,
            tenantId: $tenantId,
            ipAddress: '127.0.0.1',
            idempotencyKey: Uuid::uuid7()->toString()
        );

        try {
            // Intentionally bypasses outer decorators so the command can stress
            // the OTP attempts counter itself without requiring unrelated tables
            // such as processed_commands in isolated concurrency tests.
            $handler->handle($command);
            $this->info('SUCCESS');

            return 0;
        } catch (MaxOtpAttemptsExceededException $e) {
            $this->error('BLOCKED');

            return 429;
        } catch (InvalidOtpException $e) {
            $this->error('INVALID');

            return 401;
        } catch (\Throwable $e) {
            $this->error('ERROR: '.$e->getMessage());

            return 500;
        }
    }
}
