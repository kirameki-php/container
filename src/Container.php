<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use Kirameki\Core\Exceptions\LogicException;
use Psr\Container\ContainerInterface;
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

class Container implements ContainerInterface
{
    /**
     * Registered entries.
     *
     * @var array<class-string, mixed>
     */
    protected array $registered = [];

    /**
     * Only used when calling inject to check for circular dependencies.
     *
     * @var array<class-string, null>
     */
    protected array $processingDependencies = [];

    /**
     * @var array<int, Closure(string): void>
     */
    protected array $resolvingCallbacks = [];

    /**
     * @var array<int, Closure(string, mixed): void>
     */
    protected array $resolvedCallbacks = [];

    /**
     * Resolve given class.
     *
     * Resolving and Resolved callbacks are invoked on resolution.
     * For singleton entries, callbacks are only invoked once.
     *
     * Returns the resolved instance.
     *
     * @template TEntry of object
     * @param class-string<TEntry>|string $id
     * @return ($id is class-string<TEntry> ? TEntry : mixed)
     */
    public function get(string $id): mixed
    {
        $entry = $this->getEntry($id);
        $invokeCallbacks = !$entry->isCached();

        if($invokeCallbacks) {
            foreach ($this->resolvingCallbacks as $callback) {
                $callback($id);
            }
        }

        $instance =  $entry->getInstance();

        if ($invokeCallbacks) {
            foreach ($this->resolvedCallbacks as $callback) {
                $callback($id, $instance);
            }
        }

        return $instance;
    }

    /**
     * Check to see if a given class is registered.
     *
     * Returns **true** if class exists, **false** otherwise.
     *
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->registered);
    }

    /**
     * Register a given class.
     *
     * Returns itself for chaining.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param Closure(Container): TEntry $resolver
     * @return $this
     */
    public function bind(string $id, Closure $resolver): static
    {
        return $this->setEntry($id, new Entry($this, $id, $resolver, false));
    }

    /**
     * Register a given class as a singleton.
     *
     * Singletons will cache the result upon resolution.
     *
     * Returns itself for chaining.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param Closure(Container): TEntry $resolver
     * @return $this
     */
    public function singleton(string $id, Closure $resolver): static
    {
        return $this->setEntry($id, new Entry($this, $id, $resolver, true));
    }

    /**
     * Delete a given entry.
     *
     * Returns **true** if entry is found, **false** otherwise.
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool
    {
        if ($this->has($id)) {
            unset($this->registered[$id]);
            return true;
        }
        return false;
    }

    /**
     * Instantiate class and inject parameters if given class is not registered, or resolve if registered.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @param mixed ...$args
     * @return TEntry
     */
    public function make(string $class, mixed ...$args): object
    {
        if (count($args) === 0 && $this->has($class)) {
            return $this->get($class);
        }

        $reflection = new ReflectionClass($class);

        $this->checkForCircularReference($class);
        $this->processingDependencies[$class] = null;

        $params = $reflection->getConstructor()?->getParameters() ?? [];
        $params = $this->filterOutArgsFromParameters($reflection, $params, $args);
        foreach ($this->getInjectingArguments($reflection, $params) as $name => $arg) {
            $args[$name] = $arg;
        }

        unset($this->processingDependencies[$class]);

        /** @var TEntry */
        return $reflection->newInstance(...$args);
    }

    /**
     * @template TResult
     * @param Closure(): TResult $closure
     * @param mixed ...$args
     * @return TResult
     */
    public function call(Closure $closure, mixed ...$args): mixed
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
     * @template TEntry of object
     * @param class-string<TEntry>|string $id
     * @return ($id is class-string<TEntry> ? Entry<TEntry> : Entry<object>)
     */
    protected function getEntry(string $id): Entry
    {
        if (!$this->has($id)) {
            throw new LogicException("{$id} is not registered.", [
                'id' => $id,
            ]);
        }
        return $this->registered[$id];
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @param Entry<TEntry> $entry
     * @return $this
     */
    protected function setEntry(string $class, Entry $entry): static
    {
        if ($this->has($class)) {
            throw new LogicException("Cannot register class: {$class}. Entry already exists.", [
                'class' => $class,
                'entry' => $entry,
            ]);
        }
        $this->registered[$class] = $entry;
        return $this;
    }

    /**
     * Set a callback which is called when a class is resolving.
     *
     * @param Closure(string): void $callback
     * @return void
     */
    public function resolving(Closure $callback): void
    {
        $this->resolvingCallbacks[] = $callback;
    }

    /**
     * Set a callback which is called when a class is resolved.
     *
     * @param Closure(string, mixed): void $callback
     * @return void
     */
    public function resolved(Closure $callback): void
    {
        $this->resolvedCallbacks[] = $callback;
    }

    /**
     * Extend a registered class.
     *
     * The given Closure must return an instance of the original class or else Exception is thrown.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param Closure(TEntry, Container): TEntry $resolver
     * @return $this
     */
    public function extend(string $id, Closure $resolver): static
    {
        if (!$this->has($id)) {
            throw new LogicException($id . ' cannot be extended since it is not defined.', [
                'id' => $id,
                'resolver' => $resolver,
            ]);
        }
        $this->getEntry($id)->extend($resolver);
        return $this;
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
                    throw new LogicException("Argument with position: {$key} does not exist for class: {$class?->name}.", [
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
                    throw new LogicException("Argument with name: {$key} does not exist for class: {$class?->name}.", [
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
            $arg = $this->getInjectingArgument($declaredClass, $param);
            if ($arg !== null) {
                $args[$param->name] = $arg;
            }
        }
        return $args;
    }

    /**
     * @param ReflectionClass<object>|null $declaredClass
     * @param ReflectionParameter $param
     * @return object|null
     */
    protected function getInjectingArgument(?ReflectionClass $declaredClass, ReflectionParameter $param): mixed
    {
        if ($param->isVariadic()) {
            return null;
        }

        $type = $param->getType();

        if ($type === null) {
            if ($param->isDefaultValueAvailable()) {
                return null;
            }

            $className = $declaredClass?->name ?? 'Non-Class';
            $paramName = $param->name;
            throw new LogicException("[{$className}] Argument: \${$paramName} must be a class or have a default value.", [
                'declaredClass' => $declaredClass,
                'param' => $param,
            ]);
        }

        if (!is_a($type, ReflectionNamedType::class) || $type->isBuiltin()) {
            if ($param->isDefaultValueAvailable()) {
                return null;
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
            throw new LogicException("[{$className}] Invalid type on argument: {$typeName} \${$paramName}. {$typeCategory} are not allowed.", [
                'declaredClass' => $declaredClass,
                'param' => $param,
            ]);
        }

        $paramClass = $this->revealClass($declaredClass, $type->getName());
        return $this->make($paramClass);
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
        if (!array_key_exists($class, $this->processingDependencies)) {
            return;
        }

        $path = implode(' -> ', [...array_keys($this->processingDependencies), $class]);
        throw new LogicException('Circular Dependency detected! ' . $path, [
            'path' => $path,
            'class' => $class,
        ]);
    }
}
