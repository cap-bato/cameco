<?php

namespace Tests\Unit\Controllers\HR\Timekeeping;

use Tests\TestCase;
use App\Models\RfidLedger;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\Profile;
use App\Models\RfidDevice;
use App\Models\RfidCardMapping;
use App\Models\User;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * LedgerControllerTest
 * 
 * Unit tests for RFID Ledger Controller real data implementation.
 * Phase 5, Task 5.1: Unit Tests
 * 
 * Test Coverage:
 * - Test index() page renders with real data
 * - Test show() method with real event
 * - Test events() API with filters
 * - Test getLinkedAttendanceEvent()
 * - Test getRelatedEventsReal()
 */
class LedgerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $hrManager;
    protected Employee $testEmployee;
    protected RfidDevice $testDevice;

    protected function setUp(): void
    {
        parent::setUp();

        // Create HR Manager user for authentication
        $this->hrManager = User::factory()->create([
            'email' => 'hr.manager@test.com',
        ]);
        
        // Create HR Manager role and assign permissions
        $role = Role::firstOrCreate(['name' => 'HR Manager', 'guard_name' => 'web']);
        
        // Create required permission for ledger access
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'hr.timekeeping.attendance.view',
            'guard_name' => 'web',
        ]);
        
        $role->givePermissionTo($permission);
        $this->hrManager->assignRole($role);

        // Create test employee with profile
        $profile = Profile::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->testEmployee = Employee::factory()->create([
            'profile_id' => $profile->id,
        ]);

        // Create RFID card mapping for test employee
        RfidCardMapping::create([
            'employee_id' => $this->testEmployee->id,
            'card_uid' => 'RFID001',
            'card_type' => 'mifare',
            'is_active' => true,
            'issued_at' => now(),
            'issued_by' => $this->hrManager->id,
        ]);

        // Create test RFID device
        $this->testDevice = RfidDevice::create([
            'device_id' => 'DEVICE01',
            'device_name' => 'Main Gate Scanner',
            'location' => 'Main Entrance',
            'status' => 'online',
        ]);
    }

    /**
     * Test 1: Test index() page renders with real data
     * 
     * Verifies that the ledger index page:
     * - Returns 200 status
     * - Renders correct Inertia component
     * - Displays real ledger e'RFID001'
     * - Includes pagination data
     */
    public function test_ledger_index_displays_real_events()
    {
        // Arrange: Create 5 real ledger entries
        RfidLedger::factory()->count(5)->create([
            'device_id' => $this->testDevice->device_id,
            'employee_rfid' => 'RFID001',
        ]);

        // Act: Access ledger index page
        $response = $this->actingAs($this->hrManager)
            ->get('/hr/timekeeping/ledger');

        // Assert: Page renders with real data
        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('HR/Timekeeping/Ledger')
                ->has('logs.data', 5)
                ->has('logs.current_page')
                ->has('logs.per_page')
                ->has('logs.total')
                ->has('ledgerHealth')
                ->has('filters')
            );
    }

    /**
     * Test 2: Test show() method with real event
     * 
     * Verifies that the event detail page:
     * - Returns 200 status
     * - Renders EventDetail component
     * - Displays correct event data
     * - Includes related events
     */
    public function test_show_displays_real_event_detail()
    {
        // Arrange: Create a real ledger entry
        $ledgerEntry = RfidLedger::factory()->create([
            'sequence_id' => 12345,
            'device_id' => $this->testDevice->device_id,
            'employee_rfid' => 'RFID001',
            'event_type' => 'time_in',
            'scan_timestamp' => now(),
        ]);

        // Act: Access event detail page
        $response = $this->actingAs($this->hrManager)
            ->get("/hr/timekeeping/ledger/{$ledgerEntry->sequence_id}");

        // Assert: Page renders with correct event data
        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('HR/Timekeeping/EventDetail')
                ->where('event.sequence_id', $ledgerEntry->sequence_id)
                ->where('event.employee_rfid', 'RFID001')
                ->where('event.device_id', $this->testDevice->device_id)
                ->where('event.event_type', 'time_in')
                ->has('relatedEvents')
                ->has('relatedEvents.previous')
                ->has('relatedEvents.next')
                ->has('relatedEvents.employee_today')
            );
    }

    /**
     * Test 3a: Test events() API with date range filter
     * 
     * Verifies that API filtering by date range:
     * - Returns only events within specified date range
     * - Excludes events outside date range
     */
    public function test_events_api_filters_by_date_range()
    {
        // Arrange: Create events in different dates
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        
        // Event today (should be included)
        RfidLedger::factory()->create([
            'employee_rfid' => 'RFID001',
            'scan_timestamp' => $today->copy()->addHours(9),
        ]);

        // Event yesterday (should be excluded)
        RfidLedger::factory()->create([
            'employee_rfid' => 'RFID001',
            'scan_timestamp' => $yesterday->copy()->addHours(9),
        ]);

        // Act: Filter by today's date
        $response = $this->actingAs($this->hrManager)
            ->getJson('/hr/timekeeping/api/ledger/events?' . http_build_query([
                'date_from' => $today->toDateString(),
                'date_to' => $today->toDateString(),
            ]));

        // Assert: Only today's events are returned
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.scan_timestamp', function ($timestamp) use ($today) {
                return Carbon::parse($timestamp)->isSameDay($today);
            });
    }

    /**
     * Test 3b: Test events() API with device filter
     * 
     * Verifies that API filtering by device:
     * - Returns only events from specified device
     * - Excludes events from other devices
     */
    public function test_events_api_filters_by_device()
    {
        // Arrange: Create second device
        $device2 = RfidDevice::create([
            'device_id' => 'DEVICE02',
            'device_name' => 'Back Gate Scanner',
            'location' => 'Back Gate',
            'status' => 'online',
        ]);

        // Create events from different devices
        RfidLedger::factory()->create([
            'employee_rfid' => 'RFID001',
            'device_id' => $this->testDevice->device_id,
        ]);

        RfidLedger::factory()->create([
            'employee_rfid' => 'RFID001',
            'device_id' => $device2->device_id,
        ]);

        // Act: Filter by first device
        $response = $this->actingAs($this->hrManager)
            ->getJson('/hr/timekeeping/api/ledger/events?device_id=' . $this->testDevice->device_id);

        // Assert: Only events from specified device are returned
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.device_id', $this->testDevice->device_id);
    }

    /**
     * Test 3c: Test events() API with event type filter
     * 
     * Verifies that API filtering by event type:
     * - Returns only events of specified type
     * - Excludes events of other types
     */
    public function test_events_api_filters_by_event_type()
    {
        // Arrange: Create events of different types
        RfidLedger::factory()->create([
            'employee_rfid' => 'RFID001',
            'event_type' => 'time_in',
        ]);

        RfidLedger::factory()->create([
            'employee_rfid' => 'RFID001',
            'event_type' => 'time_out',
        ]);

        RfidLedger::factory()->create([
            'employee_rfid' => 'RFID001',
            'event_type' => 'break_start',
        ]);

        // Act: Filter by time_in events
        $response = $this->actingAs($this->hrManager)
            ->getJson('/hr/timekeeping/api/ledger/events?event_type=time_in');

        // Assert: Only time_in events are returned
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event_type', 'time_in');
    }

    /**
     * Test 3d: Test events() API with employee search filter
     * 
     * Verifies that API employee search:
     * - Returns events matching employee name
     * - Returns events matching employee RFID
     */
    public function test_events_api_filters_by_employee_search()
    {
        // Arrange: Create another employee
        $profile2 = Profile::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);

        $employee2 = Employee::factory()->create([
            'profile_id' => $profile2->id,
        ]);

        RfidCardMapping::create([
            'employee_id' => $employee2->id,
            'card_uid' => 'RFID002',
            'card_type' => 'mifare',
            'is_active' => true,
            'issued_at' => now(),
            'issued_by' => $this->hrManager->id,
        ]);

        // Create events for both employees
        RfidLedger::factory()->create([
            'employee_rfid' => 'RFID001',
        ]);

        RfidLedger::factory()->create([
            'employee_rfid' => 'RFID002',
        ]);

        // Act: Search by first employee's RFID
        $response = $this->actingAs($this->hrManager)
            ->getJson('/hr/timekeeping/api/ledger/events?employee_rfid=RFID001');

        // Assert: Only first employee's events are returned
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_rfid', 'RFID001');
    }

    /**
     * Test 3e: Test events() API pagination
     * 
     * Verifies that API pagination:
     * - Returns correct number of items per page
     * - Provides pagination metadata
     * - Can navigate between pages
     */
    public function test_events_api_pagination()
    {
        // Arrange: Create 25 ledger entries
        RfidLedger::factory()->count(25)->create([
            'employee_rfid' => 'RFID001',
        ]);

        // Act: Get first page with 10 items per page
        $response = $this->actingAs($this->hrManager)
            ->getJson('/hr/timekeeping/api/ledger/events?per_page=10');

        // Assert: Correct pagination
        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('total', 25)
            ->assertJsonPath('last_page', 3);

        // Act: Get second page
        $response2 = $this->actingAs($this->hrManager)
            ->getJson('/hr/timekeeping/api/ledger/events?per_page=10&page=2');

        // Assert: Second page has correct data
        $response2->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('current_page', 2);
    }

    /**
     * Test 4a: Test getLinkedAttendanceEvent() returns null for unprocessed
     * 
     * Verifies that linked attendance event:
     * - Returns null when ledger entry is not processed
     */
    public function test_linked_attendance_event_returns_null_for_unprocessed()
    {
        // Arrange: Create unprocessed ledger entry
        $ledgerEntry = RfidLedger::factory()->create([
            'sequence_id' => 12345,
            'employee_rfid' => 'RFID001',
            'processed' => false,
            'processed_at' => null,
        ]);

        // Act: Access event detail page
        $response = $this->actingAs($this->hrManager)
            ->get("/hr/timekeeping/ledger/{$ledgerEntry->sequence_id}");

        // Assert: No linked attendance event
        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('attendanceEvent', null)
            );
    }

    /**
     * Test 4b: Test getLinkedAttendanceEvent() returns real event when processed
     * 
     * Verifies that linked attendance event:
     * - Returns real AttendanceEvent when ledger entry is processed
     * - Includes correct event data
     */
    public function test_linked_attendance_event_returns_real_event_when_processed()
    {
        // Arrange: Create processed ledger entry
        $ledgerEntry = RfidLedger::factory()->create([
            'sequence_id' => 12345,
            'employee_rfid' => 'RFID001',
            'event_type' => 'time_in',
            'processed' => true,
            'processed_at' => now(),
        ]);

        // Create linked attendance event
        $attendanceEvent = AttendanceEvent::factory()->create([
            'employee_id' => $this->testEmployee->id,
            'ledger_sequence_id' => $ledgerEntry->sequence_id,
            'event_type' => 'time_in',
            'event_date' => $ledgerEntry->scan_timestamp->toDateString(),
            'event_time' => $ledgerEntry->scan_timestamp,
        ]);  

        // Act: Access event detail page
        $response = $this->actingAs($this->hrManager)
            ->get("/hr/timekeeping/ledger/{$ledgerEntry->sequence_id}");

        // Assert: Linked attendance event is present
        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('attendanceEvent')
                ->where('attendanceEvent.id', $attendanceEvent->id)
                ->where('attendanceEvent.ledger_sequence_id', $ledgerEntry->sequence_id)
            );
    }

    /**
     * Test 5a: Test getRelatedEventsReal() returns correct previous event
     * 
     * Verifies that previous event:
     * - Returns event with sequence_id < current sequence_id
     * - Returns most recent previous event
     */
    public function test_related_events_returns_correct_previous_event()
    {
        // Arrange: Create sequence of events
        $event1 = RfidLedger::factory()->create([
            'sequence_id' => 100,
            'employee_rfid' => 'RFID001',
            'scan_timestamp' => now()->subHours(3),
        ]);

        $event2 = RfidLedger::factory()->create([
            'sequence_id' => 101,
            'employee_rfid' => 'RFID001',
            'scan_timestamp' => now()->subHours(2),
        ]);

        $currentEvent = RfidLedger::factory()->create([
            'sequence_id' => 102,
            'employee_rfid' => 'RFID001',
            'scan_timestamp' => now()->subHours(1),
        ]);

        // Act: Access current event detail page
        $response = $this->actingAs($this->hrManager)
            ->get("/hr/timekeeping/ledger/{$currentEvent->sequence_id}");

        // Assert: Previous event is event2
        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('relatedEvents.previous.sequence_id', 101)
            );
    }

    /**
     * Test 5b: Test getRelatedEventsReal() returns correct next event
     * 
     * Verifies that next event:
     * - Returns event with sequence_id > current sequence_id
     * - Returns immediate next event
     */
    public function test_related_events_returns_correct_next_event()
    {
        // Arrange: Create sequence of events
        $currentEvent = RfidLedger::factory()->create([
            'sequence_id' => 100,
            'employee_rfid' => 'RFID001',
            'scan_timestamp' => now()->subHours(3),
        ]);

        $event2 = RfidLedger::factory()->create([
            'sequence_id' => 101,
            'employee_rfid' => 'RFID001',
            'scan_timestamp' => now()->subHours(2),
        ]);

        $event3 = RfidLedger::factory()->create([
            'sequence_id' => 102,
            'employee_rfid' => 'RFID001',
            'scan_timestamp' => now()->subHours(1),
        ]);

        // Act: Access current event detail page
        $response = $this->actingAs($this->hrManager)
            ->get("/hr/timekeeping/ledger/{$currentEvent->sequence_id}");

        // Assert: Next event is event2
        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('relatedEvents.next.sequence_id', 101)
            );
    }

    /**
     * Test 5c: Test getRelatedEventsReal() returns employee_today events
     * 
     * Verifies that employee_today events:
     * - Returns all events from same employee on same day
     * - Excludes events from other days
     * - Orders events by sequence_id
     */
    public function test_related_events_returns_accurate_employee_today_events()
    {
        // Arrange: Create events for same employee
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();

        // Today's events (should be included)
        $event1 = RfidLedger::factory()->create([
            'sequence_id' => 100,
            'employee_rfid' => 'RFID001',
            'scan_timestamp' => $today->copy()->addHours(8),
            'event_type' => 'time_in',
        ]);

        $event2 = RfidLedger::factory()->create([
            'sequence_id' => 101,
            'employee_rfid' => 'RFID001',
            'scan_timestamp' => $today->copy()->addHours(12),
            'event_type' => 'break_start',
        ]);

        // Yesterday's event (should be excluded)
        RfidLedger::factory()->create([
            'sequence_id' => 99,
            'employee_rfid' => 'RFID001',
            'scan_timestamp' => $yesterday->copy()->addHours(8),
        ]);

        // Act: Access event1 detail page
        $response = $this->actingAs($this->hrManager)
            ->get("/hr/timekeeping/ledger/{$event1->sequence_id}");

        // Assert: employee_today contains only today's events
        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('relatedEvents.employee_today', 2)
                ->where('relatedEvents.employee_today.0.sequence_id', 100)
                ->where('relatedEvents.employee_today.1.sequence_id', 101)
            );
    }

    /**
     * Test: Handle non-existent event gracefully
     * 
     * Verifies that accessing non-existent event:
     * - Returns 404 status
     */
    public function test_show_returns_404_for_non_existent_event()
    {
        // Act: Try to access non-existent event
        $response = $this->actingAs($this->hrManager)
            ->get('/hr/timekeeping/ledger/999999');

        // Assert: 404 response
        $response->assertNotFound();
    }

    /**
     * Test: Empty ledger displays gracefully
     * 
     * Verifies that with no ledger events:
     * - Page still renders correctly
     * - Shows empty data array
     */
    public function test_index_handles_empty_ledger_gracefully()
    {
        // Arrange: No ledger entries created

        // Act: Access ledger index page
        $response = $this->actingAs($this->hrManager)
            ->get('/hr/timekeeping/ledger');

        // Assert: Page renders with empty data
        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('HR/Timekeeping/Ledger')
                ->has('logs.data', 0)
                ->where('logs.total', 0)
            );
    }

    /**
     * Test: Multiple device filters work correctly
     * 
     * Verifies that API can filter by multiple criteria simultaneously
     */
    public function test_events_api_handles_multiple_filters()
    {
        // Arrange: Create diverse events
        $today = now()->startOfDay();

        // Matching event (device + date + type)
        RfidLedger::factory()->create([
            'device_id' => $this->testDevice->device_id,
            'event_type' => 'time_in',
            'scan_timestamp' => $today->copy()->addHours(9),
        ]);

        // Non-matching device
        RfidLedger::factory()->create([
            'device_id' => 'DEVICE99',
            'event_type' => 'time_in',
            'scan_timestamp' => $today->copy()->addHours(9),
        ]);

        // Non-matching type
        RfidLedger::factory()->create([
            'device_id' => $this->testDevice->device_id,
            'event_type' => 'time_out',
            'scan_timestamp' => $today->copy()->addHours(9),
        ]);

        // Act: Apply multiple filters
        $response = $this->actingAs($this->hrManager)
            ->getJson('/hr/timekeeping/api/ledger/events?' . http_build_query([
                'device_id' => $this->testDevice->device_id,
                'event_type' => 'time_in',
                'date_from' => $today->toDateString(),
                'date_to' => $today->toDateString(),
            ]));

        // Assert: Only matching event is returned
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.device_id', $this->testDevice->device_id)
            ->assertJsonPath('data.0.event_type', 'time_in');
    }
}
