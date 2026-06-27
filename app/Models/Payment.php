<?php

namespace App\Models;

use App\Enums\PaymentSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 入金記録モデル
 * 🔵 信頼性: database-schema.sql（paymentsテーブル）・TASK-0013より
 */
#[Fillable(['invoice_id', 'amount', 'paid_at', 'source', 'raw_csv_row'])]
class Payment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => PaymentSource::class,
            'amount' => 'integer',
            'paid_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
