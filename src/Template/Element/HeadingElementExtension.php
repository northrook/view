<?php

declare(strict_types=1);

namespace Core\View\Template\Element;

use Core\View\Template\{Compiler\Node, ElementExtension};

final class HeadingElementExtension extends ElementExtension
{
    protected function traverseTemplate( Node $node ) : void {}
}
