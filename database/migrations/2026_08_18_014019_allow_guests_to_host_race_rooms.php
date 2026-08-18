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
        Schema::table('race_rooms', function (Blueprint $table) {
            $table->foreignId('host_id')->nullable()->change();
            $table->uuid('host_guest_id')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('race_rooms', function (Blueprint $table) {
            $table->dropColumn('host_guest_id');
            $table->foreignId('host_id')->nullable(false)->change();
        });
    }
};
