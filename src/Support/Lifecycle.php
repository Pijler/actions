<?php

namespace Pijler\Actions\Support;

use Closure;
use Pijler\Actions\Events\ActionFailed;
use Pijler\Actions\Events\ActionFinished;
use Pijler\Actions\Events\ActionStarted;
use Pijler\Actions\Exceptions\WorkflowCancelled;
use Pijler\Actions\Testing\ActionFake;
use Throwable;

class Lifecycle
{
    /**
     * Run the action through the active fake, if any, and its events.
     */
    public static function run(string $action, array $parameters, Closure $handle): mixed
    {
        $fake = static::fake();

        $report = function () use ($action, $parameters, $handle) {
            return static::report($action, $parameters, $handle);
        };

        return filled($fake) ? $fake->execute($action, $parameters, $report) : $report();
    }

    /**
     * Get the active fake, which lives in the container so it never outlives the application.
     */
    public static function fake(): ?ActionFake
    {
        return app()->bound(ActionFake::class) ? app(ActionFake::class) : null;
    }

    /**
     * Handle the action, reporting its lifecycle through events.
     */
    protected static function report(string $action, array $parameters, Closure $handle): mixed
    {
        $redacted = Redactor::parameters($action, $parameters);

        event(new ActionStarted($action, $redacted));

        try {
            $result = $handle();
        } catch (WorkflowCancelled $exception) {
            event(new ActionFinished($action, $redacted));

            throw $exception;
        } catch (Throwable $exception) {
            event(new ActionFailed($action, $redacted, $exception));

            throw $exception;
        }

        event(new ActionFinished($action, $redacted));

        return $result;
    }
}
