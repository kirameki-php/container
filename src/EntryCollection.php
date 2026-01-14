<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use Countable;
use Kirameki\Container\Exceptions\DuplicateEntryException;
use Kirameki\Container\Exceptions\EntryNotFoundException;
use function array_key_exists;
use function array_keys;

class EntryCollection implements Countable
{
    /**
     * @param array<class-string, Entry<object>> $entries
     * @param array<class-string, null> $scopedEntryIds
     */
    public function __construct(
        protected array $entries = [],
        protected array $scopedEntryIds = [],
    ) {
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * @param class-string $id
     * @return bool
     */
    public function has(mixed $id): bool
    {
        return array_key_exists($id, $this->entries);
    }

    /**
     * @param class-string $id
     * @return Entry
     */
    public function get(string $id): Entry
    {
        $entry = $this->getOrNull($id);
        if ($entry !== null) {
            return $entry;
        }

        throw new EntryNotFoundException("{$id} is not registered.", [
            'class' => $id,
        ]);
    }

    /**
     * @param class-string $id
     * @return Entry|null
     */
    public function getOrNull(string $id): ?Entry
    {
        return $this->entries[$id] ?? null;
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @return Entry<TEntry>
     */
    public function getOrNew(string $id): Entry
    {
        /** @var Entry<TEntry> */
        return $this->entries[$id] ??= new Entry($id);
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param Lifetime $lifetime
     * @param Closure(Container): TEntry|null $resolver
     * @param TEntry|null $instance
     * @return void
     */
    public function set(string $id, Lifetime $lifetime, ?Closure $resolver = null, ?object $instance = null): void
    {
        if (array_key_exists($id, $this->entries)) {
            throw new DuplicateEntryException("Cannot register class: {$id}. Entry already exists.", [
                'class' => $id,
                'existingEntry' => $this->entries[$id],
            ]);
        }

        $entry = $this->getOrNew($id);
        if ($resolver !== null) {
            $entry->setResolver($resolver, $lifetime);
        }

        if ($instance !== null) {
            $entry->setInstance($instance);
        }

        if ($lifetime === Lifetime::Scoped) {
            $this->scopedEntryIds[$id] = null;
        }
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param Closure(TEntry, Container): TEntry $extender
     * @return void
     */
    public function extend(string $id, Closure $extender): void
    {
        $this->getOrNew($id)->extend($extender);
    }

    /**
     * @param class-string $id
     * @return void
     */
    public function remove(mixed $id): void
    {
        unset($this->entries[$id]);
        unset($this->scopedEntryIds[$id]);
    }

    /**
     * clear all scoped entries.
     *
     * @return int
     */
    public function clearScoped(): int
    {
        $count = 0;
        foreach (array_keys($this->scopedEntryIds) as $id) {
            $this->get($id)->unsetInstance();
            $count++;
        }
        $this->scopedEntryIds = [];
        return $count;
    }
}
