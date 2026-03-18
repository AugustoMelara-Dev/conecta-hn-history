<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add reminder_sent_at column to appointments table.
 *
 * IDEMPOTENCY: This column prevents duplicate reminder emails.
 * When a reminder is sent, this is set to now().
 * The SendAppointmentReminders job filters out appointments
 * where this column is NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('staff_notes');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
