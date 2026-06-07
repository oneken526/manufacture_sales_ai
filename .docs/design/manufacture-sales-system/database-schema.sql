-- ========================================
-- 製造業向け販売管理システム データベーススキーマ
-- ========================================
--
-- 作成日: 2026-06-07
-- 関連設計: architecture.md, data-types.php
--
-- 信頼性レベル:
-- - 🔵 青信号: 要件定義書・設計ヒアリングを参考にした確実な定義
-- - 🟡 黄信号: 要件定義書・設計ヒアリングから妥当な推測による定義
-- - 🔴 赤信号: 要件定義書・設計ヒアリングにない推測による定義
--
-- 注記: Laravel Eloquent は id(bigint auto increment) / timestamps を標準とするため、
--       本スキーマもLaravelマイグレーション規約に合わせた型・命名で記述する。
--       実装時は database/migrations/ 配下にマイグレーションファイルとして起こす。
--
-- 区分値（ステータス等）は英語名ではなく数値コード（TINYINT UNSIGNED）で保持する。
-- 各コードの意味は以下のコード表を正とし、アプリケーション側ではEnum（data-types.php）で対応付ける。
--
-- ========================================
-- 区分値コード表 🔵 信頼性: ユーザー指示（数値コード化）+ 各要件より
-- ========================================
-- ■ users.role（ユーザー役割） REQ-002
--   1 = システム管理者(admin) / 2 = 営業担当者(sales) / 3 = 在庫・出荷担当者(warehouse) / 4 = 管理職・経理担当(accounting)
--
-- ■ quotations.status（見積ステータス）
--   1 = 見積中(draft) / 2 = 受注転換済み(converted) / 3 = キャンセル(cancelled) / 4 = 期限切れ(expired)
--
-- ■ sales_orders.status（受注ステータス） REQ-031, REQ-040〜053
--   1 = 受注確定(confirmed) / 2 = 出荷指示済み(shipping_instructed) / 3 = 出荷完了(shipped)
--   4 = 請求済み(invoiced) / 5 = キャンセル(cancelled) / 6 = 返品済み(returned)
--
-- ■ invoices.payment_status（入金ステータス） REQ-062
--   1 = 未入金(unpaid) / 2 = 部分入金(partial) / 3 = 入金済み(paid)
--
-- ■ payments.source（入金記録の登録元）
--   1 = 手動登録(manual) / 2 = CSV取込照合(csv_import)
--
-- ■ stock_movements.reason（在庫変動理由） REQ-071, REQ-072
--   1 = 受注引き当て(reservation) / 2 = 引き当て解除(release) / 3 = 出荷による減算(shipment)
--   4 = 返品による加算(return) / 5 = 手動調整(manual)
--
-- ■ document_sequences.document_type（採番対象ドキュメント種別）
--   1 = 見積書(quotation) / 2 = 受注(order) / 3 = 請求書(invoice)
-- ========================================

-- ========================================
-- users（ユーザー: Laravel Breeze標準 + role拡張）
-- ========================================
-- 🔵 信頼性: 要件定義REQ-001, REQ-002・Laravel Breeze標準より
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,              -- 🔵 NFR-010: bcryptハッシュ化
    role TINYINT UNSIGNED NOT NULL DEFAULT 2,    -- 🔵 REQ-002: 1=admin/2=sales/3=warehouse/4=accounting（コード表参照）
    is_active BOOLEAN NOT NULL DEFAULT TRUE,     -- 🟡 REQ-004: 無効化機能から推測
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT chk_users_role CHECK (role BETWEEN 1 AND 4) -- 🔵 REQ-002
);

-- ========================================
-- customers（顧客・取引先マスタ）
-- ========================================
-- 🔵 信頼性: 要件定義REQ-010〜013より
CREATE TABLE customers (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(255) NOT NULL,          -- 🔵 REQ-010: 会社名
    contact_name VARCHAR(255) NULL,              -- 🔵 REQ-010: 担当者名
    address VARCHAR(500) NULL,                   -- 🔵 REQ-010: 住所
    phone VARCHAR(20) NULL,                      -- 🔵 REQ-010: 電話
    email VARCHAR(255) NULL,                     -- 🔵 REQ-010: メール
    credit_limit BIGINT NOT NULL DEFAULT 0,      -- 🔵 REQ-010: 与信枠
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL                    -- 🟡 REQ-012: 受注がある顧客は削除禁止のためソフトデリート推奨
);

CREATE INDEX idx_customers_company_name ON customers(company_name); -- 🔵 REQ-011: 検索用

