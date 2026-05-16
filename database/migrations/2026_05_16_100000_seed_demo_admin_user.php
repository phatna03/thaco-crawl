<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Tạo user admin khi bật biến môi trường (xong migration trên production nên TẮT).
     * Không hardcode email/mật khẩu để an toàn khi repo public.
     */
    public function up(): void
    {
        if (! filter_var(env('FILAMENT_ADMIN_SEED_ON_MIGRATE', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $email = trim((string) env('FILAMENT_ADMIN_EMAIL', ''));
        $password = env('FILAMENT_ADMIN_PASSWORD');

        if ($email === '' || ! filled($password)) {
            return;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => trim((string) env('FILAMENT_ADMIN_NAME', 'Admin')),
                'password' => Hash::make((string) $password),
            ]
        );
    }

    public function down(): void
    {
        $email = trim((string) env('FILAMENT_ADMIN_EMAIL', ''));
        if ($email === '') {
            return;
        }

        User::query()->where('email', $email)->delete();
    }
};
