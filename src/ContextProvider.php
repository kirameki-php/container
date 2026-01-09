<?php declare(strict_types=1);

namespace Kirameki\Container;

class ContextProvider
{
    /**
     * @param class-string<object> $class
     * @param array<class-string, object> $provided
     * @param array<array-key, mixed> $arguments
     */
    public function __construct(
        protected string $class,
        protected ?array $provided = null,
        protected ?array $arguments = null,
    ) {
    }

    /**
     * @template TClass of object
     * @param class-string<TClass> $class
     * @param TClass $instance
     * @return $this
     */
    public function provide(string $class, mixed $instance): static
    {
        $this->provided ??= [];
        $this->provided[$class] = $instance;
        return $this;
    }

    /**
     * @param mixed ...$arguments
     * @return $this
     */
    public function pass(mixed ...$arguments): static
    {
        $this->arguments ??= [];
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
        return $this->arguments ?? [];
    }

    /**
     * @internal
     * @return array<class-string, object>
     */
    public function getProvided(): array
    {
        return $this->provided ?? [];
    }
}
