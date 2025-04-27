<?php

namespace Core\View\Template\Compiler\Nodes;

use Core\View\ComponentFactory;
use Core\View\Template\Compiler\{Node, PrintContext};
use Support\PhpGenerator\Argument;

final class ViewRenderNode extends StatementNode
{
    /**
     * @param array<string, mixed> $arguments
     * @param string               $action
     * @param string               $provider
     */
    public function __construct(
        protected array           $arguments = [],
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
                $content = [];

                /** @var Node[] $value */
                foreach ( $value as $node ) {
                    $content[] = $node->print( new PrintContext( raw : true ) );
                }

                $this->arguments[$argument] = "'".\implode( '', $content )."'";
            }
            else {
                $this->arguments[$argument] = Argument::export( $value );
            }
        }

        $arguments       = [];
        $longestArgument = 0;

        foreach ( $this->arguments as $argument => $properties ) {
            \assert( \is_string( $properties ) );
            $argument    = \str_pad( $argument, $longestArgument );
            $arguments[] = TAB."{$argument} : {$properties},";
        }

        return \implode( "\n", $arguments );
    }

    public function print( ?PrintContext $context ) : string
    {
        // $context ??= new PrintContext();
        //
        // // dump( [ __METHOD__ => $context ] );
        //
        // if ( $context->raw ) {
        //     return $context->format(
        //             "\$this->global->icon->{$this->action}( %args )",
        //             $this->arguments,
        //     );
        // }
        //
        // return $context->format(
        //         "echo \$this->global->icon->{$this->action}( %args );",
        //         $this->arguments,
        // );
        $view = "echo \$this->global->{$this->provider}->{$this->action}(\n";
        $view .= $this->arguments();
        $view .= "\n);\n";
        return $view;
    }
}
