<x-app-layout title="Image Credits" description="Authors and licences of the photos used on LankaGuide360.">
    <x-page-header eyebrow="Legal" title="Image credits" subtitle="Most photos on this site come from Wikimedia Commons under free licences. Thank you to every photographer." />

    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
        <p class="text-sm text-slate-600">
            Text descriptions are adapted from Wikipedia and Wikivoyage (CC BY-SA 4.0). Maps use data from
            <a href="https://www.openstreetmap.org/copyright" class="text-primary-700 underline" rel="noopener" target="_blank">OpenStreetMap contributors</a> (ODbL)
            and district boundaries from geoBoundaries (CC BY 4.0). Photos are listed below with their author, licence and source.
        </p>

        @if ($photos->isEmpty())
            <p class="mt-8 text-slate-600">No photo credits yet.</p>
        @else
            <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($photos as $photo)
                    <li class="flex gap-3 rounded-xl border border-slate-200 bg-white p-3">
                        <img src="{{ $photo->url(400) }}" alt="{{ $photo->alt }}" width="96" height="72" loading="lazy" class="h-18 w-24 shrink-0 rounded-lg object-cover">
                        <div class="min-w-0 text-xs">
                            <p class="truncate font-semibold text-slate-900">{{ $photo->mediable?->name ?? $photo->alt }}</p>
                            <p class="mt-1 text-slate-600">{{ $photo->author ?: 'Unknown author' }}</p>
                            <p class="text-slate-500">
                                @if ($photo->license_url)
                                    <a href="{{ $photo->license_url }}" class="underline hover:text-primary-700" rel="noopener license" target="_blank">{{ $photo->license }}</a>
                                @else
                                    {{ $photo->license }}
                                @endif
                                @if ($photo->source_url)
                                    · <a href="{{ $photo->source_url }}" class="underline hover:text-primary-700" rel="noopener" target="_blank">source</a>
                                @endif
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="mt-6">{{ $photos->links() }}</div>
        @endif
    </div>
</x-app-layout>
