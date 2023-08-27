<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use Kirameki\Container\Exceptions\InjectionException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use function array_key_exists;
use function array_keys;
use function implode;
use function is_a;

class Injector
{
    public function __construct(
        protected readonly Container $container,
    )
    {
    }

    /**
     * Only used when calling inject to check for circular dependencies.
     *
     * @var array<class-string, null>
     */
    protected array $processingClass = [];


    /**
     * Instantiate class and inject parameters if given class is not registered, or resolve if registered.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @param array<array-key, mixed> $args
     * @return TEntry
     */
    public function instantiate(string $class, array $args): object
    {
        $reflection = new ReflectionClass($class);

        $this->checkForCircularReference($class);
        $this->processingClass[$class] = null;

        try {
            $params = $reflection->getConstructor()?->getParameters() ?? [];
            $params = $this->filterOutArgsFromParameters($reflection, $params, $args);
            foreach ($this->getInjectingArguments($reflection, $params) as $name => $arg) {
                $args[$name] = $arg;
            }
        } finally {
            unset($this->processingClass[$class]);
        }

        /** @var TEntry */
        return $reflection->newInstance(...$args);
    }

    /**
     * @template TResult
     * @param Closure(): TResult $closure
     * @param array<array-key, mixed> $args
     * @return TResult
     */
    public function invoke(Closure $closure, array $args): mixed
    {
        $reflection = new ReflectionFunction($closure);

        $scopedClass = $reflection->getClosureScopeClass();

        $params = $reflection->getParameters();
        $params = $this->filterOutArgsFromParameters($scopedClass, $params, $args);
        foreach ($this->getInjectingArguments($scopedClass, $params) as $name => $arg) {
            $args[$name] = $arg;
        }

        return $closure(...$args);
    }

    /**
     * @param ReflectionClass<object>|null $class
     * @param array<array-key, mixed> $args
     * @param list<ReflectionParameter> $params
     * @return list<ReflectionParameter>
     */
    protected function filterOutArgsFromParameters(?ReflectionClass $class, array $params, array $args):array
    {
        $paramsMap = null;
        $isVariadic = false;
        foreach ($args as $key => $arg) {
            if (is_int($key)) {
                if (array_key_exists($key, $params)) {
                    $isVariadic |= $params[$key]->isVariadic();
                    unset($params[$key]);
                } elseif (!$isVariadic) {
                    throw new InjectionException("Argument with position: {$key} does not exist for class: {$class?->name}.", [
                        'class' => $class,
                        'params' => $params,
                        'args' => $args,
                        'position' => $key,
                    ]);
                }
            } else {
                if ($paramsMap === null) {
                    $paramsMap = [];
                    foreach ($params as $param) {
                        $paramsMap[$param->name] = $param;
                    }
                }
                if (array_key_exists($key, $paramsMap)) {
                    unset($params[$paramsMap[$key]->getPosition()]);
                } else {
                    throw new InjectionException("Argument with name: {$key} does not exist for class: {$class?->name}.", [
                        'class' => $class,
                        'params' => $params,
                        'args' => $args,
                        'key' => $key,
                    ]);
                }
            }
        }
        return $params;
    }

    /**
     * @param ReflectionClass<object>|null $declaredClass
     * @param list<ReflectionParameter> $params
     * @return array<string, mixed>
     */
    protected function getInjectingArguments(?ReflectionClass $declaredClass, array $params): array
    {
        $args = [];
        foreach ($params as $param) {
            $this->setInjectingArgument($args, $declaredClass, $param);
        }
        return $args;
    }

    /**
     * @param array<array-key, mixed> $args
     * @param ReflectionClass<object>|null $declaredClass
     * @param ReflectionParameter $param
     * @return void
     */
    protected function setInjectingArgument(
        array &$args,
        ?ReflectionClass $declaredClass,
        ReflectionParameter $param,
    ): void
    {
        if ($param->isVariadic()) {
            return;
        }

        $type = $param->getType();

        if ($type === null) {
            if ($param->isDefaultValueAvailable()) {
                return;
            }

            $className = $declaredClass?->name ?? 'Non-Class';
            $paramName = $param->name;
            throw new InjectionException("[{$className}] Argument: \${$paramName} must be a class or have a default value.", [
                'declaredClass' => $declaredClass,
                'param' => $param,
            ]);
        }

        if (!is_a($type, ReflectionNamedType::class) || $type->isBuiltin()) {
            if ($param->isDefaultValueAvailable()) {
                return;
            }

            $className = $declaredClass?->name ?? 'Non-Class';
            $typeName = (string) $type;
            $paramName = $param->name;
            $typeCategory = match (true) {
                $type instanceof ReflectionUnionType => 'Union types',
                $type instanceof ReflectionIntersectionType => 'Intersection types',
                $type instanceof ReflectionNamedType && $type->isBuiltin() => 'Built-in types',
                default => 'Unknown type',
            };
            throw new InjectionException("[{$className}] Invalid type on argument: {$typeName} \${$paramName}. {$typeCategory} are not allowed.", [
                'declaredClass' => $declaredClass,
                'param' => $param,
            ]);
        }

        $paramClass = $this->revealClass($declaredClass, $type->getName());
        $args[$param->name] = $this->container->make($paramClass);
    }

    /**
     * @param ReflectionClass<object>|null $declaredClass
     * @param string $typeName
     * @return class-string<object>
     */
    protected function revealClass(?ReflectionClass $declaredClass, string $typeName): string
    {
        if ($declaredClass !== null) {
            if ($typeName === 'self') {
                $typeName = $declaredClass->name;
            }

            if ($typeName === 'parent') {
                if ($parentReflection = $declaredClass->getParentClass()) {
                    $typeName = $parentReflection->name;
                }
            }
        }

        assert(
            class_exists($typeName) || interface_exists($typeName),
            "Class: {$typeName} does not exist.",
        );

        return $typeName;
    }

    /**
     * @param class-string $class
     * @return void
     */
    protected function checkForCircularReference(string $class): void
    {
        if (!array_key_exists($class, $this->processingClass)) {
            return;
        }

        $path = implode(' -> ', [...array_keys($this->processingClass), $class]);
        throw new InjectionException('Circular Dependency detected! ' . $path, [
            'path' => $path,
            'class' => $class,
        ]);
    }
}
