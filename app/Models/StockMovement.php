<?php

namespace App\Models;

use App\Enums\StockMovementReason;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 在庫変動履歴モデル
 * 🟡 信頼性: database-schema.sql（stock_movementsテーブル定義）・TASK-0006.md実装詳細3より
 */
#[Fillable(['product_id', 'reason', 'quantity_change', 'related_order_id', 'operated_by', 'memo', 'created_at'])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => StockMovementReason::class,
            'quantity_change' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function relatedOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'related_order_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operated_by');
    }
}
