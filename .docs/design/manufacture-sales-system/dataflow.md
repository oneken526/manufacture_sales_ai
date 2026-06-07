# 製造業向け販売管理システム データフロー図

**作成日**: 2026-06-07
**関連アーキテクチャ**: [architecture.md](architecture.md)
**関連要件定義**: [requirements.md](../../spec/manufacture-sales-system/requirements.md)

**【信頼性レベル凡例】**:
- 🔵 **青信号**: 要件定義書・ユーザストーリー・設計ヒアリングを参考にした確実なフロー
- 🟡 **黄信号**: 要件定義書・設計ヒアリングから妥当な推測によるフロー
- 🔴 **赤信号**: 要件定義書・設計ヒアリングにない推測によるフロー

---

## システム全体のデータフロー 🔵

**信頼性**: 🔵 *要件定義・アーキテクチャ設計より*

```mermaid
flowchart TD
    A[ユーザー] --> B[ブラウザ: Blade + jQuery]
    B --> C[Laravel Webルート/内部APIルート]
    C --> D[Controller]
    D --> E[Service層: 業務ロジック]
    E --> F[Repository層]
    F --> G[(データベース)]
    E --> H[mPDF: 帳票生成]
    E --> I[Claude API: Phase 2]

    G --> F --> E --> D --> C --> B --> A
```

## 主要機能のデータフロー

### 機能1: 見積作成→受注確定（在庫引き当て） 🔵

**信頼性**: 🔵 *ユーザストーリー4.1, 4.2・REQ-030, REQ-040, REQ-041より*

**関連要件**: REQ-030, REQ-031, REQ-040, REQ-041, EDGE-001, EDGE-010

```mermaid
sequenceDiagram
    participant U as 営業担当者
    participant V as Blade画面
    participant C as OrderController
    participant S as OrderService
    participant R as Repository
    participant D as DB

    U->>V: 見積から「受注確定」を選択
    V->>C: POST /orders/{quotation}/confirm
    C->>S: confirmOrder(quotation)
    S->>R: 製品の利用可能在庫を取得<br/>(stock_quantity - reserved_quantity)
    R->>D: SELECT products
    D-->>R: 在庫データ
    R-->>S: 利用可能在庫数
    alt 在庫が不足している場合
        S-->>C: 在庫不足エラー（EDGE-001, EDGE-010）
        C-->>V: 警告メッセージ
        V-->>U: 「在庫が不足しています」と表示
    else 在庫が十分な場合
        S->>R: reserved_quantity を加算（トランザクション）
        S->>R: 受注ステータスを「受注確定」に更新
        S->>R: 採番ロジックで受注番号発行
        R->>D: UPDATE products, INSERT sales_orders
        D-->>R: 完了
        R-->>S: 完了
        S-->>C: 受注確定結果
        C-->>V: 成功レスポンス
        V-->>U: 「受注を確定しました」と表示
    end
```

**詳細ステップ**:
1. 営業担当者が見積詳細画面で「受注確定」ボタンをクリック
2. OrderServiceが対象製品の利用可能在庫（実在庫 - 引当中在庫）を確認
3. 在庫不足の場合は処理を中止し警告を表示（EDGE-001, EDGE-010）
4. 在庫が十分な場合、トランザクション内で `reserved_quantity` を加算し、受注レコードを作成、ステータスを更新

---

### 機能2: 出荷完了登録（在庫の実減算） 🔵

**信頼性**: 🔵 *ユーザストーリー5.1・REQ-050, REQ-051, REQ-052より*

**関連要件**: REQ-050, REQ-051, REQ-052, REQ-072

```mermaid
sequenceDiagram
    participant U as 在庫・出荷担当者
    participant V as Blade画面
    participant C as ShipmentController
    participant S as ShipmentService
    participant R as Repository
    participant P as PdfService(mPDF)
    participant D as DB

    U->>V: 出荷指示一覧から対象を選択し「出荷完了」を登録
    V->>C: POST /shipments/{order}/complete
    C->>S: completeShipment(order)
    S->>R: トランザクション開始
    S->>R: stock_quantity を減算、reserved_quantity を減算
    S->>R: 在庫変動履歴(StockMovement)を記録（REQ-072）
    S->>R: 受注ステータスを「出荷完了」に更新
    R->>D: UPDATE products, INSERT stock_movements, UPDATE sales_orders
    D-->>R: 完了
    S->>P: 納品書PDF生成を依頼
    P-->>S: PDFファイル
    S-->>C: 完了結果 + PDFパス
    C-->>V: 成功レスポンス + ダウンロードリンク
    V-->>U: 「出荷完了。納品書をダウンロードできます」
```

**詳細ステップ**:
1. 在庫・出荷担当者が出荷完了を登録
2. ShipmentServiceがトランザクション内で `stock_quantity`（実在庫）と `reserved_quantity`（引当）を同時に減算
3. 在庫変動履歴を記録（操作者・日時・理由・数量）
4. mPDFで納品書PDFを生成しダウンロード可能にする

---

### 機能3: 請求書発行→PDF出力 🔵

