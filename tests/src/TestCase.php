<?php declare(strict_types=1);

namespace Tests\Kirameki\Container;

use Kirameki\Container\Container;
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

        $this->container->resolving(function (string $class) {
            $this->countResolving[$class] ??= 0;
            ++$this->countResolving[$class];
        });

        $this->container->resolved(function (string $class) {
            $this->countResolved[$class] ??= 0;
            ++$this->countResolved[$class];
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
