<?php declare(strict_types=1);

namespace Tests\Kirameki;

use Kirameki\Container\Container;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Tests\Kirameki\Sample\AllTypes;

class TestCaseTest extends BaseTestCase
{
    public function testTypes(): void
    {
        $container = new Container();
        $container->instance(\DateTime::class, new \DateTime('1970'));
        $data = $container->inject(AllTypes::class);
        dump($data);
    }
}
