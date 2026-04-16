<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Converts favorites table to polymorphic structure.
     * Strategy: "Match by Email" - links existing accounts silently.
     */
    public function up(): void
    {
        if (Schema::hasTable('favorites')
            && Schema::hasColumns('favorites', ['favoritable_id', 'favoritable_type'])
        ) {
            return;
        }

        if (Schema::hasTable('favorites')) {
            Schema::drop('favorites');
        }

        // Create new polymorphic favorites table
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();

            // User who favorited
            $table->foreignUuid('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Polymorphic relation (favoritable_id + favoritable_type)
            $table->morphs('favoritable');

            $table->timestamps();

            // Unique constraint: user can only favorite an item once
            $table->unique(['user_id', 'favoritable_id', 'favoritable_type'], 'favorites_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
