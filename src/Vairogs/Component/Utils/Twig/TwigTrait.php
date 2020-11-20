<?php declare(strict_types = 1);

namespace Vairogs\Component\Utils\Twig;

use InvalidArgumentException;
use Twig\TwigFilter;
use Twig\TwigFunction;
use function in_array;
use function is_array;
use function sprintf;

trait TwigTrait
{
    /**
     * @param array $input
     * @param string $key
     * @param string $class
     * @return array
     */
    public function makeArray(array $input, string $key, string $class): array
    {
        if (!in_array($class, [TwigFilter::class, TwigFunction::class], true)) {
            throw new InvalidArgumentException(sprintf('Invalid type "%s":. Allowed types are filter and function', $class));
        }

        $output = [];
        $this->makeInput($input, $key, $input);
        foreach ($input as $call => $function) {
            if (is_array($function)) {
                $options = $function[2] ?? [];
                unset($function[2]);
                $output[] = new $class($call, $function, $options);
            } else {
                $output[] = new $class($call, [
                    $this,
                    $function,
                ]);
            }
        }

        return $output;
    }

    /**
     * @param array $input
     * @param string $key
     * @param array $output
     */
    private function makeInput(array $input, string $key, array &$output): void
    {
        $output = [];
        foreach ($input as $call => $function) {
            $output[sprintf('%s_%s', $key, $call)] = $function;
        }
    }
}
