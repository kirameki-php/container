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
use function assert;
use function class_exists;
use function implode;
use function interface_exists;
use function is_a;
use function is_int;

class Injector
{
    /**
     * @param array<string, ContextProvider> $contexts
     */
    public function __construct(
        protected array $contexts = [],
    ) {
    }

    /**
     * Only used when calling inject to check for circular dependencies.
     *
     * @var array<class-string, null>
     */
    protected array $processingClass = [];

    /**
     * @param class-string $class
     * @param ContextProvider $context
     * @return ContextProvider
     */
    public function setContext(string $class, ContextProvider $context): ContextProvider
    {
        return $this->contexts[$class] = $context;
    }

    /**
     * Instantiate class and inject parameters if given class is not registered, or resolve if registered.
     *
     * @template TEntry of object
     * @param Container $container
     * @param class-string<TEntry> $class
     * @param array<array-key, mixed> $args
     * @return TEntry
     */
    public function create(Container $container, string $class, array $args): object
    {
        $reflection = new ReflectionClass($class);

        $args = $this->mergeContextualArgs($reflection, $args);
        $params = $reflection->getConstructor()?->getParameters() ?? [];
        $remainingParams = $this->filterOutArgsFromParams($reflection, $params, $args);
        $injections = $this->getContextualInjections($class, $params);

        $this->checkForCircularReference($class);
        $this->processingClass[$class] = null;
        try {
            foreach ($this->getArguments($container, $reflection, $remainingParams, $injections) as $name => $arg) {
                $args[$name] = $arg;
            }
        } finally {
            unset($this->processingClass[$class]);
        }

        return new $class(...$args);
    }

    /**
     * @param ReflectionClass<object> $class
     * @param array<array-key, mixed> $args
     * @return array<array-key, mixed>
     */
    protected function mergeContextualArgs(ReflectionClass $class, array $args): array
    {
        return array_key_exists($class->name, $this->contexts)
            ? $this->contexts[$class->name]->getArguments() + $args
            : $args;
    }

    /**
     * @param class-string $class
     * @param list<ReflectionParameter> $params
     * @return array<class-string, object>
     */
    protected function getContextualInjections(string $class, array $params): array
    {
        if (!array_key_exists($class, $this->contexts)) {
            return [];
        }

        $injections = $this->contexts[$class]->getProvided();

        if ($injections === []) {
            return [];
        }

        $clone = $injections;
        foreach ($params as $param) {
            $type = $param->getType();
            if ($type !== null && is_a($type, ReflectionNamedType::class)) {
                unset($clone[$type->getName()]);
            }
        }

        if ($clone !== []) {
            $missingClasses = implode(', ', array_keys($clone));
            throw new InjectionException("Provided injections: {$missingClasses} do not exist for class: {$class}.", [
                'class' => $class,
                'params' => $params,
                'missingInjections' => $clone,
            ]);
        }

        return $injections;
    }

    /**
     * @param Container $container
     * @param Closure $closure
     * @param array<array-key, mixed> $args
     * @return mixed
     */
    public function invoke(Container $container, Closure $closure, array $args = []): mixed
    {
        $reflection = new ReflectionFunction($closure);

        $scopedClass = $reflection->getClosureScopeClass();

        $params = $reflection->getParameters();
        $params = $this->filterOutArgsFromParams($scopedClass, $params, $args);
        foreach ($this->getArguments($container, $scopedClass, $params) as $name => $arg) {
            $args[$name] = $arg;
        }

        return $closure(...$args);
    }

    /**
     * @param ReflectionClass<object>|null $class
     * @param array<array-key, mixed> $args
     * @param list<ReflectionParameter> $params
     * @return array<int, ReflectionParameter>
     */
    protected function filterOutArgsFromParams(?ReflectionClass $class, array $params, array $args):array
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
     * @param Container $container
     * @param ReflectionClass<object>|null $declaredClass
     * @param array<int, ReflectionParameter> $params
     * @param array<class-string, object> $injections
     * @return array<string, mixed>
     */
    protected function getArguments(
        Container $container,
        ?ReflectionClass $declaredClass,
        array $params,
        array $injections = [],
    ): array
    {
        $args = [];
        foreach ($params as $param) {
            $arg = $this->getArgument($container, $declaredClass, $param, $injections);
            if ($arg !== null) {
                $args[$param->name] = $arg;
            }
        }
        return $args;
    }

    /**
     * @param Container $container
     * @param ReflectionClass<object>|null $declaredClass
     * @param ReflectionParameter $param
     * @param array<class-string, object> $injections
     * @return object|null
     */
    protected function getArgument(
        Container $container,
        ?ReflectionClass $declaredClass,
        ReflectionParameter $param,
        array $injections,
    ): ?object
    {
        if ($param->isVariadic()) {
            return null;
        }

        $type = $param->getType();

        if ($type === null) {
            if ($param->isDefaultValueAvailable()) {
                return null;
            }

            $className = $declaredClass->name ?? 'Non-Class';
            $paramName = $param->name;
            throw new InjectionException("[{$className}] Argument: \${$paramName} must be a class or have a default value.", [
                'declaredClass' => $declaredClass,
                'param' => $param,
            ]);
        }

        if (!is_a($type, ReflectionNamedType::class) || $type->isBuiltin()) {
            if ($param->isDefaultValueAvailable()) {
                return null;
            }

            $className = $declaredClass->name ?? 'Non-Class';
            $typeName = (string) $type;
            $paramName = $param->name;
            $typeCategory = match (true) {
                $type instanceof ReflectionUnionType => 'Union types',
                $type instanceof ReflectionIntersectionType => 'Intersection types',
                $type instanceof ReflectionNamedType && $type->isBuiltin() => 'Built-in types',
                // @codeCoverageIgnoreStart
                default => 'Unknown type',
                // @codeCoverageIgnoreEnd
            };
            throw new InjectionException("[{$className}] Invalid type on argument: {$typeName} \${$paramName}. {$typeCategory} are not allowed.", [
                'declaredClass' => $declaredClass,
                'param' => $param,
            ]);
        }

        $paramClass = $this->revealClass($declaredClass, $type->getName());

        if (array_key_exists($paramClass, $injections)) {
            return $injections[$paramClass];
        }

        if ($container->has($paramClass)) {
            return $container->get($paramClass);
        }

        if (class_exists($paramClass) && !$param->isDefaultValueAvailable()) {
            return $container->make($paramClass);
        }

        return null;
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
