<?php

namespace Pijler\Actions\Queue;

use Closure;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use LogicException;
use Pijler\Actions\Action;
use Pijler\Actions\Support\Lifecycle;
use Pijler\Actions\Support\Redactor;

class RunThroughLifecycle
{
    /**
     * Add the middleware to a queued action, as it is written to the queue.
     *
     * The action keeps its own middleware() method, and this runs closest to its handle().
     * An action with sensitive parameters must be encrypted, or they would be stored as plain text.
     */
    public static function register(mixed $job): void
    {
        if (! $job instanceof Action || ! property_exists($job, 'middleware')) {
            return;
        }

        if (filled(Redactor::sensitive($job::class)) && ! $job instanceof ShouldBeEncrypted) {
            throw new LogicException('The queued action ['.$job::class.'] has sensitive parameters and must implement ShouldBeEncrypted.');
        }

        $middleware = collect($job->middleware);

        if ($middleware->doesntContain(function (object $middleware) {
            return $middleware instanceof self;
        })) {
            $job->middleware = $middleware->push(new self)->all();
        }
    }

    /**
     * Give a queued action the same fake and events as an executed one.
     */
    public function handle(Action $action, Closure $next): mixed
    {
        return Lifecycle::run($action::class, Redactor::constructorValues($action), function () use ($next, $action) {
            return $next($action);
        });
    }
}
