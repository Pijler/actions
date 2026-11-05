<?php

namespace Workbench\App\Actions;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Pijler\Actions\Action;
use Workbench\App\Models\Customer;

class Record extends Action implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The values recorded by this action in the current process.
     */
    public static array $runs = [];

    /**
     * Create a new action instance.
     */
    public function __construct(
        public mixed $value = null,
        public ?Customer $customer = null,
    ) {}

    /**
     * Execute the action.
     */
    public function handle(): void
    {
        static::$runs[] = $this->customer ?? $this->value;
    }
}
