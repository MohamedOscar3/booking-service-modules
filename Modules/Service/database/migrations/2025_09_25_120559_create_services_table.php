<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->integer('duration');
            $table->float('price');
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['provider_id', 'name']);
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->boolean('status')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
