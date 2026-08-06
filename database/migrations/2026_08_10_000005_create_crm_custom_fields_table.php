<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_custom_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('entity', 30); // contact/company/deal
            $table->string('name', 100);
            $table->string('key', 100);
            $table->string('type', 30); // text/number/date/select/boolean/email/url/textarea
            $table->json('options')->nullable(); // for select type
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'entity', 'key']);
            $table->index(['tenant_id', 'entity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_custom_fields');
    }
};