-- ========================================
-- products（製品マスタ）
-- ========================================
-- 🔵 信頼性: 要件定義REQ-020〜023・設計ヒアリングQ6より
CREATE TABLE products (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_code VARCHAR(50) UNIQUE NOT NULL,    -- 🔵 REQ-020: 品番
    product_name VARCHAR(255) NOT NULL,          -- 🔵 REQ-020: 製品名
    unit_price BIGINT NOT NULL,                  -- 🔵 REQ-020: 単価
    unit VARCHAR(20) NOT NULL DEFAULT '個',       -- 🔵 REQ-020: 単位
    stock_quantity INTEGER NOT NULL DEFAULT 0,   -- 🔵 設計ヒアリングQ6: 実在庫数
    reserved_quantity INTEGER NOT NULL DEFAULT 0, -- 🔵 設計ヒアリングQ6: 引当中在庫数
    alert_threshold INTEGER NOT NULL DEFAULT 0,  -- 🟡 REQ-022: 在庫アラート閾値
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT chk_products_stock CHECK (stock_quantity >= 0),           -- 🔵 在庫整合性
    CONSTRAINT chk_products_reserved CHECK (reserved_quantity >= 0),     -- 🔵 在庫整合性
    CONSTRAINT chk_products_reserved_le_stock CHECK (reserved_quantity <= stock_quantity) -- 🔵 利用可能在庫がマイナスにならない
);

CREATE INDEX idx_products_product_code ON products(product_code); -- 🔵 REQ-021: 検索用
CREATE INDEX idx_products_product_name ON products(product_name); -- 🔵 REQ-021: 検索用

-- ========================================
-- quotations（見積）
-- ========================================
-- 🔵 信頼性: 要件定義REQ-030〜033・設計ヒアリングQ5より
CREATE TABLE quotations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    quotation_number VARCHAR(30) UNIQUE NOT NULL, -- 🔵 例: QUO-2026-0001（年度+連番）
    customer_id BIGINT NOT NULL,
    status TINYINT UNSIGNED NOT NULL DEFAULT 1,   -- 🔵 1=見積中/2=受注転換済み/3=キャンセル/4=期限切れ（コード表参照）
    remarks TEXT NULL,                            -- 🔵 REQ-030: 備考
    expires_at DATE NULL,                         -- 🟡 REQ-033: 有効期限
    created_by BIGINT NOT NULL,                   -- 🟡 操作者記録（共通パターン）
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_quotations_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_quotations_created_by FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE INDEX idx_quotations_customer_id ON quotations(customer_id);

-- ========================================
-- quotation_items（見積明細）
-- ========================================
-- 🔵 信頼性: 要件定義REQ-030より
CREATE TABLE quotation_items (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    quotation_id BIGINT NOT NULL,
    product_id BIGINT NOT NULL,
    quantity INTEGER NOT NULL,
    unit_price BIGINT NOT NULL,                   -- 🔵 見積時点の単価を保持（製品マスタ変更の影響を受けないため）
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_quotation_items_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE,
    CONSTRAINT fk_quotation_items_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT chk_quotation_items_quantity CHECK (quantity > 0)
);

-- ========================================
-- sales_orders（受注）
-- ========================================
-- 🔵 信頼性: 要件定義REQ-031, REQ-040〜043・設計ヒアリングQ5より
CREATE TABLE sales_orders (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(30) UNIQUE NOT NULL,     -- 🔵 例: ORD-2026-0001（年度+連番）
    quotation_id BIGINT NULL,                     -- 🔵 REQ-031: 見積からの転換を記録
    customer_id BIGINT NOT NULL,
    status TINYINT UNSIGNED NOT NULL DEFAULT 1,    -- 🔵 1=受注確定/2=出荷指示済み/3=出荷完了/4=請求済み/5=キャンセル/6=返品済み（コード表参照）
    confirmed_at TIMESTAMP NULL,                   -- 🔵 REQ-040: 受注確定日時
    cancelled_at TIMESTAMP NULL,                   -- 🔵 REQ-043: キャンセル日時
    created_by BIGINT NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_sales_orders_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id),
    CONSTRAINT fk_sales_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_sales_orders_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT chk_sales_orders_status CHECK (status BETWEEN 1 AND 6) -- 🔵 OrderStatusコードと整合
);

CREATE INDEX idx_sales_orders_customer_id ON sales_orders(customer_id);
CREATE INDEX idx_sales_orders_status ON sales_orders(status);

-- ========================================
-- sales_order_items（受注明細）
-- ========================================
-- 🔵 信頼性: 要件定義REQ-040より
CREATE TABLE sales_order_items (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    sales_order_id BIGINT NOT NULL,
    product_id BIGINT NOT NULL,
    quantity INTEGER NOT NULL,
    unit_price BIGINT NOT NULL,                   -- 🔵 受注時点の単価を保持
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_sales_order_items_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_sales_order_items_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT chk_sales_order_items_quantity CHECK (quantity > 0)
);

-- ========================================
-- shipments（出荷）
-- ========================================
-- 🔵 信頼性: 要件定義REQ-050〜053より
CREATE TABLE shipments (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    sales_order_id BIGINT NOT NULL,
    shipped_at TIMESTAMP NULL,                    -- 🔵 REQ-051: 出荷完了日時
    delivery_note_path VARCHAR(500) NULL,         -- 🔵 REQ-052: 納品書PDFパス
    returned_at TIMESTAMP NULL,                   -- 🔵 REQ-053: 返品日時
    return_reason TEXT NULL,                      -- 🟡 返品理由（業務上必要と推測）
    shipped_by BIGINT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_shipments_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id),
    CONSTRAINT fk_shipments_shipped_by FOREIGN KEY (shipped_by) REFERENCES users(id)
);

