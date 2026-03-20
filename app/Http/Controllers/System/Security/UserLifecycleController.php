<?php

namespace App\Http\Controllers\System\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\Security\UserLifecycleRequest;
use App\Models\User;
use App\Traits\LogsSecurityAudits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserLifecycleController extends Controller
{
	use LogsSecurityAudits;

	/**
	 * Display a listing of all users with filters.
	 */
	public function index(Request $request)
	{
		$query = User::with('roles:id,name')
			->withCount('auditLogs')
			->orderByDesc('created_at');

		// Filter by status
		if ($request->has('status')) {
			match ($request->get('status')) {
				'active' => $query->where('is_active', true),
				'inactive' => $query->where('is_active', false),
				'pending' => $query->whereNull('email_verified_at'),
				default => null,
			};
		}

		// Filter by role
		if ($request->has('role') && $request->get('role') !== 'all') {
			$query->whereHas('roles', fn($q) => 
				$q->where('name', $request->get('role'))
			);
		}

		// Search by name or email
		if ($request->has('search')) {
			$search = $request->get('search');
			$query->where(function($q) use ($search) {
				$q->where('name', 'like', "%{$search}%")
					->orWhere('email', 'like', "%{$search}%");
			});
		}

		$users = $query->paginate(25)
			->through(fn($user) => [
				'id' => $user->id,
				'name' => $user->name,
				'email' => $user->email,
				'is_active' => $user->is_active ?? true,
				'email_verified_at' => $user->email_verified_at?->format('Y-m-d H:i:s'),
				'created_at' => $user->created_at->format('Y-m-d H:i:s'),
				'last_login_at' => $user->last_login_at?->format('Y-m-d H:i:s'),
				'roles' => $user->roles->pluck('name')->toArray(),
				'audit_logs_count' => $user->audit_logs_count ?? 0,
			]);

		$allRoles = Role::where('name', '!=', 'Superadmin')->get(['id', 'name']);

		$breadcrumbs = [
			['title' => 'System', 'href' => '#'],
			['title' => 'Security & Access', 'href' => '#'],
			['title' => 'User Management', 'href' => '/system/users'],
		];

		$stats = [
			'total_users' => User::count(),
			'active_users' => User::where('is_active', true)->count(),
			'inactive_users' => User::where('is_active', false)->count(),
			'unverified_users' => User::whereNull('email_verified_at')->count(),
		];

		$employeesWithoutUser = \App\Models\Employee::with(['profile' => function($q) {
			$q->select('id', 'first_name', 'last_name');
		}])
			->whereNull('user_id')
			->where('status', 'active')
			->orderBy('employee_number')
			->get(['id', 'employee_number', 'profile_id']);

		return Inertia::render('System/Security/Users', [
			'users' => $users,
			'roles' => $allRoles,
			'breadcrumbs' => $breadcrumbs,
			'stats' => $stats,
			'filters' => [
				'status' => $request->get('status', 'all'),
				'role' => $request->get('role', 'all'),
				'search' => $request->get('search', ''),
			],
			'employeesWithoutUser' => $employeesWithoutUser,
		]);
	}

	/**
	 * Create a new user account.
	 */
	public function store(Request $request)
	{
		$validated = $request->validate([
			'name'                  => ['required', 'string', 'max:255'],
			'email'                 => ['required', 'email', 'max:255', 'unique:users,email'],
			'password'              => ['required', 'string', 'min:8', 'confirmed'],
			'roles'                 => ['sometimes', 'array'],
			'roles.*'               => ['integer', 'exists:roles,id'],
		]);

		try {
			$username = $this->generateUniqueUsername($validated['name'], $validated['email']);

			$user = User::create([
				'name'      => $validated['name'],
				'username'  => $username,
				'email'     => $validated['email'],
				'password'  => Hash::make($validated['password']),
				'is_active' => true,
			]);

			if (!empty($validated['roles'])) {
				$roleNames = Role::whereIn('id', $validated['roles'])->pluck('name');
				$user->assignRole($roleNames);
			}

			$this->logAudit('user.created', 'info', [
				'user_id'    => $user->id,
				'user_email' => $user->email,
				'roles'      => $validated['roles'] ?? [],
			]);

			return redirect('/system/users')
				->with('success', "User '{$user->email}' created successfully.");
		} catch (\Exception $e) {
			return redirect()->back()
				->withErrors(['error' => 'Failed to create user: ' . $e->getMessage()])
				->withInput();
		}
	}

	/**
	 * Show user details and history.
	 */
	public function show(User $user)
	{
		$user->load('roles:id,name');
		
		$auditLogs = $user->auditLogs()
			->orderByDesc('created_at')
			->limit(50)
			->get(['id', 'user_id', 'event_type', 'description', 'severity', 'created_at']);

		$loginHistory = \DB::table('security_audit_logs')
			->where('user_id', $user->id)
			->where('event_type', 'like', '%login%')
			->orderByDesc('created_at')
			->limit(20)
			->get();

		return Inertia::render('System/Security/UserDetail', [
			'user' => [
				'id' => $user->id,
				'name' => $user->name,
				'email' => $user->email,
				'is_active' => $user->is_active ?? true,
				'email_verified_at' => $user->email_verified_at?->format('Y-m-d H:i:s'),
				'created_at' => $user->created_at->format('Y-m-d H:i:s'),
				'last_login_at' => $user->last_login_at?->format('Y-m-d H:i:s'),
				'roles' => $user->roles->pluck('name')->toArray(),
				'two_factor_confirmed' => !empty($user->two_factor_confirmed_at),
			],
			'auditLogs' => $auditLogs,
			'loginHistory' => $loginHistory,
		]);
	}

	/**
	 * Update user roles and status.
	 */
	public function update(UserLifecycleRequest $request, User $user)
	{
		if ($user->id === $request->user()->id && !$request->validated('is_active')) {
			return redirect()->back()
				->withErrors(['error' => 'Cannot deactivate your own account.']);
		}

		try {
			// Update user status
			if ($request->has('is_active')) {
				$isActive = $request->validated('is_active');
				$user->is_active = $isActive;
				$user->save();

				if (!$isActive) {
					// Log deactivation with reason
					$this->logAudit('user.deactivated', 'warning', [
						'user_id' => $user->id,
						'user_email' => $user->email,
						'reason' => $request->validated('reason_for_deactivation'),
					]);
				} else {
					$this->logAudit('user.activated', 'info', [
						'user_id' => $user->id,
						'user_email' => $user->email,
					]);
				}
			}

			// Update roles
			if ($request->has('roles')) {
				$roles = Role::whereIn('id', $request->validated('roles'))->pluck('name');
				$user->syncRoles($roles);

				$this->logAudit('user.roles_updated', 'info', [
					'user_id' => $user->id,
					'user_email' => $user->email,
					'roles' => $roles->toArray(),
				]);
			}

			// Reset password if provided
			if ($request->has('new_password') && $request->validated('new_password')) {
				$user->password = Hash::make($request->validated('new_password'));
				$user->save();

				$this->logAudit('user.password_reset', 'warning', [
					'user_id' => $user->id,
					'user_email' => $user->email,
					'by_superadmin' => true,
				]);
			}

			return redirect('/system/users')
				->with('success', "User '{$user->email}' updated successfully.");
		} catch (\Exception $e) {
			return redirect()->back()
				->withErrors(['error' => 'Failed to update user: ' . $e->getMessage()]);
		}
	}

	/**
	 * Send password reset link to user.
	 */
	public function sendPasswordReset(Request $request, User $user)
	{
		try {
			// Send password reset notification
			$user->sendPasswordResetNotification(
				\Illuminate\Support\Str::random(60)
			);

			$this->logAudit('user.password_reset_requested', 'info', [
				'user_id' => $user->id,
				'user_email' => $user->email,
				'requested_by' => $request->user()->id,
			]);

			return redirect()->back()
				->with('success', "Password reset link sent to {$user->email}.");
		} catch (\Exception $e) {
			return redirect()->back()
				->withErrors(['error' => 'Failed to send password reset: ' . $e->getMessage()]);
		}
	}

	/**
	 * Deactivate a user account.
	 */
	public function deactivate(Request $request, User $user)
	{
		$request->validate([
			'reason' => ['required', 'string', 'max:500'],
		]);

		if ($user->id === $request->user()->id) {
			return redirect()->back()
				->withErrors(['error' => 'Cannot deactivate your own account.']);
		}

		try {
			$user->update(['is_active' => false]);

			$this->logAudit('user.deactivated', 'warning', [
				'user_id' => $user->id,
				'user_email' => $user->email,
				'reason' => $request->validated('reason'),
				'deactivated_by' => $request->user()->id,
			]);

			return redirect('/system/users')
				->with('success', "User '{$user->email}' has been deactivated.");
		} catch (\Exception $e) {
			return redirect()->back()
				->withErrors(['error' => 'Failed to deactivate user: ' . $e->getMessage()]);
		}
	}

	/**
	 * Activate an inactive user account.
	 */
	public function activate(Request $request, User $user)
	{
		try {
			$user->update(['is_active' => true]);

			$this->logAudit('user.activated', 'info', [
				'user_id' => $user->id,
				'user_email' => $user->email,
				'activated_by' => $request->user()->id,
			]);

			return redirect('/system/users')
				->with('success', "User '{$user->email}' has been activated.");
		} catch (\Exception $e) {
			return redirect()->back()
				->withErrors(['error' => 'Failed to activate user: ' . $e->getMessage()]);
		}
	}

	/**
	 * Get audit trail for a specific user.
	 */
	public function auditTrail(User $user)
	{
		$logs = \DB::table('security_audit_logs')
			->where('user_id', $user->id)
			->orderByDesc('created_at')
			->paginate(50);

		return response()->json($logs);
	}

	protected function generateUniqueUsername(string $name, string $email): string
	{
		$base = Str::slug(Str::before($email, '@'), '') ?: Str::slug($name, '');
		$base = strtolower($base);

		if ($base === '') {
			$base = 'user';
		}

		$username = Str::limit($base, 40, '');
		$counter = 1;

		while (User::where('username', $username)->exists()) {
			$suffix = (string) $counter;
			$username = Str::limit($base, 40 - strlen($suffix), '') . $suffix;
			$counter++;
		}

		return $username;
	}
}
