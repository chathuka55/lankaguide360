<x-app-layout title="About us" description="LankaGuide360 helps travellers plan Sri Lanka trips online, with every plan checked and confirmed by a local travel agent.">
    <x-page-header eyebrow="About" title="Local knowledge, planned online" subtitle="LankaGuide360 combines a fast trip builder with real travel agents who know Sri Lanka." />

    <div class="mx-auto max-w-5xl space-y-16 px-4 py-14 sm:px-6 lg:px-8">
        <section class="grid gap-8 md:grid-cols-2" aria-labelledby="story-heading">
            <div>
                <h2 id="story-heading" class="text-2xl font-bold">Our story</h2>
                <p class="mt-3 leading-7 text-slate-700">
                    Planning Sri Lanka usually means weeks of emails and quotes that are hard to compare. We built LankaGuide360 so you can
                    see a realistic day-by-day route, with drive times, hotels and a full price, in a few minutes, then hand it to a local agent
                    who checks every detail before anything is booked.
                </p>
            </div>
            <div>
                <h2 class="text-2xl font-bold">Our mission</h2>
                <p class="mt-3 leading-7 text-slate-700">
                    Honest plans and clear prices for every budget, from backpacker guesthouses to private villas, and fair work for the
                    drivers, guides and small hotels who make a trip memorable. We also point you to the island's quieter hidden gems.
                </p>
            </div>
        </section>

        <section aria-labelledby="values-heading">
            <h2 id="values-heading" class="text-2xl font-bold">How we work</h2>
            <ul class="mt-6 grid gap-6 sm:grid-cols-3">
                @foreach ([
                    ['Real routes', 'Plans are built from our own checked list of places, arranged by geography and drive time — never invented.'],
                    ['Human confirmation', 'Every submitted plan is reviewed by an agent, who confirms availability and the final price.'],
                    ['Open data, credited', 'Descriptions and photos come from Wikipedia, Wikimedia Commons and OpenStreetMap, with credit to their authors.'],
                ] as [$heading, $text])
                    <li class="rounded-2xl bg-slate-50 p-6 ring-1 ring-slate-200">
                        <h3 class="font-semibold">{{ $heading }}</h3>
                        <p class="mt-2 text-sm text-slate-600">{{ $text }}</p>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="rounded-2xl bg-primary-50 p-8 ring-1 ring-primary-100" aria-labelledby="licence-heading">
            <h2 id="licence-heading" class="text-xl font-bold">Licences</h2>
            <p class="mt-2 text-slate-700">
                LankaGuide360 works with tour operators and guides registered with the Sri Lanka Tourism Development Authority (SLTDA).
                Our SLTDA registration number will be shown here once issued.
            </p>
        </section>

        <section class="text-center">
            <h2 class="text-2xl font-bold">Ready to plan?</h2>
            <div class="mt-4 flex justify-center gap-3">
                <a href="{{ route('plan') }}" class="btn btn-primary px-6 py-3">Start planning</a>
                <a href="{{ route('contact') }}" class="btn btn-secondary px-6 py-3">Talk to us</a>
            </div>
        </section>
    </div>
</x-app-layout>
