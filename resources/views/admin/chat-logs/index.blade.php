<x-admin-layout title="Chatbot conversations">
    <x-admin.header title="Chatbot conversations" subtitle="What visitors ask the travel assistant. Use it to improve FAQs and place descriptions." />

    <x-admin.filters :action="route('admin.chat-logs.index')" placeholder="Search messages" />

    <div class="grid gap-6 xl:grid-cols-5">
        <div class="admin-card overflow-x-auto xl:col-span-2">
            <table class="admin-table">
                <thead><tr><th>First question</th><th class="text-end">Messages</th><th>Started</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($conversations as $conversation)
                        <tr @class(['bg-primary-50' => $selected === $conversation->session_id])>
                            <td><a href="{{ route('admin.chat-logs.index', [...request()->except('page'), 'session' => $conversation->session_id, 'page' => $conversations->currentPage()]) }}" class="font-medium text-slate-900 hover:underline">{{ Str::limit($firstQuestions[$conversation->session_id] ?? '—', 70) }}</a></td>
                            <td class="text-end">{{ $conversation->messages }}</td>
                            <td class="text-xs whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($conversation->started_at)->format('d M, H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><x-admin.empty message="No conversations yet." /></td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $conversations->links() }}</div>
        </div>

        <section class="admin-card p-5 xl:col-span-3" aria-label="Conversation">
            @if ($thread->isEmpty())
                <x-admin.empty message="Choose a conversation to read it." />
            @else
                <ol class="space-y-3 text-sm">
                    @foreach ($thread as $line)
                        <li class="{{ $line->role === 'user' ? 'flex justify-end' : '' }}">
                            <div class="max-w-[85%] rounded-2xl px-3 py-2 whitespace-pre-line {{ $line->role === 'user' ? 'bg-primary-700 text-white' : 'bg-slate-100 text-slate-700' }}">{{ $line->message }}</div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>
</x-admin-layout>
