<?php

namespace App\Console\Commands;

use App\Models\OneTimePassword;
use Illuminate\Console\Command;

class ClearOtps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:clear-otps';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear expired One Time Passwords';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Delete OTPs expired more than 1 hour ago
        $count = OneTimePassword::where('expires_at', '<', now()->subHour())->delete();

        $this->info("{$count} OTPs eliminados.");
    }
}
