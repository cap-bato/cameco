<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interviews', function (Blueprint $table) {
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])
                  ->default('scheduled')
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('interviews', function (Blueprint $table) {
            $table->enum('status', ['scheduled', 'completed', 'canceled'])
                  ->default('scheduled')
                  ->change();
        });
    }
};
