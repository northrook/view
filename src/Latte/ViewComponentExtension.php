<?php

declare(strict_types=1);

namespace Core\View\Latte;

use Core\Symfony\DependencyInjection\Autodiscover;
use Core\View\ComponentFactory;
use Core\View\ComponentFactory\ComponentProperties;
use Latte\Compiler\Nodes\TemplateNode;
use Latte\Extension;
use Override;
use Psr\Log\LoggerInterface;

#[Autodiscover(
    tags     : ['monolog.logger', ['channel' => 'view']],
    autowire : true,
)]
final class ViewComponentExtension extends Extension
{
    /** @var array<string, ComponentProperties> */
    private array $staticComponents = [];

    /** @var array<string, ComponentProperties> */
    private array $nodeComponents = [];

    public function __construct(
        public readonly ComponentFactory  $factory,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    #[Override]
    public function getPasses() : array
    {
        $this->parseComponentPasses();

        $componentPasses = [];

        foreach ( $this->staticComponents as $component ) {
            $componentPasses["static-{$component->name}-pass"] = fn( TemplateNode $template ) => $this->componentPass(
                $template,
                $component,
            );
        }

        foreach ( $this->nodeComponents as $component ) {
            $componentPasses["node-{$component->name}-pass"] = fn( TemplateNode $template ) => $this->componentPass(
                $template,
                $component,
            );
        }

        dump( $this );
        return $componentPasses;
    }

    /**
     * @param TemplateNode        $template
     * @param ComponentProperties $component
     */
    public function componentPass( TemplateNode $template, ComponentProperties $component ) : void {}

    #[Override]
    public function getProviders() : array
    {
        return ['component' => $this->factory];
    }

    private function parseComponentPasses() : void
    {
        $staticComponents = [];
        $nodeComponents   = [];

        foreach ( $this->factory->getRegisteredComponents() as $key => $component ) {
            if ( $component->static ) {
                $index = $component->priority ?: \count( $staticComponents );
                if ( \array_key_exists( $component->priority, $staticComponents ) ) {
                    $index++;
                }
                $staticComponents[$index] = $component;
            }
            else {
                $index = $component->priority ?: \count( $nodeComponents );
                if ( \array_key_exists( $component->priority, $nodeComponents ) ) {
                    $index++;
                }
                $nodeComponents[$index] = $component;
            }
        }
        \ksort( $staticComponents );
        \ksort( $nodeComponents );

        foreach ( \array_reverse( $staticComponents ) as $component ) {
            $this->staticComponents[$component->name] = $component;
        }

        foreach ( \array_reverse( $nodeComponents ) as $component ) {
            $this->nodeComponents[$component->name] = $component;
        }
    }
}
