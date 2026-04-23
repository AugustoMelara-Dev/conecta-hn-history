<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('municipalities')) {
            Schema::create('municipalities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('department_id');
                $table->string('name');
                $table->string('slug')->unique();
                $table->timestamps();

                $table->index('department_id');
            });
        }

        if (! Schema::hasTable('cities')) {
            Schema::create('cities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('municipality_id')->nullable();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('image_path')->nullable();
                $table->json('coordinates')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_featured')->default(false);
                $table->timestamps();

                $table->index('municipality_id');
            });
        }

        $this->tryAddMunicipalityForeignKey();
        $this->tryAddCityForeignKey();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
        Schema::dropIfExists('municipalities');
        Schema::dropIfExists('departments');
    }

    private function tryAddMunicipalityForeignKey(): void
    {
        if (! Schema::hasTable('municipalities') || ! Schema::hasTable('departments')) {
            return;
        }

        if ($this->hasForeign('municipalities', 'municipalities_department_id_foreign')) {
            return;
        }

        try {
            Schema::table('municipalities', function (Blueprint $table): void {
                $table->foreign('department_id')
                    ->references('id')
                    ->on('departments')
                    ->cascadeOnDelete();
            });
        } catch (\Throwable) {
            // Legacy testing schemas can drift temporarily in migrate:fresh.
            // The FK is best-effort here to keep historical migrations deterministic.
        }
    }

    private function tryAddCityForeignKey(): void
    {
        if (! Schema::hasTable('cities') || ! Schema::hasTable('municipalities')) {
            return;
        }

        if ($this->hasForeign('cities', 'cities_municipality_id_foreign')) {
            return;
        }

        try {
            Schema::table('cities', function (Blueprint $table): void {
                $table->foreign('municipality_id')
                    ->references('id')
                    ->on('municipalities')
                    ->nullOnDelete();
            });
        } catch (\Throwable) {
            // See note above: keep migration resilient under legacy/testing drift.
        }
    }

    private function hasForeign(string $table, string $foreignName): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(static fn (array $foreign): bool => (string) ($foreign['name'] ?? '') === $foreignName);
    }
};
