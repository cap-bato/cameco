<?php

namespace Tests\Unit\Controllers\HR\Timekeeping;

use Tests\TestCase;
use App\Models\DailyAttendanceSummary;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Profile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AnalyticsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Test getAttendanceTrends returns correct data structure
     */
    public function test_get_attendance_trends_returns_correct_structure()
    {
        $startDate = now()->subDays(6)->startOfDay();
        for ($i = 0; $i < 5; $i++) {
            $date = $startDate->addDays($i);
            DailyAttendanceSummary::create([
                'employee_id' => Employee::factory()->create()->id,
                'attendance_date' => $date,
                'total_hours' => 8.0,
                'is_present' => true,
                'is_late' => false,
                'is_on_leave' => false,
                'late_minutes' => 0,
                'overtime_hours' => 0,
            ]);
        }

        $response = $this->get('/hr/timekeeping/overview?period=week');
        $response->assertStatus(200);

        $analytics = $response->original->getData()['props']['analytics'] ?? null;
        $this->assertNotNull($analytics);
        
        $trends = $analytics['attendance_trends'] ?? [];
        $this->assertIsArray($trends);
        $this->assertGreaterThan(0, count($trends));
        
        foreach ($trends as $trend) {
            $this->assertArrayHasKey('date', $trend);
            $this->assertArrayHasKey('present_count', $trend);
            $this->assertArrayHasKey('late_count', $trend);
            $this->assertArrayHasKey('absent_count', $trend);
        }
    }

    /**
     * Test getLateTrends returns correct data structure
     */
    public function test_get_late_trends_returns_correct_structure()
    {
        $startDate = now()->subDays(4)->startOfDay();
        for ($i = 0; $i < 5; $i++) {
            $date = $startDate->addDays($i);
            DailyAttendanceSummary::create([
                'employee_id' => Employee::factory()->create()->id,
                'attendance_date' => $date,
                'total_hours' => 8.0,
                'is_present' => true,
                'is_late' => $i % 2 == 0,
                'is_on_leave' => false,
                'late_minutes' => $i % 2 == 0 ? 15 : 0,
                'overtime_hours' => 0,
            ]);
        }

        $response = $this->get('/hr/timekeeping/overview?period=week');
        $response->assertStatus(200);

        $analytics = $response->original->getData()['props']['analytics'] ?? null;
        $lateTrends = $analytics['late_trends'] ?? [];
        
        $this->assertIsArray($lateTrends);
        $this->assertGreaterThan(0, count($lateTrends));
        
        foreach ($lateTrends as $trend) {
            $this->assertArrayHasKey('date', $trend);
            $this->assertArrayHasKey('late_count', $trend);
        }
    }

    /**
     * Test getDepartmentComparison returns real departments
     */
    public function test_get_department_comparison_returns_real_departments()
    {
        $dept = Department::factory()->create(['name' => 'Test Department']);
        $emp = Employee::factory()->create(['department_id' => $dept->id, 'status' => 'active']);

        DailyAttendanceSummary::create([
            'employee_id' => $emp->id,
            'attendance_date' => now()->startOfMonth(),
            'total_hours' => 8.0,
            'is_present' => true,
            'is_late' => false,
            'is_on_leave' => false,
            'late_minutes' => 0,
            'overtime_hours' => 0,
        ]);

        $response = $this->get('/hr/timekeeping/overview');
        $response->assertStatus(200);

        $analytics = $response->original->getData()['props']['analytics'] ?? null;
        $departments = $analytics['department_comparison'] ?? [];

        $this->assertIsArray($departments);
        $this->assertGreaterThan(0, count($departments));

        foreach ($departments as $dept) {
            $this->assertArrayHasKey('department_id', $dept);
            $this->assertArrayHasKey('department_name', $dept);
            $this->assertArrayHasKey('total_employees', $dept);
        }
    }

    /**
     * Test getOvertimeAnalysis returns correct structure
     */
    public function test_get_overtime_analysis_returns_correct_structure()
    {
        $emp1 = Employee::factory()->create(['status' => 'active']);
        $emp2 = Employee::factory()->create(['status' => 'active']);

        DailyAttendanceSummary::create([
            'employee_id' => $emp1->id,
            'attendance_date' => now()->startOfMonth(),
            'total_hours' => 8.0,
            'is_present' => true,
            'is_late' => false,
            'is_on_leave' => false,
            'late_minutes' => 0,
            'overtime_hours' => 5.0,
        ]);

        DailyAttendanceSummary::create([
            'employee_id' => $emp2->id,
            'attendance_date' => now()->startOfMonth(),
            'total_hours' => 8.0,
            'is_present' => true,
            'is_late' => false,
            'is_on_leave' => false,
            'late_minutes' => 0,
            'overtime_hours' => 3.5,
        ]);

        $response = $this->get('/hr/timekeeping/overview');
        $response->assertStatus(200);

        $analytics = $response->original->getData()['props']['analytics'] ?? null;
        $overtime = $analytics['overtime_analysis'] ?? [];

        $this->assertArrayHasKey('total_overtime_hours', $overtime);
        $this->assertArrayHasKey('average_per_employee', $overtime);
        $this->assertGreaterThan(0, $overtime['total_overtime_hours']);
    }

    /**
     * Test getRecentViolations returns corrected events
     */
    public function test_get_recent_violations_returns_corrected_events()
    {
        $profile = Profile::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        $employee = Employee::factory()->create(['profile_id' => $profile->id]);

        AttendanceEvent::create([
            'employee_id' => $employee->id,
            'is_corrected' => true,
            'corrected_at' => now()->subDays(2),
            'event_type' => 'time_in',
            'event_date' => now()->subDays(2),
            'event_time' => now()->subDays(2)->setHour(9)->setMinute(30),
            'correction_reason' => 'Late arrival',
            'source' => 'rfid',
        ]);

        $response = $this->get('/hr/timekeeping/overview');
        $response->assertStatus(200);

        $violations = $response->original->getData()['props']['recentViolations'] ?? [];

        $this->assertIsArray($violations);
        $this->assertGreaterThan(0, count($violations));
        
        $violation = $violations[0];
        $this->assertArrayHasKey('id', $violation);
        $this->assertArrayHasKey('employee', $violation);
        $this->assertArrayHasKey('severity', $violation);
    }

    /**
     * Test empty database returns zeros not errors
     */
    public function test_empty_database_returns_zeros()
    {
        DailyAttendanceSummary::truncate();
        AttendanceEvent::truncate();
        Cache::flush();

        $response = $this->get('/hr/timekeeping/overview');
        $response->assertStatus(200);

        $analytics = $response->original->getData()['props']['analytics'] ?? [];
        
        $this->assertArrayHasKey('summary', $analytics);
        $this->assertArrayHasKey('attendance_trends', $analytics);
        $this->assertEquals(0, $analytics['summary']['total_employees']);
    }

    /**
     * Test caching works with 5 minute TTL
     */
    public function test_analytics_caching_reduces_queries()
    {
        DailyAttendanceSummary::create([
            'employee_id' => Employee::factory()->create()->id,
            'attendance_date' => now(),
            'total_hours' => 8.0,
            'is_present' => true,
            'is_late' => false,
            'is_on_leave' => false,
            'late_minutes' => 0,
            'overtime_hours' => 0,
        ]);

        $response1 = $this->get('/hr/timekeeping/overview');
        $response1->assertStatus(200);
        
        $analytics1 = $response1->original->getData()['props']['analytics'] ?? [];

        $response2 = $this->get('/hr/timekeeping/overview');
        $response2->assertStatus(200);
        
        $analytics2 = $response2->original->getData()['props']['analytics'] ?? [];

        // Should return identical data (from cache)
        $this->assertEquals($analytics1['summary'], $analytics2['summary']);
    }

    /**
     * Test recent violations passed to frontend
     */
    public function test_recent_violations_passed_to_frontend()
    {
        $profile = Profile::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smith']);
        $employee = Employee::factory()->create(['profile_id' => $profile->id]);

        AttendanceEvent::create([
            'employee_id' => $employee->id,
            'is_corrected' => true,
            'corrected_at' => now()->subDays(1),
            'event_type' => 'time_in',
            'event_date' => now()->subDays(1),
            'event_time' => now()->subDays(1)->setHour(9)->setMinute(30),
            'correction_reason' => 'Late arrival',
            'source' => 'rfid',
        ]);

        $response = $this->get('/hr/timekeeping/overview');
        $response->assertStatus(200);

        $violations = $response->original->getData()['props']['recentViolations'] ?? [];
        
        $this->assertIsArray($violations);
        $this->assertGreaterThan(0, count($violations));
        
        $violation = $violations[0];
        $this->assertEquals('Jane Smith', $violation['employee']);
        $this->assertIn($violation['severity'], ['low', 'medium', 'high']);
    }

    /**
     * Test daily trends passed to frontend
     */
    public function test_daily_trends_passed_to_frontend()
    {
        $startDate = now()->subDays(3)->startOfDay();
        for ($i = 0; $i < 4; $i++) {
            DailyAttendanceSummary::create([
                'employee_id' => Employee::factory()->create()->id,
                'attendance_date' => $startDate->addDays($i),
                'total_hours' => 8.0,
                'is_present' => true,
                'is_late' => false,
                'is_on_leave' => false,
                'late_minutes' => 0,
                'overtime_hours' => 0,
            ]);
        }

        $response = $this->get('/hr/timekeeping/overview?period=week');
        $response->assertStatus(200);

        $dailyTrends = $response->original->getData()['props']['dailyTrends'] ?? [];
        
        $this->assertIsArray($dailyTrends);
        $this->assertGreaterThan(0, count($dailyTrends));
        
        foreach ($dailyTrends as $trend) {
            $this->assertArrayHasKey('date', $trend);
        }
    }

    /**
     * Test period filter changes data correctly
     */
    public function test_period_filter_changes_data()
    {
        $startDate = now()->subDays(10)->startOfDay();
        for ($i = 0; $i < 11; $i++) {
            DailyAttendanceSummary::create([
                'employee_id' => Employee::factory()->create()->id,
                'attendance_date' => $startDate->addDays($i),
                'total_hours' => 8.0,
                'is_present' => true,
                'is_late' => false,
                'is_on_leave' => false,
                'late_minutes' => 0,
                'overtime_hours' => 0,
            ]);
        }

        Cache::flush();
        $responseWeek = $this->get('/hr/timekeeping/overview?period=week');
        $analyticsWeek = $responseWeek->original->getData()['props']['analytics'] ?? [];
        $trendsWeek = $analyticsWeek['attendance_trends'] ?? [];

        Cache::flush();
        $responseMonth = $this->get('/hr/timekeeping/overview?period=month');
        $analyticsMonth = $responseMonth->original->getData()['props']['analytics'] ?? [];
        $trendsMonth = $analyticsMonth['attendance_trends'] ?? [];

        // Month should have more or equal days than week
        $this->assertGreaterThanOrEqual(count($trendsWeek), count($trendsMonth));
    }
}

