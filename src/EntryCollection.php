<?php declare(strict_types=1);

namespace Kirameki\Container;

use ArrayAccess;
use Countable;
use Kirameki\Container\Entry as Entry;
use Kirameki\Container\Exceptions\DuplicateEntryException;
use Kirameki\Container\Exceptions\EntryNotFoundException;
use function array_key_exists;
use function array_keys;

/**
 * @implements ArrayAccess<class-string, Entry>
 */
class EntryCollection implements ArrayAccess, Countable
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

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->entries);
    }

    /**
     * @param class-string $offset
     * @return Entry
     */
    public function offsetGet(mixed $offset): Entry
    {
        if (isset($this->entries[$offset])) {
            return $this->entries[$offset];
        }

        throw new EntryNotFoundException("{$offset} is not registered.", [
            'class' => $offset,
        ]);
    }

    /**
     * @param class-string $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (array_key_exists($offset, $this->entries)) {
            throw new DuplicateEntryException("Cannot register class: {$offset}. Entry already exists.", [
                'class' => $offset,
                'existingEntry' => $this->entries[$offset],
            ]);
        }

        if (!$value instanceof Entry) {
            throw new EntryNotFoundException("{$offset} is not an instance of Entry.");
        }

        $this->entries[$offset] = $value;

        if ($value->lifetime === Lifetime::Scoped) {
            $this->scopedEntryIds[$offset] = null;
        }
    }

    /**
     * @param class-string $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->entries[$offset]);
        unset($this->scopedEntryIds[$offset]);
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
            $this[$id]->unsetInstance();
            $count++;
        }
        $this->scopedEntryIds = [];
        return $count;
    }
}
