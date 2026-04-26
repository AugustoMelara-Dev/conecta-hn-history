<?php

declare(strict_types=1);

namespace App\Listeners\DynamicForms;

use Illuminate\Support\Facades\Log;

final class LogDynamicFormsEvent
{
    public function handle(object $event): void
    {
        Log::info('dynamic_forms.event', [
            'event' => $event::class,
            'payload' => get_object_vars($event),
        ]);
    }
}

