# TASK-0001 設定作業実行

## 作業概要

- **タスクID**: TASK-0001
- **作業内容**: プロジェクト基盤構築・開発環境セットアップ（Laravel Breeze認証scaffold、MySQL開発DB、mPDF、Vite + jQuery + Bootstrap 5）
- **実行日時**: 2026-06-07
- **実行者**: Claude Code（DIRECTタスク自動実行）

## 設計文書参照

- **参照文書**: [overview.md](../../../tasks/manufacture-sales-system/overview.md), [architecture.md](../../../design/manufacture-sales-system/architecture.md), [database-schema.sql](../../../design/manufacture-sales-system/database-schema.sql), [TASK-0001.md](../../../tasks/manufacture-sales-system/TASK-0001.md)
- **関連要件**: NFR-030（DB切替考慮）, REQ-002/003/064（認証・権限）, NFR-010〜013（セキュリティ）, REQ-100〜104（Phase 2 AI連携）

## 実行した作業

### 1. プロジェクト基盤確認

既存のLaravel 13.14（PHP 8.3, `laravel/framework: ^13.8`要件を満たす）プロジェクトであることを確認。`composer.json`は既にLaravel 13.8系の要件を満たしていたため変更不要。

### 2. Laravel Breeze インストール・認証scaffold生成

```bash
composer require laravel/breeze --dev -W
php artisan breeze:install blade --no-interaction
```

- Bladeスタック（jQueryと共存可能、SPA向けスタックは未選択）でscaffoldを生成
- ログイン・新規登録・パスワードリセット・メール確認・プロフィール編集の各画面、`routes/auth.php`、`app/Http/Controllers/Auth/*`、`app/Models/User.php`が生成されたことを確認

### 3. MySQL開発DB設定・マイグレーション実行

`.env`は事前にユーザーによりMySQL接続情報（`DB_CONNECTION=mysql`、`DB_DATABASE=manufacture_sales_ai`等）に変更済みであったため、接続確認とマイグレーション実行のみ実施。

```bash
php artisan migrate:status
php artisan migrate --force
```

**結果**: `users` / `cache` / `jobs` テーブルが作成され、MySQL（MariaDB 12.0.2）への疎通を確認。

### 4. mPDF（mpdf/mpdf）パッケージ導入

```bash
composer require mpdf/mpdf -W
```

- `config/mpdf.php` を新規作成し、`fontDir`にWindowsの日本語フォントディレクトリ（`C:\Windows\Fonts`）を追加、`meiryo`フォント（`meiryo.ttc` / TTCコレクション）を`fontdata`に登録、`default_font`に設定
- `app/Services/PdfService.php`（mPDFラッパー、`fromView()` / `download()`）を新規作成
- 確認用テンプレート `resources/views/pdf/sample.blade.php` と一時ルート `GET /dev/pdf-sample`（`routes/web.php`）を追加し、日本語を含むPDFが正常に生成・ダウンロードできることを確認（生成サイズ約59.7KB、PDF 1.4 / 1ページ）

### 5. Vite + jQuery + Bootstrap 5 のフロントエンド環境構築

```bash
npm install jquery bootstrap @popperjs/core
npm run build
```

- `resources/js/app.js`にjQuery・Bootstrapをimportし、`window.$` / `window.jQuery` / `window.bootstrap`にアタッチ（Breeze標準のAlpine.jsはナビゲーション等のコンポーネントで使用中のため共存させた）
- `resources/css/app.css`に`bootstrap/dist/css/bootstrap.min.css`を`@import`で追加
- 確認用ビュー `resources/views/dev/frontend-check.blade.php` と一時ルート `GET /dev/frontend-check` を追加し、jQueryのイベントハンドラからBootstrapのアラートコンポーネントを描画できることを確認
- `npm run build`が成功し、`public/build/`配下にアセットが生成されることを確認

### 6. .env.example整備、Phase 2向け環境変数プレースホルダー追加

- `DB_CONNECTION`をsqlite設定からMySQL設定（`mysql` / `127.0.0.1` / `3306` / `manufacture_sales_ai`）に変更
- Phase 2のAI機能（REQ-100〜REQ-104）に向けて`ANTHROPIC_API_KEY` / `ANTHROPIC_API_MODEL`のプレースホルダー（コメント付き、値は空）を追加

## 作業結果（完了条件チェック）

- [x] `php artisan serve`でアプリケーションが起動し、トップページが表示されること（HTTP 200確認）
- [x] Laravel Breezeのログイン画面・登録画面が表示され、ユーザー登録〜ログインが行えること（HTTP POST → 302リダイレクト → ダッシュボード200を確認、確認用ユーザーは削除済み）
- [x] `npm run build`（Vite）でフロントエンドアセットのビルドが成功すること
- [x] jQuery・Bootstrap 5がページ上で読み込まれ、動作すること（`/dev/frontend-check`で確認）
- [x] mPDFパッケージを用いて簡易なPDFファイルが生成・ダウンロードできること（`/dev/pdf-sample`で日本語PDF生成を確認）
- [x] MySQL開発用データベース（`manufacture_sales_ai`）が作成され、`.env`の接続設定で疎通できること（マイグレーション実行により確認）
- [x] `.env.example`が整備され、Phase 2向け（Claude API等）の環境変数プレースホルダーが追加されていること

## 遭遇した問題と解決方法

### 問題1: TASK-0001.mdの記載がSQLite前提だった

- **発生状況**: タスク文書では開発DBをSQLiteとする記載だったが、ユーザーが`.env`を事前にMySQL（`manufacture_sales_ai`）に変更済みであった
- **解決方法**: ユーザー指示に基づきタスク文書（TASK-0001.md）の該当箇所をMySQL前提の記載に修正した上で、本タスクもMySQL接続で環境構築・確認を実施した

### 問題2: mPDFに日本語フォントがバンドルされていない

- **発生状況**: `mpdf/mpdf`パッケージにはCJK（日本語）フォントが含まれておらず、デフォルト設定では日本語が文字化けする
- **解決方法**: 開発機（Windows）にインストール済みの`meiryo.ttc`（TrueType Collection）を`config/mpdf.php`の`fontDir` / `fontdata`に登録し、`TTCfontID`でコレクション内のフォントを指定することで日本語PDF生成を実現した。**注意**: 本番環境（Linux等）ではWindows標準フォントを参照できないため、`mpdf.php`の設定を、再配布可能な日本語フォント（例: IPAexゴシック）を`resources/fonts/`等に同梱する方式に切り替える必要がある（後続タスクでの対応を推奨）

### 問題3: BreezeのデフォルトUIスタック（Tailwind/Alpine）とアーキテクチャ指定（Bootstrap 5/jQuery）の併存

- **発生状況**: Breeze Bladeスタックは標準でTailwind CSS・Alpine.jsを使用するが、architecture.mdではBootstrap 5・jQueryが指定されている
- **解決方法**: 本タスクでは認証画面等のBreeze標準コンポーネント（ドロップダウン・モーダル等）がAlpine.jsに依存しているため共存させ、jQuery・Bootstrap 5をアプリケーション側で利用できるよう追加導入するに留めた。Tailwindとの併用によるスタイル競合の整理、および既存Breeze画面のBootstrap 5への置き換えはTASK-0003（UI/UXカスタマイズ）で対応する想定

## 次のステップ

- `/tsumiki:direct-verify` を実行して設定を確認
- 動作確認用の一時ルート（`/dev/pdf-sample`, `/dev/frontend-check`）は完了条件確認後に削除またはコメントアウトする
- TASK-0002（データベース設計・全マイグレーション作成）に進む
