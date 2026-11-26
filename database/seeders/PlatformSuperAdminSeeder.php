<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformSuperAdminSeeder extends Seeder
{
    /**
     * Seed the platform super admin account.
     *
     * Credentials (local/dev):
     *   email: nexvative@restora.com
     *   password: P@ssword123
     */
    public function run(): void
    {
        User::query()
            ->where('email', 'admin@restora.com')
            ->where('role', UserRole::PlatformSuperAdmin)
            ->delete();

        User::query()->updateOrCreate(
            ['email' => 'nexvative@restora.com'],
            [
                'tenant_id' => null,
                'first_name' => 'Platform',
                'last_name' => 'Admin',
                'mobile_number' => '03000000000',
                'password' => Hash::make('P@ssword123'),
                'profile_picture' => null,
                'role' => UserRole::PlatformSuperAdmin,
                'email_verified_at' => now(),
            ]
        );
    }
}
