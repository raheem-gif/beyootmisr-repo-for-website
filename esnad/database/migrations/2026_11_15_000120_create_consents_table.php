<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Consent register (PDPL Article 12 for sensitive data, Articles 17
        // and 18 for marketing). Rows are never edited: a withdrawal sets
        // withdrawn_at once, and a new grant is a new row.
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // service | sensitive_data | transfer_abroad | marketing
            $table->string('purpose', 30);
            $table->string('version', 20);
            // web_checkbox | otp | written | api
            $table->string('method', 20);
            // Free text describing what the consent covers, such as a booking ULID.
            $table->string('scope')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('given_at');
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'purpose', 'withdrawn_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
