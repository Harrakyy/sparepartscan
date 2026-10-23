<?php

namespace App\Exceptions;

/**
 * Aksi boleh dilakukan, tetapi pengguna harus mengonfirmasi dulu
 * (misalnya mengganti faktur yang nomornya sudah ada).
 */
class ConfirmationRequiredException extends DomainRuleException
{
}
