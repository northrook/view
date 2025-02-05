<?php

declare(strict_types=1);

namespace Core\View\Latte;

use Core\View\Latte\Compiler\NodeTraverserMethods;
use Core\View\Template\Compiler\NodeAttributes;
use Latte;
use Latte\Compiler\{Node, NodeTraverser};
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\TemplateNode;
use Override;
use Psr\Log\LoggerInterface;

final class StyleSystemExtension extends Latte\Extension
{
    use NodeTraverserMethods;

    public function __construct( private readonly ?LoggerInterface $logger = null ) {}

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
