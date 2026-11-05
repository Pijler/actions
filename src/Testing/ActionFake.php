<?php

namespace Pijler\Actions\Testing;

use Closure;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;

class ActionFake
{
    /**
     * The fake value or callback of each faked action.
     */
    protected Collection $actions;

    /**
     * The action and parameters of each execution.
     */
    protected Collection $executed;

    /**
     * Create a new action fake instance.
     */
    public function __construct(array $actions = [])
    {
        $this->executed = collect();

        $this->actions = collect($actions)->mapWithKeys(
            function (mixed $value, int|string $key) {
                return is_int($key) ? [$value => null] : [$key => $value];
            },
        );
    }

    /**
     * Record the execution, then run the fake or, for actions not faked, the real action.
     */
    public function execute(string $action, array $parameters, Closure $real): mixed
    {
        $this->executed->push(['action' => $action, 'parameters' => $parameters]);

        if ($this->actions->has($action)) {
            return value($this->actions->get($action), $parameters);
        }

        return $real();
    }

    /**
     * Assert the action was executed, optionally with parameters passing the given test.
     */
    public function assertExecuted(string $action, ?Closure $callback = null): void
    {
        Assert::assertNotEmpty(
            $this->matching($action, $callback)->all(),
            "The expected [{$action}] action was not executed.",
        );
    }

    /**
     * Assert the action was not executed, optionally with parameters passing the given test.
     */
    public function assertNotExecuted(string $action, ?Closure $callback = null): void
    {
        Assert::assertEmpty(
            $this->matching($action, $callback)->all(),
            "The unexpected [{$action}] action was executed.",
        );
    }

    /**
     * Assert the action was executed the given number of times.
     */
    public function assertExecutedTimes(string $action, int $times): void
    {
        $count = $this->matching($action)->count();

        Assert::assertSame(
            $times,
            $count,
            "The expected [{$action}] action was executed {$count} times instead of {$times} times.",
        );
    }

    /**
     * Get the recorded parameters of the action passing the given test.
     */
    protected function matching(string $action, ?Closure $callback = null): Collection
    {
        return $this->executed
            ->where('action', $action)
            ->pluck('parameters')
            ->filter(function (array $parameters) use ($callback) {
                return is_null($callback) || $callback($parameters);
            });
    }
}
