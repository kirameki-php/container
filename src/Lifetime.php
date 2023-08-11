<?php declare(strict_types=1);

namespace Kirameki\Container;

enum Lifetime
{
    case Transient;
    case Scoped;
    case Singleton;
}
