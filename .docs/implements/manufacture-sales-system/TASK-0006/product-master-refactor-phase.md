# TASK-0006: 製品マスタ管理機能 - リファクタリング検討記録

## 評価結果
- レイヤー構成（Controller → Service → Repository → Model）はTASK-0005（顧客マスタ）と一貫しており、
  既存実装からの逸脱や重複ロジックは見られない
- `availableQuantity()` / `isLowStock()` は `Product` モデルの生データから直接計算するシンプルな実装とし、
  `ProductData` DTO側にも同名メソッドを用意することで、設計文書（data-types.php）の定義との整合を保った
  （Controller⇔Service間のDTO変換と、Service内でのモデル操作の両方から再利用可能）
- 在庫調整の整合性検証（`reserved_quantity <= stock_quantity`、`stock_quantity >= 0`）は、
  競合状態（同時調整によるレース）を避けるため`lockForUpdate()`を伴うリポジトリ層に集約した。
  Service層は「製品の存在確認 → リポジトリへの委譲 → 結果の整形（在庫数のみ返却）」という薄い責務に留め、
  業務ロジックの起点として`StockMovementReason::MANUAL_ADJUSTMENT`の指定を一元化している
- 例外設計は`CustomerHasOrdersException`の前例（`RuntimeException`継承、識別子をpublic readonlyで保持）に倣い、
  `StockAdjustmentViolatesIntegrityException`として実装した。コントローラ側で捕捉し、
  「調整後の在庫数が引当中数量を下回るため実行できません」というユーザー向けメッセージにそのまま変換している

## 後続タスクへの申し送り事項
1. **TASK-0008（見積作成・受注確定）**: `ProductService::availableQuantity()`をそのまま再利用できる。
   受注確定時の在庫引当（`reserved_quantity`加算）は本タスクの`adjustStock()`とは別経路（`reason=1`）で
   実装される想定のため、`ProductRepository`に引当専用メソッドを追加する場合は
   `adjustStock()`の行ロック・トランザクション設計を踏襲することを推奨する
2. **TASK-0010（出荷・返品）**: `stock_quantity`と`reserved_quantity`を同時に更新する操作（出荷完了・返品）が
   発生するため、`ProductRepository`に汎用的な「在庫増減＋履歴記録」のヘルパーを切り出す余地がある。
   現状は`adjustStock()`が手動調整専用（`reason`を引数で受け取る設計にはしてある）のため、
   将来的な共通化の際はこのメソッドの拡張を検討してよい
3. **TASK-0011（在庫管理機能）**: `StockMovement`モデル・`stock_movements`テーブルへのリレーションは
   本タスクで実装済み（`product()`/`relatedOrder()`/`operator()`、`reason`のEnumキャスト）。
   `InventoryController::movements()`はこのモデルをそのまま利用できる。
   `GET /inventory/{product}/movements`ルート自体はTASK-0011側の責務として未実装のままとした
4. **製品検索のインクリメンタルサーチ（jQuery）**: TASK-0005の`customers-search.js`に相当する
   AJAX検索は、見積・受注の明細選択UI（TASK-0008）で必要になった際に
   `/api/internal/products/search`エンドポイントとあわせて実装するのが効率的
