<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 同一受注への二重請求発行を防止するための例外
 * 🔵 EDGE-004より
 */
class DuplicateInvoiceException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('この受注の請求書は既に発行済みです。');
    }
}
