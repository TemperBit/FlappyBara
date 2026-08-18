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
        Schema::create('game_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('race_room_id')->nullable()->index();
            $table->string('mode', 12)->index();
            $table->unsignedInteger('score')->index();
            $table->unsignedInteger('duration_milliseconds');
            $table->timestamps();

            $table->index(['user_id', 'score']);
            $table->index(['mode', 'score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_runs');
    }
};
