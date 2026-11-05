<?php

namespace Pijler\Actions;

class WorkflowResult
{
    /**
     * Create a new workflow result instance.
     */
    public function __construct(
        public readonly object $context,
        public readonly bool $cancelled = false,
        public readonly string $reason = '',
    ) {}

    /**
     * Create the result of a workflow that ran to its end.
     */
    public static function completed(object $context): static
    {
        return new static($context);
    }

    /**
     * Create the result of a cancelled workflow.
     */
    public static function cancelled(object $context, string $reason = ''): static
    {
        return new static($context, true, $reason);
    }
}
