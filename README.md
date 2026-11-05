<p align="center">
<img src="https://raw.githubusercontent.com/Pijler/docs/main/assets/pijler-icon-dark.svg" height="100" alt="Pijler logo">
</p>

<h2><p align="center">Actions</p></h2>

### 🚀 Introduction

Typed, testable, and queueable actions for Laravel, with workflows to orchestrate them.

> [!WARNING]
> This package is under development and has no stable release yet. The workflow API, in particular, may change while it is validated in a real application.

Requires PHP 8.3+ and Laravel 12 or 13.

### 📦 Installation

```bash
composer require pijler/actions
```

### 🎯 Actions

Generate an action with `php artisan make:action CreateUser`, which creates `app/Actions/CreateUser.php`. Constructor arguments are typed, and `handle()` returns its value directly. The container resolves the constructor parameters the caller doesn't give, as well as the parameters of `handle()`:

```php
use Pijler\Actions\Action;

class CreateUser extends Action
{
    public function __construct(
        private string $name,
        private string $email,
        private string $password,
    ) {}

    public function handle(Hasher $hasher): User
    {
        return User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $hasher->make($this->password),
        ]);
    }
}

$user = CreateUser::execute('João Pedro', 'joao@example.com', password: $password);

CreateUser::executeIf($shouldCreate, 'João Pedro', 'joao@example.com', $password);
CreateUser::executeUnless($shouldSkip, 'João Pedro', 'joao@example.com', $password);
```

### ⏳ Queues

A queued action is a regular Laravel job: implement `ShouldQueue` and use `Queueable`.

```php
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWelcomeEmail extends Action implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public User $user)
    {
        $this->onQueue('emails');
    }

    public function handle(Mailer $mailer): void
    {
        // ...
    }
}

SendWelcomeEmail::dispatch($user);
SendWelcomeEmail::dispatch($user)->delay(now()->addMinute());
SendWelcomeEmail::execute($user); // Still runs right away.
```

Everything from Laravel jobs applies, such as `$tries`, `backoff()`, `failed()`, `middleware()`, `ShouldBeUnique`, chains and batches, and on Laravel 13 the `#[Queue]` and `#[Connection]` attributes. There are two differences from an action that isn't queued:

- `handle()` must be `public`, because the worker calls it from outside the class. The generated stub already is.
- The instance itself is serialized, so the constructor can only take data. Inject services into `handle()`.

### 📣 Events

Every execution that is not faked dispatches events carrying the action class and its parameters, both with `execute` and on the queue worker:

| Event | When | Extra data |
| --- | --- | --- |
| `Pijler\Actions\Events\ActionStarted` | Before the action runs | |
| `Pijler\Actions\Events\ActionFinished` | After `handle()` returns | |
| `Pijler\Actions\Events\ActionFailed` | When the action throws | `$exception` |

With `execute`, the events carry the arguments given by the caller. On the worker, they carry the promoted constructor properties. They never carry the returned value, which may be sensitive. A queued action that is retried dispatches them on every attempt.

### 🔒 Sensitive data

Mark constructor parameters with PHP's native `#[\SensitiveParameter]` to hide them:

```php
class Login extends Action
{
    public function __construct(
        public string $email,
        #[\SensitiveParameter] public string $password,
    ) {}

    // ...
}
```

The value becomes `[sensitive]` in the event payloads, when the action is written to a log (`Log::info('...', ['action' => $this])`) and in `dump()`, `dd()` and `var_dump()`.

If the parameter is not promoted, the property holding its value is only hidden when it has the same name. PHP does not allow the attribute on properties, so a property that doesn't come from a constructor parameter cannot be marked.

> [!CAUTION]
> The attribute does not protect the queue. A queued action is stored as it is, so implement Laravel's `ShouldBeEncrypted` in any queued action with sensitive parameters, or they stay in plain text in the queue and in `failed_jobs`.

> [!IMPORTANT]
> Stack traces are only partly covered: PHP hides the value in the frame of the constructor, but `execute` and the container also receive it, so it still appears in their frames unless `zend.exception_ignore_args` is on, as recommended for production. The message of an exception is also not filtered, and neither is what `handle()` itself logs.

### 🧪 Faking in tests

`Action::fake()` returns an object to make assertions on. Actions in the list are replaced, and all others run for real, while every execution is recorded:

