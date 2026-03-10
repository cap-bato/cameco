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
        Schema::table('leave_requests', function (Blueprint $table) {
            // Track who approved at each level (manager_id and manager_approved_at already exist)
            $table->foreignId('approved_by_manager_id')
                ->nullable()
                ->after('manager_comments')
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User ID of HR Manager who approved');
            
            $table->foreignId('approved_by_admin_id')
                ->nullable()
                ->after('approved_by_manager_id')
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User ID of Office Admin who approved');
            
            // Auto-approval flag
            $table->boolean('auto_approved')
                ->default(false)
                ->after('status')
                ->comment('Whether this request was auto-approved by system');
            
            // Workforce coverage at approval time
            $table->decimal('coverage_percentage', 5, 2)
                ->nullable()
                ->after('auto_approved')
                ->comment('Department coverage % at approval time');
            
            // Timestamp for admin approval (manager_approved_at already exists)
            $table->timestamp('admin_approved_at')->nullable()->after('coverage_percentage');
            
            // Indexes
            $table->index('auto_approved');
            $table->index(['status', 'auto_approved']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropForeign(['approved_by_manager_id']);
            $table->dropForeign(['approved_by_admin_id']);
            $table->dropColumn([
                'approved_by_manager_id',
                'approved_by_admin_id',
                'auto_approved',
                'coverage_percentage',
                'admin_approved_at',
            ]);
        });
    }
};
