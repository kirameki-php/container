<?php declare(strict_types=1);

namespace Kirameki\Container;

enum Lifetime
{
    case Undefined;
    case Transient;
    case Singleton;
}
