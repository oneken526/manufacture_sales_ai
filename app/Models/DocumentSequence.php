<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * 帳票番号採番管理モデル
 * 🔵 信頼性: database-schema.sql（document_sequencesテーブル定義）・TASK-0008.md実装詳細2より
 */
#[Fillable(['document_type', 'fiscal_year', 'last_number'])]
class DocumentSequence extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'fiscal_year' => 'integer',
            'last_number' => 'integer',
        ];
    }

    /**
     * 指定した帳票種別・年度の連番を1つ進めて発行する
     *
     * 該当行が存在しない場合は新規作成し、行ロック（lockForUpdate）を取得した上で
     * last_numberをインクリメントする。これにより同時採番時の重複発行を防止する
     * （見積番号・受注番号の採番ロジックの重複を解消するための共通処理として抽出）。
     * 🔵 信頼性: TASK-0008.md実装詳細2「document_sequencesテーブルをlockForUpdate()で取得・更新する」
     *           ・ProductRepository::adjustStock()の悲観的ロックパターンより
     */
    public static function issueNextNumber(DocumentType $documentType, int $fiscalYear): int
    {
        static::query()->firstOrCreate(
            ['document_type' => $documentType->value, 'fiscal_year' => $fiscalYear],
            ['last_number' => 0],
        );

        $sequence = static::query()
            ->where('document_type', $documentType->value)
            ->where('fiscal_year', $fiscalYear)
            ->lockForUpdate()
            ->firstOrFail();

        $nextNumber = $sequence->last_number + 1;
        $sequence->update(['last_number' => $nextNumber]);

        return $nextNumber;
    }
}
