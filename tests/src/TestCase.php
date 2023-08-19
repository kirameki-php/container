<?php declare(strict_types=1);

namespace Tests\Kirameki\Container;

use Kirameki\Container\Container;
use Kirameki\Container\Entry;
use PHPUnit\Framework\TestCase as BaseTestCase;
use function array_sum;

class TestCase extends BaseTestCase
{
    /**
     * @var Container
     */
    protected Container $container;

    /**
     * @var array<string, int>
     */
    protected array $countResolving = [];

    /**
     * @var array<string, int>
     */
    protected array $countResolved = [];

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();

        $this->container->onResolving(function (Entry $entry) {
            $this->countResolving[$entry->id] ??= 0;
            ++$this->countResolving[$entry->id];
        });

        $this->container->onResolved(function (Entry $entry) {
            $this->countResolved[$entry->id] ??= 0;
            ++$this->countResolved[$entry->id];
        });
    }

    /**
     * @param int $count
     * @return void
     */
    protected function assertTotalResolvingCount(int $count): void
    {
        $this->assertSame($count, array_sum($this->countResolving));
    }

    /**
     * @param int $count
     * @return void
     */
    protected function assertTotalResolvedCount(int $count): void
    {
        $this->assertSame($count, array_sum($this->countResolved));
    }

    /**
     * @param class-string $class
     * @return int
     */
    protected function getResolvingCount(string $class): int
    {
        return $this->countResolving[$class] ?? 0;
    }

    /**
     * @param class-string $class
     * @return int
     */
    protected function getResolvedCount(string $class): int
    {
        return $this->countResolved[$class] ?? 0;
    }
}
