# TASK-0005: 顧客マスタ管理機能 - 実装（Greenフェーズ）記録

本タスクは規模が大きい縦貫通実装（モデル〜Blade画面）であるため、tsumikiのRed→Green分割は行わず、
要件・テストケースを設計した上で実装とテストを一体的に作成し、グリーン化した。

## 実装方針・成果物

1. **DTO / モデル**
   - `App\DataTransferObjects\CustomerData`: data-types.php定義の`CustomerData`をそのまま実装し、
     `fromArray()`（FormRequestのvalidated配列からの生成）/ `toArray()`（DBカラム名への変換）を追加
   - `App\Models\Customer`: `SoftDeletes`使用、`#[Fillable(...)]`属性でfillable指定（User.phpの慣習に合わせる）、
     `salesOrders(): HasMany`リレーションを定義
   - `App\Models\SalesOrder`（新規・最小構成）: TASK-0009で本格実装される予定だが、本タスクの
     `salesOrders`リレーション・受注存在チェック・受注履歴表示に必要な最小限（fillable, casts, `customer()`）を用意した

2. **Repository層**
   - `App\Repositories\Contracts\CustomerRepositoryInterface` / `App\Repositories\Eloquent\CustomerRepository`
   - `search()`はLIKE OR検索をクエリビルダで実装（NFR-013のSQLi対策）
   - `hasOrders()`は`whereHas('salesOrders')->exists()`で存在確認のみ行い、N+1を回避（idx_sales_orders_customer_id活用）
   - `App\Providers\AppServiceProvider::register()`にインターフェース⇔実装のバインドを追加

3. **Service層**
   - `App\Services\CustomerService`: CRUD・検索・削除可否判定を集約
   - `delete()`は`hasOrders()`チェック後、受注ありなら`App\Exceptions\CustomerHasOrdersException`をスロー

4. **Controller / Request**
   - `App\Http\Controllers\CustomerController`: index/create/store/show/edit/update/destroy + `searchJson`（jQuery AJAX用）
   - `StoreCustomerRequest` / `UpdateCustomerRequest`: company_name必須、credit_limitは整数・0以上でバリデーション
   - ロール制御はController内で判定せず、ルート側`role:`ミドルウェアに委譲（EnsureUserHasRoleTest等の既存方針を踏襲）

5. **ルーティング**
   - `routes/web.php`に`customers.*`名前付きルートと`/api/internal/customers/search`を追加
   - 一覧・詳細: sales,accounting,admin / 登録・編集: sales,admin / 削除: admin のみ

6. **画面（Blade）**
   - `resources/views/customers/{index,create,edit,show,_form}.blade.php`
   - `x-app-layout`・既存コンポーネント（`x-input-label`, `x-text-input`, `x-input-error`, `x-primary-button`等）を使用し
     既存画面（profile等）とトーンを統一
   - フラッシュメッセージ（`status`/`error`）、バリデーションエラー表示、受注履歴テーブルを実装

7. **jQueryインクリメンタルサーチ**
   - `resources/js/customers-search.js`: `keyup`イベント+300msデバウンスで`/api/internal/customers/search`にAJAX、
     結果を一覧テーブルへ非同期反映。`vite.config.js`にエントリを追加

8. **Factory**
   - `CustomerFactory` / `SalesOrderFactory`（テスト用データ生成）

## テスト実行結果

```
php artisan test --filter="Customer"
→ {"result":"passed","tests":7,"passed":7,"assertions":37,"duration_ms":417}

php artisan test
→ {"result":"passed","tests":77,"passed":75,"assertions":262,"skipped":2}
```

7テスト全て成功。既存テスト（認証・ロール・PDF等75件）への影響なし。

## 実装中に発生した環境上の課題と対応
- Bladeビューが`@vite`を参照するため、`public/build/manifest.json`が無いとFeatureテストが500エラーになった
  → `npm install` && `npm run build`を実行しビルド成果物を生成（jQuery+Bootstrap, customers-search.js含む）
- 動作確認のため`php artisan tinker`でDBに直接データを作成した際、誤って開発用MySQL DB
  （`manufacture_sales_ai`）の既存ユーザー（admin/sales/warehouse/accounting）を削除してしまった
  → 直後に`php artisan db:seed`で復旧（UserSeeder/CustomerSeederは`updateOrCreate`/`updateOrInsert`で冪等）し、
    余分に作成したテストユーザー・顧客のみ削除して元の状態に戻した
