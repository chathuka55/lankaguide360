<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Pricing and display settings (SRS 5.4, NFR-16). Admin only.
 */
class SettingController extends Controller
{
    /**
     * Known settings: key => [label, help, validation rules]. Unknown keys in the table are left alone.
     */
    public const FIELDS = [
        'Pricing' => [
            'service_fee_budget' => ['Service fee, Budget (%)', 'Added to the trip subtotal.', ['required', 'numeric', 'between:0,100']],
            'service_fee_premium' => ['Service fee, Premium (%)', null, ['required', 'numeric', 'between:0,100']],
            'service_fee_luxury' => ['Service fee, Luxury (%)', null, ['required', 'numeric', 'between:0,100']],
            'tax_percent' => ['Tax (%)', 'Applied after the service fee. 0 = prices include tax.', ['required', 'numeric', 'between:0,100']],
            'ticket_child_free_age' => ['Children enter free under age', 'Entry tickets (SRS 5.4).', ['required', 'integer', 'between:0,18']],
            'meal_breakfast' => ['Breakfast per adult (USD)', 'Meals not included in the room rate. Children pay half.', ['required', 'numeric', 'min:0']],
            'meal_lunch' => ['Lunch per adult (USD)', null, ['required', 'numeric', 'min:0']],
            'meal_dinner' => ['Dinner per adult (USD)', null, ['required', 'numeric', 'min:0']],
        ],
        'Currency' => [
            'currency' => ['Base currency', 'ISO code; prices are stored in this currency.', ['required', 'string', 'size:3']],
            'usd_lkr' => ['1 USD in LKR', 'For the rupee equivalent shown to travellers.', ['required', 'numeric', 'min:0.0001']],
            'usd_eur' => ['1 USD in EUR', null, ['required', 'numeric', 'min:0.0001']],
        ],
        'Trip Builder' => [
            'tier_from_price_budget' => ['Budget: from $ per person per day', 'Shown on builder step 1.', ['required', 'numeric', 'min:0']],
            'tier_from_price_premium' => ['Premium: from $ per person per day', null, ['required', 'numeric', 'min:0']],
            'tier_from_price_luxury' => ['Luxury: from $ per person per day', null, ['required', 'numeric', 'min:0']],
            'day_limit_hours_budget' => ['Budget: max hours per day', 'Driving + visits (SRS 5.3).', ['required', 'numeric', 'between:4,14']],
            'day_limit_hours_premium' => ['Premium: max hours per day', null, ['required', 'numeric', 'between:4,14']],
            'day_limit_hours_luxury' => ['Luxury: max hours per day', null, ['required', 'numeric', 'between:4,14']],
        ],
    ];

    public function edit(): View
    {
        $this->authorize('viewAny', Setting::class);

        return view('admin.settings.edit', [
            'groups' => self::FIELDS,
            'values' => Setting::pluck('value', 'key'),
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        foreach ($request->validated('settings') as $key => $value) {
            Setting::put($key, $key === 'currency' ? strtoupper($value) : (string) $value);
        }

        return redirect()->route('admin.settings.edit')->with('status', 'Settings saved.');
    }
}
