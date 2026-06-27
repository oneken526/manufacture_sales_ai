<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 見積新規登録リクエストのバリデーション
 * 🔵 信頼性: database-schema.sql（quotations/quotation_itemsテーブル定義）・REQ-030・EDGE-011より
 */
class StoreQuotationRequest extends FormRequest
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
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'expires_at' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_id' => '顧客',
            'items' => '明細',
            'items.*.product_id' => '製品',
            'items.*.quantity' => '数量',
            'items.*.unit_price' => '単価',
            'expires_at' => '有効期限',
            'remarks' => '備考',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => '明細を1件以上追加してください。',
            'items.min' => '明細を1件以上追加してください。',
        ];
    }
}
