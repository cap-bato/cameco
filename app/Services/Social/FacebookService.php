<?php

namespace App\Services\Social;

use App\Models\JobPosting;
use App\Models\FacebookPostLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * FacebookService - Handles all Facebook Graph API interactions
 * 
 * Manages job posting publication to Facebook pages, engagement tracking,
 * and post management (delete, update metrics).
 */
class FacebookService
{
    protected string $graphUrl;
    protected string $pageId;
    protected string $pageAccessToken;
    protected string $apiVersion;
    protected bool $enabled;

    /**
     * Initialize Facebook service with configuration
     */
    public function __construct()
    {
        $this->graphUrl = config('facebook.graph_url');
        $this->pageId = config('facebook.page_id');
        $this->pageAccessToken = config('facebook.page_access_token');
        $this->apiVersion = config('facebook.api_version');
        $this->enabled = config('facebook.enabled');
    }

    /**
     * Check if Facebook integration is enabled and properly configured
     */
    public function isEnabled(): bool
    {
        return $this->enabled &&
               !empty($this->pageId) &&
               !empty($this->pageAccessToken);
    }

    /**
     * Post job to Facebook Page
     *
     * @param JobPosting $jobPosting The job posting to publish
     * @param int $userId The user publishing the post
     * @param bool $isAuto Whether this is an automatic post
     * @return array Result array with success status and details
     * @throws Exception If Facebook integration is not enabled
     */
    public function postJob(JobPosting $jobPosting, int $userId, bool $isAuto = false): array
    {
        if (!$this->isEnabled()) {
            throw new Exception('Facebook integration is not enabled or configured.');
        }

        // Create log entry with pending status
        $log = FacebookPostLog::create([
            'job_posting_id' => $jobPosting->id,
            'post_type' => $isAuto ? 'auto' : 'manual',
            'status' => 'pending',
            'posted_by' => $userId,
        ]);

        try {
            // Format job posting message for Facebook
            $message = $this->formatJobMessage($jobPosting);
            $link = $this->getJobPostingLink($jobPosting);

            // Call Facebook Graph API to post to page feed
            $response = Http::post("{$this->graphUrl}/{$this->apiVersion}/{$this->pageId}/feed", [
                'message' => $message,
                'link' => $link,
                'access_token' => $this->pageAccessToken,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $postId = $data['id'];
                $postUrl = "https://www.facebook.com/{$postId}";

                // Update log entry with success
                $log->update([
                    'facebook_post_id' => $postId,
                    'facebook_post_url' => $postUrl,
                    'status' => 'posted',
                ]);

                // Update job posting with Facebook post details
                $jobPosting->update([
                    'facebook_post_id' => $postId,
                    'facebook_post_url' => $postUrl,
                    'facebook_posted_at' => now(),
                ]);

                Log::info('Facebook post created successfully', [
                    'job_posting_id' => $jobPosting->id,
                    'facebook_post_id' => $postId,
                    'user_id' => $userId,
                ]);

                return [
                    'success' => true,
                    'post_id' => $postId,
                    'post_url' => $postUrl,
                    'log_id' => $log->id,
                ];
            } else {
                throw new Exception('Facebook API returned error: ' . $response->body());
            }
        } catch (Exception $e) {
            // Update log entry with failure details
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Facebook post failed', [
                'job_posting_id' => $jobPosting->id,
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'log_id' => $log->id,
            ];
        }
    }

    /**
     * Format job posting into an attractive Facebook message
     *
     * @param JobPosting $jobPosting The job posting to format
     * @return string Formatted message for Facebook
     */
    protected function formatJobMessage(JobPosting $jobPosting): string
    {
        $department = $jobPosting->department?->name ?? 'Various Departments';
        $description = strip_tags($jobPosting->description ?? '');
        $requirements = strip_tags($jobPosting->requirements ?? '');

        // Truncate fields if too long
        $description = substr($description, 0, 350);
        $requirements = substr($requirements, 0, 200);

        $message = "🔔 We're Hiring! 🔔\n\n";
        $message .= "Position: {$jobPosting->title}\n";
        $message .= "Department: {$department}\n\n";

        if (!empty($description)) {
            $message .= "📋 Job Description:\n";
            $message .= $description . "\n\n";
        }

        if (!empty($requirements)) {
            $message .= "✅ Requirements:\n";
            $message .= $requirements . "\n\n";
        }

        $message .= "📩 Interested? Click the link below to apply online!\n";
        $message .= "#Hiring #JobOpening #CareerOpportunity";

        return $message;
    }

