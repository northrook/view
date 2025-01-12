<?php

namespace Core\View\Latte\Compiler;

// : NodeCompiler 2.0

use InvalidArgumentException;
use Latte\Compiler\{Node, NodeHelpers, Nodes\Html\AttributeNode, Position, PrintContext};
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Nodes\TextNode;
use Latte\Essential\Nodes\PrintNode;
use Stringable;

final class NodeParser
{
    // Mostly debugging
    protected readonly string $type;

    public readonly string $tag;

    public bool $hasExpression = false;

    public function __construct(
        public readonly Node   $node,
        private readonly ?self $parent = null,
    ) {
        $this->elementProperties();
    }

    /**
     * @param null|ElementNode $from
     *
     * @return null|array<array-key, string>
     */
    final public function getContent( ?ElementNode $from = null ) : ?array
    {
        if ( ! $from && $this->node instanceof ElementNode ) {
            $from = $this->node;
        }

        if ( ! $from ) {
            throw new InvalidArgumentException();
        }

        $level = 0;

        // dump( $from);

        $content = $this->parseContent( $from, $level );
        return empty( $content ) ? null : $content;
    }

    /**
     * @param ElementNode $from
     * @param int         $level
     *
     * @return array<array-key, string>
     */
    final public function parseContent( ElementNode $from, int &$level ) : array
    {
        $content = [];
        $level++;

        foreach ( $from->content->getIterator() as $index => $node ) {
            if ( $node instanceof TextNode ) {
                $value = NodeHelpers::toText( $node );
                if ( ! \trim( $value ) ) {
                    continue;
                }
                $content[$index] = $value;
            }

            if ( $node instanceof PrintNode ) {
                $node = $this->printNode( $node );
                $key  = $node->variable ?? "\${$index}";

                $content["{$key}:{$index}"] = $node->value;
            }

            if ( $node instanceof ElementNode ) {
                $content["{$node->name}:{$index}"] = [
                    'attributes' => $this->attributes( $node ),
                    'content'    => $this->parseContent( $node, $level ),
                ];
                // continue;
            }
            //
            // $content[ $index ] = NodeHelpers::toText( $node );
        }

        // dump( $content);
        return $content;
    }

    /**
     * Extract {@see ElementNode::$attributes} to `array`.
     *
     * - Each `[key=>value]` is passed through {@see NodeHelpers::toText()}.
     *
     * @param ?ElementNode $from
     *
     * @return array<string, null|array<array-key, string>|bool|int|string>
     */
    final public function attributes( ?ElementNode $from = null ) : array
    {
        // TODO : Does NOT account for expressions or n:tags at this point
        $attributes = [];

        $node = $from ?? $this->node;

        \assert( $node instanceof ElementNode );

        foreach ( self::getAttributeNodes( $node ) as $attribute ) {
            $name              = NodeHelpers::toText( $attribute->name );
            $value             = NodeHelpers::toText( $attribute->value );
            $attributes[$name] = $value;
        }

        return $attributes;
    }

    final public function properties( string|array ...$keys ) : array
    {
        $properties = [];
        $attributes = $this->attributes();

        foreach ( $keys as $key ) {
            $default          = \is_array( $key ) ? $key[\array_key_first( $key )] : null;
            $key              = \is_array( $key ) ? \array_key_first( $key ) : $key;
            $value            = $attributes[$key] ?? $default;
            $properties[$key] = $value;
            unset( $attributes[$key] );
        }

        return [...$properties, 'attributes' => $attributes];
    }

    final public function printNode( ?Node $node = null, ?PrintContext $context = null ) : PrintedNode
    {
        $printed = new PrintedNode( $node ?? $this->node, $context );
        if ( $printed->isExpression && isset( $this->parent ) ) {
            $this->parent->hasExpression = true;
        }
        return $printed;
    }

    /**
     * @param ElementNode $node
     * @param bool        $clean
     *
     * @return AttributeNode[]
     */
    final protected static function getAttributeNodes( ElementNode $node, bool $clean = false ) : array
    {
        $attributes = [];

        foreach ( $node->attributes->children as $index => $attribute ) {
            if ( $clean && ! $attribute instanceof AttributeNode ) {
                unset( $node->attributes->children[$index] );

                continue;
            }

            if ( $attribute instanceof AttributeNode
                 && ! $attribute->name instanceof PrintNode
            ) {
                $attributes[] = $attribute;
            }
        }
        return $clean ? $node->attributes->children : $attributes;
    }

    private function elementProperties() : void
    {
        $this->type = $this->node::class;

        if ( ! $this->node instanceof ElementNode ) {
            return;
        }

        $this->tag = $this->node->name;
    }
}
