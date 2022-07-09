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
    protected array $entries = [];

    /**
     * @var array<class-string, ReflectionClass<object>>
     */
    protected array $reflectionCache = [];

    /**
     * @template TEntry of object
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
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @return Entry<TEntry>
     */
    public function entry(string $id): Entry
    {
        return $this->entries[$id];
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @return TEntry
     */
    public function get(string $id): mixed
    {
        return $this->entries[$id]->getInstance();
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param TEntry $entry
     * @return void
     */
    public function instance(string $id, mixed $entry): void
    {
        $this->set($id, new InstanceEntry($id, $entry));
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param mixed ...$args
     * @return TEntry
     */
    public function make(string $id, mixed ...$args): object
    {
        $noArgs = count($args) === 0;

        if ($noArgs && $this->has($id)) {
            $this->get($id);
        }

        $class = $this->reflectionCache[$id] ??= new ReflectionClass($id);

        if ($noArgs) {
            $args = $this->resolveArguments($class);
        }

        /** @var TEntry */
        return $class->newInstance(...$args);
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param Entry<TEntry> $entry
     * @return void
     */
    protected function set(string $id, Entry $entry): void
    {
        $this->entries[$id] = $entry;
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param Closure(static): TEntry $entry
     * @return void
     */
    public function singleton(string $id, mixed $entry): void
    {
        $this->set($id, new FactoryEntry($id, $entry, [$this], true));
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
                : $this->make($paramClass);
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
