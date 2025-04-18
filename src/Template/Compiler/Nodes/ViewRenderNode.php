<?php

namespace Core\View\Template\Compiler\Nodes;

use Core\View\ComponentFactory;
use Core\View\Template\Compiler\{Node, PrintContext};
use Support\PhpGenerator\Argument;

final class ViewRenderNode extends StatementNode
{
    /**
     * @param array<string, mixed> $arguments
     * @param string               $provider
     * @param string               $action
     */
    public function __construct(
        protected array           $arguments = [],
        protected readonly string $provider = ComponentFactory::PROPERTY,
        protected readonly string $action = 'render',
    ) {}

    /**
     * @return string
     */
    private function arguments() : string
    {
        foreach ( $this->arguments as $argument => $value ) {
            if ( $argument === 'content' ) {
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
        $view = "echo \$this->global->{$this->provider}->{$this->action}(\n";
        $view .= $this->arguments();
        $view .= "\n);\n";
        return $view;
    }
}
