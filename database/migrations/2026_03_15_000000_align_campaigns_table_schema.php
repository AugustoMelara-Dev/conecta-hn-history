<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->renameColumn('title', 'subject');
            $table->integer('recipients_count')->default(0)->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->renameColumn('subject', 'title');
            $table->dropColumn('recipients_count');
        });
    }
};
