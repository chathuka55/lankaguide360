<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MediaStatus;
use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\ImportLog;
use App\Models\Media;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
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
        ]);
    }
}
