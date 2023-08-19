<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use Kirameki\Core\Exceptions\LogicException;
use function is_a;

/**
 * @template-covariant TEntry of object
 */
class Entry
{
    /** @var Lifetime */
    public Lifetime $lifetime = Lifetime::Transient;

    /** @var Closure(Container, array<array-key, mixed>): TEntry|null */
    protected ?Closure $resolver = null;

    /** @var array<int, Closure(TEntry, Container, array<array-key, mixed>): TEntry> */
    protected array $extenders = [];

    /** @var TEntry|null */
    protected mixed $cached = null;

    /**
     * @param Container $container
     * @param class-string<TEntry> $class
     */
    public function __construct(
        protected readonly Container $container,
        public readonly string $class,
    )
    {
    }

    /**
     * @param Closure(Container, array<array-key, mixed>): TEntry $resolver
     * @param Lifetime $lifetime
     * @return void
     */
    public function setResolver(Closure $resolver, Lifetime $lifetime): void
    {
        $this->resolver = $resolver;
        $this->lifetime = $lifetime;
    }

    /**
     * @param array<array-key, mixed> $args
     * @return TEntry
     */
    public function getInstance(array $args = []): object
    {
        $instance = $this->cached;

        if ($instance === null || $args !== []) {
            $instance = $this->resolve($args);
            if ($this->lifetime === Lifetime::Singleton) {
                $this->cached = $instance;
            }
        }

        return $instance;
    }

    /**
     * @param array<array-key, mixed> $args
     * @return TEntry
     */
    protected function resolve(array $args): object
    {
        if ($this->resolver === null) {
            throw new LogicException("{$this->class} is not registered.", [
                'this' => $this,
            ]);
        }

        $instance = ($this->resolver)($this->container, $args);
        $this->assertInherited($instance);

        foreach ($this->extenders as $extender) {
            $instance = $extender($instance, $this->container, $args);
            $this->assertInherited($instance);
        }

        return $instance;
    }

    /**
     * @param Closure(TEntry, Container, array<array-key, mixed>): TEntry $extender
     * @return void
     */
    public function extend(Closure $extender): void
    {
        $this->extenders[] = $extender;

        if ($this->isCached()) {
            $this->reset();
        }
    }

    /**
     * @return bool
     */
    public function isResolvable(): bool
    {
        return $this->resolver !== null;
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
