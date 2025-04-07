<?php

namespace Core\View;

use Core\View\Template\{Component, Engine};
use Core\Interface\LazyService;
use Core\View\ComponentFactory\ComponentBag;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ViewFactory implements LazyService
{
    public const string PROPERTY = 'view';

    /**
     * @param Engine                    $engine
     * @param ServiceLocator<Component> $locator
     * @param ComponentBag              $components
     * @param array<string, string>     $tags
     */
    public function __construct(
        protected readonly Engine         $engine,
        protected readonly ServiceLocator $locator,
        protected readonly ComponentBag   $components,
        protected readonly array          $tags = [],
    ) {}

    /**
     * @param string               $view
     * @param array<string, mixed> $arguments
     *
     * @return Component
     */
    final public function render(
        string $view,
        array  $arguments,
    ) : Component {
        dump( [$this, ...\get_defined_vars()] );
        return new Component\DemoComponent();
    }

    /**
     * @return array<string, string>
     */
    final public function getTags() : array
    {
        return $this->tags;
    }
}
