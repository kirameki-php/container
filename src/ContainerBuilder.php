<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use Kirameki\Container\Events\Injected;
use Kirameki\Container\Events\Injecting;
use Kirameki\Container\Events\Resolved;
use Kirameki\Container\Events\Resolving;
use Kirameki\Container\Exceptions\DuplicateEntryException;
use Kirameki\Event\EventHandler;
use function array_key_exists;

class ContainerBuilder
{
    /**
     * @param Injector $injector
     * @param array<string, Entry> $entries
     * @param array<string, null> $scopedEntryIds
     */
    public function __construct(
        protected Injector $injector = new Injector(),
        protected array $entries = [],
        protected array $scopedEntryIds = [],
    ) {
    }

    /**
     * Register a given id.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param Closure(Container): TEntry|null $resolver
     * @param Lifetime $lifetime
     * @return void
     */
    public function set(string $id, ?Closure $resolver = null, Lifetime $lifetime = Lifetime::Transient): void
    {
        $this->setEntry($id)->setResolver(
            $resolver ?? static fn(Container $c) => $c->inject($id),
            $lifetime,
        );
    }

    /**
     * Register a given class as a singleton.
     *
     * Singletons will cache the result upon resolution.
     *
     * Returns itself for chaining.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param Closure(Container): TEntry $resolver
     * @return void
     */
    public function scoped(string $id, ?Closure $resolver = null): void
    {
        $this->set($id, $resolver, Lifetime::Scoped);
        $this->scopedEntryIds[$id] = null;
    }

    /**
     * Register a given class as a singleton.
     *
     * Singletons will cache the result upon resolution.
     *
     * Returns itself for chaining.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param Closure(Container): TEntry|null $resolver
     * @return void
     */
    public function singleton(string $id, ?Closure $resolver = null): void
    {
        $this->set($id, $resolver, Lifetime::Singleton);
    }

    /**
     * Register a given class as a singleton.
     *
     * The given instance will be returned for all subsequent resolutions.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param TEntry $instance
     * @return void
     */
    public function instance(string $id, object $instance): void
    {
        $this->setEntry($id)->setInstance($instance);
    }

    /**
     * Delete a given entry.
     *
     * Returns **true** if entry is found, **false** otherwise.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @return bool
     */
    public function unset(string $id): bool
    {
        if ($this->has($id)) {
            unset($this->entries[$id]);
            unset($this->scopedEntryIds[$id]);
            return true;
        }
        return false;
    }

    /**
     * Check to see if a given class is bound to the container.
     *
     * Returns **true** if bound, **false** otherwise.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }

    /**
     * Extend a registered class.
     *
     * The given Closure must return an instance of the original class or else Exception is thrown.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param Closure(TEntry, Container): TEntry $extender
     * @return $this
     */
    public function extend(string $id, Closure $extender): static
    {
        $entry = $this->entries[$id] ?? $this->setEntry($id);
        $entry->extend($extender);
        return $this;
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @return ContextProvider
     */
    public function whenInjecting(string $class): ContextProvider
    {
        return $this->injector->setContext($class, new ContextProvider($class));
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @return Entry
     */
    protected function setEntry(string $id): Entry
    {
        if (array_key_exists($id, $this->entries)) {
            throw new DuplicateEntryException("Cannot register class: {$id}. Entry already exists.", [
                'class' => $id,
                'existingEntry' => $this->entries[$id],
            ]);
        }
        return $this->entries[$id] = new Entry($id);
    }

    /**
     * @return Container
     */
    public function build(): Container
    {
        return new Container($this->injector, $this->entries, $this->scopedEntryIds);
    }
}
