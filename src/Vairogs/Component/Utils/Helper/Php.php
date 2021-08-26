<?php declare(strict_types = 1);

namespace Vairogs\Component\Utils\Helper;

use Closure;
use InvalidArgumentException;
use JetBrains\PhpStorm\Pure;
use ReflectionClass;
use ReflectionException;
use Vairogs\Component\Utils\Annotation;
use function array_values;
use function class_exists;
use function filter_var;
use function interface_exists;
use function is_array;
use function is_bool;
use function method_exists;
use function sprintf;
use function strtolower;
use function ucfirst;
use const FILTER_VALIDATE_BOOL;

class Php
{
    /**
     * @Annotation\TwigFunction()
     */
    public static function hijackSet(object $object, string $property, mixed $value): void
    {
        self::call(function () use ($object, $property, $value): void {
            $object->$property = $value;
        }, $object);
    }

    /**
     * @noinspection PhpInconsistentReturnPointsInspection
     * @Annotation\TwigFunction()
     */
    public static function call(callable $function, object $clone, bool $return = false): mixed
    {
        $func = Closure::bind($function, $clone, $clone::class);

        if ($return) {
            return $func();
        }

        $func();
    }

    /**
     * @Annotation\TwigFunction()
     */
    public static function hijackGet(object $object, string $property): mixed
    {
        return self::call(fn () => $object->$property, $object, true);
    }

    /**
     * @Annotation\TwigFilter()
     */
    #[Pure] public static function boolval(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower((string)$value);

        if ('y' === $value) {
            return true;
        }

        if ('n' === $value) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /**
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @Annotation\TwigFunction()
     */
    public static function getClassConstantsValues(string $class): array
    {
        return array_values(self::getClassConstants($class));
    }

    /**
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @Annotation\TwigFunction()
     */
    public static function getClassConstants(string $class): array
    {
        if (class_exists($class) || interface_exists($class)) {
            return (new ReflectionClass($class))->getConstants();
        }

        throw new InvalidArgumentException(sprintf('Invalid class "%s"', $class));
    }

    /**
     * @Annotation\TwigFilter()
     * @Annotation\TwigFunction()
     */
    public static function getParameter(object|array $variable, mixed $key): mixed
    {
        if (is_array($variable)) {
            return $variable[$key];
        }

        if (method_exists($variable, 'get' . ucfirst($key))) {
            return $variable->{'get' . ucfirst($key)}();
        }

        return $variable->$key;
    }
}
