<?php

namespace Pijler\Actions\Events;

class ActionFinished
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly string $action,
        public readonly array $parameters,
    ) {}
}