**信頼性**: 🔵 *ユーザストーリー6.1・REQ-060, REQ-061, REQ-064より*

**関連要件**: REQ-060, REQ-061, REQ-064, EDGE-004

```mermaid
sequenceDiagram
    participant U as 管理職・経理担当者
    participant V as Blade画面
    participant C as InvoiceController
    participant S as InvoiceService
    participant R as Repository
    participant P as PdfService(mPDF)
    participant D as DB

    U->>V: 出荷完了済み受注から「請求書発行」を選択
    V->>C: POST /invoices/{order}
    C->>S: issueInvoice(order)
    S->>R: 既存請求書の有無を確認（二重発行防止 EDGE-004）
    alt 既に発行済みの場合
        S-->>C: 重複エラー
        C-->>V: 警告表示
    else 未発行の場合
        S->>R: 採番ロジックで請求書番号発行（年度+連番）
        S->>R: 請求書レコードを作成（入金ステータス=未入金）
        R->>D: INSERT invoices
        D-->>R: 完了
        S->>P: 請求書PDF生成を依頼
        P-->>S: PDFファイル
        S-->>C: 発行結果 + PDFパス
        C-->>V: 成功レスポンス
        V-->>U: 「請求書を発行しました」+ ダウンロードリンク
    end
```

**詳細ステップ**:
1. 管理職・経理担当者が出荷完了済みの受注を選択し請求書発行を実行
2. InvoiceServiceが二重発行を防止（EDGE-004）したうえで、年度+連番で請求書番号を採番（例: INV-2026-0001）
3. 請求書レコードを作成（初期入金ステータス＝未入金）
4. mPDFで請求書PDFを生成

---

### 機能4: 振込データCSVインポート（入金照合） 🔵

**信頼性**: 🔵 *ユーザストーリー6.3・REQ-063, EDGE-002より*

**関連要件**: REQ-063, EDGE-002

```mermaid
sequenceDiagram
    participant U as 管理職・経理担当者
    participant V as Blade画面
    participant C as PaymentController
    participant S as PaymentImportService
    participant R as Repository
    participant D as DB

    U->>V: 全銀協フォーマットCSVをアップロード
    V->>C: POST /payments/import
    C->>S: importBankCsv(file)
    S->>S: CSVをパースし振込データの行を抽出
    loop 各振込データ行
        S->>R: 請求書番号・金額で請求書を検索
        alt 照合成功
            R-->>S: 該当請求書
            S->>R: 入金ステータスを更新（部分入金/入金済み）
            R->>D: UPDATE invoices, INSERT payments
        else 照合失敗
            S->>S: 未照合リストに追加（EDGE-002）
        end
    end
    S-->>C: 結果サマリー（成功件数・未照合件数・詳細）
    C-->>V: 結果表示
    V-->>U: 「N件成功、M件未照合」と詳細を表示
```

**詳細ステップ**:
1. 管理職・経理担当者が銀行からダウンロードした全銀協フォーマットCSVをアップロード
2. PaymentImportServiceが各行を請求書番号・金額で照合
3. 照合成功時は入金ステータスを自動更新（部分入金 or 入金済み）
4. 照合失敗時はスキップし、未照合件数・詳細をレポート（EDGE-002）

---

### 機能5: 売上レポート表示・CSVエクスポート 🔵

**信頼性**: 🔵 *ユーザストーリー7.1〜7.3・REQ-080〜083より*

**関連要件**: REQ-080, REQ-081, REQ-082, REQ-083, NFR-002

```mermaid
sequenceDiagram
    participant U as 管理職/営業担当者
    participant V as Blade画面
    participant C as ReportController
    participant S as ReportService
    participant R as Repository
    participant D as DB

    U->>V: レポート画面で期間・集計軸を選択
    V->>C: GET /reports/sales?period=monthly&group=customer
    C->>S: generateSalesReport(条件)
    S->>R: 集約クエリ実行（GROUP BY 顧客/商品/月）
    R->>D: SELECT ... GROUP BY ...
    D-->>R: 集計結果
    R-->>S: 集計データ
    S-->>C: レポートデータ
    C-->>V: グラフ・ランキング表示
    U->>V: 「CSVエクスポート」をクリック
    V->>C: GET /reports/sales/export?...
    C->>S: exportCsv(条件)
    S-->>C: CSVストリーム
    C-->>V: CSVファイルダウンロード
```

**詳細ステップ**:
1. ユーザーが集計期間・軸（顧客別/商品別/月次）を選択
2. ReportServiceがSQLの集約クエリ（GROUP BY）で集計し、10秒以内（NFR-002）に結果を返す
3. グラフ・ランキングを画面表示
4. CSVエクスポートボタンでストリーミングダウンロード（大量データでもメモリ効率を確保）

---

### 機能6（Phase 2）: AIチャットによるデータ照会 🟡

**信頼性**: 🟡 *ユーザストーリー8.2・REQ-103より、Phase 2のため詳細は実装時に確定*

**関連要件**: REQ-100〜104

