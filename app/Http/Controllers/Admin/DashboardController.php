<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MediaStatus;
use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\ImportLog;
use App\Models\Media;
use App\Models\Place;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * "Getting started" for admins: what still needs doing before taking real bookings.
     *
     * @return array<int, array{done: bool, label: string, help: string, url: ?string}>
     */
    private function checklist(): array
    {
        $user = auth()->user();
        if (! $user->can('admin')) {
            return [];
        }

        $demo = Hotel::where('is_demo', true)->count();
        $pricedReal = Hotel::where('is_demo', false)->published()->has('roomRates')->count();

        return [
            ['done' => ! Hash::check('password', $user->password), 'label' => 'Change the default password', 'help' => 'Your account still uses the public seed password.', 'url' => route('profile.edit')],
            ['done' => config('mail.default') !== 'log', 'label' => 'Set up email sending', 'help' => 'Emails are only written to the log file until SMTP is configured (docs/EMAIL.md).', 'url' => route('admin.settings.edit')],
            ['done' => Place::published()->count() >= 20, 'label' => 'Publish places', 'help' => Place::published()->count().' published so far.', 'url' => route('admin.review.index')],
            ['done' => $pricedReal >= 25, 'label' => 'Add room rates to real hotels', 'help' => "{$pricedReal} real hotels have prices.", 'url' => route('admin.hotels.index', ['source' => 'real'])],
            ['done' => $demo === 0, 'label' => 'Remove the demo hotels before real bookings', 'help' => $demo > 0 ? "{$demo} demo hotels are live (php artisan lg:demo-hotels --remove)." : 'No demo hotels.', 'url' => route('admin.hotels.index', ['source' => 'demo'])],
            ['done' => Setting::get('usd_lkr') !== null, 'label' => 'Check the exchange rate and taxes', 'help' => '1 USD = '.Setting::get('usd_lkr', '?').' LKR.', 'url' => route('admin.settings.edit')],
        ];
    }

    public function index(): View
    {
        $tripsByStatus = Trip::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.dashboard', [
            'tripsByStatus' => collect(TripStatus::cases())
                ->mapWithKeys(fn (TripStatus $status) => [$status->value => (int) ($tripsByStatus[$status->value] ?? 0)]),
            'awaiting' => [
                'places' => Place::drafts()->where('is_active', true)->count(),
                'hotels' => Hotel::drafts()->where('is_active', true)->count(),
                'photos' => Media::where('status', MediaStatus::Draft->value)->count(),
            ],
            'totals' => [
                'places' => Place::count(),
                'published_places' => Place::published()->count(),
                'hotels' => Hotel::count(),
                'published_hotels' => Hotel::published()->count(),
            ],
            'usersByRole' => User::query()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role'),
            'latestTrips' => Trip::with('leadTraveller')->latest('id')->limit(5)->get(),
            'latestLogs' => ImportLog::with('target')->latest('id')->limit(8)->get(),
            'checklist' => $this->checklist(),
        ]);
    }
}
