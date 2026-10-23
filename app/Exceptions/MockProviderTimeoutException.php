<?php

namespace App\Exceptions;

class MockProviderTimeoutException extends MockProviderException
{
    public function __construct(
        string $message = 'Provider HTTP request timed out after issuance',
        public readonly ?string $providerReference = null,
        int $code = 504
    ) {
        parent::__construct($message, $code);
    }
}
