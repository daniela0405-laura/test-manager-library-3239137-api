<?php

namespace App\Exceptions;

use Exception;

// Contrato §2: cada error de negocio lleva su code y su estado HTTP.
class BusinessException extends Exception
{
    public function __construct(
        public string $errorCode,
        string $message,
        public int $status,
        public array $details = []
    ) {
        parent::__construct($message);
    }
}