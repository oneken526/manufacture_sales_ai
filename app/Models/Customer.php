<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 顧客モデル
 * 🔵 信頼性: database-schema.sql（customersテーブル定義）・TASK-0005.md実装詳細1より
 */
#[Fillable(['company_name', 'contact_name', 'address', 'phone', 'email', 'credit_limit'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_limit' => 'integer',
        ];
    }

    /**
     * 当該顧客に紐づく受注一覧
     * 🔵 信頼性: TASK-0005.md実装詳細1「salesOrders()リレーション（hasMany）を定義する」より
     *
     * @return HasMany<SalesOrder, $this>
     */
    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }
}
