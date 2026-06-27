<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 製品モデル
 * 🔵 信頼性: database-schema.sql（productsテーブル定義）・TASK-0006.md実装詳細1より
 */
#[Fillable(['product_code', 'product_name', 'unit_price', 'unit', 'stock_quantity', 'reserved_quantity', 'alert_threshold'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'stock_quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'alert_threshold' => 'integer',
        ];
    }

    /**
     * 当該製品に紐づく在庫変動履歴一覧
     * 🔵 信頼性: TASK-0006.md実装詳細1「stockMovements()リレーション（hasMany）を定義する」より
     *
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
