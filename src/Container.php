<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use Kirameki\Core\Exceptions\LogicException;
use Psr\Container\ContainerInterface;
use function array_key_exists;

class Container implements ContainerInterface
{
    /** @var Injector */
    protected Injector $injector;

    /** @var array<class-string, Entry<object>> */
    protected array $entries = [];

    /** @var array<class-string, null> */
    protected array $scopedEntries = [];

    /** @var list<Closure(Entry<object>): mixed> */
    protected array $resolvingCallbacks = [];

    /** @var list<Closure(Entry<object>, mixed): mixed> */
    protected array $resolvedCallbacks = [];

    /**
     * @param Injector|null $injector
     */
    public function __construct(?Injector $injector = null)
    {
        $this->injector = $injector ?? new Injector($this);
    }

    /**
     * Resolve given class.
     *
     * Resolving and Resolved callbacks are invoked on resolution.
     * For singleton entries, callbacks are only invoked once.
     *
     * Returns the resolved instance.
     *
     * @template TEntry of object
     * @param class-string<TEntry>|string $id
     * @return ($id is class-string<TEntry> ? TEntry : mixed)
     */
    public function get(string $id): mixed
    {
        $entry = $this->getEntry($id);
        $invokeCallbacks = !$entry->isCached();

        if($invokeCallbacks) {
            foreach ($this->resolvingCallbacks as $callback) {
                $callback($entry);
            }
        }

        $instance =  $entry->getInstance();

        if ($invokeCallbacks) {
            foreach ($this->resolvedCallbacks as $callback) {
                $callback($entry, $instance);
            }
        }

        return $instance;
    }

    /**
     * Check to see if a given class is registered.
     *
     * Returns **true** if class exists, **false** otherwise.
     *
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries) && $this->entries[$id]->isResolvable();
    }

    public function isCached(string $id): bool
    {
        return array_key_exists($id, $this->entries) && $this->entries[$id]->isCached();
    }

    /**
     * Register a given class.
     *
     * Returns itself for chaining.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param Closure(Container): TEntry $resolver
     * @return $this
     */
    public function bind(string $id, Closure $resolver): static
    {
        return $this->setEntry($id, $resolver, Lifetime::Transient);
    }

    /**
     * Register a given class with scoped lifetime.
     *
     * Returns itself for chaining.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param Closure(Container): TEntry $resolver
     * @return $this
     */
    public function scoped(string $id, Closure $resolver): static
    {
        $this->scopedEntries[$id] = null;
        return $this->setEntry($id, $resolver, Lifetime::Scoped);
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
     * @return $this
     */
    public function singleton(string $id, Closure $resolver): static
    {
        return $this->setEntry($id, $resolver, Lifetime::Singleton);
    }

    /**
     * Delete a given entry.
     *
     * Returns **true** if entry is found, **false** otherwise.
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool
    {
        if ($this->has($id)) {
            unset($this->entries[$id], $this->scopedEntries[$id]);
            return true;
        }
        return false;
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
     * @param class-string<TEntry> $id
     * @param Closure(TEntry, Container): TEntry $resolver
     * @return $this
     */
    public function extend(string $id, Closure $resolver): static
    {
        $this->getEntry($id)->extend($resolver);
        return $this;
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry>|string $id
     * @return ($id is class-string<TEntry> ? Entry<TEntry> : Entry<object>)
     */
    protected function getEntry(string $id): Entry
    {
        if (!array_key_exists($id, $this->entries)) {
            throw new LogicException("{$id} is not registered.", [
                'id' => $id,
            ]);
        }
        return $this->entries[$id];
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @param Closure(Container): TEntry $resolver
     * @param Lifetime $lifetime
     * @return $this
     */
    protected function setEntry(string $class, Closure $resolver, Lifetime $lifetime): static
    {
        if ($this->has($class)) {
            throw new LogicException("Cannot register class: {$class}. Entry already exists.", [
                'class' => $class,
            ]);
        }
        $this->entries[$class] ??= new Entry($this, $class);
        $this->entries[$class]->setResolver($resolver, $lifetime);

        return $this;
    }

    /**
     * Instantiate class and inject parameters if given class is not registered, or resolve if registered.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @param mixed ...$args
     * @return TEntry
     */
    public function make(string $class, mixed ...$args): object
    {
        if (count($args) === 0 && $this->has($class)) {
            return $this->get($class);
        }

        return $this->injector->constructorInjection($class, $args);
    }

    /**
     * @template TResult
     * @param Closure(): TResult $closure
     * @param mixed ...$args
     * @return TResult
     */
    public function call(Closure $closure, mixed ...$args): mixed
    {
        return $this->injector->closureInjection($closure, $args);
    }

    /**
     * Set a callback which is called when a class is resolving.
     *
     * @param Closure(Entry<object>): mixed $callback
     * @return void
     */
    public function onResolving(Closure $callback): void
    {
        $this->resolvingCallbacks[] = $callback;
    }

    /**
     * Set a callback which is called when a class is resolved.
     *
     * @param Closure(Entry<object>, mixed): mixed $callback
     * @return void
     */
    public function onResolved(Closure $callback): void
    {
        $this->resolvedCallbacks[] = $callback;
    }
}
