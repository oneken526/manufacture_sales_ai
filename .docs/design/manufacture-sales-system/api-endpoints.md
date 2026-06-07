# 製造業向け販売管理システム API エンドポイント仕様

**作成日**: 2026-06-07
**関連設計**: [architecture.md](architecture.md)
**関連要件定義**: [requirements.md](../../spec/manufacture-sales-system/requirements.md)

**【信頼性レベル凡例】**:
- 🔵 **青信号**: 要件定義書・設計ヒアリングを参考にした確実な定義
- 🟡 **黄信号**: 要件定義書・設計ヒアリングから妥当な推測による定義
- 🔴 **赤信号**: 要件定義書・設計ヒアリングにない推測による定義

---

## 共通仕様

### 構成方針 🔵

**信頼性**: 🔵 *設計ヒアリングQ1（jQuery採用）より*

本システムはBladeによるサーバーサイドレンダリングを基本とし、SPA用REST APIは持たない。
以下の2系統のルートを設ける：

1. **Webルート**（`routes/web.php`）: 画面表示・フォーム送信（通常のHTMLレスポンス、認証はセッションベース）
2. **内部APIルート**（`routes/api.php` の `/api/internal/*`）: jQuery AJAXによる非同期処理用（JSONレスポンス、検索・ステータス確認・在庫チェック等）
3. **AI連携ルート**（`/api/ai/*`、Phase 2）: Claude APIと連携するチャット・予測機能用

### 認証 🔵

**信頼性**: 🔵 *設計ヒアリングQ3（Laravel Breeze）より*

- Webルート・内部APIルートともにセッションベース認証（Laravel Breeze標準）
- 未認証の場合はログイン画面にリダイレクト（内部APIは401 JSONを返却）

### 権限制御 🔵

**信頼性**: 🔵 *要件定義REQ-002, REQ-003, REQ-064より*

ミドルウェア `role:xxx` で役割ベースのアクセス制御を行う。

| 役割 | アクセス可能な主な機能 |
|------|----------------------|
| admin | 全機能 |
| sales | 顧客・見積・受注・請求書閲覧 |
| warehouse | 在庫・出荷操作（請求書操作不可 REQ-003） |
| accounting | 全閲覧・レポート・請求管理（REQ-064） |

権限外アクセス時は403レスポンス（画面の場合はエラーページ、内部APIの場合はJSON）を返す。

### 共通エラーレスポンス（内部API） 🟡

**信頼性**: 🟡 *既存実装の共通パターンから妥当な推測*

```json
{
  "success": false,
  "error": {
    "code": "STOCK_INSUFFICIENT",
    "message": "在庫が不足しています",
    "details": { "productId": 12, "available": 3, "requested": 10 }
  }
}
```

### ページネーション 🔵

**信頼性**: 🔵 *要件定義NFR-021（1ページ50件）より*

一覧系エンドポイントは `page` パラメータでページネーション（1ページ50件）。Laravelの `paginate(50)` を使用。

---

## Webルート（画面）

### 顧客管理 🔵

**信頼性**: 🔵 *要件定義REQ-010〜013より*

| メソッド | パス | 説明 | 関連要件 | 権限 |
|---------|------|------|---------|------|
| GET | /customers | 顧客一覧（検索・ページネーション） | REQ-010, REQ-011 | sales, accounting, admin |
| GET | /customers/create | 顧客新規登録フォーム | REQ-010 | sales, admin |
| POST | /customers | 顧客登録 | REQ-010 | sales, admin |
| GET | /customers/{customer} | 顧客詳細（受注履歴含む） | REQ-013 | sales, accounting, admin |
| GET | /customers/{customer}/edit | 顧客編集フォーム | REQ-010 | sales, admin |
| PUT | /customers/{customer} | 顧客更新 | REQ-010 | sales, admin |
| DELETE | /customers/{customer} | 顧客削除（受注存在時は拒否 REQ-012） | REQ-012 | admin |

### 製品管理 🔵

**信頼性**: 🔵 *要件定義REQ-020〜023より*

