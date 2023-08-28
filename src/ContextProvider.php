<?php declare(strict_types=1);

namespace Kirameki\Container;

class ContextProvider
{
    /**
     * @param class-string<object> $class
     * @param array<class-string, object> $injections
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        protected string $class,
        protected array $injections = [],
        protected array $arguments = [],
    )
    {
    }

    /**
     * @template TClass of object
     * @param class-string<TClass> $class
     * @param TClass $instance
     * @return $this
     */
    public function provide(string $class, mixed $instance): static
    {
        $this->injections[$class] = $instance;
        return $this;
    }

    /**
     * @param iterable<array-key, mixed> $arguments
     * @return $this
     */
    public function setArguments(iterable $arguments): static
    {
        foreach ($arguments as $name => $value) {
            $this->arguments[$name] = $value;
        }
        return $this;
    }

    /**
     * @internal
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @internal
     * @template T of object
     * @param class-string<T> $class
     * @return T|null
     */
    public function getClassOrNull(string $class): ?object
    {
        /** @var T|null */
        return $this->injections[$class] ?? null;
    }

}
