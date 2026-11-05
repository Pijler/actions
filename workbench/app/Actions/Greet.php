<?php

namespace Workbench\App\Actions;

use Pijler\Actions\Action;

class Greet extends Action
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        public string $name,
        public string $greeting = 'Hello',
    ) {}

    /**
     * Execute the action.
     */
    protected function handle(): string
    {
        return "{$this->greeting}, {$this->name}!";
    }
}
