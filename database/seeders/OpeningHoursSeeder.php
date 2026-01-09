<?php

namespace Database\Seeders;

use App\Models\OpeningHour;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * OpeningHoursSeeder - Populate Default Business Hours
 *
 * IDEMPOTENT: Safe to run multiple times without creating duplicates.
 * Uses firstOrCreate() with composite key [tenant_id, day_of_week].
 *
 * DEFAULT SCHEDULE:
 * - Monday-Friday: 08:00 - 17:00 (Open)
 * - Saturday: 09:00 - 13:00 (Open)
 * - Sunday: NO RECORD (absence of record = closed per DB design)
 *
 * DATABASE DESIGN NOTE:
 * - opening_time and closing_time are NOT NULL
 * - Closed days are represented by ABSENCE of a record for that day
 * - This is the original schema design decision
 */
class OpeningHoursSeeder extends Seeder
{
    /**
     * Standard business hours configuration.
     *
     * Key = day_of_week (0=Sunday, 6=Saturday)
     * ONLY open days are included - absence = closed
     */
    private const STANDARD_HOURS = [
        // Sunday (0) is OMITTED - no record = closed
        1 => ['opening_time' => '08:00:00', 'closing_time' => '17:00:00'], // Monday
        2 => ['opening_time' => '08:00:00', 'closing_time' => '17:00:00'], // Tuesday
        3 => ['opening_time' => '08:00:00', 'closing_time' => '17:00:00'], // Wednesday
        4 => ['opening_time' => '08:00:00', 'closing_time' => '17:00:00'], // Thursday
        5 => ['opening_time' => '08:00:00', 'closing_time' => '17:00:00'], // Friday
        6 => ['opening_time' => '09:00:00', 'closing_time' => '13:00:00'], // Saturday
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🕐 Seeding Opening Hours for all tenants...');

        // Get all tenant IDs
        $tenantIds = Tenant::pluck('id');

        if ($tenantIds->isEmpty()) {
            $this->command->warn('⚠️  No tenants found. Skipping opening hours seeding.');

            return;
        }

        $created = 0;
        $skipped = 0;

        foreach ($tenantIds as $tenantId) {
            foreach (self::STANDARD_HOURS as $dayOfWeek => $schedule) {
                // IDEMPOTENT: firstOrCreate prevents duplicates
                $result = OpeningHour::firstOrCreate(
                    // Unique key constraint
                    [
                        'tenant_id' => $tenantId,
                        'day_of_week' => $dayOfWeek,
                    ],
                    // Values to set if creating
                    [
                        'opening_time' => $schedule['opening_time'],
                        'closing_time' => $schedule['closing_time'],
                    ]
                );

                if ($result->wasRecentlyCreated) {
                    $created++;
                } else {
                    $skipped++;
                }
            }
        }

        $totalTenants = $tenantIds->count();
        $this->command->info('✅ Opening Hours seeding complete!');
        $this->command->info("   Tenants processed: {$totalTenants}");
        $this->command->info("   Records created: {$created}");
        $this->command->info("   Records skipped (already exist): {$skipped}");
        $this->command->info('   Note: Sunday (day 0) is closed (no record)');
    }
}