    /**
     * Get the public job posting link
     *
     * @param JobPosting $jobPosting The job posting
     * @return string Public URL to the job posting
     */
    protected function getJobPostingLink(JobPosting $jobPosting): string
    {
        return url("/job-postings/{$jobPosting->id}");
    }

    /**
     * Fetch engagement metrics for a Facebook post
     *
     * @param string $postId The Facebook post ID
     * @return array|null Engagement metrics or null if fetch fails
     */
    public function getPostEngagement(string $postId): ?array
    {
        try {
            $response = Http::get("{$this->graphUrl}/{$this->apiVersion}/{$postId}", [
                'fields' => 'likes.summary(true),comments.summary(true),shares',
                'access_token' => $this->pageAccessToken,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'likes' => $data['likes']['summary']['total_count'] ?? 0,
                    'comments' => $data['comments']['summary']['total_count'] ?? 0,
                    'shares' => $data['shares']['count'] ?? 0,
                    'fetched_at' => now()->toISOString(),
                ];
            }
        } catch (Exception $e) {
            Log::error('Failed to fetch Facebook engagement metrics', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Update engagement metrics for a job posting
     *
     * @param JobPosting $jobPosting The job posting to update metrics for
     * @return bool True if metrics were updated, false otherwise
     */
    public function updateEngagementMetrics(JobPosting $jobPosting): bool
    {
        // Check if job posting has been posted to Facebook
        if (!$jobPosting->isPostedToFacebook()) {
            return false;
        }

        // Get the latest Facebook post log
        $log = $jobPosting->latestFacebookPost();
        if (!$log) {
            return false;
        }

        // Fetch current engagement metrics from Facebook
        $metrics = $this->getPostEngagement($log->facebook_post_id);

        if ($metrics) {
            $log->update([
                'engagement_metrics' => $metrics,
                'metrics_updated_at' => now(),
            ]);
            return true;
        }

        return false;
    }

    /**
     * Delete a Facebook post
     *
     * @param string $postId The Facebook post ID to delete
     * @return bool True if post was deleted, false otherwise
     */
    public function deletePost(string $postId): bool
    {
        try {
            $response = Http::delete("{$this->graphUrl}/{$this->apiVersion}/{$postId}", [
                'access_token' => $this->pageAccessToken,
            ]);

            if ($response->successful()) {
                Log::info('Facebook post deleted successfully', [
                    'post_id' => $postId,
                ]);
                return true;
            }

            Log::warning('Failed to delete Facebook post', [
                'post_id' => $postId,
                'response' => $response->body(),
            ]);
            return false;
        } catch (Exception $e) {
            Log::error('Error deleting Facebook post', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Test Facebook connection and credentials
     *
     * @return array Result array with success status and page details
     */
    public function testConnection(): array
    {
        try {
            $response = Http::get("{$this->graphUrl}/{$this->apiVersion}/{$this->pageId}", [
                'fields' => 'name,id',
                'access_token' => $this->pageAccessToken,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'page_name' => $data['name'] ?? 'Unknown',
                    'page_id' => $data['id'] ?? 'Unknown',
                    'message' => 'Successfully connected to Facebook page',
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response->body(),
                    'message' => 'Failed to connect to Facebook page',
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Exception occurred while testing connection',
            ];
        }
    }

    /**
     * Get the latest posts from Facebook page (for monitoring)
     *
     * @param int $limit Number of posts to retrieve (max 25)
     * @return array|null Array of posts or null if fetch fails
     */
    public function getPagePosts(int $limit = 10): ?array
    {
        try {
            $response = Http::get("{$this->graphUrl}/{$this->apiVersion}/{$this->pageId}/posts", [
                'fields' => 'id,message,story,created_time,permalink_url',
                'limit' => min($limit, 25),
                'access_token' => $this->pageAccessToken,
            ]);

            if ($response->successful()) {
                return $response->json()['data'] ?? [];
            }
        } catch (Exception $e) {
            Log::error('Failed to fetch Facebook page posts', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
