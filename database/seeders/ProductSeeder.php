<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 開発・動作確認用のサンプル製品データを投入するシーダー。
 * 本番環境への投入は想定していない。
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'product_code' => 'PRD-0001',
                'product_name' => '精密ベアリング A型',
                'unit_price' => 1_500,
                'unit' => '個',
                'stock_quantity' => 200,
                'reserved_quantity' => 30,
                'alert_threshold' => 50,
            ],
            [
                'product_code' => 'PRD-0002',
                'product_name' => 'アルミフレーム B型',
                'unit_price' => 8_000,
                'unit' => '本',
                'stock_quantity' => 80,
                'reserved_quantity' => 10,
                'alert_threshold' => 20,
            ],
            [
                'product_code' => 'PRD-0003',
                'product_name' => 'ステンレスボルトセット',
                'unit_price' => 500,
                'unit' => 'セット',
                'stock_quantity' => 500,
                'reserved_quantity' => 0,
                'alert_threshold' => 100,
            ],
        ];

        foreach ($products as $product) {
            DB::table('products')->updateOrInsert(
                ['product_code' => $product['product_code']],
                $product + ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
