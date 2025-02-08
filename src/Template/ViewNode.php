<?php

declare(strict_types=1);

namespace Core\View\Template;

use Core\View\Element\Attributes;
use Latte\Compiler\Nodes\{FragmentNode, Html\AttributeNode, TextNode};
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Position;
use Stringable;

final class ViewNode
{
    /**
     * @param string           $name
     * @param null|Position    $position
     * @param null|ElementNode $parent
     * @param null|Attributes  $attributes
     *
     * @return ElementNode
     */
    public static function element(
        string       $name,
        ?Position    $position = null,
        ?ElementNode $parent = null,
        ?Attributes  $attributes = null,
    ) : ElementNode {
        $element = new ElementNode(
            $name,
            $position,
            $parent,
        );
        $element->attributes = new FragmentNode();
        $element->content    = new FragmentNode();

        if ( $attributes ) {
            foreach ( $attributes->resolveAttributes( true ) as $attribute => $value ) {
                $element->attributes->append( ViewNode::text( ' ' ) );
                $element->attributes->append(
                    new AttributeNode(
                        ViewNode::text( $attribute ),
                        $value ? ViewNode::text( $value ) : null,
                        '"',
                    ),
                );
            }
        }

        return $element;
    }

    public static function text(
        bool|int|string|null|Stringable|float $value,
        ?Position                             $position = null,
    ) : TextNode {
        $value = match ( \gettype( $value ) ) {
            'boolean' => $value ? 'true' : 'false',
            default   => (string) $value,
        };
        return new TextNode( $value, $position );
    }
}
