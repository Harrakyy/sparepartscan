<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Aturan bisnis yang dilanggar (misalnya faktur sudah mulai di-scan).
 * Pesannya aman ditampilkan ke pengguna dan dijawab dengan HTTP 409.
 */
class DomainRuleException extends RuntimeException
{
}
