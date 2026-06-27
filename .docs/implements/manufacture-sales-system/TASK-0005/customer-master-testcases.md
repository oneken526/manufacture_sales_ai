# TASK-0005: 顧客マスタ管理機能 - テストケース一覧

TASK-0005.mdの単体テスト要件・統合テスト要件に基づき、以下のテストを実装した。

## 単体テスト

### tests/Unit/Services/CustomerServiceTest.php
| # | テスト名 | 内容 | 信頼性 |
|---|---|---|---|
| 1 | `test_crud_operations_persist_and_retrieve_customer_correctly` | `CustomerService::create/find/update/delete`を順に呼び出し、登録・取得・更新・ソフトデリートがDBへ正しく反映されることを確認 | 🔵 REQ-010 |
| 2 | `test_delete_throws_exception_when_customer_has_orders` | 受注（sales_orders）が紐づく顧客に対し`delete()`を呼び出すと`CustomerHasOrdersException`がスローされ、顧客が削除されず`deleted_at`もNULLのままであることを確認 | 🟡 REQ-012 |

### tests/Unit/Repositories/CustomerRepositoryTest.php
| # | テスト名 | 内容 | 信頼性 |
|---|---|---|---|
| 3 | `test_search_returns_customers_matching_company_name_contact_name_or_phone` | 会社名・担当者名・電話番号のいずれかにキーワードを部分一致で含む顧客のみが`search()`の結果に含まれることを確認 | 🟡 REQ-011 |
| 4 | `test_paginate_returns_fifty_items_per_page` | 顧客60件登録時、`paginate(50)`の1ページ目が50件・2ページ目が10件・総件数60件であることを確認 | 🔵 NFR-021 |

## 統合テスト

### tests/Feature/Customers/CustomerManagementTest.php
| # | テスト名 | シナリオ | 信頼性 |
|---|---|---|---|
| 5 | `test_customer_can_be_registered_searched_viewed_updated_and_deleted` | sales権限で①顧客登録→②検索結果表示→③詳細表示（受注履歴が空）→④編集・更新→admin権限で⑤削除（ソフトデリート・一覧から消える）の一連フローを検証 | 🔵 REQ-010〜REQ-013 |
| 6 | `test_deleting_customer_with_orders_is_rejected_with_warning_message` | 受注が紐づく顧客をadmin権限で削除しようとすると、削除が拒否され「この顧客には受注履歴があるため削除できません」という警告（フラッシュメッセージ）が表示され、顧客が削除されずに残ることを検証 | 🟡 REQ-012 |

## テスト実行結果

```
php artisan test --filter="Customer"
→ {"result":"passed","tests":7,"passed":7,"assertions":37}

php artisan test
→ {"result":"passed","tests":77,"passed":75,"assertions":262,"skipped":2}
```

既存テスト（認証・ロール・PDF等）への影響なし。スキップ2件は本タスクとは無関係の既存スキップ。
