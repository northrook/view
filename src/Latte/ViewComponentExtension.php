<?php

declare(strict_types=1);

namespace Core\View\Latte;

use Core\View\ComponentFactory;
use Latte\Extension;
use Psr\Log\LoggerInterface;

final class ViewComponentExtension extends Extension
{
    public function __construct(
        public readonly ComponentFactory  $componentFactory,
        private readonly ?LoggerInterface $logger = null,
    ) {}
}
