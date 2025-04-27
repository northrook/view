<?php

namespace Core\View\Component;

use CompileError;
use Core\Exception\RequiredMethodException;
use Core\View\Component;
use Core\View\Element\Attributes;
use Core\View\Template\Compiler\{PrintContext};
use Core\View\Template\Compiler\Nodes\Html\{AttributeNode, ElementNode};
use ReflectionClass;
use ReflectionParameter;
use ReflectionException;
use function Support\get;

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
        $this->attributes = new Attributes( ...$this->nodeAttributes() );
    }

    /**
     * @return array<string,null|string>
     */
    private function nodeAttributes() : array
    {
        if ( ! $this->node->attributes ) {
            return [];
        }

        $attributes = [];
        $context    = new PrintContext( raw : true );

        // TODO : Validate inline expressions: class="flex {$var ?? 'column'} px:16"
        foreach ( $this->node->attributes as $attribute ) {
            // Skip separators
            if ( ! $attribute instanceof AttributeNode ) {
                continue;
            }

            $name = $attribute->name->print( $context );

            if ( \str_contains( $name, ':' ) ) {
                [$property, $value] = \explode( ':', $name, 2 );
                $this->add( $property, $value );

                continue;
            }

            $value = $attribute->value?->print( $context );

            $attributes[$name] = $value;
        }
        return $attributes;
    }

    /**
     * Generate {@see ViewRenderNode} arguments.
     *
     * @return array<string,mixed>
     */
    public function __invoke() : array
    {
        $this->promoteTaggedProperties();
        return [
            '__component' => $this->component::getComponentName(),
            ...$this->getArray(),
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
        $arguments = ['__attributes' => $this->attributes->resolveAttributes( true )];

        foreach ( $this->componentArguments() as $argument => $parameter ) {
            $default = get( [$parameter, 'getDefaultValue'], null );

            $value = $this->pull( $argument, $default );

            if ( $value === null && $parameter->isVariadic() ) {
                continue;
            }

            $arguments[$argument] = $value;
        }

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
