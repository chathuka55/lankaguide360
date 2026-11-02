<x-admin-layout title="Help & user guide">
    <x-admin.header title="Help & user guide" subtitle="How LankaGuide360's back office works, in the order you will use it. Hover over any menu item for a one-line reminder." />

    @php
        $sections = [
            'trips' => ['A trip request, start to finish', [
                ['New request arrives', 'You get an email and it appears under <strong>Trip requests → New</strong>. Click <strong>Assign to me</strong>: the status becomes <em>Under review</em> and the traveller is told.'],
                ['Check and adjust the plan', 'Open the trip. Change hotels per night, the vehicle, guide or meals under <strong>Hotels, transport and guide</strong>. If a hotel is full you can move the <strong>start date</strong> (every day shifts with it), add or remove stops, and edit <strong>Price lines</strong>.'],
                ['Approve with the final price', 'Under <strong>Status</strong> choose <em>Approved</em>, enter the final price and, if anything changed, explain it in the note (e.g. "Kandy hotel was full, we moved you to Hotel X"). The traveller gets an email with the plan as PDF.'],
                ['The traveller accepts or asks for changes', 'They click <strong>Accept and confirm booking</strong> (status <em>Confirmed</em>, you get an email) or <strong>Ask for changes</strong> (back to <em>Under review</em>, their reason arrives as a message).'],
                ['Finalize and issue the e-receipt', 'On a confirmed trip, set the final time at each place and the payment received, then click <strong>Finalize timings and issue the e-receipt</strong>. The traveller receives it as PDF and can print it from their trip page. Its QR code proves it is genuine.'],
                ['During and after the trip', 'Move the status to <em>In progress</em> and <em>Completed</em>. Completed travellers can leave a review (approve it under <strong>Reviews</strong>).'],
            ]],
            'data' => ['Getting places and hotels ready', [
                ['Publish places', '<strong>Review queue</strong> lists imported places, hotels and photos. A place needs a map position and a category before it can be published. Use <em>Publish with photos</em> to publish its Commons photos too.'],
                ['Check locations', 'Each place and hotel form has <strong>Check this location in Google Maps</strong>. Drag the pin to fix it; your position is then never overwritten by an import.'],
                ['Hotels and room rates', 'The Trip Builder can only price hotels that have <strong>room rates</strong> (Hotels → edit → Room rates). Rates are per room per night in USD.'],
                ['Demo hotels', 'Hotels marked <em>Demo listing</em> are fictional, for demonstrations. Before taking real bookings remove them all with <code>php artisan lg:demo-hotels --remove</code>.'],
            ]],
            'prices' => ['Prices, currency and taxes', [
                ['Enter in USD, travellers see LKR', 'All rates are typed in US dollars. Travellers see rupees using the exchange rate in <strong>Settings → Currency</strong>; back-office screens show both.'],
                ['Sri Lankan residents', 'Residents get each place\'s local entry fee and the <strong>Resident discount</strong> on hotels, transport and guide; foreign visitors pay the foreign fee plus tourism taxes (<strong>Settings → Pricing</strong>).'],
                ['Extras', 'The optional refreshment pack price is in Settings. Vehicle services (Wi-Fi, water…) are set per vehicle.'],
            ]],
            'other' => ['Everything else', [
                ['Vehicle hire requests', 'Requests from the public <em>Vehicle hire</em> page arrive in <strong>Contact messages</strong> and by email. Reply to the customer from your email.'],
                ['Reports', '<strong>Reports</strong> shows requests, bookings and revenue for any period. Download one report as CSV or Excel, everything as one Excel workbook, or a PDF summary.'],
                ['Users and passwords', 'Change your own password under the user menu → <strong>Profile</strong>. Add agents under <strong>Users</strong>; agents can work on trips but cannot change prices or settings.'],
                ['Email', 'Check that emails go out with <strong>Settings → Send test email</strong>.'],
            ]],
        ];
    @endphp

    <nav class="mb-6 flex flex-wrap gap-2 text-sm" aria-label="On this page">
        @foreach ($sections as $id => [$title])
            <a href="#{{ $id }}" class="rounded-full bg-white px-3 py-1.5 font-medium text-slate-700 ring-1 ring-slate-200 hover:ring-primary-300">{{ $title }}</a>
        @endforeach
    </nav>

    <div class="space-y-6">
        @foreach ($sections as $id => [$title, $steps])
            <section id="{{ $id }}" class="admin-card scroll-mt-20 p-5" aria-labelledby="{{ $id }}-heading">
                <h3 id="{{ $id }}-heading" class="text-lg font-semibold">{{ $title }}</h3>
                <ol class="mt-4 space-y-4">
                    @foreach ($steps as $i => [$heading, $text])
                        <li class="flex gap-3">
                            <span class="grid size-7 shrink-0 place-items-center rounded-full bg-primary-50 text-sm font-bold text-primary-800">{{ $i + 1 }}</span>
                            <div>
                                <p class="font-medium text-slate-900">{{ $heading }}</p>
                                <p class="mt-0.5 text-sm text-slate-600">{!! $text !!}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endforeach
    </div>
</x-admin-layout>
