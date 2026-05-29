<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL', 'admin@cadence.test');
        $password = env('SUPERADMIN_PASSWORD', 'password');

        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->fill([
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ])->save();

            $this->command->info("Super admin created: {$email} / {$password}");
        } else {
            // Re-sync password on subsequent runs so a forgotten dev creds is easy to reset
            // by tweaking SUPERADMIN_PASSWORD in .env and re-running the seeder.
            $user->forceFill([
                'password' => Hash::make($password),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            $this->command->info("Super admin password refreshed for {$email}.");
        }
    }
}
