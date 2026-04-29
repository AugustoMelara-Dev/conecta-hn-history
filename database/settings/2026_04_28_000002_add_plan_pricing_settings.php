<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.free_plan_price', 0.00);
        $this->migrator->add('general.pro_plan_original_price', 799.00);
        $this->migrator->add('general.setup_fee_price', 2500.00);
    }
};
