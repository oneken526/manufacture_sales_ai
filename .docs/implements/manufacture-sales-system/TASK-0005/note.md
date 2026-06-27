# TASK-0005 開発コンテキストノート

## 1. 技術スタック
- Laravel 13 (PHP 8.4) + Eloquent ORM（SoftDeletes）
- Bladeテンプレート（`x-app-layout` / Breezeコンポーネント群）+ Tailwind CSS
- jQuery 4 + Bootstrap 5（`resources/js/app.js`でグローバル公開済み、`window.$`/`window.jQuery`）
- 参照元: composer.json, package.json, resources/js/app.js

## 2. 開発ルール
- レスポンス・コメントは日本語（CLAUDE.md）
- レイヤードアーキテクチャ: Controller → Service → Repository → Eloquent Model → DB（architecture.md）
- ロールベースアクセス制御は`EnsureUserHasRole`ミドルウェア（`role:admin,sales`等）に委譲し、
  コントローラ内でロール判定ロジックを重複させない（TASK-0003の方針を踏襲）
- DTOは`App\DataTransferObjects`配下、Repositoryは`Contracts`/`Eloquent`に分離し、
  `AppServiceProvider::register()`でインターフェースをバインドする
- テストは`RefreshDatabase`使用、PHPUnit属性ベース、
  【テスト目的】【テスト内容】【期待される動作】コメント＋信頼性レベル(🔵🟡🔴)を付与
- 参照元: app/Models/User.php, app/Http/Middleware/EnsureUserHasRole.php, app/Providers/AppServiceProvider.php,
  tests/Feature/Authorization/InvoiceGateTest.php

## 3. 関連実装（既存資産）
- `database/migrations/..._create_customers_table.php`: customersテーブル定義済み（softDeletes, idx_customers_company_name）
- `database/migrations/..._create_sales_orders_table.php`: sales_ordersテーブル定義済み（idx_sales_orders_customer_id）
- `database/seeders/CustomerSeeder.php`: 開発用初期顧客3件（updateOrInsertで冪等）
- `app/Enums/UserRole.php` / `OrderStatus.php`: ロール・受注ステータスのEnum（`label()`/`routeKey()`あり）
- `resources/views/layouts/app.blade.php`: `x-app-layout`、`@vite`でCSS/JSを読み込む構成
- 参照元: 上記各ファイル

## 4. 設計文書からの要点
- **data-types.php**: `CustomerData`DTOの定義（companyName, contactName, address, phone, email, creditLimit）が
  既に用意されており、本タスクではこれをそのまま実装した
- **architecture.md**: Repository+Serviceパターン、`Services/CustomerService.php`等の構成が明記
- **api-endpoints.md**: 顧客管理エンドポイント群・内部AJAX検索エンドポイントの一覧とロール対応表
- 参照元: .docs/design/manufacture-sales-system/{data-types.php, architecture.md, api-endpoints.md}

## 5. テスト関連情報
- `phpunit.xml`: Unit/Featureの2スイート、テスト用にSQLiteインメモリDB
- 顧客・受注のFactoryが存在しなかったため、本タスクで`CustomerFactory`/`SalesOrderFactory`を新規作成した
- 参照元: phpunit.xml, database/factories/UserFactory.php

## 6. 注意事項・申し送り
- **SalesOrderモデルが本タスク以前に未実装だった**ため、`salesOrders`リレーション・受注存在チェック・
  受注履歴表示を成立させるために最小限の`App\Models\SalesOrder`を新規作成した（詳細はTASK-0009で拡充予定、
  [customer-master-refactor-phase.md](customer-master-refactor-phase.md)参照）
- **Viteビルド成果物が無い状態だとFeatureテストが500エラーになる**: `@vite`を含むBladeをレンダリングする
  Featureテストを実行する前に`npm install && npm run build`が必要（本タスクで`public/build/`を生成済み）
- **開発用DBへの直接操作は要注意**: `.env`の`DB_CONNECTION=mysql`は開発用DB（manufacture_sales_ai）を指しており、
  `php artisan tinker`等での動作確認時に既存シードデータを誤って変更・削除しないよう注意する
  （本タスクでは誤って削除したユーザーをシーダー再実行で復旧した）
- 参照元: vite.config.js, .env, database/seeders/{UserSeeder,CustomerSeeder}.php
