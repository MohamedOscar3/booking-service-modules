<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\AvailabilityManagement\Enums\SlotType;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_management', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('users')->onDelete('cascade');
            $table->string('type')->default(SlotType::once->value)->index();
            $table->integer('week_day')->index()->nullable();
            $table->string('from');
            $table->string('to');
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_management');
    }
};
