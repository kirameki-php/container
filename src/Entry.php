<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use Kirameki\Core\Exceptions\LogicException;
use function is_a;

/**
 * @template TEntry of object
 */
class Entry
{
    /**
     * @var array<int, Closure(TEntry, Container): TEntry>
     */
    protected array $extenders = [];

    /**
     * @var TEntry|null
     */
    protected mixed $cached = null;

    /**
     * @param Container $container
     * @param class-string<TEntry> $class
     * @param Closure(Container): TEntry $resolver
     * @param bool $cacheable
     */
    public function __construct(
        protected readonly Container $container,
        public readonly string $class,
        protected readonly Closure $resolver,
        public readonly bool $cacheable,
    )
    {
    }

    /**
     * @return TEntry
     */
    public function getInstance(): mixed
    {
        $instance = $this->cached;

        if ($instance === null) {
            $instance = $this->resolve();
            if ($this->cacheable) {
                $this->cached = $instance;
            }
        }

        return $instance;
    }

    /**
     * @return TEntry
     */
    protected function resolve(): object
    {
        $instance = ($this->resolver)($this->container);
        $this->assertInherited($instance);

        foreach ($this->extenders as $extender) {
            $instance = $extender($instance, $this->container);
            $this->assertInherited($instance);
        }

        return $instance;
    }

    /**
     * @param Closure(TEntry, Container): TEntry $resolver
     * @return $this
     */
    public function extend(Closure $resolver): static
    {
        $this->extenders[] = $resolver;

        if ($this->isCached()) {
            $this->reset();
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isCached(): bool
    {
        return $this->cached !== null;
    }

    /**
     * @return $this
     */
    public function reset(): static
    {
        $this->cached = null;
        return $this;
    }

    /**
     * @param mixed $instance
     * @return void
     */
    protected function assertInherited(mixed $instance): void
    {
        if (!is_a($instance, $this->class)) {
            throw new LogicException('Instance of ' . $this->class . ' expected. ' . $instance::class . ' given.');
        }
    }
}
