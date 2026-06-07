<?php

namespace App\Enums;

/**
 * ユーザー役割
 * 🔵 信頼性: 要件定義REQ-002・設計ヒアリングより
 *
 * 区分値はDB上では数値コード（TINYINT）で保持する（database-schema.sqlのコード表と対応）。
 */
enum UserRole: int
{
    case ADMIN = 1;                 // システム管理者 🔵
    case SALES = 2;                 // 営業担当者 🔵
    case WAREHOUSE = 3;             // 在庫・出荷担当者 🔵
    case ACCOUNTING = 4;            // 管理職・経理担当 🔵

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'システム管理者',
            self::SALES => '営業担当者',
            self::WAREHOUSE => '在庫・出荷担当者',
            self::ACCOUNTING => '管理職・経理担当',
        };
    }

    /**
     * 【機能概要】: ルート名やミドルウェア引数（`role:admin`等）で使用する小文字キー表現を返す
     * 【設計方針】: ロール名⇔Enumの対応関係を本Enumに一元化し、
     *              ミドルウェアやコントローラ側での重複したマッピング定義を排除する
     * 【保守性】: ロールが追加された場合もEnumのcase名と対応キーが一致するため、追加対応箇所が増えない
     * 🟡 信頼性レベル: TASK-0003.mdに直接の記載はないが、ルート名・テストコードの命名規則（admin, sales, warehouse, accounting）から妥当な推測
     */
    public function routeKey(): string
    {
        return strtolower($this->name);
    }

    /**
     * 【機能概要】: ルート名やミドルウェア引数の小文字キー表現からUserRole Enumを逆引きする
     * 【設計方針】: routeKey()と対になる変換を提供し、変換ロジックを一箇所に集約する
     * 【再利用性】: EnsureUserHasRoleミドルウェアなど、文字列引数からロールを特定する複数箇所で利用できる
     * 🟡 信頼性レベル: routeKey()と対になる妥当な推測実装
     */
    public static function fromRouteKey(string $key): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->routeKey() === strtolower($key)) {
                return $case;
            }
        }

        return null;
    }
}
