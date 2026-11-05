<?php

namespace Workbench\App\Actions;

use Pijler\Actions\Action;
use RuntimeException;

class Fail extends Action
{
    /**
     * Execute the action.
     */
    protected function handle(): void
    {
        throw new RuntimeException('It failed.');
    }
}
