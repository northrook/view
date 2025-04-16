<?php

namespace Core\View\Template\Compiler\Nodes;

use Core\View\ComponentFactory;
use Core\View\Template\Compiler\{Node, PrintContext};
use Support\PhpGenerator\Argument;

final class ComponentProviderNode extends StatementNode
{
    /**
     * @param string               $name
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        public readonly string $name,
        protected array        $arguments = [],
    ) {}

    /**
     * @return string
     */
    private function arguments() : string
    {
        /** @var array<string, string> $array */
        $array = [
            'component' => $this->name,
            ...$this->arguments,
        ];
        $arguments       = [];
        $longestArgument = 0;

        foreach ( $array as $argument => $value ) {
            if ( $argument === 'content' ) {
                $content = [];

                /** @var Node[] $value */
                foreach ( $value as $node ) {
                    $content[] = $node->print( new PrintContext( raw : true ) );
                }

                $array[$argument] = "'".\implode( '', $content )."'";
            }
            else {
                $array[$argument] = Argument::export( $value );
            }
        }

        foreach ( $array as $argument => $properties ) {
            $argument    = \str_pad( $argument, $longestArgument );
            $arguments[] = TAB."{$argument} : {$properties},";
        }

        return \implode( "\n", $arguments );
    }

    public function print( ?PrintContext $context ) : string
    {
        $property = ComponentFactory::PROPERTY;

        $view = "echo \$this->global->{$property}->render(\n";
        $view .= $this->arguments();
        $view .= "\n);\n";
        return $view;
    }
}
