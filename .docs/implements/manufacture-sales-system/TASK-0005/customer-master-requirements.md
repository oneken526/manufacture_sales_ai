# TASK-0005: 顧客マスタ管理機能 - 要件整理

## 1. 機能の目的
顧客（取引先）マスタのCRUD一式（一覧・登録・編集・詳細・削除・検索）を、Repository+Serviceパターンに従い
DB（customersテーブル）からBlade画面までを縦に貫通して実装する。
営業業務フロー（見積・受注作成時の顧客選択、顧客別売上集計等）の土台となる。

参照元: [TASK-0005.md](../../../tasks/manufacture-sales-system/TASK-0005.md)

## 2. 関連要件（requirements.md）
- REQ-010: 顧客情報（会社名・担当者名・住所・電話・メール・与信枠）の登録・編集・削除ができること 🔵
- REQ-011: 顧客を会社名・担当者名・電話番号で検索できること 🟡（部分一致は妥当な推測）
- REQ-012: 受注が存在する顧客の削除を禁止し警告を表示すること 🟡
- REQ-013: 顧客ごとの受注履歴を一覧表示できること 🟡
- NFR-021: 一覧画面は1ページ50件でページネーションすること 🔵
- NFR-013: 検索処理はクエリビルダを用いSQLインジェクションを防止すること

## 3. DBスキーマ（database-schema.sql）
`customers`テーブル: id, company_name, contact_name, address, phone, email, credit_limit(BIGINT),
created_at/updated_at, deleted_at（ソフトデリート）, idx_customers_company_name インデックス

`sales_orders`テーブル: customer_id（外部キー）, idx_sales_orders_customer_id インデックス
→ 削除時の受注存在チェックはこのインデックスを用いた存在確認（EXISTS）で行う

## 4. アーキテクチャ方針（architecture.md）
- レイヤ構成: Controller → Service → Repository → Eloquent Model → DB
- 業務ロジック（CRUD・検索・削除可否判定）は `CustomerService` に集約する
- データアクセスは `CustomerRepositoryInterface` / `CustomerRepository`（Eloquent実装）に抽象化し、
  テスト時にモック化しやすくする
- Controller⇔Service間のデータ受け渡しは `CustomerData` DTO（data-types.phpに定義済み）を用いる

## 5. APIエンドポイント（api-endpoints.md）
| メソッド | パス | 権限 |
|---|---|---|
| GET | /customers | sales, accounting, admin |
| GET /POST | /customers/create, /customers | sales, admin |
| GET | /customers/{customer} | sales, accounting, admin |
| GET /PUT | /customers/{customer}/edit, /customers/{customer} | sales, admin |
| DELETE | /customers/{customer} | admin のみ |
| GET | /api/internal/customers/search?q={keyword}（jQueryインクリメンタルサーチ用） | sales, accounting, admin |

ロール制御は `EnsureUserHasRole` ミドルウェア（`role:xxx,yyy`）に委譲し、コントローラ内でのロール判定は行わない。

## 6. 完了条件（TASK-0005.md）
- [x] Customerモデル・CustomerRepository（IF+Eloquent実装）・CustomerServiceでCRUD操作が可能
- [x] CustomerControllerにより一覧・登録・編集・詳細・削除の各画面/処理が動作する
- [x] 一覧画面が50件ページネーションで表示される（NFR-021）
- [x] 会社名・担当者名・電話番号の部分一致検索ができる（REQ-011）
- [x] 受注が存在する顧客の削除が拒否され、警告メッセージが表示される（REQ-012）
- [x] 削除はソフトデリート（deleted_at）として記録される
- [x] 顧客詳細画面に受注履歴一覧が表示される（REQ-013）
- [x] Blade画面（一覧/登録/編集/詳細）+ jQueryインクリメンタルサーチが動作する
- [x] バリデーションエラーが画面に表示される
- [x] 単体テスト・統合テストがすべて成功する
