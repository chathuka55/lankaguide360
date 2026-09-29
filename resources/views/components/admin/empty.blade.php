@props(['message' => 'Nothing here yet.'])

<div class="px-4 py-12 text-center text-sm text-slate-500">
    {{ $message }}
    {{ $slot }}
</div>