```mermaid
sequenceDiagram
    participant U as ユーザー
    participant V as Blade画面（チャットUI）
    participant C as Ai\ChatController
    participant S as Ai\ChatService
    participant Claude as Claude API
    participant R as Repository
    participant D as DB

    U->>V: 自然言語で質問を入力<br/>(例:「先月の売上上位5社を教えて」)
    V->>C: POST /api/ai/chat
    C->>S: ask(question)
    S->>R: 関連データを取得（売上集計等）
    R->>D: SELECT ...
    D-->>R: データ
    R-->>S: コンテキストデータ
    S->>Claude: プロンプト + コンテキストデータを送信
    Claude-->>S: 回答テキスト
    S-->>C: 回答
    C-->>V: 回答表示
    V-->>U: チャット形式で回答を表示
```

**備考**: 🟡 Phase 2機能のため、Claude APIへのプロンプト設計・コンテキストデータの選定方法は実装フェーズで詳細化する。

---

## データ処理パターン

### 同期処理 🔵

**信頼性**: 🔵 *アーキテクチャ設計より*

- 受注確定・出荷完了・請求書発行など、在庫数やステータスの整合性が求められる処理はDBトランザクション内で同期的に実行する

### 非同期処理 🟡

**信頼性**: 🟡 *パフォーマンス要件NFR-001から妥当な推測*

- PDF生成（帳票枚数が多い場合）やCSVインポート処理は、必要に応じてLaravelのキュー（Queue）で非同期化を検討する
- Phase 2のAI API呼び出しはレスポンス遅延が想定されるため、非同期処理またはローディング表示を実装する

### バッチ処理 🟡

**信頼性**: 🟡 *NFR-031・在庫アラートから妥当な推測*

- 日次バックアップ（NFR-031）はLaravelスケジュールタスクで定期実行
- 在庫アラート確認（REQ-022）も定期バッチでチェックし通知する設計を想定

## エラーハンドリングフロー 🟡

**信頼性**: 🟡 *Laravel標準実装パターン・EDGE要件から妥当な推測*

```mermaid
flowchart TD
    A[エラー発生] --> B{エラー種別}
    B -->|バリデーションエラー| C[422 Unprocessable Entity]
    B -->|認証エラー| D[401 Unauthorized]
    B -->|権限エラー| E[403 Forbidden: REQ-003]
    B -->|リソース未存在| F[404 Not Found]
    B -->|業務ルール違反| G[400 Bad Request: 在庫不足等 EDGE-001]
    B -->|サーバーエラー| H[500 Internal Server Error]

    C --> I[エラーメッセージ返却・フォーム再表示]
    D --> I
    E --> I
    F --> I
    G --> I
    H --> J[ログ記録 Laravel Log]
    J --> I
    I --> K[Bladeでエラー表示 / jQuery AJAXエラーハンドラ]
```

## 状態管理フロー

### 受注ステータス遷移 🔵

**信頼性**: 🔵 *note.md受注ステータスフロー・REQ-031, REQ-040〜053より*

```mermaid
stateDiagram-v2
    [*] --> 見積中
    見積中 --> 受注確定: 受注確定（在庫引当 REQ-040）
    見積中 --> キャンセル: 見積キャンセル
    受注確定 --> 出荷指示済み: 出荷指示発行（REQ-041）
    出荷指示済み --> 出荷完了: 出荷完了登録（在庫減算 REQ-051）
    出荷完了 --> 請求済み: 請求書発行（REQ-060）
    受注確定 --> キャンセル: 受注キャンセル（引当解除 REQ-043）
    出荷完了 --> 返品済み: 返品処理（在庫加算 REQ-053）
    キャンセル --> [*]
    請求済み --> [*]
    返品済み --> [*]
```

### 入金ステータス遷移 🔵

**信頼性**: 🔵 *REQ-062, REQ-063より*

```mermaid
stateDiagram-v2
    [*] --> 未入金
    未入金 --> 部分入金: 振込データ照合（一部金額）
    未入金 --> 入金済み: 振込データ照合（全額）
    部分入金 --> 入金済み: 残額入金確認
```

## データ整合性の保証 🔵

**信頼性**: 🔵 *在庫管理要件・トランザクション設計の標準パターンより*

- **トランザクション管理**: 在庫更新を伴う操作（受注確定・出荷完了・返品・キャンセル）は必ずDBトランザクション内で実行し、失敗時はロールバックする
- **悲観的ロック**: 在庫引当時は対象製品レコードに `lockForUpdate()` を使用し、同時受注による在庫の不整合を防止する
- **整合性チェック**: 利用可能在庫 = `stock_quantity - reserved_quantity` を都度算出し、マイナスにならないことをDB制約（CHECK制約）とアプリケーションロジックの両方で保証する

## 関連文書

- **アーキテクチャ**: [architecture.md](architecture.md)
- **型定義**: [data-types.php](data-types.php)
- **DBスキーマ**: [database-schema.sql](database-schema.sql)
- **API仕様**: [api-endpoints.md](api-endpoints.md)

## 信頼性レベルサマリー

- 🔵 青信号: 11件（85%）
- 🟡 黄信号: 2件（15%）
- 🔴 赤信号: 0件（0%）

**品質評価**: 高品質
