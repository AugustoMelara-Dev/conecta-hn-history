<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('domain_outbox_messages')) {
            return;
        }

        Schema::create('domain_outbox_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('aggregate_type')->nullable()->index();
            $table->uuid('aggregate_id')->nullable()->index();
            $table->json('payload');
            $table->string('status')->default('PENDING')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_outbox_messages');
    }
};
