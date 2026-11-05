<?php

namespace Pijler\Actions;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use LogicException;
use Pijler\Actions\Attributes\Transactional;
use Pijler\Actions\Exceptions\WorkflowCancelled;

abstract class Workflow extends Action
{
    /**
     * Execute the workflow.
     *
     * A workflow faked with anything but a result completes with the given context,
     * so the calling code reads the same result it gets from a real workflow.
     */
    public static function execute(...$args): WorkflowResult
    {
        $result = parent::execute(...$args);

        return $result instanceof WorkflowResult ? $result : WorkflowResult::completed(collect($args)->first());
    }

    /**
     * Run the steps in sequence, inside a transaction if the workflow asks for one.
     */
    final public function handle(): WorkflowResult
    {
        $context = $this->context();

        $transaction = static::classAttribute(Transactional::class);

        try {
            if (is_null($transaction)) {
                $this->runSteps($context);
            } else {
                DB::connection($transaction->connection)->transaction(
                    function () use ($context, $transaction) {
                        return $this->runSteps($context, $transaction);
                    },
                );
            }
        } catch (WorkflowCancelled $exception) {
            return WorkflowResult::cancelled($context, $exception->getMessage());
        }

        return WorkflowResult::completed($context);
    }

    /**
     * Run each step, stopping at the first one that cancels the workflow.
     */
    private function runSteps(object $context, ?Transactional $transaction = null): void
    {
        collect($this->steps())
            ->map(function (Step|string $step) {
                return $step instanceof Step ? $step : Step::make($step);
            })
            ->each(function (Step $step) use ($context, $transaction) {
                if ($step->shouldCancel($context)) {
                    throw new WorkflowCancelled($step->cancelReason());
                }

                if ($step->shouldRun($context)) {
                    $this->runStep($step, $context, $transaction);
                }
            });
    }

    /**
     * Execute the step, or queue it, which only happens once the transaction commits.
     */
    private function runStep(Step $step, object $context, ?Transactional $transaction): void
    {
        $action = $step->action;

        $parameters = $step->parameters($context);

        if ($step->isQueued() && ! is_subclass_of($action, ShouldQueue::class)) {
            throw new LogicException("The queued step [{$action}] must implement ShouldQueue.");
        }

        if (! $step->isQueued()) {
            $action::execute(...$parameters);
        } elseif (is_null($transaction)) {
            $action::dispatch(...$parameters);
        } else {
            DB::connection($transaction->connection)->afterCommit(function () use ($action, $parameters) {
                $action::dispatch(...$parameters);
            });
        }
    }

    /**
     * Get the context shared by the steps.
     */
    abstract protected function context(): object;

    /**
     * Get the steps of the workflow.
     */
    abstract protected function steps(): array;
}
