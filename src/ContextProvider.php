<?php declare(strict_types=1);

namespace Kirameki\Container;

class ContextProvider
{
    /**
     * @param class-string<object> $class
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        protected string $class,
        protected array $arguments = [],
    )
    {
    }


    public function set(string $name, mixed $value): static
    {
        $this->arguments[$name] = $value;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}
