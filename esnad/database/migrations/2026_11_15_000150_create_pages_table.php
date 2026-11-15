<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            // legal | info
            $table->string('kind', 20)->default('info')->index();
            $table->string('title_ar');
            $table->string('title_en');
            $table->longText('body_ar');
            $table->longText('body_en');
            $table->string('version', 20)->nullable();
            $table->boolean('is_published')->default(false);
            // Legal texts go live only after counsel has reviewed the Arabic.
            $table->date('counsel_reviewed_on')->nullable();
            $table->string('counsel_name')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
