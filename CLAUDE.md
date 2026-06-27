# 応答言語

レスポンスは必ず日本語で返すこと。

## タスク実行時のドキュメント作成

`.docs/tasks/manufacture-sales-system/TASK-XXXX.md` のタスクを実行する際は、実装・テストに加えて
`.docs/implements/manufacture-sales-system/TASK-XXXX/` フォルダに開発記録ドキュメントを作成すること
（TASK-0001〜0004の構成を参考にする）。最低限、以下を作成する。

- 要件整理（`*-requirements.md`）: 関連要件・DBスキーマ・アーキテクチャ方針・APIエンドポイント・完了条件のチェック
- テストケース一覧（`*-testcases.md`）: 単体・統合テストの内容と実行結果
- 実装記録（`*-green-phase.md` 等）: 実装方針・成果物・テスト実行結果・発生した課題と対応
- リファクタリング検討記録（`*-refactor-phase.md`）: 評価結果・後続タスクへの申し送り事項
- 開発コンテキストノート（`note.md`）: 技術スタック・開発ルール・関連実装・注意事項

tsumikiのTDDコマンド（`/tsumiki:tdd-requirements`等）を使う場合はそのフェーズごとの出力をそのまま保存し、
直接実装する場合も上記相当の内容を後追いで要約して記録すること。

### タスク完了時のマーキング

タスクの実装・テストがすべて完了したら、`.docs/tasks/manufacture-sales-system/TASK-XXXX.md` 自体を以下のように更新すること（TASK-0005の体裁を参考にする）。

- 完了条件のチェックボックスをすべて `- [x]` に変更する
- タイトル行（`# TASK-XXXX: ...`）の末尾に完了マークを追記する
  例: `# TASK-0005: 顧客マスタ管理機能 ✅ **完了** (TDD開発完了 - 7テストケース全通過)`
  （カッコ内には実装したテストケース数など、完了の根拠となる簡潔な情報を記載する）

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

