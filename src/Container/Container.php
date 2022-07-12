<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use LogicException;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use function array_key_exists;
use function array_keys;
use function assert;
use function dump;
use function implode;
use function sprintf;
use function strtr;

class Container
{
    /**
     * @var array<class-string, mixed>
     */
    protected array $registered = [];

    /**
     * @var array<class-string, null>
     */
    protected array $processing = [];

    /**
     * @var array<Closure(class-string): void>
     */
    protected array $resolvingCallbacks = [];

    /**
     * @var array<Closure(class-string, mixed): void>
     */
    protected array $resolvedCallbacks = [];

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @return TEntry
     */
    public function resolve(string $class): mixed
    {
        $entry = $this->getEntry($class);
        $callEvent = !$entry->isCached();

        if($callEvent) {
            foreach ($this->resolvingCallbacks as $callback) {
                $callback($class);
            }
        }

        $instance =  $entry->getInstance();

        if ($callEvent) {
            foreach ($this->resolvedCallbacks as $callback) {
                $callback($class, $instance);
            }
        }

        return $instance;
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @return bool
     */
    public function contains(string $class): bool
    {
        return array_key_exists($class, $this->registered);
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @return bool
     */
    public function notContains(string $class): bool
    {
        return !$this->contains($class);
    }

    /**
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
        $this->registered[$class] = $entry;
        return $this;
    }

    /**
     * @param Closure(class-string): void $callback
     * @return void
     */
    public function resolving(Closure $callback): void
    {
        $this->resolvingCallbacks[] = $callback;
    }

    /**
     * @param Closure(class-string, mixed): void $callback
     * @return void
     */
    public function resolved(Closure $callback): void
    {
        $this->resolvedCallbacks[] = $callback;
    }

    /**
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
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @param mixed ...$args
     * @return TEntry
     */
    public function inject(string $class, mixed ...$args): object
    {
        $noArgs = count($args) === 0;

        if ($noArgs && $this->contains($class)) {
            return $this->resolve($class);
        }

        $classReflection = new ReflectionClass($class);

        // Check for circular references
        if (array_key_exists($class, $this->processing)) {
            $path = implode(' -> ', [...array_keys($this->processing), $class]);
            throw new LogicException('Circular Dependency detected! ' . $path);
        }
        $this->processing[$class] = null;

        if ($noArgs) {
            $args = $this->getInjectingArguments($classReflection);
        }

        unset($this->processing[$class]);

        /** @var TEntry */
        return $classReflection->newInstance(...$args);
    }

    /**
     * @param ReflectionClass<object> $classReflection
     * @return array<int, mixed>
     */
    protected function getInjectingArguments(ReflectionClass $classReflection): array
    {
        $args = [];
        $params = $classReflection->getConstructor()?->getParameters() ?? [];
        foreach ($params as $param) {
            $arg = $this->getInjectingArgument($classReflection, $param);
            if ($arg !== null) {
                $args[] = $arg;
            }
        }
        return $args;
    }

    /**
     * @param ReflectionClass<object> $classReflection
     * @param ReflectionParameter $param
     * @return mixed
     */
    protected function getInjectingArgument(ReflectionClass $classReflection, ReflectionParameter $param): mixed
    {
        $type = $param->getType();

        if ($type === null) {
            if ($param->isDefaultValueAvailable()) {
                return null;
            }
            throw new LogicException(strtr('Argument $%name for %class must be a class or have a default value.', [
                '%name' => $param->name,
                '%class' => $classReflection->name,
            ]));
        }

        if (!($type instanceof ReflectionNamedType) || $type->isBuiltin()) {
            if ($param->isDefaultValueAvailable()) {
                return null;
            }

            $errorMessage = '%class Invalid type on argument: %type $%name. ' .
                            'Union/intersect/built-in types are not allowed.';

            throw new LogicException(strtr($errorMessage, [
                '%type' => (string) $type,
                '%name' => $param->name,
                '%class' => $classReflection->name,
            ]));
        }

        $paramClass = $this->revealTrueClass($classReflection, $type);
        return $this->contains($paramClass)
            ? $this->resolve($paramClass)
            : $this->inject($paramClass);
    }

    /**
     * @param ReflectionClass<object> $classReflection
     * @param ReflectionNamedType $type
     * @return class-string
     */
    private function revealTrueClass(ReflectionClass $classReflection, ReflectionNamedType $type): string
    {
        $className = $type->getName();

        if ($className === 'self') {
            return $classReflection->getName();
        }

        if ($className === 'parent') {
            if ($parentReflection = $classReflection->getParentClass()) {
                return $parentReflection->getName();
            }
        }

        /** @var class-string */
        return $className;
    }
}
