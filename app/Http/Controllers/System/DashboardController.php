<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\System\SystemHealthService;
use App\Services\System\SystemCronService;
use App\Services\System\User\UserOnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class DashboardController extends Controller
{
	public function __construct(
		protected SystemHealthService $healthService,
		protected SystemCronService $cronService
	) {}

	/**
	 * Build module categories with badges for the quick access grid.
	 */
	protected function getModuleCategories(): array
	{
		// Safely query counts, returning 0 if table doesn't exist or query fails
		try {
			$failedCrons = \App\Models\ScheduledJob::where('last_exit_code', '!=', 0)
				->whereNotNull('last_exit_code')
				->count();
		} catch (\Exception $e) {
			$failedCrons = 0;
		}

		try {
			$securityAlerts = \App\Models\SecurityAuditLog::where('severity', 'critical')->count();
		} catch (\Exception $e) {
			$securityAlerts = 0;
		}

		try {
			$pendingBackups = \App\Models\SystemBackupLog::where('status', 'pending')->count();
		} catch (\Exception $e) {
			$pendingBackups = 0;
		}

		return [
			[
				'id' => 'system-admin',
				'title' => 'System Administration',
				'description' => 'Core system health, logs, storage, backup, cron, and device management',
				'modules' => [
					[
						'id' => 'health',
						'icon' => 'Activity',
						'title' => 'System Health',
						'description' => 'Monitor CPU, memory, disk usage, and overall platform health',
						'href' => '/system/health',
						'badge' => ['count' => 0, 'label' => 'alerts'],
						'isDisabled' => false,
						'comingSoon' => false,
					],
					[
						'id' => 'storage',
						'icon' => 'HardDrive',
						'title' => 'Storage',
						'description' => 'View and manage disk space and storage resources',
						'href' => '/system/storage',
						'badge' => ['count' => 0, 'label' => 'alerts'],
						'isDisabled' => false,
						'comingSoon' => false,
					],
					[
						'id' => 'backups',
						'icon' => 'Database',
						'title' => 'Backups',
						'description' => 'Schedule, monitor, and manage system backups',
						'href' => '/system/backups',
						'badge' => ['count' => $pendingBackups, 'label' => 'pending'],
						'isDisabled' => false,
						'comingSoon' => false,
					],
					[
						'id' => 'security-audit',
						'icon' => 'Shield',
						'title' => 'Security Audit',
						'description' => 'Review security events and access logs',
						'href' => '/system/security/audit',
						'badge' => ['count' => $securityAlerts, 'label' => 'critical'],
						'isDisabled' => false,
						'comingSoon' => false,
					],
					[
						'id' => 'error-logs',
						'icon' => 'AlertCircle',
						'title' => 'Error Logs',
						'description' => 'Review system error logs for failures and anomalies',
						'href' => '/system/logs/errors',
						'badge' => ['count' => 0, 'label' => 'errors'],
						'isDisabled' => false,
						'comingSoon' => false,
					],
					[
						'id' => 'cron-jobs',
						'icon' => 'Calendar',
						'title' => 'Cron Jobs',
						'description' => 'Schedule and monitor automated system tasks',
						'href' => '/system/cron',
						'badge' => ['count' => $failedCrons, 'label' => 'failed'],
						'isDisabled' => false,
						'comingSoon' => false,
					],
					[
						'id' => 'timekeeping-devices',
						'icon' => 'Cpu',
						'title' => 'Timekeeping Devices',
						'description' => 'Manage and monitor enrolled timekeeping devices',
						'href' => '/system/timekeeping/devices',
						'badge' => ['count' => 0, 'label' => 'devices'],
						'isDisabled' => false,
						'comingSoon' => false,
					],
				],
			],
			[
				'id' => 'security-access',
				'title' => 'Security & Access',
				'description' => 'User management and role-based permissions',
				'modules' => [
					[
						'id' => 'users',
						'icon' => 'Users',
						'title' => 'User Management',
						'description' => 'Manage users, roles, and permissions',
						'href' => '/system/users',
						'badge' => ['count' => 0, 'label' => 'users'],
						'isDisabled' => false,
						'comingSoon' => false,
					],
					[
						'id' => 'roles-permissions',
						'icon' => 'Key',
						'title' => 'Roles & Permissions',
						'description' => 'Configure user roles and access controls',
						'href' => '/system/security/roles',
						'badge' => ['count' => 0, 'label' => 'roles'],
						'isDisabled' => false,
						'comingSoon' => false,
					],
				],
			],
			[
				'id' => 'monitoring-reporting',
				'title' => 'Reports',
				'description' => 'System analytics and security reporting',
				'modules' => [
					[
						'id' => 'usage-analytics',
						'icon' => 'BarChart3',
						'title' => 'Usage Analytics',
						'description' => 'User activity, module usage, and session metrics',
						'href' => '/system/reports/usage',
						'badge' => ['count' => 0, 'label' => 'reports'],
						'isDisabled' => false,
						'comingSoon' => false,
					],
					[
						'id' => 'security-reports',
						'icon' => 'Shield',
						'title' => 'Security Reports',
						'description' => 'Login attempts, role changes, and security events',
						'href' => '/system/reports/security',
						'badge' => ['count' => 0, 'label' => 'reports'],
						'isDisabled' => false,
						'comingSoon' => false,
					],
				],
			],
		];
	}

	/**
	 * Show the superadmin dashboard.
	 */
	public function index(Request $request)
	{
		// Compute real values for a single-organization app.
		$counts = [
			'users' => User::count(),
		];

		// Read company name from a simple settings table if present. The codebase sometimes uses 'settings' for key/value.
		$companyName = null;
		try {
			if (Schema::hasTable('settings')) {
				$companyName = DB::table('settings')->where('key', 'company.name')->value('value');
			}
		} catch (\Exception $e) {
			$companyName = null;
		}

		// System onboarding removed - focusing on system health monitoring
		$onboardingStatus = 'completed';

		// Per-user onboarding (profile) — show modal when user's onboarding is not skipped
		$userOnboarding = null;
		try {
			$userOnboarding = app(UserOnboardingService::class)->getForUser($request->user()->id);
			if ($userOnboarding && isset($userOnboarding->checklist_json) && is_string($userOnboarding->checklist_json)) {
				$decodedChecklist = json_decode($userOnboarding->checklist_json, true);
				if (json_last_error() === JSON_ERROR_NONE && is_array($decodedChecklist)) {
					$userOnboarding->checklist_json = $decodedChecklist;
				}
			}
		} catch (\Throwable $e) {
			$userOnboarding = null;
		}

		// If no per-user onboarding row exists, generate placeholder checklist for Superadmin
		// This allows fresh Superadmin users to see meaningful onboarding items
		if (!$userOnboarding || empty($userOnboarding->checklist_json)) {
			$currentUser = $request->user();
			
			// Helper function to safely get route or return fallback
			$safeRoute = function($routeName, $fallback = '#') {
				try {
					return \Illuminate\Support\Facades\Route::has($routeName) 
						? route($routeName) 
						: $fallback;
				} catch (\Exception $e) {
					return $fallback;
				}
			};
			
			// Generate placeholder checklist for demonstration
			$placeholderChecklist = [
				[
					'id' => 'profile_complete',
					'title' => 'Complete Your Profile',
					'description' => 'Add your full name, contact information, and emergency contact details',
					'completed' => !empty($currentUser->profile),
					'action_url' => $safeRoute('profile.show', '/settings/profile'),
					'action_label' => 'Update Profile',
					'required' => true,
				],
				[
					'id' => 'password_set',
					'title' => 'Set a Strong Password',
					'description' => 'Ensure your account is secured with a strong password',
					'completed' => !empty($currentUser->password),
					'action_url' => $safeRoute('password.show', '/settings/password'),
					'action_label' => 'Change Password',
					'required' => true,
				],
				[
					'id' => 'two_factor_enabled',
					'title' => 'Enable Two-Factor Authentication',
					'description' => 'Add an extra layer of security to your Superadmin account',
					'completed' => $currentUser->two_factor_secret !== null,
					'action_url' => $safeRoute('two-factor.show', '/settings/two-factor'),
					'action_label' => 'Enable 2FA',
					'required' => true,
				],
				[
					'id' => 'email_verified',
					'title' => 'Verify Your Email Address',
					'description' => 'Confirm your email address to receive system notifications',
					'completed' => $currentUser->email_verified_at !== null,
					'action_url' => $safeRoute('verification.notice', '#'),
					'action_label' => 'Verify Email',
					'required' => true,
				],
				[
					'id' => 'system_tour',
					'title' => 'Take the System Tour',
					'description' => 'Learn about the Superadmin dashboard and available features',
					'completed' => false,
					'action_url' => '#',
					'action_label' => 'Start Tour',
					'required' => false,
				],
				[
					'id' => 'security_review',
					'title' => 'Review Security Settings',
					'description' => 'Configure password policies, session timeouts, and IP restrictions',
					'completed' => false,
					'action_url' => '#',
					'action_label' => 'Review Settings',
					'required' => false,
				],
			];

			// Calculate completion percentage
			$completedCount = count(array_filter($placeholderChecklist, fn($item) => $item['completed']));
			$totalCount = count($placeholderChecklist);
			$completionPercentage = $totalCount > 0 ? round(($completedCount / $totalCount) * 100) : 0;

			$userOnboarding = (object) [
				'id' => null,
				'user_id' => $currentUser->id,
				'status' => $completionPercentage === 100 ? 'completed' : 'pending',
				'checklist_json' => $placeholderChecklist,
				'completion_percentage' => $completionPercentage,
			];
		}

		// Show the setup modal when the user hasn't completed onboarding
		// For Superadmins, we show profile onboarding (not system onboarding)
		$showByUserOnboarding = false;
		try {
			$u = $request->user();
			if ($u && $userOnboarding && isset($userOnboarding->status)) {
				// Show for Superadmins with incomplete profiles
				$showByUserOnboarding = in_array($userOnboarding->status, ['pending', 'in_progress'], true);
			}
		} catch (\Throwable $e) {
			$showByUserOnboarding = false;
		}

		// Determine whether the current user may complete system onboarding
		$canCompleteOnboarding = false;
		try {
			$u = $request->user();
			if ($u) {
				$canCompleteOnboarding = $u->hasRole('Superadmin') || $u->hasRole('Admin');
			}
		} catch (\Throwable $e) {
			$canCompleteOnboarding = false;
		}

		// Get system health metrics
		$systemHealth = null;
		try {
			$systemHealth = $this->healthService->getDashboardMetrics();
		} catch (\Exception $e) {
			$systemHealth = null;
		}

		// Get cron job metrics
		$cronMetrics = null;
		try {
			$cronMetrics = $this->healthService->getCronMetrics();
		} catch (\Exception $e) {
			$cronMetrics = null;
		}

		$data = [
			'counts' => $counts,
			'company' => [
				'name' => $companyName,
			],
			'systemOnboarding' => null,
			'userOnboarding' => $userOnboarding,
			'onboardingStatus' => $onboardingStatus,
			'showSetupModal' => $showByUserOnboarding,
			'canCompleteOnboarding' => $canCompleteOnboarding,
			'welcomeText' => 'Welcome to the Superadmin dashboard — manage platform settings and users from here.',
			'systemHealth' => $systemHealth,
			'cronMetrics' => $cronMetrics,
			'moduleCategories' => $this->getModuleCategories(),
		];

		return Inertia::render('System/Dashboard', $data);
	}
}