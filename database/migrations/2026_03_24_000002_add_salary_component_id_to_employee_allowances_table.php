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
        Schema::table('employee_allowances', function (Blueprint $table) {
            $table->foreignId('salary_component_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('salary_components')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_allowances', function (Blueprint $table) {
            $table->dropForeign(['salary_component_id']);
            $table->dropColumn('salary_component_id');
        });
    }
};
