<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_onboarding_milestone_events')) {
            return;
        }

        Schema::create('tenant_onboarding_milestone_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->index();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('role', 64)->index();
            $table->string('milestone_key', 120)->index();
            $table->string('event_type', 32)->index();
            $table->unsignedTinyInteger('snapshot_progress')->default(0);
            $table->timestamp('occurred_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'role', 'milestone_key'], 'tenant_onboarding_event_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_onboarding_milestone_events');
    }
};
