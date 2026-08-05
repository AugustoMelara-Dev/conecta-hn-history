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
        if (! Schema::hasColumn('campaigns', 'subject')) {
            Schema::table('campaigns', function (Blueprint $table): void {
                $table->string('subject')->nullable();
            });
        }

        if (Schema::hasColumn('campaigns', 'title')) {
            DB::table('campaigns')
                ->whereNull('subject')
                ->update(['subject' => DB::raw('title')]);
        }

        if (! Schema::hasColumn('campaigns', 'recipients_count')) {
            Schema::table('campaigns', function (Blueprint $table): void {
                $table->unsignedInteger('recipients_count')->default(0);
            });
        }
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['subject', 'recipients_count'],
            static fn (string $column): bool => Schema::hasColumn('campaigns', $column),
        ));

        if ($columns !== []) {
            Schema::table('campaigns', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
