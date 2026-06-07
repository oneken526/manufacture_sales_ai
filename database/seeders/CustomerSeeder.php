<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 開発・動作確認用のサンプル顧客データを投入するシーダー。
 * 本番環境への投入は想定していない。
 */
class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            [
                'company_name' => '株式会社山田製作所',
                'contact_name' => '山田 一郎',
                'address' => '東京都大田区大森北1-2-3',
                'phone' => '03-1234-5678',
                'email' => 'yamada@example.com',
                'credit_limit' => 5_000_000,
            ],
            [
                'company_name' => '佐藤工業株式会社',
                'contact_name' => '佐藤 二郎',
                'address' => '大阪府大阪市西区靭本町2-1-1',
                'phone' => '06-2345-6789',
                'email' => 'sato@example.com',
                'credit_limit' => 3_000_000,
            ],
            [
                'company_name' => '鈴木商事有限会社',
                'contact_name' => '鈴木 三郎',
                'address' => '愛知県名古屋市中村区名駅3-1-1',
                'phone' => '052-345-6789',
                'email' => 'suzuki@example.com',
                'credit_limit' => 1_000_000,
            ],
        ];

        foreach ($customers as $customer) {
            DB::table('customers')->updateOrInsert(
                ['company_name' => $customer['company_name']],
                $customer + ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
