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
        Schema::table('employees', function (Blueprint $table) {
            // Add email field
            $table->string('email')->unique()->nullable()->after('employee_number');
            
            // Change position to position_id (foreign key)
            $table->dropColumn('position');
            $table->foreignId('position_id')->nullable()->after('department_id')->constrained('positions')->onDelete('set null');
            
            // Update employment_type enum values to match form
            $table->dropColumn('employment_type');
            $table->enum('employment_type', ['regular', 'probationary', 'contractual', 'project-based', 'part-time'])->nullable()->after('position_id');
            
            // Rename date fields to match form expectations
            $table->renameColumn('date_employed', 'date_hired');
            $table->renameColumn('date_regularized', 'regularization_date');
            
            // Update status enum values
            $table->dropColumn('status');
            $table->enum('status', ['active', 'on_leave', 'suspended', 'terminated', 'archived'])->default('active')->after('immediate_supervisor_id');
            
            // Make audit fields nullable (will be set by service/controller logic)
            $table->foreignId('created_by')->nullable()->change();
            $table->foreignId('updated_by')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('email');
            $table->dropForeign(['position_id']);
            $table->dropColumn('position_id');
            $table->string('position')->nullable();
            $table->dropColumn('employment_type');
            $table->enum('employment_type', ['regular', 'contractual', 'probationary', 'consultant'])->nullable();
            $table->renameColumn('date_hired', 'date_employed');
            $table->renameColumn('regularization_date', 'date_regularized');
            $table->dropColumn('status');
            $table->enum('status', ['active', 'archived', 'terminated', 'on_leave', 'suspended'])->default('active');
            $table->foreignId('created_by')->nullable(false)->change();
        });
    }
};
