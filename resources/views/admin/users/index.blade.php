<x-admin-layout title="Users">
    <x-admin.header title="Users" subtitle="Travellers, travel agents and admins.">
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Add user</a>
    </x-admin.header>

    <x-admin.filters :action="route('admin.users.index')" placeholder="Name or email">
        <x-admin.filter-select name="role" label="Role" :options="collect(App\Enums\UserRole::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])" />
    </x-admin.filters>

    <div class="admin-card overflow-x-auto">
        <table class="admin-table">
            <thead><tr><th>Name</th><th>Role</th><th>Country</th><th class="text-end">Trips</th><th>Joined</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($users as $user)
                    <tr>
                        <td><span class="font-semibold text-slate-900">{{ $user->name }}</span>@if ($user->is(auth()->user())) <span class="text-xs text-slate-500">(you)</span>@endif<div class="text-xs text-slate-500">{{ $user->email }}</div></td>
                        <td><x-admin.status-badge :status="$user->role->label()" /></td>
                        <td>{{ $user->country ?? '—' }}</td>
                        <td class="text-end">{{ $user->trips_count }}</td>
                        <td class="text-xs">{{ $user->created_at?->format('d M Y') }}</td>
                        <td class="space-x-3 text-end">
                            <a href="{{ route('admin.users.edit', $user) }}" class="text-sm font-medium text-primary-700 hover:underline">Edit</a>
                            @can('delete', $user)<x-admin.delete-form :action="route('admin.users.destroy', $user)" :confirm="'Delete the account '.$user->email.'?'" />@endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</x-admin-layout>
