<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Pijler\Actions\Action;
use Pijler\Actions\Events\ActionFailed;
use Pijler\Actions\Events\ActionFinished;
use Pijler\Actions\Step;
use Pijler\Actions\Workflow;
use Pijler\Actions\WorkflowResult;
use Workbench\App\Actions\AddItem;
use Workbench\App\Actions\Charge;
use Workbench\App\Actions\CreateCustomer;
use Workbench\App\Actions\Fail;
use Workbench\App\Actions\OutOfStock;
use Workbench\App\Actions\Record;
use Workbench\App\Models\Customer;
use Workbench\App\Workflows\OrderContext;
use Workbench\App\Workflows\OrderWorkflow;
use Workbench\App\Workflows\TransactionalOrderWorkflow;

beforeEach(function () {
    config(['queue.default' => 'sync']);

    Record::$runs = [];

    $this->context = new OrderContext;
});

test('it should run the steps in sequence sharing the context', function () {
    $result = OrderWorkflow::execute($this->context, [AddItem::class, Charge::class]);

    expect($result)->toBeInstanceOf(WorkflowResult::class)
        ->cancelled->toBeFalse()
        ->context->toBe($this->context);

    expect($this->context->log)->toBe(['add-item', 'charge-100']);
});

test('it should run a step without a context parameter', function () {
    $result = OrderWorkflow::execute($this->context, [CreateCustomerless::class]);

    expect($result->cancelled)->toBeFalse();

    expect(CreateCustomerless::$ran)->toBeTrue();
});

test('it should pass the context to a parameter named context when no type accepts it', function () {
    $result = OrderWorkflow::execute($this->context, [UntypedContextStep::class]);

    expect($result->context->log)->toBe(['untyped']);
});

test('it should skip the steps that do not pass runIf', function () {
    OrderWorkflow::execute($this->context, [
        Step::make(AddItem::class)->runIf(false),
        Step::make(Charge::class)->runIf(function (OrderContext $context) {
            return $context->total === 0;
        }),
    ]);

    expect($this->context->log)->toBe(['charge-0']);
});

test('it should cancel the workflow from a step condition', function () {
    $result = OrderWorkflow::execute($this->context, [
        AddItem::class,
        Step::make(Charge::class)->cancelIf(function (OrderContext $context) {
            return $context->total > 50;
        }, 'Too expensive.'),
        Charge::class,
    ]);

    expect($result)->cancelled->toBeTrue()->reason->toBe('Too expensive.');

    expect($this->context->log)->toBe(['add-item']);
});

test('it should cancel the workflow from inside an action', function () {
    Event::fake([ActionFinished::class, ActionFailed::class]);

    $result = OrderWorkflow::execute($this->context, [AddItem::class, OutOfStock::class, Charge::class]);

    expect($result)->cancelled->toBeTrue()->reason->toBe('Out of stock.');

    expect($this->context->log)->toBe(['add-item']);

    Event::assertDispatched(ActionFinished::class, function ($event) {
        return $event->action === OutOfStock::class;
    });

    Event::assertNotDispatched(ActionFailed::class);
});

test('it should let the failure of a step reach the caller', function () {
    OrderWorkflow::execute($this->context, [AddItem::class, Fail::class]);
})->throws(RuntimeException::class, 'It failed.');

