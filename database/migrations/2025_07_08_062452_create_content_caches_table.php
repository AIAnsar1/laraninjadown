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
        Schema::create('content_caches', function (Blueprint $table) {
            $table->id();
            $table->string("title");
            $table->string("content_link");
            $table->string("quality");
            $table->string("formats");
            $table->bigInteger("chat_id");
            $table->bigInteger("message_id");
            $table->string("file_id")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_caches');
    }
};
