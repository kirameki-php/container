<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;
use function array_key_exists;

class Container
{
    /**
     * @var array<class-string, Entry<mixed>>
     */
    protected array $entries = [];

    /**
     * @var array<class-string, ReflectionClass<object>>
     */
    protected array $reflectionCache = [];

    /**
     * @template TEntry
     * @param class-string<TEntry> $id
     * @return TEntry
     */
    public function get(string $id): mixed
    {
        return $this->entries[$id]->getInstance();
    }

    /**
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }

    /**
     * @template TEntry
     * @param class-string<TEntry> $id
     * @param TEntry $entry
     * @return void
     */
    public function instance(string $id, mixed $entry): void
    {
        $this->set($id, $entry);
    }

    /**
     * @template TEntry
     * @param class-string<TEntry> $id
     * @param TEntry $entry
     * @param bool $cached
     * @return void
     */
    protected function set(string $id, mixed $entry, bool $cached = false): void
    {
        $this->entries[$id] = $entry instanceof Closure
            ? new FactoryEntry($id, $entry, [$this], $cached)
            : new InstanceEntry($id, $entry);
    }

    /**
     * @template TEntry
     * @param class-string<TEntry> $id
     * @param TEntry|Closure(static): TEntry $entry
     * @return void
     */
    public function singleton(string $id, mixed $entry): void
    {
        $this->set($id, $entry, true);
    }

    /**
     * @template TEntry
     * @param class-string<TEntry> $id
     * @return bool
     */
    public function delete(string $id): bool
    {
        if ($this->has($id)) {
            unset($this->entries[$id]);
            return true;
        }
        return false;
    }

    /**
     * @template TEntry
     * @param class-string<TEntry> $id
     * @return Entry<TEntry>
     */
    public function entry(string $id): Entry
    {
        return $this->entries[$id];
    }

    /**
     * @return array<class-string, Entry<mixed>>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @template TEntry
     * @param class-string<TEntry> $id
     * @return TEntry
     */
    public function resolve(string $id): mixed
    {
        if ($this->has($id)) {
            return $this->get($id);
        }

        $class = $this->reflectionCache[$id] ??= new ReflectionClass($id);

        $args = $this->resolveArguments($class);

        /** @var TEntry */
        return $class->newInstance(...$args);
    }

    /**
     * @param ReflectionClass<object> $class
     * @return array<string, mixed>
     */
    protected function resolveArguments(ReflectionClass $class): array
    {
        $args = [];
        $params = $class->getConstructor()?->getParameters() ?? [];
        foreach ($params as $param) {
            $type = $param->getType();
            if ($type !== null && $paramClass = $this->revealArgumentClass($class, $type)) {
                $args[$param->getName()] = $this->has($paramClass)
                    ? $this->get($paramClass)
                    : $this->resolve($paramClass);
            }
        }
        return $args;
    }

    /**
     * @param ReflectionClass<object> $class
     * @param ReflectionType $type
     * @return class-string|null
     */
    private function revealArgumentClass(ReflectionClass $class, ReflectionType $type): ?string
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
