<?php declare(strict_types=1);

namespace Kirameki\Container;

class SetOptions
{
    /**
     * @param Tags $tags
     * @param Entry $entry
     */
    public function __construct(
        protected Tags $tags,
        protected Entry $entry,
    )
    {
    }

    /**
     * @param string ...$names
     * @return $this
     */
    public function tag(string ...$names): static
    {
        foreach ($names as $name) {
            $this->tags->add($name, $this->entry->id);
        }
        return $this;
    }
}