```php
$fake = Action::fake([
    SendWelcomeEmail::class,                       // Returns null.
    FetchRate::class => 1.5,                       // Returns the value.
    CreateUser::class => function (array $parameters) { // Returns or throws.
        return new User($parameters);
    },
]);

// ...

$fake->assertExecuted(SendWelcomeEmail::class);
$fake->assertExecuted(CreateUser::class, function (array $parameters) {
    return data_get($parameters, 'name') === 'Ana';
});
$fake->assertNotExecuted(FetchRate::class);
$fake->assertExecutedTimes(CreateUser::class, 1);
```

An action run by the queue worker goes through the same fake. To test only that it was queued, use Laravel's own fake:

```php
Queue::fake();

SendWelcomeEmail::dispatch($user);

Queue::assertPushed(SendWelcomeEmail::class, function (SendWelcomeEmail $action) use ($user) {
    return $action->user->is($user);
});
```

The fake ends along with the application of each test. Use `Action::restore()` to stop it earlier, or `Action::fakeFor($callback, $actions)` to limit it to a callback. Assertions see the real values of sensitive parameters.

### 🔗 Workflows

A workflow is an action that executes other actions in sequence, sharing data through a typed context. `php artisan make:workflow Checkout` creates `app/Workflows/Checkout.php` and `CheckoutContext.php`:

```php
use Pijler\Actions\Step;
use Pijler\Actions\Workflow;

class CheckoutContext
{
    public function __construct(
        public Order $order,
        public ?Payment $payment = null,
    ) {}
}

class Checkout extends Workflow
{
    public function __construct(protected CheckoutContext $context) {}

    protected function context(): CheckoutContext
    {
        return $this->context;
    }

    protected function steps(): array
    {
        return [
            ReserveStock::class,
            ChargeCard::class,
            Step::make(ApplyCoupon::class)->runIf(function (CheckoutContext $context) {
                return $context->order->hasCoupon();
            }),
            Step::make(SendReceipt::class)->queued(),
        ];
    }
}

$result = Checkout::execute(new CheckoutContext($order));

$result->context->payment;
$result->cancelled;
$result->reason;
```

Each step is a regular action. The context goes to the constructor parameter that accepts its type, or else to one named `$context`, and steps share data by changing it. The container resolves the other parameters, and the value returned by a step is ignored:

```php
class ChargeCard extends Action
{
    public function __construct(
        private CheckoutContext $context,
        private PaymentGateway $gateway,
    ) {}

    public function handle(): void
    {
        $this->context->payment = $this->gateway->charge($this->context->order);
    }
}
```

Since a workflow is an action, it has `executeIf`, `executeUnless`, events and `#[\SensitiveParameter]` too, and it can be queued with `ShouldQueue` and `Queueable`. The action of a `queued()` step must be a [queued action](#-queues).

#### Cancelling

Cancelling stops the workflow without being a failure: it returns a result with `cancelled` set, instead of throwing. A step cancels with a condition, checked before its `runIf`, or from inside the action:

```php
Step::make(ChargeCard::class)->cancelIf(function (CheckoutContext $context) {
    return $context->order->isPaid();
}, 'Already paid.');

throw new WorkflowCancelled('Out of stock.'); // Pijler\Actions\Exceptions\WorkflowCancelled
```

An action that throws `WorkflowCancelled` dispatches `ActionFinished`, not `ActionFailed`. A queued step runs apart from the workflow, so it cannot cancel it. Any other exception reaches the caller of the workflow.

#### Transactions

Add `#[Transactional]`, optionally with a connection, to run the steps inside a database transaction:

```php
use Pijler\Actions\Attributes\Transactional;

#[Transactional]
class Checkout extends Workflow
{
    // ...
}
```

Both a failure and a cancellation roll it back. Queued steps are dispatched only after the commit, in order and with the context as it was at the end of the workflow, and never after a rollback. Without a transaction, they are dispatched when the workflow reaches them. Changes to the context are not rolled back, only the database.

A queued step receives a copy of the context, so its changes do not return to the workflow.

#### Faking

Use the same fake to replace steps or the whole workflow, which then returns a completed result by default:

```php
$fake = Workflow::fake([ChargeCard::class]);

Checkout::execute($context);

$fake->assertExecuted(ChargeCard::class);
$fake->assertExecuted(ReserveStock::class);

Workflow::fake([Checkout::class]);
Workflow::fake([Checkout::class => WorkflowResult::cancelled($context, 'Out of stock.')]);
```

### 🧪 Testing

```bash
composer test
```

### 📝 License

Open-source under the [MIT license](LICENSE).

### 🚀 Thanks!
