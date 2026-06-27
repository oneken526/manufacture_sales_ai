<?php

namespace App\Repositories\Contracts;

use App\DataTransferObjects\QuotationData;
use App\Models\Quotation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 見積リポジトリインターフェース
 * 🔵 信頼性: architecture.md（Repository+Serviceパターン）・TASK-0008.md実装詳細1・2より
 */
interface QuotationRepositoryInterface
{
    /**
     * 見積一覧をページネーション付きで取得する
     */
    public function paginate(int $perPage = 50): LengthAwarePaginator;

    /**
     * 主キーで見積を取得する（存在しない場合はnull）
     */
    public function find(int $id): ?Quotation;

    /**
     * 見積（および明細）を新規作成する
     */
    public function create(QuotationData $data, string $quotationNumber): Quotation;

    /**
     * 指定年度の見積番号を発行し、document_sequencesを更新する
     *
     * document_sequencesを行ロック（lockForUpdate）した上でlast_numberをインクリメントし、
     * `QUO-{年度}-{連番4桁}` 形式の見積番号を返却する。呼び出し元はDBトランザクション内で実行すること。
     */
    public function issueQuotationNumber(int $year): string;
}