| メソッド | パス | 説明 | 関連要件 | 権限 |
|---------|------|------|---------|------|
| GET | /products | 製品一覧（検索・在庫アラート表示） | REQ-020, REQ-021, REQ-022 | 全役割 |
| GET | /products/create | 製品新規登録フォーム | REQ-020 | admin |
| POST | /products | 製品登録 | REQ-020 | admin |
| GET | /products/{product}/edit | 製品編集フォーム | REQ-020 | admin |
| PUT | /products/{product} | 製品更新 | REQ-020 | admin |
| POST | /products/{product}/adjust-stock | 在庫数の手動調整（REQ-023） | REQ-023, REQ-072 | warehouse, admin |

### 見積管理 🔵

**信頼性**: 🔵 *要件定義REQ-030〜033より*

| メソッド | パス | 説明 | 関連要件 | 権限 |
|---------|------|------|---------|------|
| GET | /quotations | 見積一覧 | REQ-030 | sales, admin |
| GET | /quotations/create | 見積作成フォーム | REQ-030 | sales, admin |
| POST | /quotations | 見積保存 | REQ-030, REQ-033 | sales, admin |
| GET | /quotations/{quotation} | 見積詳細 | REQ-030 | sales, admin |
| GET | /quotations/{quotation}/pdf | 見積PDFプレビュー・ダウンロード | REQ-032 | sales, admin |
| POST | /quotations/{quotation}/confirm | 受注確定（在庫引当 → 受注作成） | REQ-031, REQ-040, REQ-041 | sales, admin |

### 受注管理 🔵

**信頼性**: 🔵 *要件定義REQ-040〜043より*

| メソッド | パス | 説明 | 関連要件 | 権限 |
|---------|------|------|---------|------|
| GET | /orders | 受注一覧（ステータスフィルタ） | REQ-040 | sales, accounting, admin |
| GET | /orders/{order} | 受注詳細 | REQ-040 | sales, accounting, admin |
| PUT | /orders/{order} | 受注内容編集（確定後はadminのみ REQ-042） | REQ-042 | admin |
| POST | /orders/{order}/cancel | 受注キャンセル（在庫引当解除 REQ-043） | REQ-043 | sales, admin |
| POST | /orders/{order}/shipping-instruction | 出荷指示発行 | REQ-041 | sales, admin |

### 出荷管理 🔵

**信頼性**: 🔵 *要件定義REQ-050〜053より*

| メソッド | パス | 説明 | 関連要件 | 権限 |
|---------|------|------|---------|------|
| GET | /shipments | 出荷指示一覧 | REQ-050 | warehouse, admin |
| POST | /shipments/{order}/complete | 出荷完了登録（在庫実減算・履歴記録） | REQ-051, REQ-072 | warehouse, admin |
| GET | /shipments/{shipment}/delivery-note | 納品書PDFダウンロード | REQ-052 | warehouse, sales, admin |
| POST | /shipments/{shipment}/return | 返品登録（在庫加算） | REQ-053 | warehouse, admin |

### 請求管理 🔵

**信頼性**: 🔵 *要件定義REQ-060〜064より*

| メソッド | パス | 説明 | 関連要件 | 権限 |
|---------|------|------|---------|------|
| GET | /invoices | 請求書一覧（入金ステータスフィルタ） | REQ-062 | accounting, admin |
| POST | /invoices/{order} | 請求書発行（採番・PDF生成 EDGE-004で二重防止） | REQ-060, REQ-064, EDGE-004 | accounting, admin |
| GET | /invoices/{invoice}/pdf | 請求書PDFダウンロード | REQ-061 | accounting, sales, admin |
| PUT | /invoices/{invoice}/payment-status | 入金ステータス手動更新 | REQ-062, REQ-064 | accounting, admin |
| GET | /payments/import | 振込データインポート画面 | REQ-063 | accounting, admin |
| POST | /payments/import | 全銀協CSVアップロード・照合実行 | REQ-063, EDGE-002 | accounting, admin |

### 在庫管理 🔵

**信頼性**: 🔵 *要件定義REQ-070〜072より*

| メソッド | パス | 説明 | 関連要件 | 権限 |
|---------|------|------|---------|------|
| GET | /inventory | 在庫一覧（現在庫・引当中・利用可能数） | REQ-070 | warehouse, admin |
| GET | /inventory/{product}/movements | 在庫変動履歴 | REQ-072 | warehouse, admin |

### 売上レポート 🔵

**信頼性**: 🔵 *要件定義REQ-080〜084より*

