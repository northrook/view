<?php

declare(strict_types=1);

namespace Core\View\Template;

use Core\View\ComponentFactory;
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

    public function managedAnchor( string $value ) : string
    {
        $this->logger?->notice(
            '{method} handled {value}.',
            ['method' => __METHOD__, 'value' => $value],
        );

        return $value;
    }

    #[Override]
    public function getProviders() : array
    {
        return ['component' => $this->factory];
    }

    #[Override]
    public function getFunctions() : array
    {
        return ['anchor' => [$this, 'managedAnchor']];
    }

    #[Override]
    public function getPasses() : array
    {
        return [
            'view-components' => fn( TemplateNode $template ) => Node::traverse(
                $template,
                [$this, 'traverseNode'],
            ),
        ];
    }

    // &

    /**
     * @param Node $node
     *
     * @return Node|NodeTraverser::CONTINUE
     * @throws Exception\CompileException
     */
    public function traverseNode( Node $node ) : Node|int
    {
        // Skip expression nodes, as a component cannot exist there
        if ( $node instanceof ExpressionNode || $node instanceof NewComponentNode ) {
            return NodeTraverser::CONTINUE;
        }

        if ( ! $componentName = $this->matchTag( $node ) ) {
            return $node;
        }

        \assert( $node instanceof ElementNode );

        if ( $node->getAttribute( 'component-id' ) ) {
            return $node;
        }

        $component  = $this->factory->getComponent( $componentName );
        $properties = $this->factory->getComponentProperties( $componentName );

        // @phpstan-ignore-next-line
        $component->create( $node, $properties->tagged );

        return $component->getComponentNode( $node, $properties->tagged );
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
