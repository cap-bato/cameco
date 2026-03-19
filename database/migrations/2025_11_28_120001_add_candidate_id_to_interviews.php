<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add candidate_id column to interviews table.
     * InterviewController::store() accepts and stores candidate_id, but the column was missing.
     */
    public function up(): void
    {
        Schema::table('interviews', function (Blueprint $table) {
            $table->foreignId('candidate_id')
                ->nullable()
                ->after('application_id')
                ->constrained('candidates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('interviews', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['candidate_id']);
            $table->dropColumn('candidate_id');
        });
    }
};
