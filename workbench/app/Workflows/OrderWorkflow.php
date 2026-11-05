<?php

namespace Workbench\App\Workflows;

use Pijler\Actions\Workflow;

class OrderWorkflow extends Workflow
{
    /**
     * Create a new workflow instance.
     */
    public function __construct(
        protected OrderContext $context,
        protected array $steps = [],
    ) {}

    /**
     * Get the context shared by the steps.
     */
    protected function context(): OrderContext
    {
        return $this->context;
    }

    /**
     * Get the steps of the workflow.
     */
    protected function steps(): array
    {
        return $this->steps;
    }
}
