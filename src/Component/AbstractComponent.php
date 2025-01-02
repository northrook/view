<?php

declare(strict_types=1);

namespace Core\View\Component;

use Core\View\Html\{Attributes, Tag};
use Core\View\Attribute\ViewComponent;
use Core\View\Interface\ViewComponentInterface;
use Core\View\Template\View;
use InvalidArgumentException;
use Northrook\Logger\Log;
use Override;
use function Cache\memoize;
use ReflectionClass;
use BadMethodCallException;

/**
 * @method static string view()
 */
abstract class AbstractComponent extends View implements ViewComponentInterface
{
    /** @var ?string Define a name for this component */
    protected const ?string NAME = null;

    public readonly string $name;

    public readonly string $uniqueID;

    public readonly Attributes $attributes;

    #[Override]
    public function __toString() : string
    {
        return $this->render();
    }

    final public function create(
        array   $arguments,
        array   $promote = [],
        ?string $uniqueId = null,
    ) : ViewComponentInterface {
        $this->name       = $this::componentName();
        $this->attributes = new Attributes();
        $this->prepareArguments( $arguments );
        $this->componentUniqueID( $uniqueId ?? \serialize( [$arguments] ) );
        $this->promoteTaggedProperties( $arguments, $promote );
        $this->maybeAssignTag( $arguments );
        $this->assignAttributes( $arguments );

        if ( isset( $arguments['content'] ) ) {
            dump( ['Component HtmlContent' => $arguments] );
            unset( $arguments['content'] );
        }

        foreach ( $arguments as $property => $value ) {
            if ( \property_exists( $this, $property ) && ! isset( $this->{$property} ) ) {
                $this->{$property} = $value;

                continue;
            }

            if ( \is_string( $value ) && \method_exists( $this, $value ) ) {
                $this->{$value}();
            }

            Log::error(
                'The {component} was provided with undefined property {property}.',
                ['component' => $this->name, 'property' => $property],
            );
        }

        return $this;
    }

    /**
     * Internally or using the {@see \Core\View\TemplateEngine}.
     *
     * @return string
     */
    abstract protected function render() : string;

    /**
     * Process arguments passed to the {@see self::create()} method.
     *
     * @param array<string, mixed> $arguments
     *
     * @return void
     */
    protected function prepareArguments( array &$arguments ) : void {}

    /**
     * @param array                $attributes
     * @param array<string, mixed> $arguments
     *
     * @return void
     */
    final protected function setAttributes( array $attributes ) : void
    {
        $this->attributes->set( $attributes );
    }

    private function componentUniqueID( string $set ) : void
    {
        // Set a predefined hash
        if ( \strlen( $set ) === 16
             && \ctype_alnum( $set )
             && \strtolower( $set ) === $set
        ) {
            $this->uniqueID = $set;
            return;
        }
        $this->uniqueID = \hash( algo : 'xxh3', data : $set );
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return void
     */
    private function maybeAssignTag( array &$arguments ) : void
    {
        if ( ! ( isset( $arguments['tag'], $this->tag ) && $this->tag instanceof Tag ) ) {
            return;
        }

        \assert( \is_string( $arguments['tag'] ) );

        $this->tag->set( $arguments['tag'] );

        unset( $arguments['tag'] );
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return void
     */
    private function assignAttributes( array &$arguments ) : void
    {
        if ( ! isset( $arguments['attributes'] ) ) {
            return;
        }

        \assert( \is_array( $arguments['attributes'] ) );

        $this->setAttributes( $arguments['attributes'] );

        unset( $arguments['attributes'] );
    }

    /**
     * @param array<string, mixed>     $arguments
     * @param array<string, ?string[]> $promote
     *
     * @return void
     */
    private function promoteTaggedProperties( array &$arguments, array $promote = [] ) : void
    {
        if ( ! isset( $arguments['tag'] ) ) {
            return;
        }

        \assert( \is_string( $arguments['tag'] ) );

        /** @var array<int, string> $exploded */
        $exploded         = \explode( ':', $arguments['tag'] );
        $arguments['tag'] = $exploded[0];

        $promote = $promote[$arguments['tag']] ?? null;

        foreach ( $exploded as $position => $tag ) {
            if ( $promote && ( $promote[$position] ?? false ) ) {
                $arguments[$promote[$position]] = $tag;
                unset( $arguments[$position] );

                continue;
            }
            if ( $position ) {
                $arguments[$position] = $tag;
            }
        }
    }

    final public static function componentName() : string
    {
        $name = self::NAME ?? self::viewComponentAttribute()->name;
        return memoize(
            static function() use ( $name ) {
                if ( ! $name || ! \preg_match( '/^[a-z0-9:]+$/', $name ) ) {
                    $message = static::class." name '{$name}' must be lower-case alphanumeric.";

                    if ( \is_numeric( $name[0] ) ) {
                        $message = static::class." name '{$name}' cannot start with a number.";
                    }

                    if ( \str_starts_with( $name, ':' ) || \str_ends_with( $name, ':' ) ) {
                        $message = static::class." name '{$name}' must not start or end with a separator.";
                    }

                    throw new InvalidArgumentException( $message );
                }

                return $name;
            },
            $name,
        );
    }

    final public static function viewComponentAttribute() : ViewComponent
    {
        $viewComponentAttributes = ( new ReflectionClass( static::class ) )->getAttributes( ViewComponent::class );

        if ( empty( $viewComponentAttributes ) ) {
            $message = 'This Component is missing the '.ViewComponent::class.' attribute.';
            throw new BadMethodCallException( $message );
        }

        $viewAttribute = $viewComponentAttributes[0]->newInstance();
        $viewAttribute->setClassName( static::class );

        return $viewAttribute;
    }
}
