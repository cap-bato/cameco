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
        Schema::table('job_postings', function (Blueprint $table) {
            // Facebook Integration Fields
            $table->string('facebook_post_id')->nullable()
                ->comment('Facebook post ID from Graph API');
            $table->string('facebook_post_url')->nullable()
                ->comment('Direct URL to Facebook post');
            $table->timestamp('facebook_posted_at')->nullable()
                ->comment('Timestamp when posted to Facebook');
            $table->boolean('auto_post_facebook')->default(false)
                ->comment('Automatically post to Facebook when published');
            
            // Indexes
            $table->index('facebook_post_id', 'idx_job_postings_facebook_post');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropIndex('idx_job_postings_facebook_post');
            $table->dropColumn([
                'facebook_post_id',
                'facebook_post_url',
                'facebook_posted_at',
                'auto_post_facebook',
            ]);
        });
    }
};
