<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $email = env('FILAMENT_ADMIN_EMAIL');
        $password = env('FILAMENT_ADMIN_PASSWORD');

        if (filled($email) && filled($password)) {
            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => (string) env('FILAMENT_ADMIN_NAME', 'Quản trị'),
                    'password' => Hash::make($password),
                ],
            );
        }
    }
}
