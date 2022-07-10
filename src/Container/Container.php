<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use function array_key_exists;

class Container
{
    /**
     * @var array<class-string, mixed>
     */
    protected array $registered = [];

    /**
     * @var array<Closure(string): void>
     */
    protected array $resolvingCallbacks = [];

    /**
     * @var array<Closure(string, mixed): void>
     */
    protected array $resolvedCallbacks = [];

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @return Entry<TEntry>
     */
    public function entry(string $class): Entry
    {
        return $this->registered[$class];
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @return TEntry
     */
    public function resolve(string $class): mixed
    {
        $entry = $this->entry($class);
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
    public function has(string $class): bool
    {
        return array_key_exists($class, $this->registered);
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
        if ($this->has($class)) {
            unset($this->registered[$class]);
            return true;
        }
        return false;
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
     * @param Closure(mixed): void $callback
     * @return void
     */
    public function resolving(Closure $callback): void
    {
        $this->resolvingCallbacks[] = $callback;
    }

    /**
     * @param Closure(mixed): void $callback
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
        $this->entry($class)->extend($resolver);
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

        if ($noArgs && $this->has($class)) {
            $this->resolve($class);
        }

        $classReflection = new ReflectionClass($class);

        if ($noArgs) {
            $args = $this->getInjectingArguments($classReflection);
        }

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
            return null;
        }

        if ($paramClass = $this->revealTrueClass($classReflection, $type)) {
            return $this->has($paramClass)
                ? $this->resolve($paramClass)
                : $this->inject($paramClass);
        }
        return null;
    }

    /**
     * @param ReflectionClass<object> $classReflection
     * @param ReflectionType $type
     * @return class-string|null
     */
    private function revealTrueClass(ReflectionClass $classReflection, ReflectionType $type): ?string
    {
        if (!($type instanceof ReflectionNamedType)) {
            return null;
        }

        if ($type->isBuiltin()) {
            return null;
        }

        $className = $type->getName();

        if ($className === 'self') {
            return $classReflection->getName();
        }

        if ($className === 'parent') {
            return ($classReflection->getParentClass() ?: null)?->getName();
        }

        /** @var class-string */
        return $className;
    }
}
