<?php

namespace Workbench\App\Actions;

use Pijler\Actions\Action;
use Pijler\Actions\Exceptions\WorkflowCancelled;

class OutOfStock extends Action
{
    /**
     * Execute the action.
     */
    protected function handle(): void
    {
        throw new WorkflowCancelled('Out of stock.');
    }
}
