<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Default staff logins (SRS 10.4). Password "password": change before going live.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['name' => 'Site Admin', 'email' => 'admin@lankaguide360.test', 'role' => UserRole::Admin],
            ['name' => 'Travel Agent', 'email' => 'agent@lankaguide360.test', 'role' => UserRole::Agent],
        ];

        foreach ($accounts as $account) {
            $user = User::firstOrNew(['email' => $account['email']]);
            $user->name = $account['name'];
            $user->password = Hash::make('password');
            $user->role = $account['role'];
            $user->country = 'Sri Lanka';
            $user->email_verified_at ??= now();
            $user->save();
        }
    }
}
