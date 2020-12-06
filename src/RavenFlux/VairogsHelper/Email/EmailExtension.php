<?php declare(strict_types = 1);

namespace RavenFlux\VairogsHelper;

use ReflectionException;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Vairogs\Component\Utils\Annotation;
use Vairogs\Component\Utils\Helper\Email;
use Vairogs\Component\Utils\Twig\Helper;
use Vairogs\Component\Utils\Twig\TwigTrait;
use Vairogs\Component\Utils\Vairogs;

class EmailExtension extends AbstractExtension
{
    use TwigTrait;

    private const SUFFIX = '_email_';

    /**
     * @return array
     * @throws ReflectionException
     */
    public function getFilters(): array
    {
        return $this->makeArray(Helper::getTwigAnnotations(Email::class, Annotation\TwigFilter::class), Vairogs::RAVEN . self::SUFFIX, TwigFilter::class);
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    public function getFunctions(): array
    {
        return $this->makeArray(Helper::getTwigAnnotations(Email::class, Annotation\TwigFunction::class), Vairogs::RAVEN . self::SUFFIX, TwigFilter::class);
    }
}
