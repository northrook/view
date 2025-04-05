<?php

declare(strict_types=1);

namespace Core\View\Template;

use Core\View\Template\Compiler\{Node, NodeAttributes, NodeTraverser};
use Core\View\Template\Compiler\Traverser\NodeTraverserMethods;
use Core\View\Template\Compiler\Nodes\Html\ElementNode;
use Core\View\Template\Compiler\Nodes\Php\ExpressionNode;
use Core\View\Template\Compiler\Nodes\TemplateNode;
use Override;

final class StyleSystemExtension extends Extension
{
    use NodeTraverserMethods;

    #[Override]
    public function getPasses() : array
    {
        return [
            'style-system' => fn( TemplateNode $template ) => Node::traverse(
                $template,
                [$this, 'traverseNode'],
            ),
        ];
    }

    /**
     * @param Node $node
     *
     * @return Node|NodeTraverser::CONTINUE
     */
    public function traverseNode( Node $node ) : Node|int
    {
        // Skip expression nodes, as a component cannot exist there
        if ( $node instanceof ExpressionNode ) {
            return NodeTraverser::CONTINUE;
        }
        // Components are only called from ElementNodes
        if ( ! $node instanceof ElementNode ) {
            return $node;
        }

        $attributes = new NodeAttributes( $node );

        if ( $attributes()->has( 'role', 'list' ) ) {
            $attributes()->class->add( 'list', true );
        }

        if ( $this->matchTag( $node, 'ol', 'ul' ) ) {
            if ( $node->parent instanceof ElementNode && $this->matchTag( $node->parent, 'nav', 'menu' ) ) {
                dump( 'Skipped node:', $node );
                return $node;
            }
            $attributes()->class->add( 'list', true );
        }

        $node->attributes = $attributes->getNode();

        return $node;
    }
}
