<?php

namespace Core\View\Template\Compiler\Nodes;

use Core\View\ComponentFactory;
use Core\View\Template\Compiler\{ArgumentExporter, PrintContext};

final class ComponentProviderNode extends TextNode
{
    public readonly string $name;

    public readonly string $arguments;

    public readonly string $cache;

    /**
     * @param string               $name
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        string $name,
        array  $arguments = [],
    ) {
        $export   = new ArgumentExporter();
        $property = ComponentFactory::PROPERTY;

        $this->name      = $export->string( $name );
        $this->arguments = $export->arguments( $arguments );

        parent::__construct(
            <<<VIEW
                echo \$this->global->{$property}->render(
                    component : {$this->name},
                    arguments : {$this->arguments},
                );
                VIEW.NEWLINE,
        );
    }

    public function print( PrintContext $context ) : string
    {
        return $this->content;
    }
}
