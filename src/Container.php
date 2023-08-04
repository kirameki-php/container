<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use LogicException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use function array_key_exists;
use function array_keys;
use function implode;
use function is_a;
use function strtr;

class Container
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
     * @var array<int, Closure(class-string): void>
     */
    protected array $resolvingCallbacks = [];

    /**
     * @var array<int, Closure(class-string, mixed): void>
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
     * @param class-string<TEntry> $class
     * @return TEntry
     */
    public function resolve(string $class): mixed
    {
        $entry = $this->getEntry($class);
        $invokeCallbacks = !$entry->isCached();

        if($invokeCallbacks) {
            foreach ($this->resolvingCallbacks as $callback) {
                $callback($class);
            }
        }

        $instance =  $entry->getInstance();

        if ($invokeCallbacks) {
            foreach ($this->resolvedCallbacks as $callback) {
                $callback($class, $instance);
            }
        }

        return $instance;
    }

    /**
     * Check to see if a given class is registered.
     *
     * Returns **true** if class exists, **false** otherwise.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @return bool
     */
    public function contains(string $class): bool
    {
        return array_key_exists($class, $this->registered);
    }

    /**
     * Check to see if a given class is missing.
     *
     * Returns **false** if class exists, **true** otherwise.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @return bool
     */
    public function notContains(string $class): bool
    {
        return !$this->contains($class);
    }

    /**
     * Register a given class.
     *
     * Returns itself for chaining.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @param Closure(Container): TEntry $resolver
     * @return $this
     */
    public function bind(string $class, Closure $resolver): static
    {
        return $this->setEntry($class, new Entry($this, $class, $resolver, false));
    }

    /**
     * Register a given class as a singleton.
     *
     * Singletons will cache the result upon resolution.
     *
     * Returns itself for chaining.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @param Closure(Container): TEntry $resolver
     * @return $this
     */
    public function singleton(string $class, Closure $resolver): static
    {
        return $this->setEntry($class, new Entry($this, $class, $resolver, true));
    }

    /**
     * Delete a given entry.
     *
     * Returns **true** if entry is found, **false** otherwise.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @return bool
     */
    public function delete(string $class): bool
    {
        if ($this->contains($class)) {
            unset($this->registered[$class]);
            return true;
        }
        return false;
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @return Entry<TEntry>
     */
    protected function getEntry(string $class): Entry
    {
        if ($this->notContains($class)) {
            throw new LogicException("{$class} is not registered.");
        }
        return $this->registered[$class];
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @param Entry<TEntry> $entry
     * @return $this
     */
    protected function setEntry(string $class, Entry $entry): static
    {
        if ($this->contains($class)) {
            throw new LogicException("Cannot register class: {$class}. Entry already exists.");
        }
        $this->registered[$class] = $entry;
        return $this;
    }

    /**
     * Set a callback which is called when a class is resolving.
     *
     * @param Closure(class-string): void $callback
     * @return void
     */
    public function resolving(Closure $callback): void
    {
        $this->resolvingCallbacks[] = $callback;
    }

    /**
     * Set a callback which is called when a class is resolved.
     *
     * @param Closure(class-string, mixed): void $callback
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
     * @param class-string<TEntry> $class
     * @param Closure(TEntry, Container): TEntry $resolver
     * @return $this
     */
    public function extend(string $class, Closure $resolver): static
    {
        if ($this->notContains($class)) {
            throw new LogicException($class . ' cannot be extended since it is not defined.');
        }
        $this->getEntry($class)->extend($resolver);
        return $this;
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
        $noArgs = count($args) === 0;

        if ($noArgs && $this->contains($class)) {
            return $this->resolve($class);
        }

        $classReflection = new ReflectionClass($class);

        // Check for circular references
        if (array_key_exists($class, $this->processingDependencies)) {
            $path = implode(' -> ', [...array_keys($this->processingDependencies), $class]);
            throw new LogicException('Circular Dependency detected! ' . $path);
        }
        $this->processingDependencies[$class] = null;

        if ($noArgs) {
            $params = $classReflection->getConstructor()?->getParameters() ?? [];
            $args = $this->getInjectingArguments($params);
        }

        unset($this->processingDependencies[$class]);

        /** @var TEntry */
        return $classReflection->newInstance(...$args);
    }

    /**
     * @template TResult
     * @param Closure(): TResult $closure
     * @return TResult
     */
    public function call(Closure $closure): mixed
    {
        $reflection = new ReflectionFunction($closure);
        $parameters = $reflection->getParameters();

        $args = $this->getInjectingArguments($parameters);

        return $closure(...$args);
    }

    /**
     * @param list<ReflectionParameter> $params
     * @return array<int, mixed>
     */
    protected function getInjectingArguments(array $params): array
    {
        return array_filter(
            array_map($this->getInjectingArgument(...), $params),
            fn ($arg) => $arg !== null,
        );
    }

    /**
     * @param ReflectionParameter $param
     * @return mixed
     */
    protected function getInjectingArgument(ReflectionParameter $param): mixed
    {
        if ($param->isVariadic()) {
            return null;
        }

        $class = $param->getDeclaringClass();
        $type = $param->getType();

        if ($type === null) {
            if ($param->isDefaultValueAvailable()) {
                return null;
            }
            throw new LogicException(strtr('[%class] Argument: $%name must be a class or have a default value.', [
                '%class' => $class?->getName() ?? 'Non-Class',
                '%name' => $param->getName(),
            ]));
        }

        if (!is_a($type, ReflectionNamedType::class) || $type->isBuiltin()) {
            if ($param->isDefaultValueAvailable()) {
                return null;
            }

            $errorMessage = '[%class] Invalid type on argument: %type $%name. ' .
                            'Union/intersect/built-in types are not allowed.';

            throw new LogicException(strtr($errorMessage, [
                '%class' => $class?->getName() ?? 'Non-Class',
                '%type' => (string) $type,
                '%name' => $param->getName(),
            ]));
        }

        $paramClass = $class !== null
            ? $this->revealTrueClass($class, $type->getName())
            : $type->getName();

        assert(
            class_exists($paramClass) || interface_exists($paramClass),
            strtr('Class: %class does not exist.', ['%class' => $paramClass])
        );

        return $this->contains($paramClass)
            ? $this->resolve($paramClass)
            : $this->make($paramClass);
    }

    /**
     * @param ReflectionClass<object> $classReflection
     * @param class-string|string $className
     * @return class-string<object>
     */
    protected function revealTrueClass(
        ReflectionClass $classReflection,
        string $className,
    ): string
    {
        if ($className === 'self') {
            $className = $classReflection->getName();
        }

        if ($className === 'parent') {
            if ($parentReflection = $classReflection->getParentClass()) {
                $className = $parentReflection->getName();
            }
        }

        return $className;
    }
}
