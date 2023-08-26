<?php declare(strict_types=1);

namespace Kirameki\Container;

class Tags
{
    /**
     * @param array<string, array<string, bool>> $idLookup
     * @param array<string, array<string, bool>> $nameLookup
     */
    public function __construct(
        protected array $idLookup = [],
        protected array $nameLookup = [],
    )
    {
    }

    /**
     * @param string $id
     * @return list<string>
     */
    public function getById(string $id): array
    {
        return array_keys($this->idLookup[$id] ?? []);
    }

    /**
     * @param string $name
     * @return list<string>
     */
    public function getByName(string $name): array
    {
        return array_keys($this->nameLookup[$name] ?? []);
    }

    public function add(string $tag, string $id): void
    {
        $this->idLookup[$id][$tag] = true;
        $this->nameLookup[$tag][$id] = true;
    }

    public function deleteId(string $id): bool
    {
        if (!array_key_exists($id, $this->idLookup)) {
            return false;
        }

        $tags = array_keys($this->idLookup[$id]);
        unset($this->idLookup[$id]);

        foreach ($tags as $tag) {
            unset($this->nameLookup[$tag][$id]);
            if ($this->nameLookup[$tag] === []) {
                unset($this->nameLookup[$tag]);
            }
        }

        return true;
    }
}
