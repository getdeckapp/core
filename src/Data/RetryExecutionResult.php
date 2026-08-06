<?php

namespace Deck\Core\Data;

readonly class RetryExecutionResult
{
    public function __construct(
        public bool $success,
        public string $message,
    ) {}
}
