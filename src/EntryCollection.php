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
     * @param array<class-string, Entry> $entries
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
     * @template T of object
     * @param class-string<T> $id
     * @return Entry<T>
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
     * @template T of object
     * @param class-string<T> $id
     * @return Entry<T>|null
     */
    public function getOrNull(string $id): ?Entry
    {
        /** @var Entry<T>|null */
        return $this->entries[$id] ?? null;
    }

    /**
     * @template T of object
     * @param Entry<T> $entry
     * @return void
     */
    public function set(Entry $entry): void
    {
        $id = $entry->id;

        if ($this->has($id)) {
            throw new DuplicateEntryException("Cannot register class: {$id}. Entry already exists.", [
                'class' => $id,
                'existingEntry' => $this->entries[$id],
            ]);
        }

        $this->entries[$id] = $entry;

        if ($entry->lifetime === Lifetime::Scoped) {
            $this->scopedEntryIds[$id] = null;
        }
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @param Closure(T, Container): T $extender
     * @return void
     */
    public function extend(string $id, Closure $extender): void
    {
        $entry = $this->getOrNull($id);

        if ($entry === null) {
            throw new EntryNotFoundException("Cannot extend {$id}. Entry not found.", [
                'class' => $id,
            ]);
        }

        $entry->extend($extender);
    }

    /**
     * @param class-string $id
     * @return bool
     */
    public function unset(mixed $id): bool
    {
        if ($this->has($id)) {
            unset($this->entries[$id]);
            unset($this->scopedEntryIds[$id]);
            return true;
        }
        return false;
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
            $entry = $this->get($id);
            if ($entry instanceof LazyEntry) {
                $entry->unsetInstance();
                $count++;
            }
        }
        $this->scopedEntryIds = [];
        return $count;
    }
}
