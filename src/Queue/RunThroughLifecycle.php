<?php

namespace Pijler\Actions\Queue;

use Closure;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Str;
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
     */
    public static function register(mixed $job): void
    {
        if (is_object($job)) {
            static::ensureEncrypted($job);
        }

        if (! $job instanceof Action || ! property_exists($job, 'middleware')) {
            return;
        }

        $middleware = collect($job->middleware);

        $registered = $middleware->contains(function (object $middleware) {
            return $middleware instanceof self;
        });

        if (! $registered) {
            $job->middleware = $middleware->push(new self)->all();
        }
    }

    /**
     * Require encryption from the job and from each job in its chain up to the last action with
     * sensitive parameters, because the next jobs of a chain are stored inside the payload.
     */
    protected static function ensureEncrypted(object $job): void
    {
        $unencrypted = collect([$job::class])
            ->merge(collect(data_get($job, 'chained', []))->map(function (string $serialized) {
                return Str::match('/^O:\d+:"([^"]+)"/', $serialized);
            }))
            ->reverse()
            ->skipUntil(function (string $class) {
                return is_subclass_of($class, Action::class) && filled(Redactor::sensitive($class));
            })
            ->first(function (string $class) {
                return ! is_subclass_of($class, ShouldBeEncrypted::class);
            });

        if (filled($unencrypted)) {
            throw new LogicException("The queued job [{$unencrypted}] must implement ShouldBeEncrypted, because it would store sensitive parameters as plain text.");
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
