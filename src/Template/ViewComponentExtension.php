<?php

declare(strict_types=1);

namespace Core\View\Template;

use Core\View\ComponentFactory;
use Core\View\Template\Component\NodeParser;
use Core\View\Template\Component\Node\ComponentNode;
use Core\View\Template\Compiler\Nodes\{ComponentNode as NewComponentNode, TemplateNode};
use Core\View\Template\Compiler\{Node, NodeTraverser};
use Core\View\Template\Compiler\Nodes\Html\ElementNode;
use Core\View\Template\Compiler\Nodes\Php\ExpressionNode;
use Override;

final class ViewComponentExtension extends Extension
{
    /** @var array<string, string> */
    private array $tags;

    public function __construct( public readonly ComponentFactory $factory ) {}

    #[Override]
    public function getProviders() : array
    {
        return ['component' => $this->factory];
    }

    #[Override]
    public function getPasses() : array
    {
        return [
            fn( TemplateNode $template ) => $this->traverse(
                $template,
                [$this, 'traverseNode'],
            ),
        ];
    }

    // &
    public function traverseNode( Node $node ) : Node|int
    {
        // Skip expression nodes, as a component cannot exist there
        if ( $node instanceof ExpressionNode || $node instanceof NewComponentNode ) {
            return NodeTraverser::DontTraverseChildren;
        }

        if ( ! $componentName = $this->matchTag( $node ) ) {
            return $node;
        }

        \assert( $node instanceof ElementNode );

        if ( $node->getAttribute( 'component-id' ) ) {
            return $node;
        }

        // dump( $componentName, $node->parent );
        $parser     = new NodeParser( $node );
        $properties = $this->factory->getComponentProperties( $componentName );
        $component  = $this->factory->getComponent( $componentName );
        $arguments  = ComponentNode::nodeArguments( $parser );

        // @phpstan-ignore-next-line
        $component->create( $arguments, $properties->tagged );

        if ( ! $component instanceof Component ) {
            return $component->getElementNode( $node->position, $node->parent );
        }

        return $component->getComponentNode( $node );
    }

    /**
     * @param Node $node
     *
     * @return false|string
     */
    private function matchTag( Node $node ) : false|string
    {
        if ( ! $node instanceof ElementNode ) {
            return false;
        }

        $this->tags ??= $this->factory->getTags();
        $tag = $node->name;
        if ( $separator = \strpos( $tag, ':' ) ) {
            $tag = \substr( $tag, 0, $separator + 1 );
        }

        if ( ! \array_key_exists( $tag, $this->tags ) ) {
            return false;
        }

        return $this->tags[$tag];
    }
}
