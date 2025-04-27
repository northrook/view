<?php

namespace Core\View\Template\Compiler\Nodes;

use Core\View\ComponentFactory;
use Core\View\Template\Compiler\{Node, Nodes\Html\ElementNode, PrintContext};
use Support\PhpGenerator\Argument;

final class ViewRenderNode extends StatementNode
{
    /**
     * @param array<string, mixed> $arguments
     * @param bool                 $raw
     * @param string               $action
     * @param string               $provider
     */
    public function __construct(
        protected array           $arguments = [],
        protected readonly bool   $raw = false,
        protected readonly string $action = 'render',
        protected readonly string $provider = ComponentFactory::PROPERTY,
    ) {}

    /**
     * @return string
     */
    private function arguments() : string
    {
        foreach ( $this->arguments as $argument => $value ) {
            if ( $value instanceof FragmentNode ) {
                $fragment = [];
                $content  = [];

                /** @var Node[] $value */
                foreach ( $value as $node ) {
                    $var = $node->print( new PrintContext( raw : true ) );
                    if ( $node instanceof TextNode || $node instanceof ElementNode ) {
                        $fragment[] = $var;
                    }
                    else {
                        $content[] = "'".\implode( '', $fragment )."'";
                        $content[] = $var;
                        $fragment  = [];
                        // dump( $fragment );
                    }
                    // $fragment[] = $var;
                }

                if ( $fragment ) {
                    $content[] = "'".\implode( '', $fragment )."'";
                }
                //     else {
                //         if ( $buffer !== '' ) {
                //             $content[] = "'{$buffer}'";
                //             $buffer    = '';
                //         }
                //         $content[] = $var;
                //     }
                //     // dump( $var );
                //     // $content[] = $var;
                // }
                // // flush trailing buffer if any
                // if ( $buffer !== '' ) {
                //     $content[] = $buffer;
                // }

                // dd(
                //         $content,
                //         $variables,
                // );
                // dump( $content );
                $this->arguments[$argument] = \implode( '.', $content );
                // dd( $this->arguments[ $argument ] );
            }
            else {
                $this->arguments[$argument] = Argument::export( $value );
            }
        }

        $arguments       = [];
        $longestArgument = 0;

        foreach ( $this->arguments as $argument => $properties ) {
            \assert( \is_string( $properties ) );
            $argument = \str_pad( $argument, $longestArgument );

            if ( \str_starts_with( $properties, "''." ) ) {
                $properties = \mb_substr( $properties, 3 );
            }

            if ( \str_ends_with( $properties, ".''" ) ) {
                $properties = \mb_substr( $properties, 0, -3 );
            }

            $properties = \str_replace( ".''.", '.', $properties );

            $arguments[] = TAB."{$argument} : {$properties},";
        }

        return \implode( "\n", $arguments );
    }

    public function print( ?PrintContext $context ) : string
    {
        // $context->raw = $this->raw;

        if ( $context->raw ) {
            return "\$this->global->{$this->provider}->{$this->action}({$this->arguments()})";
        }

        $view = "echo \$this->global->{$this->provider}->{$this->action}(\n";
        $view .= $this->arguments();
        $view .= "\n);\n";
        return $view;
    }
}
