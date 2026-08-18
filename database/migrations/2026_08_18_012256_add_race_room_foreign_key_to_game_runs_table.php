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
        Schema::table('game_runs', function (Blueprint $table) {
            $table->foreign('race_room_id')
                ->references('id')
                ->on('race_rooms')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_runs', function (Blueprint $table) {
            $table->dropForeign(['race_room_id']);
        });
    }
};
