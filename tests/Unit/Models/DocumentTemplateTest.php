<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DocumentTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->admin = User::factory()->create();
    }

    /**
     * Test DocumentTemplate relationships
     */
    public function test_document_template_belongs_to_created_by_user()
    {
        $template = DocumentTemplate::factory()
            ->create(['created_by' => $this->user->id]);

        $this->assertTrue($template->createdBy->is($this->user));
    }

    public function test_document_template_belongs_to_approved_by_user()
    {
        $template = DocumentTemplate::factory()
            ->create(['approved_by' => $this->admin->id]);

        $this->assertTrue($template->approvedBy->is($this->admin));
    }

    /**
     * Test DocumentTemplate scopes
     */
    public function test_active_scope()
    {
        DocumentTemplate::factory()->create(['is_active' => true]);
        DocumentTemplate::factory()->create(['is_active' => true]);
        DocumentTemplate::factory()->create(['is_active' => false]);

        $active = DocumentTemplate::active()->get();
        
        $this->assertCount(2, $active);
        $this->assertTrue($active->every(fn($t) => $t->is_active));
    }

    public function test_approved_scope()
    {
        DocumentTemplate::factory()->create(['status' => 'approved']);
        DocumentTemplate::factory()->create(['status' => 'draft']);

        $approved = DocumentTemplate::approved()->get();
        
        $this->assertCount(1, $approved);
        $this->assertEquals('approved', $approved->first()->status);
    }

    public function test_pending_scope()
    {
        DocumentTemplate::factory()->create(['status' => 'pending_approval']);
        DocumentTemplate::factory()->create(['status' => 'draft']);

        $pending = DocumentTemplate::pending()->get();
        
        $this->assertCount(1, $pending);
    }

    public function test_by_type_scope()
    {
        DocumentTemplate::factory()->create(['template_type' => 'contract']);
        DocumentTemplate::factory()->create(['template_type' => 'contract']);
        DocumentTemplate::factory()->create(['template_type' => 'coe']);

        $contracts = DocumentTemplate::byType('contract')->get();
        
        $this->assertCount(2, $contracts);
    }

    public function test_locked_scope()
    {
        DocumentTemplate::factory()->create(['is_locked' => true]);
        DocumentTemplate::factory()->create(['is_locked' => true]);
        DocumentTemplate::factory()->create(['is_locked' => false]);

        $locked = DocumentTemplate::locked()->get();
        
        $this->assertCount(2, $locked);
    }

    /**
     * Test DocumentTemplate methods
     */
    public function test_increment_version()
    {
        $template = DocumentTemplate::factory()->create(['version' => 1]);

        $template->incrementVersion();

        $this->refresh($template);
        $this->assertEquals(2, $template->version);
    }

    public function test_approve_method()
    {
        $template = DocumentTemplate::factory()
            ->create(['status' => 'draft', 'approved_by' => null]);

        $template->approve($this->admin);

        $this->refresh($template);
        $this->assertEquals('approved', $template->status);
        $this->assertTrue($template->approved_by === $this->admin->id);
        $this->assertTrue($template->is_locked);
    }

    public function test_reject_method()
    {
        $template = DocumentTemplate::factory()
            ->create(['status' => 'pending_approval']);

        $template->reject();

        $this->refresh($template);
        $this->assertEquals('draft', $template->status);
    }

    public function test_archive_method()
    {
        $template = DocumentTemplate::factory()
            ->create(['status' => 'approved', 'is_active' => true]);

        $template->archive();

        $this->refresh($template);
        $this->assertEquals('archived', $template->status);
        $this->assertFalse($template->is_active);
    }

    public function test_lock_method()
    {
        $template = DocumentTemplate::factory()
            ->create(['is_locked' => false]);

        $template->lock();

        $this->refresh($template);
        $this->assertTrue($template->is_locked);
    }

    public function test_unlock_method()
    {
        $template = DocumentTemplate::factory()
            ->create(['is_locked' => true]);

        $template->unlock();

        $this->refresh($template);
        $this->assertFalse($template->is_locked);
    }
}
