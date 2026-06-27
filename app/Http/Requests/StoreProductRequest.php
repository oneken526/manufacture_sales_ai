<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 製品新規登録リクエストのバリデーション
 * 🔵 信頼性: database-schema.sql（productsテーブル定義）・TASK-0006.md実装詳細2より
 */
class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_code' => ['required', 'string', 'max:50', 'unique:products,product_code'],
            'product_name' => ['required', 'string', 'max:255'],
            'unit_price' => ['required', 'integer', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'alert_threshold' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_code' => '品番',
            'product_name' => '製品名',
            'unit_price' => '単価',
            'unit' => '単位',
            'stock_quantity' => '在庫数',
            'alert_threshold' => '在庫アラート閾値',
        ];
    }
}
