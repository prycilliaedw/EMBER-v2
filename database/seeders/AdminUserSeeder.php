<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->updateOrInsert(
            ['email' => env('CMS_ADMIN_EMAIL', 'admin@ember.test')],
            [
                'name' => 'Administrator EMBER',
                'password' => Hash::make(env('CMS_ADMIN_PASSWORD', 'password')),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
