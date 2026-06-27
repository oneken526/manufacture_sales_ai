# TASK-0007 要件整理: ユーザー管理機能（管理者用）

## 1. 関連要件
- REQ-002: システムは4つの役割（システム管理者・営業担当者・在庫出荷担当者・管理職経理担当）を持たなければならない
- REQ-003: 役割に応じたアクセス拒否方針（warehouseは請求書操作不可 等）
- REQ-004: システム管理者はユーザーの作成・編集・無効化ができなければならない
- REQ-005: システムはパスワードリセット機能を提供しなければならない
- NFR-021: 一覧画面はページネーション1ページ50件

## 2. DBスキーマ（usersテーブル抜粋）
- `role` TINYINT（`UserRole` Enum: 1=admin, 2=sales, 3=warehouse, 4=accounting）
- `is_active` BOOLEAN NOT NULL DEFAULT TRUE
- `password` は bcrypt ハッシュ化（NFR-010、Laravel標準）

## 3. アーキテクチャ方針
- レイヤー: Route(`role:admin`ミドルウェア) → Controller → Eloquent Model（本タスクでは単純なCRUDのためService/Repository層は設けず、
  TASK-0005/0006のCustomer/Productほど業務ロジックが複雑でないことから、Controllerに直接Eloquentを利用する方針とした）
- ロールベースアクセス制御は既存の`EnsureUserHasRole`ミドルウェア（`role:admin`）に委譲し、コントローラ内でロール判定を重複させない
- ロール⇔キー変換は`UserRole::routeKey()`/`UserRole::fromRouteKey()`に集約済みのものを再利用する
- `is_active`によるログイン拒否、Breeze標準パスワードリセットは TASK-0003 で実装済み（本タスクでは流用・確認のみ）

## 4. APIエンドポイント（ルーティング）
| メソッド | パス | 名前 | 説明 |
|---|---|---|---|
| GET | /admin/users | admin.users.index | ユーザー一覧（ページネーション50件） |
| GET | /admin/users/create | admin.users.create | 新規作成フォーム |
| POST | /admin/users | admin.users.store | 新規作成処理 |
| GET | /admin/users/{user}/edit | admin.users.edit | 編集フォーム |
| PUT | /admin/users/{user} | admin.users.update | 更新処理 |
| PATCH | /admin/users/{user}/toggle-active | admin.users.toggle-active | 有効/無効切替 |
| POST | /admin/users/{user}/send-password-reset | admin.users.send-password-reset | パスワード再設定メール再送 |

いずれも`['auth', 'verified', 'role:admin']`ミドルウェア配下に配置。

## 5. 完了条件チェック
- [x] UserController（管理者専用）が実装され、ユーザーの作成・編集・無効化が可能であること
- [x] `is_active`フラグによりユーザーの有効/無効を切り替えられること
- [x] 無効化（`is_active=false`）されたユーザーがログインを拒否されること（TASK-0003実装済み・回帰テストで確認）
- [x] admin以外のロールのユーザーがユーザー管理画面にアクセスできないこと（REQ-002, アクセス拒否）
- [x] パスワードリセットメール送信フローが実装され、Laravel Breeze標準機能を活用していること
- [x] 役割選択ドロップダウン・無効化確認ダイアログを含むBlade画面が実装されていること
- [x] バリデーションエラーが画面に適切に表示されること
- [x] 単体テスト・統合テストがすべて成功すること
