<?php

namespace AioSSL\Core;

class UnsupportedOperationException extends \RuntimeException
{
    /** @var string */
    private $providerName;

    /** @var string */
    private $operation;

    public function __construct(string $providerName, string $operation, int $code = 0, \Throwable $previous = null)
    {
        $this->providerName = $providerName;
        $this->operation = $operation;
        $msg = sprintf(
            'Operation "%s" is not supported by %s. Please manage this directly in the provider portal.',
            $operation,
            $providerName
        );
        parent::__construct($msg, $code, $previous);
    }

    public function getProviderName(): string { return $this->providerName; }
    public function getOperation(): string { return $this->operation; }
}