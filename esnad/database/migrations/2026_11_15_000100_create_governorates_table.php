<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governorates', function (Blueprint $table) {
            $table->id();
            // Two-digit code used in positions 8 and 9 of the national ID.
            $table->char('id_code', 2)->unique();
            $table->string('slug')->unique();
            $table->string('name_ar');
            $table->string('name_en');
            // Launch with Cairo, Giza and Alexandria active.
            $table->boolean('is_active')->default(false)->index();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governorates');
    }
};
