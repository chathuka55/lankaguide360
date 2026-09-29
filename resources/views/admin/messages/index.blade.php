<x-admin-layout title="Contact messages">
    <x-admin.header title="Contact messages" subtitle="Messages sent through the Contact page form." />

    <x-admin.filters :action="route('admin.messages.index')" placeholder="Name, email or subject">
        <x-admin.filter-select name="status" label="Show" :options="['unread' => 'Unread only']" placeholder="All" />
    </x-admin.filters>

    <div class="admin-card overflow-x-auto">
        <table class="admin-table">
            <thead><tr><th>From</th><th>Subject</th><th>Received</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($messages as $message)
                    <tr @class(['font-semibold' => ! $message->is_read])>
                        <td>{{ $message->name }}<div class="text-xs font-normal text-slate-500">{{ $message->email }}</div></td>
                        <td><a href="{{ route('admin.messages.show', $message) }}" class="text-slate-900 hover:underline">{{ $message->subject ?: Str::limit($message->body, 60) }}</a></td>
                        <td class="text-xs font-normal">{{ $message->created_at?->format('d M Y, H:i') }}</td>
                        <td class="text-end font-normal"><x-admin.delete-form :action="route('admin.messages.destroy', $message)" /></td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-admin.empty message="No messages yet. The public contact form arrives in phase 5." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $messages->links() }}</div>
</x-admin-layout>
