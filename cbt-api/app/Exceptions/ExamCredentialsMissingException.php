<?php

namespace App\Exceptions;

use RuntimeException;

class ExamCredentialsMissingException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Kredensial peserta harus digenerate sebelum kartu ujian dapat dicetak.');
    }
}
