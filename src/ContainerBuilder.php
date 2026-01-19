<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;

class ContainerBuilder
{
    /**
     * @param EntryCollection $entries
     * @param Injector $injector
     */
    public function __construct(
        protected EntryCollection $entries = new EntryCollection(),
        protected Injector $injector = new Injector(),
    ) {
    }

    /**
     * Register a given id.
     * Returns itself for chaining.
     *
     * @template T of object
     * @param class-string<T> $id
     * @param Lifetime $lifetime
     * @param Closure(Container): T|null $resolver
     * @return $this
     */
    public function set(string $id, Lifetime $lifetime, ?Closure $resolver = null): static
    {
        $this->entries->set(new EntryLazy($id, $lifetime, $resolver));
        return $this;
    }

    /**
     * Register a given class as transient.
     * Transient entries will create a new instance upon each resolution.
     * Returns itself for chaining.
     *
     * @template T of object
     * @param class-string<T> $id
     * @param Closure(Container): T|null $resolver
     * @return $this
     */
    public function transient(string $id, ?Closure $resolver = null): static
    {
        return $this->set($id, Lifetime::Transient, $resolver);
    }

    /**
     * Register a given class as a singleton.
     * Singletons will cache the result upon resolution.
     * Returns itself for chaining.
     *
     * @template T of object
     * @param class-string<T> $id
     * @param Closure(Container): T $resolver
     * @return $this
     */
    public function scoped(string $id, ?Closure $resolver = null): static
    {
        return $this->set($id, Lifetime::Scoped, $resolver);
    }

    /**
     * Register a given class as a singleton.
     * Singletons will cache the result upon resolution.
     * Returns itself for chaining.
     *
     * @template T of object
     * @param class-string<T> $id
     * @param Closure(Container): T|null $resolver
     * @return $this
     */
    public function singleton(string $id, ?Closure $resolver = null): static
    {
        return $this->set($id, Lifetime::Singleton, $resolver);
    }

    /**
     * Register a given class as a singleton.
     * The given instance will be returned for all subsequent resolutions.
     *
     * @template T of object
     * @param class-string<T> $id
     * @param T $instance
     * @return $this
     */
    public function instance(string $id, object $instance): static
    {
        $this->entries->set(new EntryFixed($id, $instance));
        return $this;
    }

    /**
     * Delete a given entry.
     * Returns **true** if entry is found, **false** otherwise.
     *
     * @template T of object
     * @param class-string<T> $id
     * @return bool
     */
    public function unset(string $id): bool
    {
        return $this->entries->unset($id);
    }

    /**
     * Check to see if a given class is bound to the container.
     * Returns **true** if bound, **false** otherwise.
     *
     * @template T of object
     * @param class-string<T> $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return $this->entries->has($id);
    }

    /**
     * Extend a registered class.
     * The given Closure must return an instance of the original class or else Exception is thrown.
     *
     * @template T of object
     * @param class-string<T> $id
     * @param Closure(T, Container): T $extender
     * @return $this
     */
    public function extend(string $id, Closure $extender): static
    {
        $this->entries->extend($id, $extender);
        return $this;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return ContextProvider
     */
    public function whenInjecting(string $class): ContextProvider
    {
        return $this->injector->setContext($class, new ContextProvider($class));
    }

    /**
     * Build the container.
     *
     * @return Container
     */
    public function build(): Container
    {
        return new Container($this->injector, $this->entries);
    }
}
