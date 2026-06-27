<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 受注履歴が存在する顧客の削除が試みられた場合にスローされる例外
 * 🟡 信頼性: REQ-012「受注が存在する顧客の場合、システムは削除を禁止し警告を表示しなければならない」より
 */
class CustomerHasOrdersException extends RuntimeException
{
    public function __construct(public readonly int $customerId)
    {
        parent::__construct('この顧客には受注履歴があるため削除できません');
    }
}
