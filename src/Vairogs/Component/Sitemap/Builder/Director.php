<?php declare(strict_types = 1);

namespace Vairogs\Sitemap\Builder;

use InvalidArgumentException;
use function gettype;
use function sprintf;

class Director
{
    /**
     * @var mixed
     */
    private $buffer;

    public function __construct($buffer)
    {
        $this->buffer = $buffer;
    }

    /**
     * @param Builder $builder
     *
     * @return mixed
     */
    public function build(Builder $builder)
    {
        if (($actual = gettype($this->buffer)) !== $expected = $builder->getType()) {
            throw new InvalidArgumentException(sprintf('Director __constructor parameter must be %s, %s given', $expected, $actual));
        }

        $builder->start($this->buffer);
        $builder->build($this->buffer);
        $builder->end($this->buffer);

        return $this->buffer;
    }
}