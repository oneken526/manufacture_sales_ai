# 認証・権限基盤（auth-rbac） TDD開発完了記録

## 確認すべきドキュメント

- `docs/tasks/manufacture-sales-system/TASK-0003.md`
- `docs/implements/manufacture-sales-system/TASK-0003/auth-rbac-requirements.md`
- `docs/implements/manufacture-sales-system/TASK-0003/auth-rbac-testcases.md`
- `docs/implements/manufacture-sales-system/TASK-0003/auth-rbac-green-phase.md`
- `docs/implements/manufacture-sales-system/TASK-0003/auth-rbac-refactor-phase.md`

## 🎯 最終結果（2026-06-07）

- **実装率**: 100%（17/17テストケース、テストケース定義書記載の13ケースを完全網羅）
- **品質判定**: ✅ 合格（高品質・完全達成）
- **TODO更新**: ✅ 完了マーク追加済み
- **全体テスト**: 60件中58件成功・2件スキップ（スキップはTASK-0002由来のMySQL専用テストでスコープ外、失敗ではない）

## 💡 重要な技術学習

### 実装パターン

- **ロール名⇔Enumマッピングの一元化**: `UserRole::routeKey()`（Enum→小文字キー）と`UserRole::fromRouteKey()`（キー→Enum）をEnum自身に実装し、`EnsureUserHasRole`ミドルウェアと`AuthenticatedSessionController`の両方から利用することで、ロール追加時の修正箇所を1箇所（Enumへのcase追加＋ルート定義＋ビュー追加）に削減できた。同種の文字列⇔Enum変換が複数箇所に必要になる場合は、変換ロジックをEnum自身に集約するのが有効。
- **ロール別リダイレクト**: `redirect()->intended($this->intendedRouteFor($role))`の形で、Laravelの`intended()`（本来アクセスしようとしていたURLへの復帰）とロール別フォールバック先を両立。ルート名規則を`{routeKey}.dashboard`に統一したことで、`route("{$role->routeKey()}.dashboard")`の1行で遷移先を解決できる。
- **無効化ユーザーの拒否**: `Auth::attempt()`成功直後に`is_active`を確認し、`false`なら`Auth::logout()`で即座に未認証へ戻してから`ValidationException`を投げる実装により、`assertGuest()`を含む厳密なテストを通過できた。

### テスト設計

- ミドルウェア・Gate・リダイレクト・hasRoleヘルパー・無効化ユーザー・セッション設定を、機能単位で6ファイルに分割してテストすることで、各ファイルが100行未満に収まり可読性・保守性が高い状態を維持できた。
- `#[DataProvider]`属性（PHPUnit 12系のアトリビュート構文）を使い、Gate拒否側（warehouse/sales）や許可側（accounting/admin）のように同種パターンの複数ロールをまとめてテストすることで、テストケース数を抑えつつ網羅性を確保した。

### 品質保証

- 既存テスト（`AuthenticationTest`）がロールベースリダイレクト導入により失敗した際、「仕様変更に伴う期待値の更新」であることを明確にコメントで残しつつ修正することで、後から見ても仕様変更の経緯が追える状態を保てた。
- セキュリティレビュー（認可・入力検証・無効化ユーザー対応・Gate定義・CSRF/XSS/SQLi）とパフォーマンスレビュー（全処理がO(1)〜O(4)の軽量処理）をRefactorフェーズで実施し、リファクタが機能・非機能面に悪影響を与えていないことを裏付けた。

## ⚠️ 注意点（後続タスクへの申し送り）

- 暫定ダッシュボードビュー（`resources/views/{admin,sales,warehouse,accounting}/dashboard.blade.php`）は最小限の表示のみであり、後続タスク（TASK-0005〜0007等）で実画面に置き換える前提。
- `InvoicePolicy`は雛形のみで、`Invoice`モデル実装後にモデル引数を取る形へ拡張が必要（`viewAny`/`view`/`create`/`update`の各アビリティは現状ロール判定のみ）。
- スキップされている2件（"Check constraints reject invalid values on mysql"、"Products reserved quantity cannot exceed stock quantity on mysql"）はTASK-0002由来のMySQL専用テストで、SQLiteテスト環境では構造上スキップされる。TASK-0003とは無関係のスコープ外事項であり、対応不要。

---
*Red/Green/Refactor各フェーズの詳細な実装コード・経過記録は `auth-rbac-green-phase.md` / `auth-rbac-refactor-phase.md` を参照*
