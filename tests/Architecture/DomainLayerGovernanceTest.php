<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

arch('Domain layer must not contain jobs')
    ->expect('App\Domain')
    ->not->toImplement(ShouldQueue::class)
    ->ignoring([
        'App\Domain\ReportingAndExports\Jobs\GenerateSalesReportJob',
    ]);

arch('Domain layer must not contain mailables')
    ->expect('App\Domain')
    ->not->toExtend(Mailable::class);

arch('Domain layer must not contain notifications')
    ->expect('App\Domain')
    ->not->toExtend(Notification::class);

arch('Domain layer must not contain artisan commands')
    ->expect('App\Domain')
    ->not->toExtend(Command::class);

arch('Infrastructure jobs should implement should queue')
    ->expect('App\Infrastructure\Jobs')
    ->toImplement(ShouldQueue::class);
