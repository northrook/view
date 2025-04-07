<?php

declare(strict_types=1);

namespace Core\View\Template\Compiler;

use Core\View\Template\Extension;
use Core\View\Template\Compiler\{Nodes\Html\ElementNode, Nodes\Php\ExpressionNode};
use Core\View\Template\Compiler\Nodes\TemplateNode;
use function Support\slug;

abstract class CompilerExtension extends Extension
{
    abstract protected function conditions( ElementNode $node ) : bool;

    abstract protected function node( ElementNode $node ) : Node;

    protected function traverseTemplate( Node $node ) : Node|int
    {
        // Skip expression nodes, as a component cannot exist there
        if ( $node instanceof ExpressionNode ) {
            return NodeTraverser::CONTINUE;
        }

        if ( ! $node instanceof ElementNode ) {
            return $node;
        }

        return $this->conditions( $node )
                ? $this->node( $node )
                : $node;
    }

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
