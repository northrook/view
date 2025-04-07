<?php

namespace Core\View\Template\Compiler\Nodes;

use Core\View\ViewFactory;
use Core\View\Template\Compiler\{NodeArgumentExporter, PrintContext};

final class ViewProviderNode extends TextNode
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
        $export   = new NodeArgumentExporter();
        $property = ViewFactory::PROPERTY;

        $this->name      = $export->string( $name );
        $this->arguments = $export->arguments( $arguments );

        parent::__construct(
            <<<VIEW
                echo \$this->global->{$property}->render(
                    component : {$this->name},
                    arguments : {$this->arguments},
                );
                VIEW,
        );
    }

    public function print( PrintContext $context ) : string
    {
        return $this->content;
    }
}
