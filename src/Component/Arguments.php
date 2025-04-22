<?php

namespace Core\View\Component;

use CompileError;
use Core\Exception\RequiredMethodException;
use Core\View\Component;
use Core\View\Element\Attributes;
use Core\View\Template\Compiler\NodeAttributes;
use Core\View\Template\Compiler\Nodes\Html\ElementNode;
use ReflectionClass;
use ReflectionParameter;
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
    }

    /**
     * Generate {@see ViewRenderNode} arguments.
     *
     * @return array{component: string, arguments: array<string,mixed>}
     */
    public function __invoke() : array
    {
        $this->promoteTaggedProperties();
        return [
            'component' => $this->component::getComponentName(),
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
            try {
                $default = $default->getDefaultValue();
            }
            catch ( ReflectionException $e ) {
                $default = null;
            }

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
     * @return array<string,ReflectionParameter>
     */
    private function componentArguments() : array
    {
        try {
            $reflect  = new ReflectionClass( $this->component );
            $__invoke = $reflect->getMethod( '__invoke' );
        }
        catch ( ReflectionException $exception ) {
            throw new RequiredMethodException(
                method   : '__invoke',
                class    : $this->component::class,
                previous : $exception,
            );
        }

        $arguments = [];

        foreach ( $__invoke->getParameters() as $argument ) {
            $arguments[$argument->name] = $argument;
        }

        return $arguments;
    }

    private function promoteTaggedProperties() : void
    {
        /** @var array<int, string> $tagged */
        $tagged = \explode( ':', $this->node->name );
        $tag    = $tagged[0] ?? null;

        $arguments = $this->componentArguments();
        $promote   = $this->properties->tagged[$tag] ?? [];

        foreach ( $promote as $position => $property ) {
            $value = $tagged[$position] ?? null;
            $value = match ( true ) {
                \is_numeric( $value ) => (int) $value,
                default               => $value,
            };

            if ( ! \array_key_exists( $property, $arguments ) ) {
                throw new CompileError(
                    \sprintf(
                        'Property "%s" does not exist in %s.',
                        $property,
                        $this->component::class,
                    ),
                );
            }

            // if ( !match_property_type( $this->component, $property, from : $value ) ) {
            //     throw new TypeError(
            //             \sprintf(
            //                     'Invalid property type: "%s" does not allow %s.',
            //                     $this->component::class . "->{$property}",
            //                     \gettype( $value ),
            //             ),
            //     );
            // }

            $this->add( $property, $value );
        }
    }
}
