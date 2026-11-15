<?php

namespace MohamedSaid\LaravelGa4Events\Contracts;

use MohamedSaid\LaravelGa4Events\Support\DriverResult;

interface ServerTrackingDriver
{
    public function destination(): string;

    public function enabled(): bool;

    public function shouldSend(array $event): bool;

    public function buildPayload(array $event): array;

    public function send(array $event): DriverResult;
}
