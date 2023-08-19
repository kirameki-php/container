<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use Kirameki\Core\Exceptions\LogicException;
use function array_key_exists;

class Container
{
    /** @var Injector */
    protected Injector $injector;

    /** @var array<string, Entry> */
    protected array $entries = [];

    /** @var array<string, null> */
    protected array $scopedEntries = [];

    /** @var list<Closure(Entry): mixed> */
    protected array $resolvingCallbacks = [];

    /** @var list<Closure(Entry, mixed): mixed> */
    protected array $resolvedCallbacks = [];

    /**
     * @param Injector|null $injector
     */
    public function __construct(?Injector $injector = null)
    {
        $this->injector = $injector ?? new Injector($this);
    }

    /**
     * Get a given class from the container.
     *
     * Resolving and Resolved callbacks are invoked on resolution.
     * For singleton entries, callbacks are only invoked once.
     *
     * Returns the resolved instance.
     *
     * @template TEntry of object
     * @param class-string<TEntry>|string $id
     * @param array<array-key, mixed> $args
     * @return ($id is class-string<TEntry> ? TEntry : object)
     */
    public function get(string $id, array $args = []): mixed
    {
        $entry = $this->getEntry($id);
        $invokeCallbacks = !$entry->isCached();

        if($invokeCallbacks) {
            foreach ($this->resolvingCallbacks as $callback) {
                $callback($entry);
            }
        }

        $instance =  $entry->getInstance($args);

        if ($invokeCallbacks) {
            foreach ($this->resolvedCallbacks as $callback) {
                $callback($entry, $instance);
            }
        }

        return $instance;
    }

    /**
     * Register a given class.
     *
     * Returns itself for chaining.
     *
     * @template TEntry of object
     * @param class-string<TEntry>|string $id
     * @param ($id is class-string<TEntry> ? (Closure(Container, array<array-key, mixed>): TEntry) : (Closure(Container, array<array-key, mixed>): object)) $resolver
     * @param Lifetime $lifetime
     * @return void
     */
    public function set(string $id, Closure $resolver, Lifetime $lifetime = Lifetime::Transient): void
    {
        $this->setEntry($id)->setResolver($resolver, $lifetime);
    }

    /**
     * Register a given class with scoped lifetime.
     *
     * Returns itself for chaining.
     *
     * @template TEntry of object
     * @param class-string<TEntry>|string $id
     * @param ($id is class-string<TEntry> ? (Closure(Container, array<array-key, mixed>): TEntry) : (Closure(Container, array<array-key, mixed>): object)) $resolver
     * @return void
     */
    public function scoped(string $id, Closure $resolver): void
    {
        $this->scopedEntries[$id] = null;
        $this->set($id, $resolver, Lifetime::Scoped);
    }

    /**
     * Register a given class as a singleton.
     *
     * Singletons will cache the result upon resolution.
     *
     * Returns itself for chaining.
     *
     * @template TEntry of object
     * @param class-string<TEntry>|string $id
     * @param ($id is class-string<TEntry> ? (Closure(Container, array<array-key, mixed>): TEntry) : (Closure(Container, array<array-key, mixed>): object)) $resolver
     * @return void
     */
    public function singleton(string $id, Closure $resolver): void
    {
        $this->set($id, $resolver, Lifetime::Singleton);
    }

    /**
     * Delete a given entry.
     *
     * Returns **true** if entry is found, **false** otherwise.
     *
     * @param string $id
     * @return bool
     */
    public function unset(string $id): bool
    {
        if ($this->has($id)) {
            unset($this->entries[$id], $this->scopedEntries[$id]);
            return true;
        }
        return false;
    }

    /**
     * Check to see if a given class is bound to the container.
     *
     * Returns **true** if bound, **false** otherwise.
     *
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries) && $this->entries[$id]->isResolvable();
    }

    /**
     * @param string $id
     * @return bool
     */
    public function isCached(string $id): bool
    {
        return array_key_exists($id, $this->entries) && $this->entries[$id]->isCached();
    }

    /**
     * Clears all entries.
     *
     * @return $this
     */
    public function clearEntries(): static
    {
        $this->entries = [];
        $this->scopedEntries = [];
        return $this;
    }

    /**
     * Clears all scope for a scoped entry.
     *
     * @return $this
     */
    public function clearScopedEntries(): static
    {
        foreach (array_keys($this->scopedEntries) as $id) {
            unset($this->entries[$id]);
        }
        $this->scopedEntries = [];
        return $this;
    }

    /**
     * Extend a registered class.
     *
     * The given Closure must return an instance of the original class or else Exception is thrown.
     *
     * @template TEntry of object
     * @param class-string<TEntry>|string $id
     * @param Closure(TEntry, Container, array<array-key, mixed>): TEntry $extender
     * @return $this
     */
    public function extend(string $id, Closure $extender): static
    {
        array_key_exists($id, $this->entries)
            ? $this->getEntry($id)->extend($extender)
            : $this->setEntry($id)->extend($extender);
        return $this;
    }

    /**
     * @param string $id
     * @return Entry
     */
    protected function getEntry(string $id): Entry
    {
        if (!array_key_exists($id, $this->entries)) {
            throw new LogicException("{$id} is not registered.", [
                'class' => $id,
            ]);
        }
        return $this->entries[$id];
    }

    /**
     * @param string $id
     * @return Entry
     */
    protected function setEntry(string $id): Entry
    {
        if ($this->has($id)) {
            throw new LogicException("Cannot register class: {$id}. Entry already exists.", [
                'class' => $id,
            ]);
        }
        return $this->entries[$id] ??= new Entry($this, $id);
    }

    /**
     * Instantiate class and inject parameters if given class is not registered, or resolve if registered.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param array<array-key, mixed> $args
     * @return TEntry
     */
    public function resolve(string $id, array $args = []): object
    {
        return $this->has($id)
            ? $this->get($id, $args)
            : $this->make($id, $args);
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @param array<array-key, mixed> $args
     * @return TEntry
     */
    public function make(string $class, array $args = []): object
    {
        return $this->injector->constructorInjection($class, $args);
    }

    /**
     * @template TResult
     * @param Closure(): TResult $closure
     * @param array<array-key, mixed> $args
     * @return TResult
     */
    public function call(Closure $closure, mixed $args): mixed
    {
        return $this->injector->closureInjection($closure, $args);
    }

    /**
     * Set a callback which is called when a class is resolving.
     *
     * @param Closure(Entry): mixed $callback
     * @return void
     */
    public function onResolving(Closure $callback): void
    {
        $this->resolvingCallbacks[] = $callback;
    }

    /**
     * Set a callback which is called when a class is resolved.
     *
     * @param Closure(Entry, mixed): mixed $callback
     * @return void
     */
    public function onResolved(Closure $callback): void
    {
        $this->resolvedCallbacks[] = $callback;
    }
}
