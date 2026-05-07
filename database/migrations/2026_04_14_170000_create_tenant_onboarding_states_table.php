<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_onboarding_states')) {
            return;
        }

        Schema::create('tenant_onboarding_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->index();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('role', 64)->default('seller');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('next_action_key', 120)->nullable();
            $table->string('next_action_title')->nullable();
            $table->text('next_action_copy')->nullable();
            $table->string('next_action_href')->nullable();
            $table->json('milestones');
            $table->timestamp('last_completed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'role'], 'tenant_onboarding_state_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_onboarding_states');
    }
};
