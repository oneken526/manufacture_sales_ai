<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 顧客更新リクエストのバリデーション
 * 🔵 信頼性: database-schema.sql（customersテーブル定義）・TASK-0005.md注意事項より
 */
class UpdateCustomerRequest extends FormRequest
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
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'credit_limit' => ['required', 'integer', 'min:0', 'max:9223372036854775807'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_name' => '会社名',
            'contact_name' => '担当者名',
            'address' => '住所',
            'phone' => '電話番号',
            'email' => 'メールアドレス',
            'credit_limit' => '与信枠',
        ];
    }
}
