<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_conversations')) {
            return;
        }

        if (Schema::hasTable('customer_notes')) {
            Schema::table('customer_notes', function (Blueprint $table): void {
                if (! Schema::hasColumn('customer_notes', 'customer_conversation_id')) {
                    $table->foreignId('customer_conversation_id')
                        ->nullable()
                        ->after('customer_profile_id')
                        ->constrained('customer_conversations')
                        ->nullOnDelete();
                }

                if (! $this->indexExists('customer_notes', 'customer_notes_tenant_conversation_idx')) {
                    $table->index(['tenant_id', 'customer_conversation_id'], 'customer_notes_tenant_conversation_idx');
                }
            });
        }

        if (Schema::hasTable('customer_touchpoints')) {
            Schema::table('customer_touchpoints', function (Blueprint $table): void {
                if (! Schema::hasColumn('customer_touchpoints', 'customer_conversation_id')) {
                    $table->foreignId('customer_conversation_id')
                        ->nullable()
                        ->after('customer_profile_id')
                        ->constrained('customer_conversations')
                        ->nullOnDelete();
                }

                if (! $this->indexExists('customer_touchpoints', 'customer_touchpoints_tenant_conversation_idx')) {
                    $table->index(['tenant_id', 'customer_conversation_id'], 'customer_touchpoints_tenant_conversation_idx');
                }
            });
        }

        if (Schema::hasTable('customer_tasks')) {
            Schema::table('customer_tasks', function (Blueprint $table): void {
                if (! Schema::hasColumn('customer_tasks', 'customer_conversation_id')) {
                    $table->foreignId('customer_conversation_id')
                        ->nullable()
                        ->after('customer_profile_id')
                        ->constrained('customer_conversations')
                        ->nullOnDelete();
                }

                if (! $this->indexExists('customer_tasks', 'customer_tasks_tenant_conversation_idx')) {
                    $table->index(['tenant_id', 'customer_conversation_id'], 'customer_tasks_tenant_conversation_idx');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('customer_tasks', function (Blueprint $table): void {
            $table->dropIndex('customer_tasks_tenant_conversation_idx');
            $table->dropConstrainedForeignId('customer_conversation_id');
        });

        Schema::table('customer_touchpoints', function (Blueprint $table): void {
            $table->dropIndex('customer_touchpoints_tenant_conversation_idx');
            $table->dropConstrainedForeignId('customer_conversation_id');
        });

        Schema::table('customer_notes', function (Blueprint $table): void {
            $table->dropIndex('customer_notes_tenant_conversation_idx');
            $table->dropConstrainedForeignId('customer_conversation_id');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return Schema::getConnection()
            ->table('information_schema.statistics')
            ->where('table_schema', Schema::getConnection()->getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
