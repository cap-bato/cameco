<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\BulkUploadBatch;
use App\Models\EmployeeDocument;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BulkUploadBatchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Test BulkUploadBatch relationships
     */
    public function test_bulk_upload_batch_belongs_to_uploader()
    {
        $batch = BulkUploadBatch::factory()
            ->create(['uploaded_by' => $this->user->id]);

        $this->assertTrue($batch->uploadedBy->is($this->user));
    }

    public function test_bulk_upload_batch_has_many_documents()
    {
        $batch = BulkUploadBatch::factory()->create();
        
        EmployeeDocument::factory()
            ->count(5)
            ->create(['bulk_upload_batch_id' => $batch->id]);

        $this->assertCount(5, $batch->documents);
    }

    /**
     * Test BulkUploadBatch scopes
     */
    public function test_completed_scope()
    {
        BulkUploadBatch::factory()->create(['status' => 'completed']);
        BulkUploadBatch::factory()->create(['status' => 'processing']);

        $completed = BulkUploadBatch::completed()->get();
        
        $this->assertCount(1, $completed);
        $this->assertEquals('completed', $completed->first()->status);
    }

    public function test_failed_scope()
    {
        BulkUploadBatch::factory()->create(['status' => 'failed']);
        BulkUploadBatch::factory()->create(['status' => 'completed']);

        $failed = BulkUploadBatch::failed()->get();
        
        $this->assertCount(1, $failed);
        $this->assertEquals('failed', $failed->first()->status);
    }

    public function test_processing_scope()
    {
        BulkUploadBatch::factory()->create(['status' => 'processing']);
        BulkUploadBatch::factory()->create(['status' => 'completed']);

        $processing = BulkUploadBatch::processing()->get();
        
        $this->assertCount(1, $processing);
        $this->assertEquals('processing', $processing->first()->status);
    }

    public function test_by_uploader_scope()
    {
        $batch1 = BulkUploadBatch::factory()->create(['uploaded_by' => $this->user->id]);
        $batch2 = BulkUploadBatch::factory()->create(['uploaded_by' => $this->user->id]);
        BulkUploadBatch::factory()->create();

        $batches = BulkUploadBatch::byUploader($this->user->id)->get();
        
        $this->assertCount(2, $batches);
        $this->assertTrue($batches->contains($batch1));
        $this->assertTrue($batches->contains($batch2));
    }

    public function test_recent_scope()
    {
        BulkUploadBatch::factory()->create([
            'started_at' => Carbon::now()->subDays(2)
        ]);
        BulkUploadBatch::factory()->create([
            'started_at' => Carbon::now()->subDays(10)
        ]);

        $recent = BulkUploadBatch::recent(5)->get();
        
        $this->assertCount(1, $recent);
    }

    /**
     * Test BulkUploadBatch methods
     */
    public function test_mark_processing()
    {
        $batch = BulkUploadBatch::factory()
            ->create(['status' => 'processing', 'started_at' => null]);

        $batch->markProcessing();

        $this->refresh($batch);
        $this->assertEquals('processing', $batch->status);
        $this->assertNotNull($batch->started_at);
    }

    public function test_mark_completed()
    {
        $batch = BulkUploadBatch::factory()
            ->create(['status' => 'processing', 'completed_at' => null]);

        $batch->markCompleted();

        $this->refresh($batch);
        $this->assertEquals('completed', $batch->status);
        $this->assertNotNull($batch->completed_at);
    }

    public function test_mark_failed()
    {
        $batch = BulkUploadBatch::factory()
            ->create(['status' => 'processing']);

        $batch->markFailed('CSV format invalid');

        $this->refresh($batch);
        $this->assertEquals('failed', $batch->status);
    }

    public function test_add_error()
    {
        $batch = BulkUploadBatch::factory()->create(['error_log' => []]);

        $batch->addError(1, 'Employee not found', ['employee_id' => '123']);
        $batch->addError(2, 'Invalid date format');

        $this->refresh($batch);
        $this->assertCount(2, $batch->error_log);
        $this->assertEquals('Employee not found', $batch->error_log[0]['message']);
    }

    public function test_increment_success()
    {
        $batch = BulkUploadBatch::factory()
            ->create(['success_count' => 0]);

        $batch->incrementSuccess();
        $batch->incrementSuccess();

        $this->refresh($batch);
        $this->assertEquals(2, $batch->success_count);
    }

    public function test_success_rate_accessor()
    {
        $batch = BulkUploadBatch::factory()
            ->create(['total_count' => 10, 'success_count' => 8]);

        $this->assertEquals(80, $batch->success_rate);
    }

    public function test_error_rate_accessor()
    {
        $batch = BulkUploadBatch::factory()
            ->create(['total_count' => 10, 'error_count' => 2]);

        $this->assertEquals(20, $batch->error_rate);
    }

    public function test_is_completed_accessor()
    {
        $completedBatch = BulkUploadBatch::factory()
            ->create(['status' => 'completed']);
        
        $processingBatch = BulkUploadBatch::factory()
            ->create(['status' => 'processing']);

        $this->assertTrue($completedBatch->is_completed);
        $this->assertFalse($processingBatch->is_completed);
    }
}
