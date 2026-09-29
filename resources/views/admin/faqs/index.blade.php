<x-admin-layout title="FAQs">
    <x-admin.header title="FAQs" subtitle="Shown on the Contact page. The chatbot uses them as context and answers from them when the AI service is unavailable.">
        <a href="{{ route('admin.faqs.create') }}" class="btn btn-primary">Add FAQ</a>
    </x-admin.header>

    <div class="admin-card overflow-x-auto">
        <table class="admin-table">
            <thead><tr><th class="w-12">#</th><th>Question</th><th>Keywords</th><th>Active</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($faqs as $faq)
                    <tr>
                        <td class="text-xs">{{ $faq->sort_order }}</td>
                        <td><span class="font-semibold text-slate-900">{{ $faq->question }}</span><p class="mt-1 max-w-2xl text-xs text-slate-500">{{ Str::limit($faq->answer, 140) }}</p></td>
                        <td class="text-xs">{{ $faq->keywords }}</td>
                        <td>{{ $faq->is_active ? 'Yes' : 'No' }}</td>
                        <td class="space-x-3 whitespace-nowrap text-end">
                            <a href="{{ route('admin.faqs.edit', $faq) }}" class="text-sm font-medium text-primary-700 hover:underline">Edit</a>
                            <x-admin.delete-form :action="route('admin.faqs.destroy', $faq)" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-admin.empty /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