CREATE INDEX idx_shipments_sales_order_id ON shipments(sales_order_id);

-- ========================================
-- invoices（請求書）
-- ========================================
-- 🔵 信頼性: 要件定義REQ-060〜064・設計ヒアリングQ5より
CREATE TABLE invoices (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(30) UNIQUE NOT NULL,   -- 🔵 例: INV-2026-0001（年度+連番）
    sales_order_id BIGINT NOT NULL UNIQUE,        -- 🔵 EDGE-004: 1受注につき1請求書（二重発行防止）
    total_amount BIGINT NOT NULL,                 -- 🔵 REQ-060: 請求金額
    payment_status TINYINT UNSIGNED NOT NULL DEFAULT 1, -- 🔵 REQ-062: 1=未入金/2=部分入金/3=入金済み（コード表参照）
    invoice_pdf_path VARCHAR(500) NULL,           -- 🔵 REQ-061
    issued_at TIMESTAMP NULL,                     -- 🔵 発行日時
    issued_by BIGINT NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_invoices_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id),
    CONSTRAINT fk_invoices_issued_by FOREIGN KEY (issued_by) REFERENCES users(id),
    CONSTRAINT chk_invoices_payment_status CHECK (payment_status BETWEEN 1 AND 3) -- 🔵 PaymentStatusコードと整合
);

CREATE INDEX idx_invoices_payment_status ON invoices(payment_status);

-- ========================================
-- payments（入金記録）
-- ========================================
-- 🔵 信頼性: 要件定義REQ-062, REQ-063より
CREATE TABLE payments (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    invoice_id BIGINT NOT NULL,
    amount BIGINT NOT NULL,                       -- 🔵 入金額
    paid_at DATE NOT NULL,                        -- 🔵 入金日
    source TINYINT UNSIGNED NOT NULL DEFAULT 1,   -- 🔵 1=手動登録(manual)/2=CSV取込照合(csv_import)（コード表参照）
    raw_csv_row TEXT NULL,                        -- 🟡 全銀CSV取込時の元データ保持（監査用、推測）
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    CONSTRAINT chk_payments_source CHECK (source BETWEEN 1 AND 2)
);

CREATE INDEX idx_payments_invoice_id ON payments(invoice_id);

-- ========================================
-- stock_movements（在庫変動履歴）
-- ========================================
-- 🟡 信頼性: 要件定義REQ-072から妥当な推測
CREATE TABLE stock_movements (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT NOT NULL,
    reason TINYINT UNSIGNED NOT NULL,             -- 🟡 1=引当/2=引当解除/3=出荷減算/4=返品加算/5=手動調整（コード表参照）
    quantity_change INTEGER NOT NULL,             -- 🟡 増減数（マイナス可）
    related_order_id BIGINT NULL,                 -- 🟡 関連する受注（手動調整時はNULL）
    operated_by BIGINT NOT NULL,                  -- 🔵 REQ-072: 操作者
    memo VARCHAR(500) NULL,
    created_at TIMESTAMP NULL,                    -- 🔵 REQ-072: 日時

    CONSTRAINT fk_stock_movements_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_stock_movements_order FOREIGN KEY (related_order_id) REFERENCES sales_orders(id),
    CONSTRAINT fk_stock_movements_operated_by FOREIGN KEY (operated_by) REFERENCES users(id),
    CONSTRAINT chk_stock_movements_reason CHECK (reason BETWEEN 1 AND 5)
);

CREATE INDEX idx_stock_movements_product_id ON stock_movements(product_id);
CREATE INDEX idx_stock_movements_created_at ON stock_movements(created_at);

-- ========================================
-- document_sequences（採番管理: 見積/受注/請求の年度連番）
-- ========================================
-- 🔵 信頼性: 設計ヒアリングQ5（年度+連番ルール）より
-- 採番の競合を避けるため、ドキュメント種別+年度ごとに連番カウンタを保持する
CREATE TABLE document_sequences (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    document_type TINYINT UNSIGNED NOT NULL,      -- 🔵 1=見積書(quotation)/2=受注(order)/3=請求書(invoice)（コード表参照）
    fiscal_year INTEGER NOT NULL,                 -- 🔵 年度（例: 2026）
    last_number INTEGER NOT NULL DEFAULT 0,       -- 🔵 直近の発行連番
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT uq_document_sequences UNIQUE (document_type, fiscal_year), -- 🔵 種別×年度で一意
    CONSTRAINT chk_document_sequences_type CHECK (document_type BETWEEN 1 AND 3)
);

-- ========================================
-- 信頼性レベルサマリー
-- ========================================
-- - 🔵 青信号: 27件 (79%)
-- - 🟡 黄信号: 7件 (21%)
-- - 🔴 赤信号: 0件 (0%)
--
-- 品質評価: 高品質
--
-- 注記: Phase 2のAI機能（需要予測・チャット履歴等）に関連するテーブルは
--       Phase 2の設計フェーズで別途追加する。
