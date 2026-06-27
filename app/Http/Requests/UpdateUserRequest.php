<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 管理者によるユーザー編集リクエストのバリデーション
 * 🟡 信頼性: TASK-0007.md実装詳細1（名前・メールアドレス・役割の変更）より
 */
class UpdateUserRequest extends FormRequest
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
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user),
            ],
            'role' => ['required', 'string', Rule::in(array_map(
                fn (UserRole $role): string => $role->routeKey(),
                UserRole::cases()
            ))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '名前',
            'email' => 'メールアドレス',
            'role' => '役割',
        ];
    }
}
