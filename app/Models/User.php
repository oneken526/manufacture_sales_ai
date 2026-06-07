<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * 【機能概要】: 指定したロールがこのユーザーの現在のロールと一致するかを判定する
     * 【実装方針】: Enumインスタンス同士を厳密比較するシンプルな実装とする
     * 【テスト対応】: tests/Unit/Models/UserRoleHelperTest.php の2テストを通すための実装
     * 🟡 信頼性レベル: TASK-0003.md「hasRole(UserRole $role): bool等のヘルパーメソッドを実装する」に基づく妥当な推測
     */
    public function hasRole(UserRole $role): bool
    {
        // 【処理内容】: Enumは値オブジェクトのため === で同一ケースかどうかを判定できる
        return $this->role === $role;
    }
}
