<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Handlers\VerifyOtpHandlerInterface;
use App\Domain\Auth\Exceptions\InvalidOtpException;
use App\Domain\Auth\Exceptions\MaxOtpAttemptsExceededException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class AttackOtpCommand extends Command
{
    protected $signature = 'security:attack-otp {identifier} {tenantId} {code}';

    protected $description = 'Simulate an OTP attack attempt';

    public function handle(): int
    {
        $dbPath = env('OTP_ATTACK_DB_PATH');

        if (is_string($dbPath) && $dbPath !== '') {
            config(['database.connections.sqlite.database' => $dbPath]);
            DB::purge('sqlite');
            DB::setDefaultConnection('sqlite');
        }

        /** @var VerifyOtpHandlerInterface $handler */
        $handler = app(VerifyOtpHandlerInterface::class);

        $identifier = $this->argument('identifier');
        $tenantId = $this->argument('tenantId');
        $code = $this->argument('code');

        $command = new VerifyOtpCommand(
            identifier: $identifier,
            code: $code,
            tenantId: $tenantId,
            ipAddress: '127.0.0.1',
            userAgent: 'AttackBot/1.0',
            idempotencyKey: Uuid::uuid7()->toString()
        );

        try {
            $handler->handle($command);
            $this->info('SUCCESS');

            return 0; // Should not happen with wrong code
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
