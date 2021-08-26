<?php declare(strict_types = 1);

namespace Vairogs\Extra\Specification;

class OrSpecification extends CompositeSpecification
{
    public function __construct(private SpecificationInterface $one, private SpecificationInterface $other)
    {
    }

    public function isSatisfiedBy($expectedValue, $actualValue = null): bool
    {
        return $this->one->isSatisfiedBy($expectedValue) || $this->other->isSatisfiedBy($expectedValue);
    }
}
