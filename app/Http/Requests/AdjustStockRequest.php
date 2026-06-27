<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 在庫手動調整リクエストのバリデーション
 * 🔵 信頼性: REQ-023・TASK-0006.md実装詳細3「リクエストには増減数（quantity_change）とメモ（任意）を含める」より
 */
class AdjustStockRequest extends FormRequest
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
            'quantity_change' => ['required', 'integer', 'not_in:0'],
            'memo' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'quantity_change' => '増減数',
            'memo' => 'メモ',
        ];
    }
}
