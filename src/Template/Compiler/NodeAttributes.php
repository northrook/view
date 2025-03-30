<?php

namespace Core\View\Template\Compiler;

use Core\View\Element\Attributes;
use Core\View\Template\Compiler\Nodes\FragmentNode;
use Core\View\Template\Compiler\Nodes\Html\{AttributeNode, ElementNode};
use Core\View\Template\ViewNode;

final readonly class NodeAttributes
{
    public Attributes $attributes;

    public function __construct( ElementNode|FragmentNode $from = null )
    {
        $attributeFragmentNode = match ( true ) {
            $from instanceof ElementNode  => $from->attributes ?? new FragmentNode(),
            $from instanceof FragmentNode => $from,
            default                       => new FragmentNode(),
        };

        // dump( $attributeFragmentNode );
        $this->attributes = new Attributes();

        foreach ( $attributeFragmentNode as $attribute ) {
            if ( $attribute instanceof AttributeNode ) {
                $name  = NodeHelpers::toText( $attribute->name );
                $value = NodeHelpers::toText( $attribute->value );
                $this->attributes->set( (string) $name, $value );
            }
        }
        // dump( $this->attributes );
    }

    public function __invoke() : Attributes
    {
        return $this->attributes;
    }

    public function getNode() : ?FragmentNode
    {
        $attributes = $this->attributes->resolveAttributes( true );

        if ( empty( \array_filter( $attributes ) ) ) {
            return null;
        }

        $fragmentNode = new FragmentNode();
        // $firstKey     = \array_key_first( $attributes );
        // $lastKey      = \array_key_last( $attributes );

        // dump( $attributes );
        foreach ( $attributes as $name => $value ) {
            // dump( 'Is first item? ' . ( $firstKey === $name ? 'true' : 'false' ) );
            // dump( 'Is last item? ' . ( $lastKey === $name ? 'true' : 'false' ) );
            // dump( $name, $value );
            $fragmentNode->append( ViewNode::text( ' ' ) );
            $fragmentNode->append( new AttributeNode( ViewNode::text( $name ), ViewNode::text( $value ), '"' ) );
        }

        return $fragmentNode;
    }
}
