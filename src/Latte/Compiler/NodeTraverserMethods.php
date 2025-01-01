<?php

declare(strict_types=1);

namespace Core\View\Latte\Compiler;

use Latte\Compiler\{Node, Nodes\FragmentNode, Nodes\TextNode, NodeTraverser};
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Support\{Character, Normalize};

trait NodeTraverserMethods
{
    /**
     * Loop one level of children and trim whitespace from the first and last child.
     *
     * @param FragmentNode $fragment
     * @param ?string      $nodeTag
     *
     * @return void
     */
    final protected function trimFragmentWhitespace( FragmentNode &$fragment, ?string $nodeTag = null ) : void
    {
        // Only parse if the node has children
        if ( ! $fragment->children ) {
            return;
        }

        $lastIndex = \count( $fragment->children ) - 1;

        /** @var int $index */
        foreach ( $fragment->children as $index => $node ) {
            // We're only trimming TextNodes
            if ( ! $node instanceof TextNode ) {
                continue;
            }

            $firstNode = 0      === $index;
            $lastNode  = $index === $lastIndex;
            $edgeNode  = ( $firstNode || $lastNode );
            $linebreak = \str_contains( PHP_EOL, $node->content );

            $before   = $fragment->children[$index - 2] ?? null;
            $previous = $fragment->children[$index - 1] ?? null;
            $next     = $fragment->children[$index + 1] ?? null;
            $after    = $fragment->children[$index + 2] ?? null;

            if ( $node->isWhitespace() ) {
                if ( $next instanceof TextNode && $next->isWhitespace() ) {
                    unset( $fragment->children[$index] );

                    continue;
                }

                if ( $next instanceof ElementNode ) {
                    $node->content = ' ';
                }

                continue;
            }

            if ( $nodeTag ) {
                $this->balanceContentWhitespace(
                    $node,
                    $nodeTag,
                    $previous instanceof ElementNode ? $previous->name : null,
                    $next instanceof ElementNode ? $next->name : null,
                    $before,
                    $after,
                );
            }
        }
    }

    /**
     * Optimize whitespace.
     *
     * @param TextNode   &$textNode
     * @param ?string    $nodeTag
     * @param ?string    $previousTag
     * @param ?string    $nextTag
     * @param null|mixed $before
     * @param null|mixed $after
     *
     * @return void
     */
    final protected function balanceContentWhitespace(
        TextNode & $textNode,
        ?string  $nodeTag = null,
        ?string  $previousTag = null,
        ?string  $nextTag = null,
        mixed    $before = null,
        mixed    $after = null,
    ) : void {
        // dump( $nodeTag);
        // if ( Tag::isContent( $nodeTag ) ) {
        // }
        $textNode->content = Normalize::whitespace( $textNode->content );

        if ( ! ( $previousTag || $nextTag ) || ! $textNode->content ) {
            return;
        }

        if ( $nextTag ) {
            $textNode->content = "{$textNode->content}&nbsp;";
        }

        if ( $previousTag && ! Character::isPunctuation( $textNode->content[0] ) ) {
            $textNode->content = "&nbsp;{$textNode->content}";
        }

        // if ( $before ) {
        //     dump( [ $textNode->content, $before ] );
        // }

        // dump( "[{$previousTag}]{$textNode->content}" );

        // dump( "[{$nodeTag}]" );

        // dump( $textNode->content );

        // dump(
        //     [
        //         'this'   => $nodeTag,
        //         'text'   => $textNode->content,
        //         'prev'   => $previousTag,
        //         'next'   => $nextTag,
        //         'before' => $before,
        //         'after'  => $after,
        //     ],
        // );
    }

    private function pruneWhitespace( TextNode &$textNode, bool $edgeNode ) : bool
    {
        $linebreak = \str_contains( PHP_EOL, $textNode->content );

        $textNode->content = \trim( $textNode->content );

        if ( $linebreak ) {
            $textNode->content .= \PHP_EOL;
        }

        return $edgeNode || ! $textNode->content;
    }

    /**
     * @param Node $node
     *
     * @phpstan-assert-if-true ElementNode $node
     * @return false|int|Node
     */
    final protected function skip( Node $node ) : int|Node|false
    {
        // Skip expression nodes, as a component cannot exist there
        if ( $node instanceof ExpressionNode ) {
            return NodeTraverser::DontTraverseChildren;
        }

        // Components are only called from ElementNodes
        if ( ! $node instanceof ElementNode ) {
            return $node;
        }

        return false;
    }
}
