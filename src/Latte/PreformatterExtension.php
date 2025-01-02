<?php

declare(strict_types=1);

namespace Core\View\Latte;

use Override;
use Latte;
use Latte\Compiler\{Node, NodeTraverser};
use Latte\Compiler\Nodes\{FragmentNode, TemplateNode, TextNode};
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Core\View\Latte\Compiler\NodeTraverserMethods;
use Psr\Log\LoggerInterface;

final class PreformatterExtension extends Latte\Extension
{
    use NodeTraverserMethods;

    private const array SKIP_TAGS = ['code', 'pre', 'script', 'style'];

    private const array SKIP_PARTIAL_TAGS = ['code:', 'pre:'];

    public function __construct( private readonly ?LoggerInterface $logger = null ) {}

    #[Override]
    public function getPasses() : array
    {
        return [
            'node-preformatter' => [$this, 'nodePreformatter'],
        ];
    }

    public function nodePreformatter( TemplateNode $template ) : void
    {
        $this->trimFragmentWhitespace( $template->main );

        ( new NodeTraverser() )->traverse(
            $template,
            // [$this, 'prepare'],
            leave : [$this, 'parse'],
        );
    }

    public function parse( Node $node ) : int|Node
    {
        // Skip expression nodes, as a component cannot exist there
        if ( $node instanceof ExpressionNode ) {
            return NodeTraverser::DontTraverseChildren;
        }

        // Components are only called from ElementNodes
        if ( ! $node instanceof ElementNode ) {
            return $node;
        }

        $this->elementAttributes( $node );

        if ( $this->skipFragment( $node ) ) {
            return $node;
        }

        if ( $node->content instanceof FragmentNode ) {
            $this->trimFragmentWhitespace( $node->content, $node->name );
        }

        return $node;
    }

    private function skipFragment( ElementNode $node ) : bool
    {
        $tag = \strtolower( $node->name );

        if ( \in_array( $tag, $this::SKIP_TAGS ) ) {
            return true;
        }

        foreach ( $this::SKIP_PARTIAL_TAGS as $skp ) {
            if ( \str_starts_with( $tag, $skp ) ) {
                return true;
            }
        }

        return false;
    }

    protected function elementAttributes( ElementNode &$element ) : void
    {
        // Get a reference for the $element attributes
        $attributes = &$element->attributes->children;

        if ( ! $attributes ) {
            return;
        }

        $lastAttributeIndex = \count( $attributes ) - 1;

        foreach ( $attributes as $index => $attribute ) {
            // TODO : Parse attributes, find and warn for common errors
            //        like comma separating styles or classes

            if ( $attribute instanceof TextNode ) {
                // $attribute->content = \preg_replace( '#\s.+#', '', $attribute->content );
                $attribute->content = \trim( $attribute->content ).' ';
            }

            // Prevent trailing whitespace
            if ( $index === $lastAttributeIndex
                 && $attribute instanceof TextNode
            ) {
                unset( $attributes[$index] );
            }
        }
    }
}
