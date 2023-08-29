<?php declare(strict_types=1);

namespace Tests\Kirameki\Container;

use Kirameki\Container\Container;
use Kirameki\Container\Events\Injected;
use Kirameki\Container\Events\Resolved;
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
    protected array $countResolved = [];

    /**
     * @var array<string, int>
     */
    protected array $countInjected = [];

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();

        $this->container->onResolved(function (Resolved $event) {
            $this->countResolved[$event->id] ??= 0;
            ++$this->countResolved[$event->id];
        });

        $this->container->onInjected(function (Injected $event) {
            $this->countInjected[$event->class] ??= 0;
            ++$this->countInjected[$event->class];
        });
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
     * @param int $count
     * @return void
     */
    protected function assertTotalInjectedCount(int $count): void
    {
        $this->assertSame($count, array_sum($this->countInjected));
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
