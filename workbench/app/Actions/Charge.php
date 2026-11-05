<?php

namespace Workbench\App\Actions;

use Pijler\Actions\Action;
use Workbench\App\Workflows\OrderContext;

class Charge extends Action
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        public OrderContext $order,
    ) {}

    /**
     * Execute the action.
     */
    protected function handle(): void
    {
        $this->order->log[] = "charge-{$this->order->total}";
    }
}
