<?php

namespace Core\View\Component;

use CompileError;
use Core\View\Component;
use Core\View\Component\Properties;
use Core\View\Element\Attributes;
use Core\View\Template\Compiler\NodeAttributes;
use Core\View\Template\Compiler\Nodes\Html\ElementNode;
use ReflectionClass;
use TypeError;
use function Support\match_property_type;
use ReflectionException;

final class Arguments
{
    public readonly Component $component;

    public readonly Properties $properties;

    public readonly ElementNode $node;

    public readonly ?ElementNode $parent;

    /**
     * Passed to the {@see Component::__invoke} method on render.
     *
     * @var array<string,mixed>
     */
    private array $arguments = [];

    public readonly Attributes $attributes;

    /**
     * @param ElementNode $node
     * @param Properties  $properties
     */
    public function __construct(
        ElementNode $node,
        Properties  $properties,
    ) {
        $this->properties = $properties;
        $this->node       = $node;
        $this->parent     = $node->parent;
        $this->attributes = ( new NodeAttributes( $node ) )->attributes;

        $this->promoteTaggedProperties();
    }

    /**
     * Generate {@see ViewRenderNode} arguments.
     *
     * @return array{component: string, arguments: array<string,mixed>}
     */
    public function __invoke() : array
    {
        return [
            'component' => $this->component->name,
            'arguments' => $this->getArray(),
        ];
    }

    public function setComponent( Component $component ) : self
    {
        $this->component = $component;
        $this->component::prepareArguments( $this );
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArray() : array
    {
        $arguments = [];

        foreach ( $this->componentArguments() as $argument => $default ) {
            $arguments[$argument] = $this->pull( $argument, $default );
        }

        $arguments['__attributes'] ??= $this->attributes->resolveAttributes( true );

        return $arguments;
    }

    public function has( string $argument ) : bool
    {
        return isset( $this->arguments[$argument] );
    }

    public function get( string $argument ) : mixed
    {
        return $this->arguments[$argument] ?? null;
    }

    public function add( string $argument, mixed $value ) : self
    {
        $this->arguments[$argument] = $value;
        return $this;
    }

    public function remove( string $argument ) : self
    {
        unset( $this->arguments[$argument] );
        return $this;
    }

    private function pull( string $argument, mixed $default = null ) : mixed
    {
        $value = $this->arguments[$argument] ?? $default;
        unset( $this->arguments[$argument] );
        return $value;
    }

    /**
     * @return string[]
     */
    private function componentArguments() : array
    {
        $reflect = new ReflectionClass( $this->component );

        $__invoke = $reflect->getMethod( '__invoke' );

        $arguments = [];

        foreach ( $__invoke->getParameters() as $parameter ) {
            $argument = $parameter->getName();
            try {
                $value = $parameter->getDefaultValue();
            }
            catch ( ReflectionException ) {
                $value = null;
            }

            $arguments[$argument] = $value;
        }

        return $arguments;
    }

    private function promoteTaggedProperties() : void
    {
        /** @var array<int, string> $tagged */
        $tagged = \explode( ':', $this->node->name );
        $tag    = $tagged[0] ?? null;

        foreach ( $this->properties->tagged[$tag] ?? [] as $position => $property ) {
            $value = $tagged[$position] ?? null;
            $value = match ( true ) {
                \is_numeric( $value ) => (int) $value,
                default               => $value,
            };

            if ( ! \property_exists( $this->component, $property ) ) {
                throw new CompileError(
                    \sprintf(
                        'Property "%s" does not exist in %s.',
                        $property,
                        $this::class,
                    ),
                );
            }

            if ( ! match_property_type( $this->component, $property, from : $value ) ) {
                throw new TypeError(
                    \sprintf(
                        'Invalid property type: "%s" does not allow %s.',
                        $this::class."->{$property}",
                        \gettype( $value ),
                    ),
                );
            }

            $this->add( $property, $value );
        }
    }
}
