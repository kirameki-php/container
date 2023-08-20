<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use Kirameki\Container\Exceptions\InvalidInstanceException;
use Kirameki\Container\Exceptions\ResolverNotFoundException;
use function is_a;

class Entry
{
    /** @var Lifetime|null */
    protected ?Lifetime $lifetime = null;

    /** @var Closure(Container, array<array-key, mixed>): object|null */
    protected ?Closure $resolver = null;

    /** @var list<Closure(mixed, Container): mixed> */
    protected array $extenders = [];

    /** @var object|null */
    protected mixed $instance = null;

    /**
     * @param Container $container
     * @param string $id
     */
    public function __construct(
        protected readonly Container $container,
        public readonly string $id,
    )
    {
    }

    /**
     * @param Closure(Container, array<array-key, mixed>): object $resolver
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
     * @return object
     */
    public function getInstance(array $args = []): object
    {
        if ($args !== []) {
            return $this->resolve($args);
        }

        $instance = $this->instance ?? $this->resolve($args);

        if ($this->lifetime !== Lifetime::Transient) {
            $this->setInstance($instance);
        }

        return $instance;
    }

    /**
     * @param object $instance
     * @return void
     */
    protected function setInstance(object $instance): void
    {
        $this->instance = $instance;
    }

    /**
     * Unset the instance if it exists.
     * Returns **true** if the instance existed and was unset, **false** otherwise.
     *
     * @return bool
     */
    public function unsetInstance(): bool
    {
        if ($this->instance !== null) {
            $this->instance = null;
            return true;
        }
        return false;
    }

    /**
     * Extender will be executed immediately if the instance already exists.
     *
     * @template TEntry of object
     * @param Closure(TEntry, Container): TEntry $extender
     * @return void
     */
    public function extend(Closure $extender): void
    {
        $this->extenders[] = $extender;

        /** @var TEntry $instance */
        $instance = $this->instance;
        if ($instance !== null) {
            $this->instance = $this->applyExtender($instance, $extender);
        }
    }

    /**
     * @param array<array-key, mixed> $args
     * @return object
     */
    protected function resolve(array $args): object
    {
        if ($this->resolver === null) {
            throw new ResolverNotFoundException("{$this->id} is not set.", [
                'this' => $this,
                'args' => $args,
            ]);
        }

        $instance = ($this->resolver)($this->container, $args);
        $this->assertInherited($instance);

        foreach ($this->extenders as $extender) {
            $instance = $this->applyExtender($instance, $extender);
        }

        return $instance;
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
    public function isExtended(): bool
    {
        return $this->extenders !== [];
    }

    /**
     * @return bool
     */
    public function isCached(): bool
    {
        return $this->instance !== null;
    }

    /**
     * @return Lifetime|null
     */
    public function getLifetime(): ?Lifetime
    {
        return $this->lifetime;
    }

    /**
     * @template TEntry of object
     * @param TEntry $instance
     * @param Closure(TEntry, Container): TEntry $extender
     * @return TEntry
     */
    protected function applyExtender(object $instance, Closure $extender): object
    {
        $instance = $extender($instance, $this->container);
        $this->assertInherited($instance);
        return $instance;
    }

    /**
     * @param mixed $instance
     * @return void
     */
    protected function assertInherited(mixed $instance): void
    {
        if (!is_a($instance, $this->id)) {
            throw new InvalidInstanceException("Expected: Instance of {$this->id} " . $instance::class . ' given.', [
                'this' => $this,
                'instance' => $instance,
            ]);
        }
    }
}
