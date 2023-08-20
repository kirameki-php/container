<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use Kirameki\Container\Exceptions\InvalidInstanceException;
use Kirameki\Container\Exceptions\ResolverNotFoundException;
use function is_a;

class Entry
{
    /**
     * @param Container $container
     * @param Tags $tags
     * @param string $id
     * @param Closure(Container): object|null $resolver
     * @param Lifetime $lifetime
     * @param list<Closure(mixed, Container): mixed> $extenders
     * @param object|null $instance
     */
    public function __construct(
        protected readonly Container $container,
        protected readonly Tags $tags,
        public readonly string $id,
        protected ?Closure $resolver = null,
        protected Lifetime $lifetime = Lifetime::Undefined,
        protected array $extenders = [],
        protected ?object $instance = null,
    )
    {
    }

    /**
     * @internal
     * @param Closure(Container): object $resolver
     * @param Lifetime $lifetime
     * @return void
     */
    public function setResolver(Closure $resolver, Lifetime $lifetime): void
    {
        $this->resolver = $resolver;
        $this->lifetime = $lifetime;
    }

    /**
     * @return object
     */
    public function getInstance(): object
    {
        $instance = $this->instance ?? $this->resolve();

        if ($this->lifetime === Lifetime::Singleton) {
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
     * @return list<string>
     */
    public function getTags(): array
    {
        return $this->tags->getById($this->id);
    }

    /**
     * @return object
     */
    protected function resolve(): object
    {
        if ($this->resolver === null) {
            throw new ResolverNotFoundException("{$this->id} is not set.", [
                'this' => $this,
            ]);
        }

        $instance = ($this->resolver)($this->container);
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
     * @return Lifetime
     */
    public function getLifetime(): Lifetime
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
