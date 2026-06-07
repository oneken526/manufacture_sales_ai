<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 開発・動作確認用の初期ユーザーデータを投入するシーダー。
 * 本番環境への投入は想定していない。
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => '管理者 太郎',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMIN->value,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'sales@example.com'],
            [
                'name' => '営業 花子',
                'password' => Hash::make('password'),
                'role' => UserRole::SALES->value,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'warehouse@example.com'],
            [
                'name' => '倉庫 次郎',
                'password' => Hash::make('password'),
                'role' => UserRole::WAREHOUSE->value,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'accounting@example.com'],
            [
                'name' => '経理 三郎',
                'password' => Hash::make('password'),
                'role' => UserRole::ACCOUNTING->value,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
