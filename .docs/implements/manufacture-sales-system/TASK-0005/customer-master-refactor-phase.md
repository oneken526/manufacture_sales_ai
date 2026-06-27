# TASK-0005: 顧客マスタ管理機能 - リファクタリング検討記録

## 現状の評価
- レイヤ構成（Controller → Service → Repository → Model）はarchitecture.mdの方針通りに分離されており、
  Service層が`CustomerRepositoryInterface`に依存することでテスト容易性を確保している
- バリデーション・ロール制御・例外処理は責務ごとに適切なレイヤ（FormRequest／ルートミドルウェア／Service+専用例外）に配置済み
- 重複コードや複雑な条件分岐は見当たらず、現時点で大きなリファクタリングは不要と判断した

## 今後の課題・申し送り事項（後続タスクへの引き継ぎ）
1. **`App\Models\SalesOrder`の拡充（TASK-0009）**
   本タスクでは顧客詳細・削除制限機能に必要な最小構成（fillable, casts, `customer()`belongsTo）のみ実装した。
   TASK-0009.mdの実装詳細にある`quotation`/`items`/`createdBy`リレーションやステータス用スコープ等は
   TASK-0009側で追加されることを前提としている。本タスクで追加した内容と矛盾しないよう、
   既存の`fillable`属性・casts・`customer()`を維持しつつ拡張すること。

2. **受注詳細画面へのリンク（REQ-013関連）**
   `customers/show.blade.php`の受注履歴テーブルでは、受注詳細画面（OrderController管轄、別タスク）への
   リンクをプレースホルダ（受注番号のテキスト表示のみ）としている。OrderController実装後、
   `route('orders.show', $order)`等への差し替えが必要。

3. **検索結果の件数が多い場合の表示**
   現状は受注履歴を全件Eagerロードして新しい順に表示している（REQ-013「件数が多い場合はページネーション
   または直近N件表示」は将来的な検討事項として残した）。データ量増加時はページネーションまたは
   `latest()->limit(N)`への変更を検討する。

4. **内部API `/api/internal/customers/search` のレスポンス整形**
   現状`CustomerController::searchJson()`は最小限のフィールド（id, company_name, contact_name, phone）のみ返す。
   見積作成画面（TASK-0008）のオートコンプリートで追加項目が必要になった場合は、レスポンス形式の
   調整（および専用Resourceクラスの導入）を検討する。

## 結論
現時点でのリファクタリングは不要。上記の申し送り事項を後続タスク（TASK-0008, TASK-0009）の実装時に
参照できるよう本ドキュメントに記録した。
