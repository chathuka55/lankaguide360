@php($contact = config('lankaguide.contact'))

<x-app-layout title="Contact" description="Contact the LankaGuide360 team by phone, WhatsApp, email or the contact form.">
    @push('head') @vite(['resources/js/map.js']) @endpush

    <x-page-header eyebrow="Contact" title="Talk to our team" subtitle="Questions about a trip or a quote? We usually reply within one working day." />

    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-5 lg:px-8">
        {{-- Form --}}
        <section class="lg:col-span-3" aria-labelledby="form-heading">
            <h2 id="form-heading" class="text-2xl font-bold">Send us a message</h2>

            @if (session('status'))
                <div class="mt-4 rounded-lg border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-800" role="status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('contact.store') }}" class="mt-6 space-y-4" novalidate>
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.input name="name" label="Your name" :required="true" autocomplete="name" maxlength="120" />
                    <x-admin.input name="email" label="Email" type="email" :required="true" autocomplete="email" maxlength="190" />
                    <x-admin.input name="phone" label="Phone / WhatsApp (with country code)" type="tel" autocomplete="tel" maxlength="30" />
                    <x-admin.input name="subject" label="Subject" maxlength="150" />
                </div>
                <x-admin.textarea name="body" label="Message" :required="true" rows="6" maxlength="5000" />
                {{-- Honeypot: leave empty --}}
                <div class="hidden" aria-hidden="true">
                    <label for="website">Website</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>
                <p class="text-xs text-slate-500">We use your details only to answer your message.</p>
                <button type="submit" class="btn btn-primary px-6 py-3">Send message</button>
            </form>
        </section>

        {{-- Details --}}
        <aside class="space-y-6 lg:col-span-2" aria-label="Contact details">
            <ul class="space-y-3">
                <li><a href="tel:{{ preg_replace('/[^\d+]/', '', $contact['phone']) }}" class="flex items-center gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200 hover:ring-primary-300"><x-icon name="phone" class="size-6 text-primary-700" /><span><span class="block text-sm text-slate-500">Phone</span>{{ $contact['phone'] }}</span></a></li>
                <li><a href="https://wa.me/{{ $contact['whatsapp'] }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200 hover:ring-primary-300"><x-icon name="chat" class="size-6 text-primary-700" /><span><span class="block text-sm text-slate-500">WhatsApp</span>Message us any time</span></a></li>
                <li><a href="mailto:{{ $contact['email'] }}" class="flex items-center gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200 hover:ring-primary-300"><x-icon name="envelope" class="size-6 text-primary-700" /><span><span class="block text-sm text-slate-500">Email</span>{{ $contact['email'] }}</span></a></li>
                <li class="flex items-center gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200"><x-icon name="map-pin" class="size-6 text-primary-700" /><span><span class="block text-sm text-slate-500">Office</span>{{ $contact['address'] }}</span></li>
            </ul>
            <div x-data="pinMap({ lat: {{ $contact['lat'] }}, lng: {{ $contact['lng'] }}, label: 'LankaGuide360 office', zoom: 14 })" class="h-64 overflow-hidden rounded-2xl ring-1 ring-slate-200" role="region" aria-label="Office map"></div>
        </aside>
    </div>

    {{-- FAQ --}}
    @if ($faqs->isNotEmpty())
        <section class="bg-slate-50 py-14" aria-labelledby="faq-heading">
            <div class="mx-auto max-w-3xl px-4 sm:px-6">
                <h2 id="faq-heading" class="text-2xl font-bold">Frequently asked questions</h2>
                <div class="mt-6 divide-y divide-slate-200 rounded-2xl bg-white ring-1 ring-slate-200">
                    @foreach ($faqs as $faq)
                        <details class="group p-5">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-slate-900">
                                {{ $faq->question }}
                                <x-icon name="chevron-down" class="size-5 shrink-0 text-slate-400 transition group-open:rotate-180" />
                            </summary>
                            <p class="mt-3 leading-7 text-slate-600">{{ $faq->answer }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-app-layout>
