<?php

namespace Core\View\Template\Compiler\Nodes;

use Core\View\ComponentFactory;
use Core\View\Template\Compiler\{PrintContext};
use Support\PhpGenerator\Argument;

final class ComponentProviderNode extends TextNode
{
    public readonly string $name;

    /**
     * @param string               $name
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        string $name,
        array  $arguments = [],
    ) {
        $property = ComponentFactory::PROPERTY;

        $this->name = $name;

        $arguments = $this->exportArguments(
            ['component' => $this->name, ...$arguments],
        );

        $view = "echo \$this->global->{$property}->render(\n";
        $view .= \implode( "\n", $arguments );
        $view .= "\n);\n";

        parent::__construct( $view );
    }

    /**
     * @param array<string, mixed> $array
     *
     * @return string[]
     */
    private function exportArguments( array $array ) : array
    {
        /** @var array<string, string> $array */
        $arguments       = [];
        $longestArgument = 0;

        foreach ( $array as $argument => $value ) {
            $longestArgument  = \max( $longestArgument, \strlen( $argument ) );
            $array[$argument] = Argument::export( $value );
        }

        foreach ( $array as $argument => $properties ) {
            $argument    = \str_pad( $argument, $longestArgument );
            $arguments[] = TAB."{$argument} : {$properties},";
        }

        return $arguments;
    }

    public function print( PrintContext $context ) : string
    {
        return $this->content;
    }
}
