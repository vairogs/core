<?php declare(strict_types = 1);

namespace Vairogs\Component\Cache\Utils;

use Doctrine\Common\Annotations\Reader;
use InvalidArgumentException;
use JsonSerializable;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use function end;
use function explode;
use function is_array;
use function method_exists;
use function reset;
use function sprintf;

class Attribute
{
    /**
     * @param Reader $reader
     * @param TokenStorageInterface|null $tokenStorage
     */
    public function __construct(protected Reader $reader, protected ?TokenStorageInterface $tokenStorage = null)
    {
    }

    /**
     * @param KernelEvent $kernelEvent
     * @param string $class
     * @return array
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function getAttributes(KernelEvent $kernelEvent, string $class): array
    {
        $input = [];
        if (null !== ($annotation = $this->getAnnotation($kernelEvent, $class))) {
            $request = $kernelEvent->getRequest();

            $user = null;
            if (null !== $this->tokenStorage && $this->tokenStorage->getToken() && $object = $this->tokenStorage->getToken()
                    ->getUser()) {
                if (is_array($object)) {
                    $user = $object;
                } else if ($object instanceof JsonSerializable) {
                    $user = $object->jsonSerialize();
                } else if (method_exists($object, 'toArray')) {
                    $user = $object->toArray();
                } else if (method_exists($object, '__toArray')) {
                    $user = $object->__toArray();
                }
            }

            switch ($annotation->getStrategy()) {
                case Strategy::GET:
                    $input = $request->attributes->get('_route_params') + $request->query->all();
                    break;
                case Strategy::POST:
                    $input = $request->request->all();
                    break;
                case Strategy::USER:
                    if (null !== $user) {
                        $input = $user;
                    }
                    break;
                case Strategy::MIXED:
                    $input = [
                        Strategy::GET => $request->attributes->get('_route_params') + $request->query->all(),
                        Strategy::POST => $request->request->all(),
                    ];
                    if (null !== $user) {
                        $input[Strategy::USER] = $user;
                    }
                    break;
                case Strategy::ALL:
                    $input = $request->attributes->get('_route_params') + $request->query->all() + $request->request->all();
                    if (null !== $user) {
                        /** @noinspection AdditionOperationOnArraysInspection */
                        $input += $user;
                    }
                    break;
                default:
                    throw new InvalidArgumentException(sprintf('Unknown strategy: %s', $annotation->getStrategy()));
            }
        }

        return $input;
    }

    /**
     * @param KernelEvent $kernelEvent
     * @param string $class
     *
     * @return object|null
     * @throws ReflectionException
     */
    public function getAnnotation(KernelEvent $kernelEvent, string $class): ?object
    {
        $controller = $this->getController($kernelEvent);
        $reflectionClass = new ReflectionClass(reset($controller));

        if ($method = $reflectionClass->getMethod(end($controller))) {
            return $this->reader->getMethodAnnotation($method, $class);
        }

        return null;
    }

    /**
     * @param KernelEvent $kernelEvent
     *
     * @return array
     */
    public function getController(KernelEvent $kernelEvent): array
    {
        if (is_array($controller = explode('::', $kernelEvent->getRequest()
                ->get('_controller'), 2)) && isset($controller[1])) {
            return $controller;
        }

        return [];
    }
}
