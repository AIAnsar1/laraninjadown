<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Constants\GeneralStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->string('role_code')->index();
            $table->foreignId("user_id")->index()->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->smallInteger('status')->default(GeneralStatus::STATUS_NOT_ACTIVE)->index();
            // $table->foreignId("role_id")->constrained('rolles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
