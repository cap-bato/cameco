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
        Schema::create('rfid_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->unique()->comment('Unique device identifier (e.g., GATE-01)');
            $table->string('device_name')->comment('Human-readable device name');
            $table->string('location')->comment('Physical location of device');
            $table->enum('status', ['online', 'offline', 'maintenance'])->default('offline')->comment('Device status');
            $table->timestamp('last_heartbeat')->nullable()->comment('Last heartbeat timestamp');
            $table->json('config')->nullable()->comment('Device configuration JSON');
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfid_devices');
    }
};
