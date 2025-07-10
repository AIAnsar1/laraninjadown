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
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->string('ad_uuid')->index();
            $table->text('content')->nullable();
            $table->string('media_type')->nullable();
            $table->string('media_file_id')->nullable();
            $table->string('target_lang')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps(); // created_at, updated_at

            $table->unique(['ad_uuid', 'target_lang'], 'ix_advertisements_ad_uuid_lang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};
