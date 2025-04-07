<?php

declare(strict_types=1);

namespace Core\View\Template\Element;

use Core\View\Template\{Compiler\Node,
    Compiler\Nodes\Html\ElementNode,
    Compiler\Nodes\Php\Expression\BinaryOpNode,
    Compiler\Nodes\Php\Expression\VariableNode,
    Compiler\Nodes\Php\Scalar\StringNode,
    Compiler\CompilerExtension
};
use Core\View\Template\Compiler\Nodes\TextNode;
use function Support\str_starts_with_any;
use const Support\TAG_HEADING;

final class HeadingCompilerExtension extends CompilerExtension
{
    protected const string DEFAULT = 'h2';

    public function __construct(
        private readonly array $levels = [
            'hero'  => 1,
            'title' => 1,
        ],
    ) {}

    protected function node( ElementNode $node ) : Node
    {
        $this->headingContent( $node );
        dump( $node );
        return $node;
    }

    private function headingContent( ElementNode &$node ) : void
    {
        $elementContent   = (array) $node->content->children;
        $content          = [];
        $subheadingBefore = false;

        $heading    = [];
        $subheading = null;
        $decorative = [];

        foreach ( $elementContent as $index => $child ) {
            // Skip leading empty content
            if ( empty( $content['heading'] ) && $child instanceof TextNode && ! \trim( $child->content ) ) {
                continue;
            }

            if ( $child instanceof ElementNode ) {
                if ( \in_array( $child->name, ['p', 'small', 'subheading'], true ) ) {
                    $subheadingBefore        = \array_key_first( $elementContent ) === $index;
                    $subheading              = $child;
                    $content['subheading'][] = $subheading;

                    continue;
                }

                if ( \in_array( $child->name, ['p', 'small', 'subheading'], true )
                     || str_starts_with_any( $child->name, 'icon:' ) 
                ) {
                    $content['decorative'][] = $child;
                    $decorative[]            = $child;

                    continue;
                }
            }

            $content['heading'][] = $child;
            $heading[]            = $child;
            // dump( [ $index => $node ] );
        }

        dd( \get_defined_vars() );
        // dd( $node );
    }

    protected function conditions( ElementNode $node ) : bool
    {
        // Return early for native tags
        if ( $node->name && \in_array( $node->name, TAG_HEADING ) ) {
            return true;
        }

        if ( ! $node->name && $node->variableName instanceof BinaryOpNode ) {
            if ( $node->variableName->left instanceof StringNode ) {
                if ( $node->variableName->left->value === 'heading:' ) {
                    $node->variableName->left->value = 'h';
                }
                $node->name = $node->variableName->left->value;
            }
            if ( $node->variableName->right instanceof VariableNode ) {
                $node->name .= '$'.$node->variableName->right->name;
            }

            if ( \str_starts_with( $node->name, 'h$' ) ) {
                return true;
            }
        }

        // Parse heading: prefixes
        if ( $node->name && str_starts_with_any( $node->name, 'h:', 'heading:' ) ) {
            [$tag, $level] = \explode( ':', $node->name, 2 );

            if ( \is_numeric( $level ) ) {
                $node->name = "h{$level}";
                return true;
            }
            $node->name = $this->levels[$level] ?? '';

            if ( ! $level ) {
                $this->logger?->error(
                    'Unknown castable level name {level}. Defaulted to {default}.',
                    ['level' => $level, 'default' => $this::DEFAULT],
                );
            }
            $node->name = $this::DEFAULT;

            return true;
        }

        return false;
    }
}
