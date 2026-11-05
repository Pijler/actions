<?php

namespace Pijler\Actions\Events;

use Throwable;

class ActionFailed
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly string $action,
        public readonly array $parameters,
        public readonly Throwable $exception,
    ) {}
}
