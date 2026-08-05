<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Markets\HondurasMarketBackfill;
use Illuminate\Database\Seeder;

final class HondurasMarketPackSeeder extends Seeder
{
    public function run(HondurasMarketBackfill $backfill): void
    {
        $backfill->run();
    }
}
