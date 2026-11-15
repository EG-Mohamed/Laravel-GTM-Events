<?php

namespace MohamedSaid\LaravelGa4Events\Support;

final class DriverResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?int $status = null,
        public readonly array $body = [],
        public readonly bool $retryable = false,
    ) {}
}
