# TASK-0007 リファクタリング検討記録: ユーザー管理機能（管理者用）

## 1. 評価結果
- **アーキテクチャ**: Customer/Productで採用したController→Service→Repositoryパターンに対し、本機能は
  Eloquentの標準的なCRUD操作のみで完結するため、Controllerに直接`User`モデルを操作させる構成とした。
  業務ロジック（バリデーション以上の不変条件・トランザクション）が発生しないため、Service/Repository層を
  追加しても保守性向上には繋がらず、むしろ間接層が増えるだけと判断し、現状維持が適切と評価した。
- **ロール変換ロジックの再利用**: `UserRole::fromRouteKey()`/`routeKey()`をフォーム値の変換にもそのまま
  再利用でき、`EnsureUserHasRole`ミドルウェアと変換ロジックの一貫性が保たれている。重複コードなし。
- **アクセス制御**: ルーティング側の`role:admin`ミドルウェアに完全委譲しており、コントローラ・ビューでの
  二重チェックは行っていない（既存方針と一貫）。
- **自己無効化防止**: `toggleActive()`内で`$request->user()->is($user)`によるガードを実装。要件定義に
  明記がなかった項目（注意事項に記載の「チームと相談の上判断」事項）だが、運用上の事故防止のため実装する
  判断とした。

## 2. 後続タスクへの申し送り事項
- **TASK-0016（管理者向け運用機能）**: 本タスクで構築した`Admin\UserController`・ユーザー管理画面の
  基盤（ルーティング名前空間`admin.users.*`、ビュー`resources/views/admin/users/`）を踏襲・拡張する形で
  実装することを推奨する。
- **パスワードリセット再送のレート制限**: 現状`sendPasswordResetLink()`にはレート制限を設けていない
  （Breeze標準の`/forgot-password`にはスロットリングがあるが、管理者経由の再送エンドポイントには未適用）。
  運用上必要であれば`throttle`ミドルウェアの追加を検討されたい。
- **モバイル対応の検証**: UI/UX要件に「ユーザー一覧テーブルのスマートフォン幅対応」が挙げられているが、
  既存のCustomer/Product一覧と同じ`overflow-x-auto`による横スクロール方式を踏襲した。カード型レイアウトへの
  切替が必要な場合は、Customer/Product一覧と合わせて横断的に対応する方が一貫性が保てる。

## 3. 完了条件の最終確認
全完了条件を満たし、`php artisan test`（全92件、skipped 2件除く90件成功）でリグレッションのないことを確認済み。
詳細は[user-management-requirements.md](user-management-requirements.md)の完了条件チェック表を参照。
