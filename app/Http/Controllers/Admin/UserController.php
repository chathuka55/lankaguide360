<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->withCount('trips')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->when($request->query('role'), fn ($q, $role) => $q->where('role', $role))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.form', ['user' => new User(['role' => UserRole::Agent])]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = new User;
        $user->forceFill([
            ...$request->safe()->except(['password', 'password_confirmation']),
            'password' => Hash::make($request->validated('password')),
            'email_verified_at' => now(),
        ])->save();

        return redirect()->route('admin.users.index')->with('status', "Account for {$user->email} created.");
    }

    public function edit(User $user): View
    {
        $this->authorize('view', $user);

        return view('admin.users.form', ['user' => $user]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->safe()->except(['password', 'password_confirmation']);

        // Admins can't change their own role (so the last admin can't lock everyone out).
        if (! $request->user()->can('changeRole', $user)) {
            unset($data['role']);
        }

        if (filled($request->validated('password'))) {
            $data['password'] = Hash::make($request->validated('password'));
        }

        $user->forceFill($data)->save();

        return redirect()->route('admin.users.index')->with('status', 'Account saved.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', "Account {$user->email} deleted. Their trips are kept.");
    }
}
