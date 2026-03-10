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
        Schema::create('facebook_post_logs', function (Blueprint $table) {
            // Primary Key
            $table->id();
            
            // Job Posting Reference
            $table->foreignId('job_posting_id')
                ->constrained('job_postings')
                ->onDelete('cascade')
                ->comment('Reference to job posting');
            
            // Facebook Data
            $table->string('facebook_post_id')->nullable()
                ->comment('Facebook post ID from API response');
            $table->string('facebook_post_url')->nullable()
                ->comment('Direct URL to Facebook post');
            
            // Post Metadata
            $table->enum('post_type', ['manual', 'auto'])
                ->default('manual')
                ->comment('How post was triggered');
            $table->enum('status', ['pending', 'posted', 'failed'])
                ->default('pending')
                ->comment('Post status');
            $table->text('error_message')->nullable()
                ->comment('Error message if posting failed');
            
            // Engagement Metrics (updated periodically)
            $table->json('engagement_metrics')->nullable()
                ->comment('Likes, shares, comments, reach');
            $table->timestamp('metrics_updated_at')->nullable()
                ->comment('Last time engagement metrics were fetched');
            
            // Audit Fields
            $table->foreignId('posted_by')
                ->constrained('users')
                ->comment('User who triggered the post');
            $table->timestamps();
            
            // Indexes
            $table->index('job_posting_id', 'idx_fb_logs_job_posting');
            $table->index('status', 'idx_fb_logs_status');
            $table->index('created_at', 'idx_fb_logs_created_at');
            
            // Table Comment
            $table->comment('Log of all Facebook job posting activity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facebook_post_logs');
    }
};
