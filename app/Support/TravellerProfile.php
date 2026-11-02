<?php

namespace App\Support;

use App\Rules\SriLankanNic;
use Illuminate\Validation\Rule;

/**
 * Traveller details asked at registration and in the profile (feedback round, Nov 2026):
 * phone, country, nationality, Local/Foreign (from the country) and an NIC for Sri Lankans.
 */
final class TravellerProfile
{
    public const LOCAL_COUNTRY = 'Sri Lanka';

    public const COUNTRIES = [
        'Sri Lanka', 'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Argentina', 'Armenia', 'Australia', 'Austria',
        'Azerbaijan', 'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan',
        'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi', 'Cambodia',
        'Cameroon', 'Canada', 'Cape Verde', 'Chad', 'Chile', 'China', 'Colombia', 'Comoros', 'Congo', 'Costa Rica', 'Croatia',
        'Cuba', 'Cyprus', 'Czech Republic', 'Denmark', 'Djibouti', 'Dominican Republic', 'Ecuador', 'Egypt', 'El Salvador',
        'Estonia', 'Eswatini', 'Ethiopia', 'Fiji', 'Finland', 'France', 'Gabon', 'Gambia', 'Georgia', 'Germany', 'Ghana',
        'Greece', 'Guatemala', 'Guinea', 'Guyana', 'Haiti', 'Honduras', 'Hong Kong', 'Hungary', 'Iceland', 'India', 'Indonesia',
        'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy', 'Ivory Coast', 'Jamaica', 'Japan', 'Jordan', 'Kazakhstan', 'Kenya',
        'Kuwait', 'Kyrgyzstan', 'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein', 'Lithuania',
        'Luxembourg', 'Macau', 'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Mauritania', 'Mauritius',
        'Mexico', 'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Mozambique', 'Myanmar', 'Namibia', 'Nepal',
        'Netherlands', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'North Macedonia', 'Norway', 'Oman', 'Pakistan',
        'Palestine', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines', 'Poland', 'Portugal', 'Qatar', 'Romania',
        'Russia', 'Rwanda', 'Saudi Arabia', 'Senegal', 'Serbia', 'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia',
        'Slovenia', 'Somalia', 'South Africa', 'South Korea', 'Spain', 'Sudan', 'Suriname', 'Sweden', 'Switzerland', 'Syria',
        'Taiwan', 'Tajikistan', 'Tanzania', 'Thailand', 'Togo', 'Trinidad and Tobago', 'Tunisia', 'Turkey', 'Turkmenistan',
        'Uganda', 'Ukraine', 'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay', 'Uzbekistan', 'Venezuela',
        'Vietnam', 'Yemen', 'Zambia', 'Zimbabwe',
    ];

    public static function typeFor(?string $country): string
    {
        return $country === self::LOCAL_COUNTRY ? 'local' : 'foreign';
    }

    /**
     * Validation rules shared by registration and the profile form.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?string $country, bool $required = true): array
    {
        $need = $required ? 'required' : 'nullable';

        return [
            'phone' => [$need, 'string', 'max:30', 'regex:/^\+?\d[\d\s\-()]{6,}$/'],
            'country' => [$need, 'string', Rule::in(self::COUNTRIES)],
            'nationality' => [$need, 'string', Rule::in(self::COUNTRIES)],
            // Only Sri Lankans give an NIC; for anyone else the field is ignored (it may still hold
            // text typed before the country was changed).
            'nic' => $country === self::LOCAL_COUNTRY ? ['required', 'string', 'max:14', new SriLankanNic] : ['exclude'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'nic.required' => 'Sri Lankan travellers need to enter their NIC number.',
            'phone.regex' => 'Enter a phone number with digits, e.g. +94 77 123 4567.',
            'country.in' => 'Choose your country from the list.',
            'nationality.in' => 'Choose your nationality from the list.',
        ];
    }

    /**
     * Attributes to save from validated input.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function attributes(array $data): array
    {
        $type = self::typeFor($data['country'] ?? null);

        return [
            'phone' => $data['phone'] ?? null,
            'country' => $data['country'] ?? null,
            'nationality' => $data['nationality'] ?? null,
            'traveller_type' => isset($data['country']) ? $type : null,
            'nic' => $type === 'local' ? SriLankanNic::normalise($data['nic'] ?? null) : null,
        ];
    }
}
