<?php

declare(strict_types=1);

namespace Core\View\Template;

use Core\View\ComponentFactory;
use Core\View\ComponentFactory\ComponentProperties;
use Core\View\Latte\Compiler\NodeParser;
use Core\View\Latte\Node\ComponentNode;
use Core\View\Template\Compiler\{Node, NodeTraverser};
use Core\View\Template\Compiler\Nodes\Html\ElementNode;
use Core\View\Template\Compiler\Nodes\Php\ExpressionNode;
use Core\View\Template\Compiler\Nodes\TemplateNode;
use Override;
use Exception;

final class ViewComponentExtension extends Extension
{
    public function __construct( public readonly ComponentFactory $factory ) {}

    #[Override]
    public function getPasses() : array
    {
        $registeredComponents = [];

        foreach ( $this->factory->getRegisteredComponents() as $component ) {
            $index = $component->priority ?: \count( $registeredComponents );
            if ( \array_key_exists( $component->priority, $registeredComponents ) ) {
                $index += \count( $registeredComponents );
                $this->logger?->warning(
                    '{component} priority {priority} already defined. Auto-bumped to {bump} to prevent conflict.',
                    [
                        'component' => $component->name,
                        'priority'  => $component->priority,
                        'bump'      => $index,
                    ],
                );
            }
            $registeredComponents[$index] = $component;
        }

        \krsort( $registeredComponents );

        $componentPasses = [];

        foreach ( $registeredComponents as $component ) {
            $componentPasses["static-{$component->name}-pass"] = fn( TemplateNode $template ) => $this->componentPass(
                $template,
                $component,
            );
        }

        return $componentPasses;
    }

    /**
     * @param TemplateNode        $template
     * @param ComponentProperties $component
     */
    public function componentPass( TemplateNode $template, ComponentProperties $component ) : void
    {
        ( new NodeTraverser() )->traverse(
            $template,
            // null,
            function( Node $node ) use ( $component ) : int|Node {
                // Skip expression nodes, as a component cannot exist there
                if ( $node instanceof ExpressionNode ) {
                    return NodeTraverser::DontTraverseChildren;
                }

                // Components are only called from ElementNodes
                if ( ! $node instanceof ElementNode ) {
                    return $node;
                }

                if ( ! $component->targetTag( $node->name ) ) {
                    return $node;
                }

                // View Engine Parser for Latte
                $parser = new NodeParser( $node );

                if ( $component->static ) {
                    $build = $this->factory->getComponent( $component );
                    $build->create(
                        ComponentNode::nodeArguments( $parser ),
                        $component->tagged,
                    );

                    try {
                        return $build->getElementNode( $node->position, $node->parent );
                    }
                    catch ( Exception $e ) {
                        $this->logger?->critical( $e->getMessage() );
                        return $node;
                    }
                }

                return new ComponentNode( $component->name, $parser );
            },
        );
    }

    #[Override]
    public function getProviders() : array
    {
        return ['component' => $this->factory];
    }

    // private function parseComponentPasses() : void
    // {
    //     $staticComponents = [];
    //     $nodeComponents   = [];
    //
    //     foreach ( $this->factory->getRegisteredComponents() as $component ) {
    //         if ( $component->static ) {
    //             $index = $component->priority ?: \count( $staticComponents );
    //             if ( \array_key_exists( $component->priority, $staticComponents ) ) {
    //                 $index += \count( $staticComponents );
    //                 $this->logger?->warning(
    //                     '{component} priority {priority} already defined. Auto-bumped to {bump} to prevent conflict.',
    //                     [
    //                         'component' => $component->name,
    //                         'priority'  => $component->priority,
    //                         'bump'      => $index,
    //                     ],
    //                 );
    //             }
    //             $staticComponents[$index] = $component;
    //         }
    //         else {
    //             $index = $component->priority ?: \count( $nodeComponents );
    //             if ( \array_key_exists( $component->priority, $nodeComponents ) ) {
    //                 $this->logger?->warning(
    //                     '{component} priority {priority} already defined. Auto-bumped to {bump} to prevent conflict.',
    //                     [
    //                         'component' => $component->name,
    //                         'priority'  => $component->priority,
    //                         'bump'      => $index,
    //                     ],
    //                 );
    //                 $index += \count( $nodeComponents );
    //             }
    //             $nodeComponents[$index] = $component;
    //         }
    //     }
    //     \ksort( $staticComponents );
    //     \ksort( $nodeComponents );
    //
    //     foreach ( \array_reverse( $staticComponents ) as $component ) {
    //         $this->staticComponents[$component->name] = $component;
    //     }
    //
    //     foreach ( \array_reverse( $nodeComponents ) as $component ) {
    //         $this->nodeComponents[$component->name] = $component;
    //     }
    // }
}
