<?php

declare(strict_types=1);

namespace Core\View\Template;

use Core\View\Template\Compiler\{Node, NodeTraverser};
use Core\View\Template\Compiler\Nodes\TemplateNode;
use function Support\slug;

abstract class ElementExtension extends Extension
{
    abstract protected function traverseTemplate( Node $node ) : void;

    final public function getPasses() : array
    {
        return [slug( $this::class ) => $this->proccessTemplatePass( ... )];
    }

    final public function proccessTemplatePass( TemplateNode $templateNode ) : void
    {
        ( new NodeTraverser() )->traverse(
            node  : $templateNode,
            enter : $this->traverseTemplate( ... ),
        );
    }
}
