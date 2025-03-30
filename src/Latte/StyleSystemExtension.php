<?php

declare(strict_types=1);

namespace Core\View\Latte;

use Core\View\Latte\Compiler\NodeTraverserMethods;
use Core\View\Template\Compiler\{Node, NodeAttributes, NodeTraverser};
use Core\View\Template\Compiler\Nodes\Html\ElementNode;
use Core\View\Template\Compiler\Nodes\Php\ExpressionNode;
use Core\View\Template\Compiler\Nodes\TemplateNode;
use Core\View\Template\Extension;
use Override;

final class StyleSystemExtension extends Extension
{
    use NodeTraverserMethods;

    #[Override]
    public function getPasses() : array
    {
        return [
            'inject-system-attributes' => [$this, 'templateNodeParser'],
        ];
    }

    public function templateNodeParser( TemplateNode $template ) : void
    {
        ( new NodeTraverser() )->traverse(
            $template,
            // [$this, 'prepare'],
            function( Node $node ) : int|Node {
                // Skip expression nodes, as a component cannot exist there
                if ( $node instanceof ExpressionNode ) {
                    return NodeTraverser::DontTraverseChildren;
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
            },
        );
    }
}
