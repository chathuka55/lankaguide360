<x-app-layout title="Trip Packages" description="Ready-made Sri Lanka tour packages: book a proven route as it is, or customize it in the Trip Builder.">
    <x-page-header eyebrow="Ready-made" title="Trip packages" subtitle="Proven routes planned by our team. Book one as it is, or open it in the Trip Builder and make it yours." />

    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        @if ($packages->isEmpty())
            <p class="py-12 text-center text-slate-600">No packages yet. <a href="{{ route('plan') }}" class="text-primary-700 underline">Plan your own trip</a> instead.</p>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($packages as $package)
                    <x-package-card :package="$package" />
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
