<?php

namespace Core\View\Latte\Node;

use Core\View\Latte\Compiler\NodeExporter;
use Core\View\Latte\Compiler\NodeParser;
use Latte\Compiler\Nodes\TextNode;
use Latte\Compiler\PrintContext;
use const Cache\AUTO;

final class ComponentNode extends TextNode
{
    public readonly string $name;

    public readonly string $arguments;

    public readonly string $cache;

    /**
     * @param string $name
     *
     * @param array{tag: string, attributes: array<string, ?string>, content: array<array-key, string>}|NodeParser $arguments
     * @param ?int                                                                                                 $cache     [AUTO]
     */
    public function __construct(
        string           $name,
        array|NodeParser $arguments = [],
        ?int             $cache = AUTO,
    ) {
        if ( $arguments instanceof NodeParser ) {
            $arguments = ComponentNode::nodeArguments( $arguments );
            \assert( \is_array( $arguments ) );
        }

        $export = new NodeExporter();

        $this->name      = $export->string( $name );
        $this->arguments = $export->arguments( $arguments );
        $this->cache     = $export->cacheConstant( $cache );

        parent::__construct(
            <<<VIEW
                echo \$this->global->component->render(
                    component : {$this->name},
                    arguments : {$this->arguments},
                    cache     : {$this->cache},
                );
                VIEW,
        );
    }

    public function print( PrintContext $context ) : string
    {
        return $this->content;
    }

    public static function nodeArguments( NodeParser $node ) : array
    {
        return [
            'tag'        => $node->tag,
            'attributes' => $node->attributes(),
            'content'    => $node->getContent(),
        ];
    }
}
