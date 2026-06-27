<?php

namespace Tests\Unit\Repositories;

use App\Enums\DocumentType;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Contracts\QuotationRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * TASK-0008 単体テストケース3・境界値TC12（採番の排他制御）に対応するテスト（Redフェーズ）。
 *
 * 現時点では QuotationRepositoryInterface・EloquentQuotationRepository・DocumentSequence モデルが
 * 未実装のため、本テストはクラス未検出（Fatal Error）または機能未実装によりすべて失敗する。
 *
 * @see .docs/implements/manufacture-sales-system/TASK-0008/quotation-testcases.md TC12
 */
class QuotationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): QuotationRepositoryInterface
    {
        return $this->app->make(QuotationRepositoryInterface::class);
    }

    /**
     * 【テスト目的】: document_sequencesに対するlockForUpdate()による排他制御で、採番が直列化され重複が発生しないことを確認する
     * 【テスト内容】: 同一年度に対して複数回(5回)連続して採番処理を呼び出し、発行される見積番号がすべて一意であり、
     *               document_sequences.last_numberが呼び出し回数分だけ正しくインクリメントされることを検証する
     * 【期待される動作】: QUO-{年度}-0001〜0005が重複なく発行され、last_numberが最終的に5になる
     * 🔵 信頼性レベル: TASK-0008.md単体テスト要件テストケース3・設計ヒアリングQ5・database-schema.sql（UNIQUE制約・悲観的ロック方針）より直接抽出
     *
     * 【補足】: 真の並行実行（マルチプロセス）はテスト環境上の制約で再現が難しいため、
     *           「連続呼び出しでも一意な番号が発行され、last_numberが呼び出し回数と一致する」ことを
     *           もって排他制御が機能している証跡として検証する（DBレベルではトランザクション+lockForUpdateにより直列化される）
     */
    public function test_quotation_number_sequence_is_serialized_and_does_not_duplicate_under_repeated_calls(): void
    {
        // 【テストデータ準備】: 採番に必要な最小限の関連データ（顧客・製品・作成者）を準備する
        // 【初期条件設定】: document_sequencesにはまだ当該年度のレコードが存在しない状態から開始する
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 1000, 'reserved_quantity' => 0]);
        $creator = User::factory()->create();
        $year = (int) now()->year;

        $issuedNumbers = [];

        // 【実際の処理実行】: 同一年度に対して採番ロジック（generateQuotationNumber相当）を5回連続で呼び出す
        // 【処理内容】: 各呼び出しがdocument_sequencesをlockForUpdate()で取得し、last_numberをインクリメントしてから見積番号を生成する想定
        for ($i = 0; $i < 5; $i++) {
            $issuedNumbers[] = DB::transaction(fn () => $this->repository()->issueQuotationNumber($year));
        }

        // 【結果検証】: 発行された5件の見積番号がすべて一意であることを確認する
        // 【期待値確認】: 排他制御により採番が直列化され、重複が発生しないことを保証する
        $this->assertCount(5, array_unique($issuedNumbers)); // 【確認内容】: 発行された見積番号に重複がないことを確認 🔵
        $this->assertSame([
            sprintf('QUO-%d-0001', $year),
            sprintf('QUO-%d-0002', $year),
            sprintf('QUO-%d-0003', $year),
            sprintf('QUO-%d-0004', $year),
            sprintf('QUO-%d-0005', $year),
        ], $issuedNumbers); // 【確認内容】: 連番が1から5まで欠番なく順番に発行されることを確認 🔵

        // 【結果検証】: document_sequences.last_numberが呼び出し回数分(5)だけ正しくインクリメントされていることを確認する
        $this->assertDatabaseHas('document_sequences', [
            'document_type' => DocumentType::QUOTATION->value,
            'fiscal_year' => $year,
            'last_number' => 5,
        ]); // 【確認内容】: last_numberが呼び出し回数と一致することを確認 🔵
        $this->assertSame(1, DocumentSequence::query()
            ->where('document_type', DocumentType::QUOTATION->value)
            ->where('fiscal_year', $year)
            ->count()); // 【確認内容】: UNIQUE(document_type, fiscal_year)制約どおり、年度につき1レコードのみ作成されることを確認 🔵
    }
}
