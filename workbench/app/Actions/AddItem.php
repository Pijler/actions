<?php

namespace Workbench\App\Actions;

use Illuminate\Contracts\Config\Repository;
use Pijler\Actions\Action;
use Workbench\App\Workflows\OrderContext;

class AddItem extends Action
{
    /**
     * Create a new action instance, with the context after an injected dependency.
     */
    public function __construct(
        public Repository $config,
        public OrderContext $context,
    ) {}

    /**
     * Execute the action.
     */
    protected function handle(): void
    {
        $this->context->total += 100;

        $this->context->log[] = 'add-item';
    }
}
