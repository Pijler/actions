<?php

namespace Workbench\App\Actions;

use Pijler\Actions\Action;
use Workbench\App\Models\Customer;

class CreateCustomer extends Action
{
    /**
     * Execute the action.
     */
    protected function handle(): Customer
    {
        return Customer::create(['name' => 'Ana']);
    }
}
