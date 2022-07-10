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
     * @var array<class-string, Entry<mixed>>
     */
    protected array $registered = [];

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @return Entry<TEntry>
     */
    public function entry(string $id): Entry
    {
        return $this->registered[$id];
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @return TEntry
     */
    public function get(string $id): mixed
    {
        return $this->registered[$id]->getInstance();
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->registered);
    }

    /**
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
     * @template TEntry of object
     * @param class-string<TEntry> $id
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
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param Entry<TEntry> $entry
     * @return $this
     */
    protected function setEntry(string $id, Entry $entry): static
    {
        $this->registered[$id] = $entry;
        return $this;
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @return $this
     */
    protected function rebind(string $id): static
    {
        $this->entry($id)->rebind();
        return $this;
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param Closure(TEntry, Container): TEntry $resolver
     * @return $this
     */
    public function extend(string $id, Closure $resolver): static
    {
        $this->entry($id)->extend($resolver);
        return $this;
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param mixed ...$args
     * @return TEntry
     */
    public function resolve(string $id, mixed ...$args): object
    {
        $noArgs = count($args) === 0;

        if ($noArgs && $this->has($id)) {
            $this->get($id);
        }

        $class = new ReflectionClass($id);

        if ($noArgs) {
            $args = $this->resolveArguments($class);
        }

        /** @var TEntry */
        return $class->newInstance(...$args);
    }

    /**
     * @param ReflectionClass<object> $class
     * @return array<int, mixed>
     */
    protected function resolveArguments(ReflectionClass $class): array
    {
        $args = [];
        $params = $class->getConstructor()?->getParameters() ?? [];
        foreach ($params as $param) {
            $arg = $this->resolveArgument($class, $param);
            if ($arg !== null) {
                $args[] = $arg;
            }
        }
        return $args;
    }

    /**
     * @param ReflectionClass<object> $class
     * @param ReflectionParameter $param
     * @return mixed
     */
    protected function resolveArgument(ReflectionClass $class, ReflectionParameter $param): mixed
    {
        $type = $param->getType();

        if ($type === null) {
            return null;
        }

        if ($paramClass = $this->revealTrueClass($class, $type)) {
            return $this->has($paramClass)
                ? $this->get($paramClass)
                : $this->resolve($paramClass);
        }
        return null;
    }

    /**
     * @param ReflectionClass<object> $class
     * @param ReflectionType $type
     * @return class-string|null
     */
    private function revealTrueClass(ReflectionClass $class, ReflectionType $type): ?string
    {
        if (!($type instanceof ReflectionNamedType)) {
            return null;
        }

        if ($type->isBuiltin()) {
            return null;
        }

        $className = $type->getName();

        if ($className === 'self') {
            return $class->getName();
        }

        if ($className === 'parent') {
            return ($class->getParentClass() ?: null)?->getName();
        }

        /** @var class-string */
        return $className;
    }
}
