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
        Schema::table('bookings', function (Blueprint $table) {
            $table->time('time')->nullable()->after('date');
            $table->foreignId('slot_id')->nullable()->constrained('availability_management')->after('service_id');
            $table->foreignId('provider_id')->nullable()->constrained('users')->after('user_id');
            $table->text('provider_notes')->nullable()->after('service_description');
            $table->text('customer_notes')->nullable()->after('provider_notes');

            // Add indexes for better performance
            $table->index(['date', 'service_id']);
            $table->index(['provider_id', 'date']);
            $table->index(['status', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['slot_id']);
            $table->dropForeign(['provider_id']);
            $table->dropColumn(['time', 'slot_id', 'provider_id', 'provider_notes', 'customer_notes']);

            $table->dropIndex(['date', 'service_id']);
            $table->dropIndex(['provider_id', 'date']);
            $table->dropIndex(['status', 'date']);
        });
    }
};
