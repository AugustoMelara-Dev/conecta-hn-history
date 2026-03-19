<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

test('Domain layer must not contain jobs', function () {
    expect('App\Domain')
        ->not()->toImplement(ShouldQueue::class)
        ->ignoring([
            'App\Domain\ReportingAndExports\Jobs\GenerateSalesReportJob',
        ]);
});

test('Domain layer must not contain mailables', function () {
    expect('App\Domain')
        ->not()->toExtend(Mailable::class);
});

test('Domain layer must not contain notifications', function () {
    expect('App\Domain')
        ->not()->toExtend(Notification::class);
});

test('Domain layer must not contain artisan commands', function () {
    expect('App\Domain')
        ->not()->toExtend(Command::class);
});

test('Infrastructure jobs should implement should queue', function () {
    expect('App\Infrastructure\Jobs')
        ->toImplement(ShouldQueue::class);
});
