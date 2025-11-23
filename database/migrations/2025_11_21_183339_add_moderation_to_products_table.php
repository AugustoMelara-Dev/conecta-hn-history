<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('price');
            $table->text('moderation_note')->nullable()->after('status');
            $table->timestamp('rejected_at')->nullable()->after('moderation_note');
            
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['status', 'moderation_note', 'rejected_at']);
        });
    }
};
