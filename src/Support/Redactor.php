<?php

namespace Pijler\Actions\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use ReflectionClass;
use ReflectionObject;
use ReflectionParameter;
use ReflectionProperty;
use SensitiveParameter;

class Redactor
{
    /**
     * The value shown in place of sensitive data.
     */
    public const string MASK = '[sensitive]';

    /**
     * The sensitive names of each class already inspected.
     */
    private static array $sensitive = [];

    /**
     * Get the names of the constructor parameters marked with #[\SensitiveParameter].
     */
    public static function sensitive(string $class): array
    {
        return self::$sensitive[$class] ??= collect((new ReflectionClass($class))->getConstructor()?->getParameters())
            ->filter(function (ReflectionParameter $parameter) {
                return filled($parameter->getAttributes(SensitiveParameter::class));
            })
            ->map(function (ReflectionParameter $parameter) {
                return $parameter->getName();
            })
            ->values()
            ->all();
    }

    /**
     * Hide the sensitive values of the given constructor parameters.
     */
    public static function parameters(string $class, array $parameters): array
    {
        $sensitive = collect(self::sensitive($class));

        return collect($parameters)
            ->map(function (mixed $value, string $name) use ($sensitive) {
                return $sensitive->contains($name) ? self::MASK : $value;
            })
            ->all();
    }

    /**
     * Get the values of the promoted constructor parameters of the given object.
     */
    public static function constructorValues(object $object): array
    {
        return collect((new ReflectionObject($object))->getConstructor()?->getParameters())
            ->filter(function (ReflectionParameter $parameter) {
                return $parameter->isPromoted();
            })
            ->mapWithKeys(function (ReflectionParameter $parameter) {
                return [
                    $parameter->getName() => $parameter->getDeclaringClass()->getProperty($parameter->getName()),
                ];
            })
            ->filter(function (ReflectionProperty $property) use ($object) {
                return $property->isInitialized($object);
            })
            ->map(function (ReflectionProperty $property) use ($object) {
                return $property->getValue($object);
            })
            ->all();
    }

    /**
     * Get the properties of the given object with the sensitive values hidden,
     * leaving out the ones that only configure the queue.
     */
    public static function properties(object $object): array
    {
        $sensitive = collect(self::sensitive($object::class));

        return collect((new ReflectionObject($object))->getProperties())
            ->reject(function (ReflectionProperty $property) use ($object) {
                return $property->isStatic()
                    || ! $property->isInitialized($object)
                    || property_exists(Queueable::class, $property->getName())
                    || property_exists(InteractsWithQueue::class, $property->getName());
            })
            ->mapWithKeys(function (ReflectionProperty $property) use ($sensitive, $object) {
                return [
                    $property->getName() => $sensitive->contains($property->getName()) ? self::MASK : $property->getValue($object),
                ];
            })
            ->all();
    }
}
