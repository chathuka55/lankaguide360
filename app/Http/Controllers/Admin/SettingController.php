<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Models\Setting;
use App\Services\Mail\MailTester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'tax_percent' => ['Tax, foreign travellers (%)', 'Applied after the service fee (tourism taxes and service charges). 0 = prices include tax.', ['required', 'numeric', 'between:0,100']],
            'tax_percent_local' => ['Tax, Sri Lankan residents (%)', 'Applied after the service fee for local travellers.', ['required', 'numeric', 'between:0,100']],
            'local_discount_percent' => ['Resident discount (%)', 'Off hotels, transport and guide for Sri Lankan residents. Entry tickets use the local fee of each place.', ['required', 'numeric', 'between:0,100']],
            'refreshments_per_person_day' => ['Refreshment pack per person per day (USD)', 'Optional water, soft drinks and snacks in the vehicle.', ['required', 'numeric', 'min:0']],
            'ticket_child_free_age' => ['Children enter free under age', 'Entry tickets (SRS 5.4).', ['required', 'integer', 'between:0,18']],
            'meal_breakfast' => ['Breakfast per adult (USD)', 'Meals not included in the room rate. Children pay half.', ['required', 'numeric', 'min:0']],
            'meal_lunch' => ['Lunch per adult (USD)', null, ['required', 'numeric', 'min:0']],
            'meal_dinner' => ['Dinner per adult (USD)', null, ['required', 'numeric', 'min:0']],
        ],
        'Currency' => [
            'currency' => ['Base currency', 'Prices are entered and stored in US dollars.', ['required', 'string', 'in:USD,usd']],
            'display_currency' => ['Currency shown to travellers', 'LKR, USD or EUR. Amounts are converted with the rates below.', ['required', 'string', 'in:LKR,USD,EUR,lkr,usd,eur']],
            'usd_lkr' => ['1 USD in LKR', 'Exchange rate used to show prices in rupees.', ['required', 'numeric', 'min:0.0001']],
            'usd_eur' => ['1 USD in EUR', null, ['required', 'numeric', 'min:0.0001']],
        ],
        'Trip Builder' => [
            'tier_from_price_budget' => ['Budget: from (USD) per person per day', 'Shown on builder step 1.', ['required', 'numeric', 'min:0']],
            'tier_from_price_premium' => ['Premium: from (USD) per person per day', null, ['required', 'numeric', 'min:0']],
            'tier_from_price_luxury' => ['Luxury: from (USD) per person per day', null, ['required', 'numeric', 'min:0']],
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

    /**
     * Send a test email now to check the SMTP settings.
     */
    public function testEmail(Request $request, MailTester $tester): RedirectResponse
    {
        $this->authorize('viewAny', Setting::class);
        $email = $request->validate(['email' => ['required', 'email', 'max:190']])['email'];

        $result = $tester->send($email);

        return redirect()->route('admin.settings.edit')->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        foreach ($request->validated('settings') as $key => $value) {
            Setting::put($key, in_array($key, ['currency', 'display_currency'], true) ? strtoupper($value) : (string) $value);
        }

        return redirect()->route('admin.settings.edit')->with('status', 'Settings saved.');
    }
}
