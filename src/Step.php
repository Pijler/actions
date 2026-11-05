<?php

namespace Pijler\Actions;

use Closure;
use Illuminate\Support\Reflector;
use ReflectionClass;
use ReflectionParameter;

class Step
{
    /**
     * The condition for the step to run.
     */
    protected Closure|bool $runIf = true;

    /**
     * The condition for the workflow to be cancelled when it reaches the step.
     */
    protected Closure|bool $cancelIf = false;

    /**
     * The reason reported when the step cancels the workflow.
     */
    protected string $cancelReason = '';

    /**
     * Whether the step is pushed onto the queue.
     */
    protected bool $queued = false;

    /**
     * Create a new step instance.
     */
    public function __construct(
        public readonly string $action,
    ) {}

    /**
     * Create a new step for the given action.
     */
    public static function make(string $action): static
    {
        return new static($action);
    }

    /**
     * Skip the step unless the given condition, which receives the context, passes.
     */
    public function runIf(Closure|bool $condition): static
    {
        $this->runIf = $condition;

        return $this;
    }

    /**
     * Cancel the workflow at this step if the given condition, which receives the context, passes.
     */
    public function cancelIf(Closure|bool $condition, string $reason = ''): static
    {
        $this->cancelIf = $condition;

        $this->cancelReason = $reason;

        return $this;
    }

    /**
     * Push the step onto the queue instead of executing it in sequence.
     */
    public function queued(bool $queued = true): static
    {
        $this->queued = $queued;

        return $this;
    }

    /**
     * Whether the step passes its run condition.
     */
    public function shouldRun(object $context): bool
    {
        return (bool) value($this->runIf, $context);
    }

    /**
     * Whether the step cancels the workflow.
     */
    public function shouldCancel(object $context): bool
    {
        return (bool) value($this->cancelIf, $context);
    }

    /**
     * Get the reason reported when the step cancels the workflow.
     */
    public function cancelReason(): string
    {
        return $this->cancelReason;
    }

    /**
     * Whether the step is pushed onto the queue.
     */
    public function isQueued(): bool
    {
        return $this->queued;
    }

    /**
     * Get the action parameters: the context goes to the constructor parameter accepting
     * its type or, failing that, named "context". The container resolves everything else.
     */
    public function parameters(object $context): array
    {
        $parameters = collect((new ReflectionClass($this->action))->getConstructor()?->getParameters());

        $parameter = $parameters->first(
            function (ReflectionParameter $parameter) use ($context) {
                return collect(Reflector::getParameterClassNames($parameter))
                    ->contains(function (string $class) use ($context) {
                        return $context instanceof $class;
                    });
            },
        ) ?? $parameters->firstWhere('name', 'context');

        return is_null($parameter) ? [] : [$parameter->getName() => $context];
    }
}
