# 応答言語

レスポンスは必ず日本語で返すこと。

## 開発コマンド

### テスト実行
```bash
# すべてのテストを実行
php artisan test

# 特定のテストファイルを実行
php artisan test --filter=ExampleTest

# データベーススキーマ・Enum・シーダーのテスト（TASK-0002）
php artisan test --filter="DatabaseSchemaTest|EnumsTest|DatabaseSeederTest"
```

### アプリケーション実行
```bash
# 開発サーバー起動
php artisan serve

# フロントエンド開発ビルド（HMR）
npm run dev

# フロントエンド本番ビルド（public/build/に出力）
npm run build
```

### データベース操作
```bash
# マイグレーション実行（MySQL: manufacture_sales_ai を使用）
php artisan migrate

# マイグレーション状態確認
php artisan migrate:status

# シードデータ投入
php artisan db:seed

# 全テーブルを作り直して初期データを投入（開発環境専用、既存データは削除される）
php artisan migrate:fresh --seed
```