describe('transactions', function () {
    beforeEach(function () {
        Schema::create('customers', function ($table) {
            $table->id();
            $table->string('name');
        });
    });

    test('it should commit when the steps finish', function () {
        TransactionalOrderWorkflow::execute($this->context, [CreateCustomer::class]);

        expect(Customer::count())->toBe(1);
    });

    test('it should roll back when a step fails', function () {
        expect(function () {
            return TransactionalOrderWorkflow::execute($this->context, [CreateCustomer::class, Fail::class]);
        })
            ->toThrow(RuntimeException::class);

        expect(Customer::count())->toBe(0);
    });

    test('it should roll back when the workflow is cancelled', function () {
        $result = TransactionalOrderWorkflow::execute($this->context, [CreateCustomer::class, OutOfStock::class]);

        expect($result->cancelled)->toBeTrue();

        expect(Customer::count())->toBe(0);
    });

    test('it should not use a transaction without the attribute', function () {
        expect(function () {
            return OrderWorkflow::execute($this->context, [CreateCustomer::class, Fail::class]);
        })
            ->toThrow(RuntimeException::class);

        expect(Customer::count())->toBe(1);
    });

    test('it should dispatch queued steps only after the commit', function () {
        Queue::fake();

        TransactionalOrderWorkflow::execute($this->context, [
            Step::make(Record::class)->queued(),
            Step::make(AssertNothingQueued::class),
        ]);

        Queue::assertPushed(Record::class);
    });

    test('it should not dispatch queued steps after a rollback', function (string $failingStep) {
        Queue::fake();

        rescue(function () use ($failingStep) {
            return TransactionalOrderWorkflow::execute($this->context, [
                Step::make(Record::class)->queued(),
                $failingStep,
            ]);
        }, report: false);

        Queue::assertNothingPushed();
    })->with([OutOfStock::class, Fail::class]);
});

test('it should dispatch queued steps immediately without a transaction', function () {
    Queue::fake();

    OrderWorkflow::execute($this->context, [Step::make(Record::class)->queued(), AssertSomethingQueued::class]);

    Queue::assertPushed(Record::class);
});

test('it should refuse a queued step that is not a queued action', function () {
    OrderWorkflow::execute($this->context, [Step::make(Charge::class)->queued()]);
})->throws(LogicException::class, 'must implement ShouldQueue');

test('it should queue a whole workflow', function () {
    QueuedOrderWorkflow::dispatch($this->context);

    expect(QueuedOrderWorkflow::$contexts[0]->log)->toBe(['add-item']);
});

describe('fake', function () {
    test('it should fake the steps of a workflow', function () {
        $fake = Workflow::fake([AddItem::class]);

        OrderWorkflow::execute($this->context, [AddItem::class, Charge::class]);

        $fake->assertExecuted(AddItem::class, function (array $parameters) {
            return data_get($parameters, 'context') === $this->context;
        });

        $fake->assertExecuted(Charge::class);

        expect($this->context->log)->toBe(['charge-0']);
    });

    test('it should fake a whole workflow and return a completed result', function () {
        $fake = Workflow::fake([OrderWorkflow::class]);

        $result = OrderWorkflow::execute($this->context, [AddItem::class]);

        expect($result)->cancelled->toBeFalse()->context->toBe($this->context);

        $fake->assertExecuted(OrderWorkflow::class);

        $fake->assertNotExecuted(AddItem::class);
    });

    test('it should fake a cancelled workflow', function () {
        Action::fake([OrderWorkflow::class => WorkflowResult::cancelled($this->context, 'Faked.')]);

        expect(OrderWorkflow::execute($this->context))->cancelled->toBeTrue()->reason->toBe('Faked.');
    });
});

class QueuedOrderWorkflow extends OrderWorkflow implements ShouldQueue
{
    use Queueable;

    public static array $contexts = [];

    protected function context(): OrderContext
    {
        return static::$contexts[] = parent::context();
    }

    protected function steps(): array
    {
        return [AddItem::class];
    }
}

class CreateCustomerless extends Action
{
    public static bool $ran = false;

    protected function handle(): void
    {
        static::$ran = true;
    }
}

class UntypedContextStep extends Action
{
    public function __construct(public $context) {}

    protected function handle(): void
    {
        $this->context->log[] = 'untyped';
    }
}

class AssertNothingQueued extends Action
{
    protected function handle(): void
    {
        Queue::assertNothingPushed();
    }
}

class AssertSomethingQueued extends Action
{
    protected function handle(): void
    {
        Queue::assertPushed(Record::class);
    }
}
