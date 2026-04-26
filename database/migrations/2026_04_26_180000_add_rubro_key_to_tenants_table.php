<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenants', 'rubro_key')) {
                $table->text('rubro_key')->nullable()->after('business_segment_key');
            }
        });

        $rubros = array_keys((array) config('rubro-manifest.rubros', []));
        $defaults = (array) config('rubro-manifest.business_type_defaults', []);
        $fallback = (string) config('rubro-manifest.default_rubro', 'retail_general');

        DB::table('tenants')
            ->select(['id', 'business_type', 'business_segment_key'])
            ->orderBy('id')
            ->chunkById(200, function ($tenants) use ($rubros, $defaults, $fallback): void {
                foreach ($tenants as $tenant) {
                    $legacySegment = (string) ($tenant->business_segment_key ?? '');
                    $businessType = (string) ($tenant->business_type ?? '');
                    $rubroKey = in_array($legacySegment, $rubros, true)
                        ? $legacySegment
                        : (string) ($defaults[$businessType] ?? $fallback);

                    DB::table('tenants')
                        ->where('id', $tenant->id)
                        ->update(['rubro_key' => $rubroKey]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (Schema::hasColumn('tenants', 'rubro_key')) {
                $table->dropColumn('rubro_key');
            }
        });
    }
};
