<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## 製造業向け販売管理システム - 開発環境セットアップ

本プロジェクトは製造業の販売管理業務（見積→受注→出荷→請求→入金確認）を管理するLaravel 13製のWebシステムです（[アーキテクチャ設計](docs/design/manufacture-sales-system/architecture.md) 参照）。

### 1. 必須環境

- PHP 8.3 / Composer
- Node.js（npm）
- MySQL（開発環境）

### 2. 初期セットアップ

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
```

### 3. データベース（MySQL）の設定

`.env`を環境に合わせて編集します（開発用データベース名は`manufacture_sales_ai`を想定）。

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=manufacture_sales_ai
DB_USERNAME=root
DB_PASSWORD=root
```

データベースを作成し、マイグレーションを実行します。

```bash
# MySQL上に manufacture_sales_ai データベースを作成しておくこと
php artisan migrate
```

#### 全テーブル作成・初期データ投入（TASK-0002 データベース設計）

業務テーブル（顧客・製品・見積・受注・出荷・請求・入金・在庫変動・採番管理）のマイグレーションとシーダーを用意しています。開発・動作確認用に、初期管理者ユーザー・サンプル顧客・サンプル製品をまとめて投入できます。

```bash
# 全テーブルを作り直して初期データを投入する（開発環境専用、既存データは削除されるので注意）
php artisan migrate:fresh --seed
```

- `database/seeders/UserSeeder.php`: 管理者（`admin@example.com` / `password`）と営業・在庫出荷・経理の各役割テストユーザーを作成（パスワードは`Hash::make()`でbcryptハッシュ化）
- `database/seeders/CustomerSeeder.php` / `ProductSeeder.php`: サンプル顧客・製品データを作成
- いずれも本番投入を想定しない開発・テスト用データです

業務区分値（ユーザー役割・見積/受注/入金ステータス等）は`app/Enums/`配下のint-backed Enum（`UserRole`, `QuotationStatus`, `OrderStatus`, `PaymentStatus`, `PaymentSource`, `StockMovementReason`, `DocumentType`）で扱います。DB上は数値コードで保持し、`docs/design/manufacture-sales-system/database-schema.sql`の区分値コード表と対応します。

### 4. フロントエンドのビルド（Vite + jQuery + Bootstrap 5）

本システムのフロントエンドはBladeテンプレート + jQuery + Bootstrap 5（`resources/js/app.js` / `resources/css/app.css`でロード）で構成されます（Breeze標準のAlpine.js/Tailwindとも共存）。

```bash
npm run dev    # 開発時（HMR）
npm run build  # 本番ビルド（public/build/ に出力）
```

### 5. 認証（Laravel Breeze）

セッションベース認証はLaravel Breeze（Bladeスタック）で構築されています。`/register`からユーザー登録、`/login`からログインが行えます。

### 6. PDF生成（mPDF）

帳票PDFの生成には`mpdf/mpdf`を使用します。設定は[config/mpdf.php](config/mpdf.php)、ラッパーサービスは[app/Services/PdfService.php](app/Services/PdfService.php)にあります。

```php
$pdf = app(\App\Services\PdfService::class);
return $pdf->download('pdf.invoice', $data, 'invoice.pdf');
```

**日本語フォントについて**: 開発環境ではWindowsにバンドルされている`meiryo.ttc`を参照する設定にしています（`config/mpdf.php`の`fontDir` / `fontdata`）。Linux等の本番環境ではWindows標準フォントを参照できないため、IPAexゴシック等の再配布可能な日本語フォントを`resources/fonts/`配下に配置し、設定のフォントパスを切り替えてください。

### 動作確認（TASK-0001 環境構築の検証手順）

```bash
# 開発サーバー起動
php artisan serve

# トップページ・認証画面の表示確認
curl -I http://127.0.0.1:8000/
curl -I http://127.0.0.1:8000/login
curl -I http://127.0.0.1:8000/register

# DB接続・マイグレーション状態の確認
php artisan migrate:status

# フロントエンドビルド確認
npm run build
```

**期待される結果**: トップページ・ログイン・登録画面がHTTP 200で表示され、`migrate:status`でusers/cache/jobsテーブルが`Ran`と表示され、`npm run build`が`public/build/`にアセットを出力して成功すること。

### パフォーマンス基準（TASK-0001 検証時の実測値）

- 開発サーバー起動時間: 約0.8秒（1秒以内）
- 開発サーバーのメモリ使用量: 約37MB
- `npm run build`所要時間: 約1秒

### 動作確認（TASK-0002 データベース設計・マイグレーションの検証手順）

```bash
# 全テーブル作成・初期データ投入（MySQL）
php artisan migrate:fresh --seed

# SQLite（テスト用 :memory:）でも実行可能であることの確認
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan migrate:fresh --env=testing

# データベーススキーマ・Enum・シーダーの単体テスト
php artisan test --filter="DatabaseSchemaTest|EnumsTest|DatabaseSeederTest"
```

**期待される結果**: 全15マイグレーション（Breeze標準3件＋業務テーブル12件相当）がエラーなく完了し、`UserSeeder`/`CustomerSeeder`/`ProductSeeder`によって初期管理者ユーザー・サンプル顧客・サンプル製品が投入されること。テストは18件中16件成功・2件スキップ（CHECK制約のDBレベル検証はMySQL接続時のみ実行されるため、SQLite実行時はスキップされる。MySQL接続に切り替えると18件全件成功）。

### パフォーマンス基準（TASK-0002 検証時の実測値）

- `migrate:fresh --seed`所要時間: 約1.5秒（MySQL）
- テストスイート実行時間: 約1.7秒（43テスト, SQLite `:memory:`）

### トラブルシューティング

#### `intl` PHP拡張に関する警告

`php artisan db:show`実行時に以下の警告が出ることがありますが、DB接続自体には影響しません（テーブルサイズ表示の数値整形にのみ影響）。

```bash
RuntimeException: The "intl" PHP extension is required to use the [format] method.
```

#### CHECK制約がSQLiteとMySQLで挙動が異なる

`database-schema.sql`で定義したCHECK制約（`chk_users_role`, `chk_products_reserved_le_stock`等）は、SQLiteが`ALTER TABLE ... ADD CONSTRAINT`によるCHECK制約の事後追加をサポートしないため、マイグレーションでは本番想定のMySQL（8.0.16+）にのみ適用しています。

```bash
# MySQL接続時のみCHECK制約を作成する実装例（database/migrations/配下）
if (DB::getDriverName() === 'mysql') {
    DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_stock CHECK (stock_quantity >= 0)');
}
```

開発・テストで使用するSQLite（`phpunit.xml`の`DB_CONNECTION=sqlite`）ではDBレベルのCHECK制約は適用されないため、業務ロジック側（モデルのバリデーション・サービス層）でも同等の整合性チェックを行う二重防御が必要です。

php.iniで`extension=intl`を有効化することで解消できます。

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
