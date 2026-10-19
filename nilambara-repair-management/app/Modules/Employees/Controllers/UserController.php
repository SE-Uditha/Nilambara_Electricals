<?php

namespace App\Modules\Employees\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Employees\Services\UserAccountService;
use App\Modules\RolesPermissions\Models\Permission;
use App\Modules\RolesPermissions\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('modules.users.index', [
            'users' => User::query()->with(['employee', 'roles'])
                ->orderBy('username')
                ->paginate(20),
        ]);
    }

    public function show(User $user): View
    {
        $user->load(['employee', 'roles']);

        return view('modules.users.show', [
            'managedUser' => $user,
            'roles' => Role::query()->where('guard_name', 'web')->where('is_active', true)->orderBy('label')->get(),
            'overrides' => $user->permissions()->withPivot('effect')->get()->keyBy('id'),
            'permissions' => Permission::query()->orderBy('module')->orderBy('name')->get()->groupBy('module'),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if (is_string($request->input('username'))) {
            $request->merge(['username' => strtolower(trim($request->input('username')))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:64',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->fill([
            'name' => $data['name'],
            'username' => strtolower($data['username']),
            'email' => $data['email'] ?? null,
        ])->save();

        $this->audit($request, 'user.account.updated', $user, [
            'name' => $user->name,
            'username' => $user->username,
        ]);

        return back()->with('status', __('User account updated.'));
    }

    public function updateRole(Request $request, User $user, UserAccountService $accounts): RedirectResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')->where('is_active', true)],
        ]);

        $accounts->assignRole($user, Role::query()->findOrFail($data['role_id']), $request->user());

        return back()->with('status', __('User role updated.'));
    }

    public function setEnabled(Request $request, User $user, UserAccountService $accounts): RedirectResponse
    {
        $accounts->setEnabled($user, ! $user->is_active, $request->user());

        return back()->with('status', $user->is_active ? __('Account enabled.') : __('Account disabled.'));
    }

    public function resetPassword(Request $request, User $user, UserAccountService $accounts): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', Password::min(12)->mixedCase()->letters()->numbers(), 'confirmed'],
        ]);

        $accounts->resetPassword($user, $data['password'], $request->user());

        return back()->with('status', __('Password reset and existing sessions invalidated.'));
    }

    public function updateOverrides(Request $request, User $user, UserAccountService $accounts): RedirectResponse
    {
        if ($user->hasRole('administrator')) {
            return back()->withErrors(['permissions' => __('Administrator accounts always inherit all available permissions.')]);
        }

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['required', Rule::in(['allow', 'deny', 'inherit'])],
        ]);

        $overrides = collect($data['permissions'] ?? [])
            ->reject(fn (string $effect): bool => $effect === 'inherit')
            ->map(fn (string $effect, string $permissionId): array => [
                'permission_id' => (int) $permissionId,
                'effect' => $effect,
            ])
            ->values()
            ->all();

        $validPermissionIds = Permission::query()
            ->whereIn('id', array_column($overrides, 'permission_id'))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (array_diff(array_column($overrides, 'permission_id'), $validPermissionIds) !== []) {
            return back()->withErrors(['permissions' => __('One or more selected permissions are invalid.')])->withInput();
        }

        $accounts->syncPermissionOverrides($user, $overrides, $request->user());

        return back()->with('status', __('Individual permission overrides saved.'));
    }

    public function permissions(): View
    {
        return view('modules.permissions.index', [
            'permissions' => Permission::query()->withCount('roles')->orderBy('module')->orderBy('name')->get()->groupBy('module'),
        ]);
    }

    private function audit(Request $request, string $action, User $user, array $newValues): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => User::class,
            'entity_id' => $user->id,
            'occurred_at' => now(),
            'new_values' => json_encode($newValues, JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'request_id' => $request->header('X-Request-Id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
