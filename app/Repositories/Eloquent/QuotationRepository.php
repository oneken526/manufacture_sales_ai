<?php

namespace App\Repositories\Eloquent;

use App\DataTransferObjects\QuotationData;
use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use App\Models\Quotation;
use App\Repositories\Contracts\QuotationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 見積リポジトリのEloquent実装
 * 🔵 信頼性: architecture.md（Repository+Serviceパターン）・TASK-0008.md実装詳細1・2
 *           ・ProductRepository::adjustStock()の悲観的ロックパターンより
 */
class QuotationRepository implements QuotationRepositoryInterface
{
    public function paginate(int $perPage = 50): LengthAwarePaginator
    {
        return Quotation::query()
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function find(int $id): ?Quotation
    {
        return Quotation::query()->find($id);
    }

    /**
     * 見積本体と明細を作成する
     * 🔵 信頼性: TASK-0008.md実装詳細1「Quotation/QuotationItemモデルとリレーション」より
     */
    public function create(QuotationData $data, string $quotationNumber): Quotation
    {
        $quotation = Quotation::query()->create([
            'quotation_number' => $quotationNumber,
            'customer_id' => $data->customerId,
            'remarks' => $data->remarks,
            'expires_at' => $data->expiresAt,
            'created_by' => $data->createdBy,
        ]);

        foreach ($data->items as $item) {
            $quotation->items()->create($item->toArray());
        }

        return $quotation->refresh();
    }

    /**
     * document_sequencesを行ロックして見積番号を採番する
     *
     * 採番処理そのものは DocumentSequence::issueNextNumber() に集約されており、
     * ここでは見積番号特有のフォーマット（QUO-{年度}-{連番4桁}）への変換のみを担う。
     * 🔵 信頼性: TASK-0008.md実装詳細2「document_sequencesテーブルをlockForUpdate()で取得・更新する」より
     */
    public function issueQuotationNumber(int $year): string
    {
        $nextNumber = DocumentSequence::issueNextNumber(DocumentType::QUOTATION, $year);

        return sprintf('QUO-%d-%04d', $year, $nextNumber);
    }
}
