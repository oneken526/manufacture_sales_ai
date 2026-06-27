# シークレット管理方針（TASK-0017 / NFR-013）

## 概要

本システムにおけるシークレット（APP_KEY・DB認証情報・APIキー等）の管理方針を定める。

---

## 基本方針

### 1. `.env` ファイルをGitにコミットしない

- `.gitignore` に `.env` が記載されていることを確認すること（Laravel デフォルト設定済み）
- `.env.production.example` のみリポジトリに置き、実際の値は含めない

### 2. シークレットの注入方法

環境に応じて以下のいずれかを使用すること：

| 環境 | 推奨方法 |
|------|---------|
| VPS / 専用サーバー | サーバー上の `/etc/environment` または `~/.profile` に設定 |
| クラウド（AWS） | AWS Secrets Manager または Parameter Store |
| クラウド（GCP） | Google Secret Manager |
| コンテナ（Docker/K8s） | Kubernetes Secrets または Docker Secrets |
| CI/CD | GitHub Actions Secrets / GitLab CI Variables |

### 3. APP_KEY の管理

- 本番環境ごとに `php artisan key:generate` で**新規生成**すること
- 開発環境・ステージング環境の値を本番に流用しないこと
- APP_KEY を変更すると既存の暗号化セッション・クッキーが無効になるため、変更は計画的に行うこと

### 4. APP_DEBUG=false の徹底

- 本番環境では必ず `APP_DEBUG=false` を設定すること
- `true` のままでは内部エラー情報（スタックトレース・SQL・環境変数）が画面に露出する
- デプロイ前チェックリストに必ず含めること（NFR-013関連）

### 5. ログへのシークレット漏洩防止

- `config/logging.php` のログレベルを本番では `warning` 以上に設定する（`.env.production.example` の `LOG_LEVEL=warning`）
- DBクエリログを本番で有効化しないこと（認証情報・個人情報が記録される恐れあり）
- Laravelの `dontFlash` 設定（`app/Exceptions/Handler.php`）にパスワードフィールドが含まれていることを確認すること

---

## デプロイ前チェックリスト

- [ ] `APP_ENV=production` が設定されている
- [ ] `APP_DEBUG=false` が設定されている
- [ ] `APP_KEY` が本番専用の値で設定されている
- [ ] `DB_PASSWORD` が設定されている（環境変数または Secrets Manager 経由）
- [ ] `SESSION_ENCRYPT=true` が設定されている
- [ ] `SESSION_SECURE_COOKIE=true` が設定されている
- [ ] ログレベルが `warning` 以上である
- [ ] `.env` ファイルのパーミッションが `600`（所有者のみ読み書き可）である
- [ ] `ANTHROPIC_API_KEY` を使用する場合、本番用の値が設定されている
- [ ] `composer install --no-dev --optimize-autoloader` が実行されている
- [ ] `php artisan config:cache` が実行されている
- [ ] `php artisan route:cache` が実行されている
- [ ] `php artisan view:cache` が実行されている

---

## 参照要件

- **NFR-013**: セキュリティ要件（APP_DEBUG=false, ログへの機密情報漏洩防止）
- **NFR-030**: 本番DB（MySQL/PostgreSQL）使用要件
- **.env.production.example**: 本番設定テンプレート
