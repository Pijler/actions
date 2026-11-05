<?php

namespace Workbench\App\Workflows;

class OrderContext
{
    /**
     * Create a new context instance.
     */
    public function __construct(
        public int $total = 0,
        public array $log = [],
    ) {}
}
