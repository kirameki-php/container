<?php declare(strict_types=1);

namespace Tests\Kirameki;

use Kirameki\Container\Container;
use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    public function container(): Container
    {
        return new Container();
    }
}
