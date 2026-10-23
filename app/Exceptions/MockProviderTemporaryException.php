<?php

namespace App\Exceptions;

class MockProviderTemporaryException extends MockProviderException
{
    public function __construct(
        string $message = 'Provider returned temporary 503 Service Unavailable',
        int $code = 503
    ) {
        parent::__construct($message, $code);
    }
}
