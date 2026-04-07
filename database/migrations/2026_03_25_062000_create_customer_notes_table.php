<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_notes')) {
            return;
        }

        Schema::create('customer_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('customer_profile_id')->constrained('customer_profiles')->cascadeOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note_type', 32)->default('general');
            $table->text('body');
            $table->timestamp('next_action_at')->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'note_type']);
            $table->index(['customer_profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_notes')) {
            Schema::drop('customer_notes');
        }
    }
};
