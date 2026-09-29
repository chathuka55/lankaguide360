<x-admin-layout :title="$message->subject ?: 'Message'">
    <x-admin.header :title="$message->subject ?: 'Message from '.$message->name" :back="route('admin.messages.index')">
        <a href="mailto:{{ $message->email }}?subject={{ rawurlencode('Re: '.($message->subject ?: 'Your message to LankaGuide360')) }}" class="btn btn-primary">Reply by email</a>
        <x-admin.delete-form :action="route('admin.messages.destroy', $message)" button="btn btn-secondary" />
    </x-admin.header>

    <article class="admin-card space-y-4 p-5">
        <dl class="grid gap-3 text-sm sm:grid-cols-3">
            <div><dt class="text-slate-500">From</dt><dd class="font-medium">{{ $message->name }}</dd></div>
            <div><dt class="text-slate-500">Email</dt><dd><a href="mailto:{{ $message->email }}" class="text-primary-700 hover:underline">{{ $message->email }}</a></dd></div>
            <div><dt class="text-slate-500">Phone</dt><dd>{{ $message->phone ?: '—' }}</dd></div>
            <div><dt class="text-slate-500">Received</dt><dd>{{ $message->created_at?->format('d M Y, H:i') }}</dd></div>
        </dl>
        <div class="border-t border-slate-200 pt-4 text-sm leading-6 whitespace-pre-line text-slate-800">{{ $message->body }}</div>
    </article>
</x-admin-layout>