| メソッド | パス | 説明 | 関連要件 | 権限 |
|---------|------|------|---------|------|
| GET | /reports/sales | 売上レポート画面（月次/年次/顧客別/商品別） | REQ-080〜082, REQ-084 | sales, accounting, admin |
| GET | /reports/sales/export | レポートCSVエクスポート | REQ-083 | sales, accounting, admin |

---

## 内部APIルート（jQuery AJAX用 / `/api/internal/*`）

### 検索・サジェスト系 🔵

**信頼性**: 🔵 *設計ヒアリングQ1（jQuery採用）・REQ-011, REQ-021より*

| メソッド | パス | 説明 | 関連要件 |
|---------|------|------|---------|
| GET | /api/internal/customers/search?q={keyword} | 顧客名インクリメンタル検索（見積作成画面のオートコンプリート用） | REQ-011 |
| GET | /api/internal/products/search?q={keyword} | 製品検索（見積・受注明細追加用） | REQ-021 |

### 在庫チェック系 🔵

**信頼性**: 🔵 *EDGE-001, EDGE-010・設計ヒアリングQ6より*

| メソッド | パス | 説明 | 関連要件 |
|---------|------|------|---------|
| GET | /api/internal/products/{product}/availability?quantity={n} | 利用可能在庫チェック（受注確定前のリアルタイムバリデーション） | EDGE-001, EDGE-010 |

**レスポンス例**:
```json
{
  "success": true,
  "data": {
    "productId": 12,
    "stockQuantity": 50,
    "reservedQuantity": 20,
    "availableQuantity": 30,
    "sufficient": true
  }
}
```

### 見積・受注の動的明細操作 🟡

**信頼性**: 🟡 *見積作成のUX（行追加・削除）から妥当な推測*

| メソッド | パス | 説明 | 関連要件 |
|---------|------|------|---------|
| POST | /api/internal/quotations/calculate | 見積金額のリアルタイム計算（行追加・数量変更時） | REQ-030 |

---

## AI連携ルート（Phase 2 / `/api/ai/*`） 🟡

**信頼性**: 🟡 *ユーザストーリー8.1, 8.2・REQ-100〜104より、詳細はPhase 2設計時に確定*

| メソッド | パス | 説明 | 関連要件 |
|---------|------|------|---------|
| POST | /api/ai/chat | チャットAIへの自然言語質問 | REQ-103 |
| GET | /api/ai/forecast?productId={id} | 商品別需要予測の取得 | REQ-100 |
| GET | /api/ai/customers/{customer}/insights | 顧客購買パターン分析結果取得 | REQ-101 |
| GET | /api/ai/inventory/recommendations | 在庫最適化（発注推奨）提案取得 | REQ-102 |

**チャットAPIリクエスト例**:
```json
{
  "question": "先月の売上上位5社を教えて"
}
```

**チャットAPIレスポンス例**:
```json
{
  "success": true,
  "data": {
    "answer": "先月の売上上位5社は以下の通りです：\n1. 株式会社A（¥12,345,000）...",
    "relatedData": { "ranking": [ ... ] }
  }
}
```

**備考**: Claude APIキーは `.env` の `ANTHROPIC_API_KEY` に設定する（[prep.md](../../spec/manufacture-sales-system/prep.md)参照）。

---

## バージョニング 🟡

**信頼性**: 🟡 *将来の拡張性を考慮した妥当な推測*

内部APIは現時点でバージョニングを行わない（`/api/internal/*`）。外部公開APIを設ける場合は `/api/v1/` 形式を検討する。

## CORS設定 🔵

**信頼性**: 🔵 *本システムが社内利用のSSRアプリであることより*

外部オリジンからのアクセスを想定しないため、CORSは無効（同一オリジンのみ許可、Laravel標準のSanctum設定は不要）。

## 関連文書

- **アーキテクチャ**: [architecture.md](architecture.md)
- **型定義**: [data-types.php](data-types.php)
- **データフロー**: [dataflow.md](dataflow.md)
- **要件定義**: [requirements.md](../../spec/manufacture-sales-system/requirements.md)

## 信頼性レベルサマリー

- 🔵 青信号: 13件（76%）
- 🟡 黄信号: 4件（24%）
- 🔴 赤信号: 0件（0%）

**品質評価**: 高品質
