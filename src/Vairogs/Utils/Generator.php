<?php declare(strict_types = 1);

namespace Vairogs\Utils;

use Exception;
use LogicException;
use Vairogs\Extra\Constants\Symbol;
use function array_rand;
use function count;
use function function_exists;
use function is_array;
use function random_int;
use function str_shuffle;
use function str_split;

final class Generator
{
    final public const RAND_BASIC = Symbol::EN_LOWERCASE . Symbol::EN_UPPERCASE . Symbol::DIGITS;
    final public const RAND_EXTENDED = self::RAND_BASIC . Symbol::SYMBOLS;

    private array $sets = [];

    private string $lowerCase = Symbol::EN_LOWERCASE;
    private string $upperCase = Symbol::EN_UPPERCASE;
    private string $digits = Symbol::DIGITS;
    private string $symbols = Symbol::SYMBOLS;
    private string $latvian = Symbol::LV_ALPHABET;

    /**
     * @throws LogicException
     * @throws Exception
     */
    public function generate(int $length = 32): string
    {
        if (empty($this->sets)) {
            throw new LogicException(message: 'At least one set must be used!');
        }

        $all = $unique = '';

        foreach ($this->sets as $set) {
            if (is_array(value: $split = str_split(string: $set))) {
                $unique .= $set[$this->tweak(array: $split)];
                $all .= $set;
            }
        }

        if (is_array(value: $all = str_split(string: $all))) {
            $setsCount = count(value: $this->sets);

            for ($i = 0; $i < $length - $setsCount; $i++) {
                $unique .= $all[$this->tweak(array: $all)];
            }
        }

        /* @noinspection NonSecureStrShuffleUsageInspection */
        return str_shuffle(string: $unique);
    }

    public function useLower(): self
    {
        $this->sets['lower'] = $this->lowerCase;

        return $this;
    }

    public function useUpper(): self
    {
        $this->sets['upper'] = $this->upperCase;

        return $this;
    }

    public function useDigits(): self
    {
        $this->sets['digits'] = $this->digits;

        return $this;
    }

    public function useSymbols(): self
    {
        $this->sets['symbols'] = $this->symbols;

        return $this;
    }

    public function useLatvian(): self
    {
        $this->sets['latvian'] = $this->latvian;

        return $this;
    }

    public function setLowerCase(string $lowerCase): self
    {
        $this->lowerCase = $lowerCase;

        return $this;
    }

    public function setUpperCase(string $upperCase): self
    {
        $this->upperCase = $upperCase;

        return $this;
    }

    public function setDigits(string $digits): self
    {
        $this->digits = $digits;

        return $this;
    }

    public function setSymbols(string $symbols): self
    {
        $this->symbols = $symbols;

        return $this;
    }

    public function setLatvian(string $latvian): self
    {
        $this->latvian = $latvian;

        return $this;
    }

    public function reset(): self
    {
        $this->sets = [];

        return $this;
    }

    public function getSets(): array
    {
        return $this->sets;
    }

    /**
     * @throws Exception
     */
    private function tweak(array $array): array|int|string
    {
        if (function_exists(function: 'random_int')) {
            return random_int(min: 0, max: count(value: $array) - 1);
        }

        return array_rand(array: $array);
    }
}
