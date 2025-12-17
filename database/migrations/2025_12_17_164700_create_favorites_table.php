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
        // Drop existing favorites table if it exists (old structure)
        Schema::dropIfExists('favorites');

        // Create new polymorphic favorites table
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();

            // User who favorited
            $table->foreignId('user_id')
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
