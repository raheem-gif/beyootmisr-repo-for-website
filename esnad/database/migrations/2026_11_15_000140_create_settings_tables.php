<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
        });

        // Every change to a setting keeps the earlier value, who and when.
        Schema::create('setting_versions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->index();
            $table->unsignedInteger('version');
            $table->json('value')->nullable();
            $table->foreignId('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('enabled')->default(false);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Last time the scheduler and the queue worker ran, for /install/check.
        Schema::create('system_heartbeats', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->timestamp('beat_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_heartbeats');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('setting_versions');
        Schema::dropIfExists('settings');
    }
};
