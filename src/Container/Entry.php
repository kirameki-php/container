<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use Webmozart\Assert\Assert;

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
        protected Container $container,
        protected string $class,
        protected Closure $resolver,
        protected bool $cacheable,
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

        Assert::notNull($instance);

        return $instance;
    }

    /**
     * @return TEntry
     */
    protected function resolve(): mixed
    {
        $instance = ($this->resolver)($this->container);

        foreach ($this->extenders as $extender) {
            $instance = $extender($instance, $this->container);
            Assert::isInstanceOf($instance, $this->class);
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
}
