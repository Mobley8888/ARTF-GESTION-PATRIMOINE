<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\UserPasswordResetRequest;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(\Illuminate\Http\Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
            'active' => ['nullable', 'in:active,inactive'],
        ]);

        $users = User::query()
            ->when(
                auth()->user()->role === UserRole::Administrator,
                fn (Builder $query) => $query->where('role', '!=', UserRole::SuperAdmin->value),
            )
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->where('role', $role))
            ->when($filters['active'] ?? null, fn (Builder $query, string $active) => $query->where('is_active', $active === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => $this->roles(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'user' => null,
            'roles' => $this->roles(),
        ]);
    }

    public function show(User $user): View
    {
        $this->authorizeTarget($user);

        return view('admin.users.show', ['user' => $user]);
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['must_change_password'] = true;
        User::query()->create($data);

        return redirect()
            ->route('users.index')
            ->with('status', 'Le compte a été créé. Le titulaire devra changer son mot de passe à la première connexion.');
    }

    public function edit(User $user): View
    {
        $this->authorizeTarget($user);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => $this->roles(),
        ]);
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();

        DB::transaction(function () use ($data, $actor, $user): void {
            User::query()->orderBy('id')->lockForUpdate()->get(['id']);
            $target = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
            $newRole = UserRole::from($data['role']);
            $administrativeRoles = [UserRole::Administrator->value, UserRole::SuperAdmin->value];

            if ($actor->is($target) && ! $isActive) {
                throw ValidationException::withMessages([
                    'is_active' => 'Vous ne pouvez pas désactiver votre propre compte.',
                ]);
            }

            if (
                $target->is_active
                && in_array($target->role->value, $administrativeRoles, true)
                && (! $isActive || ! in_array($newRole->value, $administrativeRoles, true))
                && ! User::query()
                    ->whereIn('role', $administrativeRoles)
                    ->where('is_active', true)
                    ->whereKeyNot($target->getKey())
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'role' => 'Le dernier compte actif habilité à administrer les utilisateurs ne peut pas être désactivé ni rétrogradé.',
                ]);
            }

            if (
                $target->role === UserRole::SuperAdmin
                && $newRole !== UserRole::SuperAdmin
                && User::query()->where('role', UserRole::SuperAdmin->value)->count() <= 1
            ) {
                throw ValidationException::withMessages([
                    'role' => 'Le dernier compte super administrateur ne peut pas perdre ce rôle.',
                ]);
            }

            if (
                $target->role === UserRole::SuperAdmin
                && (! $isActive || $newRole !== UserRole::SuperAdmin)
                && User::query()
                    ->where('role', UserRole::SuperAdmin->value)
                    ->where('is_active', true)
                    ->whereKeyNot($target->getKey())
                    ->count() === 0
            ) {
                throw ValidationException::withMessages([
                    'is_active' => 'Le dernier super administrateur actif ne peut pas être désactivé ni rétrogradé.',
                ]);
            }

            $target->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $newRole,
                'is_active' => $isActive,
            ])->save();
        }, attempts: 3);

        return redirect()
            ->route('users.index')
            ->with('status', 'Le compte a été mis à jour.');
    }

    public function resetPassword(UserPasswordResetRequest $request, User $user): RedirectResponse
    {
        $user->forceFill([
            'password' => $request->validated('password'),
            'must_change_password' => true,
        ])->save();

        return redirect()
            ->route('users.edit', $user)
            ->with('status', 'Le mot de passe a été réinitialisé; son titulaire devra le changer à la prochaine connexion.');
    }

    private function roles(): array
    {
        $roles = collect(UserRole::cases());

        if (auth()->user()->role === UserRole::Administrator) {
            $roles = $roles->reject(fn (UserRole $role) => $role === UserRole::SuperAdmin);
        }

        return $roles
            ->mapWithKeys(fn (UserRole $role) => [$role->value => $role->label()])
            ->all();
    }

    private function authorizeTarget(User $user): void
    {
        abort_unless(auth()->user()->can('manage-users'), 403);
        abort_if(auth()->user()->role === UserRole::Administrator && $user->isSuperAdmin(), 403);
    }
}
