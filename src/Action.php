<?php

namespace Pijler\Actions;

use JsonSerializable;
use Pijler\Actions\Support\Lifecycle;
use Pijler\Actions\Support\Redactor;
use Pijler\Actions\Testing\ActionFake;
use ReflectionClass;

/**
 * Subclasses define a handle() method, which the container can inject dependencies into.
 * It must be public in queued actions, because the queue worker calls it from outside.
 */
abstract class Action implements JsonSerializable
{
    /**
     * Execute the action instance.
     */
    public static function execute(...$args): mixed
    {
        $parameters = static::normalize(static::class, $args);

        return Lifecycle::run(static::class, $parameters, function () use ($parameters) {
            return app()->call(resolve(static::class, $parameters)->handle(...));
        });
    }

    /**
     * Execute the action with the given arguments if the given truth test passes.
     */
    public static function executeIf(bool $boolean, ...$args): mixed
    {
        return $boolean ? static::execute(...$args) : null;
    }

    /**
     * Execute the action with the given arguments unless the given truth test passes.
     */
    public static function executeUnless(bool $boolean, ...$args): mixed
    {
        return $boolean ? null : static::execute(...$args);
    }

    /**
     * Fake the given actions in tests.
     *
     * Entries are either an action class, which returns null, or a map of action class
     * to a value or a callback receiving the parameters. Other actions run for real.
     */
    public static function fake(array $actions = []): ActionFake
    {
        return tap(new ActionFake($actions), function (ActionFake $fake) {
            app()->instance(ActionFake::class, $fake);
        });
    }

    /**
     * Run the given callable with actions faked, then put back the previous fake.
     */
    public static function fakeFor(callable $callable, array $actions = []): mixed
    {
        $previous = Lifecycle::fake();

        static::fake($actions);

        try {
            return $callable();
        } finally {
            filled($previous) ? app()->instance(ActionFake::class, $previous) : static::restore();
        }
    }

    /**
     * Whether actions are currently being faked.
     */
    public static function isFake(): bool
    {
        return filled(Lifecycle::fake());
    }

    /**
     * Stop faking actions.
     */
    public static function restore(): void
    {
        app()->forgetInstance(ActionFake::class);
    }

    /**
     * Get the action properties that are safe to log, with sensitive values hidden.
     */
    public function jsonSerialize(): array
    {
        return Redactor::properties($this);
    }

    /**
     * Get the action properties shown by dump(), dd() and var_dump().
     */
    public function __debugInfo(): array
    {
        return Redactor::properties($this);
    }

    /**
     * Get an attribute of the action class or of its closest parent declaring it.
     */
    protected static function classAttribute(string $attribute): ?object
    {
        return collect([static::class, ...class_parents(static::class)])
            ->flatMap(function (string $class) use ($attribute) {
                return (new ReflectionClass($class))->getAttributes($attribute);
            })
            ->first()
            ?->newInstance();
    }

    /**
     * Map positional arguments to constructor parameter names, as the container expects.
     *
     * Example: normalize(Foo::class, ['John', 'age' => 30]) → ['name' => 'John', 'age' => 30]
     */
    protected static function normalize(string $className, array $args): array
    {
        $constructor = (new ReflectionClass($className))->getConstructor();

        $names = collect($constructor?->getParameters())->pluck('name');

        return collect($args)
            ->filter(function (mixed $value, int|string $key) use ($names) {
                return is_string($key) || $names->has($key);
            })
            ->mapWithKeys(function (mixed $value, int|string $key) use ($names) {
                return [is_int($key) ? $names->get($key) : $key => $value];
            })
            ->all();
    }
}
