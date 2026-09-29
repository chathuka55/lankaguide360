<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ChatLogController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\CuisineController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DistrictController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\GuideController;
use App\Http\Controllers\Admin\HotelController;
use App\Http\Controllers\Admin\ImportToolController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\PlaceController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\ReviewQueueController;
use App\Http\Controllers\Admin\RoomRateController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TripRequestController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CreditsController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PackageController as PublicPackageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\TripBuilderController;
use App\Http\Controllers\TripController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public site
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/destinations', [DestinationController::class, 'index'])->name('destinations.index');
Route::get('/destinations/{district:slug}/{place:slug}', [DestinationController::class, 'show'])->scopeBindings()->name('destinations.show');
Route::get('/districts/{district:slug}', [DestinationController::class, 'district'])->name('districts.show');
Route::get('/plan', [TripBuilderController::class, 'start'])->name('plan');
Route::post('/plan/places/{place:slug}', [TripBuilderController::class, 'addPlace'])->middleware('throttle:60,1')->name('plan.places.toggle');
Route::get('/packages', [PublicPackageController::class, 'index'])->name('packages.index');
Route::get('/packages/{package:slug}', [PublicPackageController::class, 'show'])->name('packages.show');
Route::post('/packages/{package:slug}/customize', [PublicPackageController::class, 'customize'])->middleware('throttle:30,1')->name('packages.customize');
Route::view('/about', 'about')->name('about');
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,10')->name('contact.store');
Route::post('/newsletter', [NewsletterController::class, 'store'])->middleware('throttle:10,10')->name('newsletter.store');
Route::post('/api/chat', [ChatbotController::class, 'send'])->middleware('throttle:chat')->name('chat.send');
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/terms', 'legal.terms')->name('terms');
Route::get('/image-credits', CreditsController::class)->name('credits');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

// Trips: submit, then the private trip page (owner, staff or the emailed token link)
Route::post('/trips', [TripController::class, 'store'])->middleware('throttle:5,10')->name('trips.store');
Route::get('/trips/{trip}', [TripController::class, 'show'])->name('trips.show');
Route::get('/trips/{trip}/pdf', [TripController::class, 'pdf'])->middleware('throttle:20,1')->name('trips.pdf');
Route::post('/trips/{trip}/messages', [TripController::class, 'message'])->middleware('throttle:10,1')->name('trips.message');

// Signed-in users
Route::middleware('auth')->group(function () {
    // Breeze sends users to "dashboard" after login/registration; route them by role.
    Route::get('/dashboard', fn (Request $request) => redirect($request->user()->homeRoute()))->name('dashboard');

    Route::get('/my-trips', [TripController::class, 'index'])->name('my-trips');
    Route::post('/trips/{trip}/cancel', [TripController::class, 'cancel'])->name('trips.cancel');
    Route::post('/trips/{trip}/review', [TripController::class, 'review'])->name('trips.review');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Back office: agents and admins. Agents get read-only master data; policies enforce the rest.
Route::middleware(['auth', 'role:admin,agent'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Review queue for imported drafts (admin)
    Route::get('review', [ReviewQueueController::class, 'index'])->name('review.index');

    // Trip requests (agents and admins)
    Route::prefix('trips')->name('trips.')->group(function () {
        Route::get('/', [TripRequestController::class, 'index'])->name('index');
        Route::get('{trip}', [TripRequestController::class, 'show'])->name('show');
        Route::put('{trip}', [TripRequestController::class, 'update'])->name('update');
        Route::post('{trip}/assign', [TripRequestController::class, 'assign'])->name('assign');
        Route::post('{trip}/assign-to-me', [TripRequestController::class, 'assignToMe'])->name('assign-to-me');
        Route::post('{trip}/status', [TripRequestController::class, 'transition'])->name('transition');
        Route::post('{trip}/messages', [TripRequestController::class, 'message'])->name('message');
        Route::post('{trip}/items', [TripRequestController::class, 'storeItem'])->name('items.store');
        Route::post('{trip}/recalculate', [TripRequestController::class, 'recalculate'])->name('recalculate');
        Route::post('{trip}/package', [TripRequestController::class, 'saveAsPackage'])->name('package');
        Route::post('days/{day}/stops', [TripRequestController::class, 'addStop'])->name('stops.store');
    });
    Route::put('trip-items/{item}', [TripRequestController::class, 'updateItem'])->name('trips.items.update');
    Route::delete('trip-items/{item}', [TripRequestController::class, 'destroyItem'])->name('trips.items.destroy');
    Route::post('trip-stops/{stop}/move', [TripRequestController::class, 'moveStop'])->name('trips.stops.move');
    Route::delete('trip-stops/{stop}', [TripRequestController::class, 'removeStop'])->name('trips.stops.destroy');

    // Master data
    Route::post('places/bulk', [PlaceController::class, 'bulk'])->name('places.bulk');
    Route::resource('places', PlaceController::class)->except('show');
    Route::resource('districts', DistrictController::class)->only(['index', 'edit', 'update']);
    Route::resource('categories', CategoryController::class)->except('show');
    Route::post('hotels/bulk', [HotelController::class, 'bulk'])->name('hotels.bulk');
    Route::resource('hotels', HotelController::class)->except('show');
    Route::post('hotels/{hotel}/rates', [RoomRateController::class, 'store'])->name('hotels.rates.store');
    Route::put('rates/{rate}', [RoomRateController::class, 'update'])->name('rates.update');
    Route::delete('rates/{rate}', [RoomRateController::class, 'destroy'])->name('rates.destroy');
    Route::resource('vehicles', VehicleController::class)->except('show');
    Route::resource('guides', GuideController::class)->except('show');
    Route::resource('cuisines', CuisineController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('packages', PackageController::class)->except('show');

    // Photos (admin)
    Route::prefix('media')->name('media.')->group(function () {
        Route::get('commons-search', [MediaController::class, 'commonsSearch'])->name('commons-search');
        Route::post('bulk', [MediaController::class, 'bulk'])->name('bulk');
        Route::post('{type}/{id}/upload', [MediaController::class, 'upload'])->whereNumber('id')->name('upload');
        Route::post('{type}/{id}/url', [MediaController::class, 'url'])->whereNumber('id')->name('url');
        Route::post('{type}/{id}/commons', [MediaController::class, 'commonsImport'])->whereNumber('id')->name('commons');
        Route::post('{type}/{id}/reorder', [MediaController::class, 'reorder'])->whereNumber('id')->name('reorder');
        Route::patch('{media}', [MediaController::class, 'update'])->name('update');
        Route::post('{media}/cover', [MediaController::class, 'cover'])->name('cover');
        Route::delete('{media}', [MediaController::class, 'destroy'])->name('destroy');
    });

    // Customers (admin)
    Route::patch('reviews/{review}/approve', [ReviewController::class, 'approve'])->name('reviews.approve');
    Route::resource('reviews', ReviewController::class)->except('show');
    Route::resource('messages', ContactMessageController::class)->only(['index', 'show', 'destroy']);
    Route::resource('faqs', FaqController::class)->except('show');
    Route::get('chat-logs', [ChatLogController::class, 'index'])->name('chat-logs.index');

    // System (admin)
    Route::resource('users', UserController::class)->except('show');
    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    Route::get('imports', [ImportToolController::class, 'index'])->name('imports.index');
    Route::post('imports/run', [ImportToolController::class, 'run'])->name('imports.run');
    Route::post('imports/run-all', [ImportToolController::class, 'runAll'])->name('imports.run-all');
});

require __DIR__.'/auth.php';
