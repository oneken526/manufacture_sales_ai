<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 受注編集バリデーションリクエスト
 * 🟡 信頼性: TASK-0009.md実装詳細2「受注編集フォーム表示・更新処理」より
 */
class UpdateSalesOrderRequest extends FormRequest
{
    /**
     * 認可処理はルートミドルウェアおよびOrderPolicyで行うため、ここでは常にtrueを返す
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'remarks' => '備考',
        ];
    }
}
