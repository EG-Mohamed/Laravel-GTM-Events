<?php

namespace MohamedSaid\LaravelGa4Events\Exceptions;

use RuntimeException;

class InvalidGa4ConfigurationException extends RuntimeException
{
    public static function missingContainerId(): self
    {
        return new self((string) __('GTM container ID is missing. Set GTM_CONTAINER_ID or ga4-events.container_id.'));
    }
}
