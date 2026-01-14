<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use Kirameki\Container\Exceptions\InvalidInstanceException;
use Kirameki\Container\Exceptions\ResolverNotFoundException;
use function in_array;
use function is_a;

/**
 * @template T of object = object
 */
class Entry
{
    /**
     * @param class-string<T> $id
     * @param Lifetime $lifetime
     * @param Closure(Container): T|null $resolver
     * @param T|null $instance
     * @param list<Closure(T, Container): T> $extenders
     */
    public function __construct(
        public readonly string $id,
        public Lifetime $lifetime = Lifetime::Transient,
        protected ?Closure $resolver = null,
        protected ?object $instance = null,
        protected array $extenders = [],
    ) {
    }

    /**
     * @internal
     * @param Closure(Container): T $resolver
     * @param Lifetime $lifetime
     * @return void
     */
    public function setResolver(Closure $resolver, Lifetime $lifetime): void
    {
        $this->resolver = $resolver;
        $this->lifetime = $lifetime;
    }

    /**
     * @param Container $container
     * @return T
     */
    public function getInstance(Container $container): object
    {
        $instance = $this->instance ?? $this->resolve($container);

        if (in_array($this->lifetime, [Lifetime::Scoped, Lifetime::Singleton], true)) {
            $this->instance = $instance;
        }

        return $instance;
    }

    /**
     * @param T $instance
     * @return void
     */
    public function setInstance(object $instance): void
    {
        $this->instance = $instance;
        $this->lifetime = Lifetime::Singleton;
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
     * @param Closure(T, Container): T $extender
     * @return void
     */
    public function extend(Closure $extender): void
    {
        $this->extenders[] = $extender;
    }

    /**
     * @param Container $container
     * @return T
     */
    protected function resolve(Container $container): object
    {
        if ($this->resolver === null) {
            throw new ResolverNotFoundException("{$this->id} is not set.", [
                'this' => $this,
            ]);
        }

        $instance = ($this->resolver)($container);
        $this->assertInherited($instance);

        foreach ($this->extenders as $extender) {
            $instance = $extender($instance, $container);
            $this->assertInherited($instance);
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
     * @param mixed $instance
     * @return void
     */
    protected function assertInherited(mixed $instance): void
    {
        if (!is_a($instance, $this->id)) {
            throw new InvalidInstanceException("Expected: instance of {$this->id}. Got: " . $instance::class . '.', [
                'this' => $this,
                'instance' => $instance,
            ]);
        }
    }
}
