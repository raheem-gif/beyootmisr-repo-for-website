<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Anti-Cybercrime Law No. 175 of 2018, Article 2: identity, traffic
        // and device data kept for 180 consecutive days, then deleted.
        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip', 45);
            $table->string('method', 8);
            // Route name or path template, never query strings with personal data.
            $table->string('route', 191);
            $table->unsignedSmallInteger('status');
            $table->string('user_agent', 255)->nullable();
            $table->string('device_id', 64)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // Register of judicial orders for log disclosure.
        Schema::create('legal_requests', function (Blueprint $table) {
            $table->id();
            $table->string('authority');
            $table->string('order_reference');
            $table->date('order_date');
            $table->text('scope');
            $table->string('status', 20)->default('received');
            $table->foreignId('recorded_by');
            $table->foreignId('approved_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_requests');
        Schema::dropIfExists('access_logs');
    }
};
