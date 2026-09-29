@php
$contact = config('lankaguide.contact');
$social = config('lankaguide.social');
$quickLinks = [
    'Home' => route('home'),
    'Destinations' => route('destinations.index'),
    'Packages' => route('packages.index'),
    'Plan a Trip' => route('plan'),
    'About' => route('about'),
    'Contact' => route('contact'),
];
@endphp

<footer class="bg-slate-900 text-slate-300">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:grid-cols-2 sm:px-6 lg:grid-cols-12 lg:px-8">
        {{-- Brand --}}
        <div class="lg:col-span-4">
            <x-logo :inverted="true" />
            <p class="mt-4 max-w-sm text-sm leading-6 text-slate-400">{{ config('lankaguide.tagline') }}</p>

            <ul class="mt-6 flex gap-3" aria-label="Social media">
                <li>
                    <a href="{{ $social['facebook'] }}" target="_blank" rel="noopener" class="grid size-10 place-items-center rounded-full bg-slate-800 text-slate-300 transition hover:bg-primary-700 hover:text-white">
                        <span class="sr-only">Facebook</span>
                        <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-7.5h2.53l.38-2.94H13.5V8.69c0-.85.24-1.43 1.46-1.43h1.55V4.63A20.7 20.7 0 0 0 14.25 4.5c-2.24 0-3.77 1.37-3.77 3.88v2.18H7.95v2.94h2.53V21h3.02Z"/></svg>
                    </a>
                </li>
                <li>
                    <a href="{{ $social['instagram'] }}" target="_blank" rel="noopener" class="grid size-10 place-items-center rounded-full bg-slate-800 text-slate-300 transition hover:bg-primary-700 hover:text-white">
                        <span class="sr-only">Instagram</span>
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r="1" fill="currentColor" stroke="none"/></svg>
                    </a>
                </li>
                <li>
                    <a href="{{ $social['youtube'] }}" target="_blank" rel="noopener" class="grid size-10 place-items-center rounded-full bg-slate-800 text-slate-300 transition hover:bg-primary-700 hover:text-white">
                        <span class="sr-only">YouTube</span>
                        <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.6 7.2a2.5 2.5 0 0 0-1.76-1.77C18.25 5 12 5 12 5s-6.25 0-7.84.43A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.76 1.77C5.75 19 12 19 12 19s6.25 0 7.84-.43a2.5 2.5 0 0 0 1.76-1.77A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8ZM10 15V9l5.2 3L10 15Z"/></svg>
                    </a>
                </li>
                <li>
                    <a href="https://wa.me/{{ $contact['whatsapp'] }}" target="_blank" rel="noopener" class="grid size-10 place-items-center rounded-full bg-slate-800 text-slate-300 transition hover:bg-[#25D366] hover:text-white">
                        <span class="sr-only">WhatsApp</span>
                        <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.5a9.5 9.5 0 0 0-8.2 14.3L2.5 21.5l4.8-1.26A9.5 9.5 0 1 0 12 2.5Zm0 17.3a7.8 7.8 0 0 1-4-1.1l-.28-.17-2.85.75.76-2.78-.19-.29A7.8 7.8 0 1 1 12 19.8Zm4.28-5.84c-.23-.12-1.38-.68-1.6-.76-.21-.08-.37-.12-.52.12-.16.23-.6.76-.74.91-.14.16-.27.18-.5.06a6.4 6.4 0 0 1-3.2-2.8c-.24-.41.24-.38.69-1.27.08-.16.04-.29-.02-.41l-.72-1.73c-.19-.45-.38-.39-.52-.4h-.45a.86.86 0 0 0-.62.3 2.6 2.6 0 0 0-.82 1.94 4.5 4.5 0 0 0 .95 2.4 10.4 10.4 0 0 0 4 3.52c1.48.64 2.06.7 2.8.59.45-.07 1.38-.57 1.58-1.11.2-.55.2-1.02.14-1.12-.06-.1-.21-.16-.44-.27Z"/></svg>
                    </a>
                </li>
            </ul>
        </div>

        {{-- Quick links --}}
        <nav class="lg:col-span-2" aria-labelledby="footer-quick-links">
            <h2 id="footer-quick-links" class="text-sm font-semibold uppercase tracking-wider text-white">Quick links</h2>
            <ul class="mt-4 space-y-2.5 text-sm">
                @foreach ($quickLinks as $label => $url)
                    <li><a href="{{ $url }}" class="hover:text-white">{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>

        {{-- Categories --}}
        <nav class="lg:col-span-3" aria-labelledby="footer-categories">
            <h2 id="footer-categories" class="text-sm font-semibold uppercase tracking-wider text-white">Top categories</h2>
            <ul class="mt-4 space-y-2.5 text-sm">
                @foreach ($siteCategories as $category)
                    <li><a href="{{ route('destinations.index', ['category' => $category->slug]) }}" class="hover:text-white">{{ $category->name }}</a></li>
                @endforeach
            </ul>
        </nav>

        {{-- Contact + newsletter --}}
        <div class="sm:col-span-2 lg:col-span-3">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-white">Contact us</h2>
            <ul class="mt-4 space-y-3 text-sm">
                <li class="flex items-start gap-2.5">
                    <x-icon name="map-pin" class="mt-0.5 size-5 text-primary-400" />
                    <span>{{ $contact['address'] }}</span>
                </li>
                <li>
                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $contact['phone']) }}" class="flex items-center gap-2.5 hover:text-white">
                        <x-icon name="phone" class="size-5 text-primary-400" /> {{ $contact['phone'] }}
                    </a>
                </li>
                <li>
                    <a href="https://wa.me/{{ $contact['whatsapp'] }}" target="_blank" rel="noopener" class="flex items-center gap-2.5 hover:text-white">
                        <x-icon name="chat" class="size-5 text-primary-400" /> Chat on WhatsApp
                    </a>
                </li>
                <li>
                    <a href="mailto:{{ $contact['email'] }}" class="flex items-center gap-2.5 hover:text-white">
                        <x-icon name="envelope" class="size-5 text-primary-400" /> {{ $contact['email'] }}
                    </a>
                </li>
            </ul>

            <form method="POST" action="{{ route('newsletter.store') }}" class="mt-6"
                  x-data="{ message: '' }"
                  @submit.prevent="fetch($el.action, { method: 'POST', body: new FormData($el), headers: { Accept: 'application/json' } })
                      .then((r) => r.json().then((d) => message = r.ok ? d.message : (d.message || 'Please enter a valid email address.')))
                      .catch(() => $el.submit())">
                @csrf
                <label for="newsletter-email" class="text-sm font-semibold text-white">Travel tips, once a month</label>
                <div class="mt-2 flex gap-2">
                    <input id="newsletter-email" type="email" name="email" required placeholder="you@example.com" autocomplete="email"
                           class="min-w-0 flex-1 rounded-lg border-slate-700 bg-slate-800 text-sm text-white placeholder:text-slate-500 focus:border-primary-500 focus:ring-primary-500">
                    <button type="submit" class="rounded-lg bg-accent-500 px-4 text-sm font-semibold text-slate-900 hover:bg-accent-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-300">
                        Subscribe
                    </button>
                </div>
                <p x-show="message" x-text="message" x-cloak class="mt-2 text-xs text-accent-300" role="status"></p>
            </form>
        </div>
    </div>

    <div class="border-t border-slate-800">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-6 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <p>&copy; {{ now()->year }} LankaGuide360. All rights reserved.</p>
            <ul class="flex gap-5">
                <li><a href="{{ route('privacy') }}" class="hover:text-white">Privacy Policy</a></li>
                <li><a href="{{ route('terms') }}" class="hover:text-white">Terms</a></li>
                <li><a href="{{ route('credits') }}" class="hover:text-white">Image credits</a></li>
            </ul>
        </div>
    </div>
</footer>
