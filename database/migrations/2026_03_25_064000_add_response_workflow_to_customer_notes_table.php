<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_notes', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_notes', 'status')) {
                $table->string('status', 20)->default('open')->after('note_type');
            }

            if (! Schema::hasColumn('customer_notes', 'priority')) {
                $table->string('priority', 20)->default('normal')->after('status');
            }

            if (! Schema::hasColumn('customer_notes', 'source_channel')) {
                $table->string('source_channel', 30)->nullable()->after('priority');
            }

            if (! Schema::hasColumn('customer_notes', 'requires_follow_up')) {
                $table->boolean('requires_follow_up')->default(false)->after('source_channel');
            }

            if (! Schema::hasColumn('customer_notes', 'handled_by_user_id')) {
                $table->foreignUuid('handled_by_user_id')
                    ->nullable()
                    ->after('created_by_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('customer_notes', 'handled_at')) {
                $table->timestamp('handled_at')->nullable()->after('next_action_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_notes', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_notes', 'handled_by_user_id')) {
                $table->dropConstrainedForeignId('handled_by_user_id');
            }

            foreach (['handled_at', 'requires_follow_up', 'source_channel', 'priority', 'status'] as $column) {
                if (Schema::hasColumn('customer_notes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
