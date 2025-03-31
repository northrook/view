<?php

declare(strict_types=1);

namespace Core\View\Component;

use Core\Interface\ViewInterface;
use Core\Symfony\DependencyInjection\SettingsAccessor;
use Core\View\Attribute\ViewComponent;
use Core\View\Latte\Node\StaticNode;
use Core\View\Element;
use Core\View\Element\Attributes;
use Core\View\Template\Compiler\Nodes\FragmentNode;
use Core\View\Template\Compiler\Nodes\Html\ElementNode;
use Core\View\Template\Compiler\Position;
use Core\View\Template\Support\NewNode;
use Northrook\Logger\Log;
use Stringable, ReflectionClass, BadMethodCallException, InvalidArgumentException;
use function Support\key_hash;

abstract class AbstractComponent implements ViewInterface
{
    use SettingsAccessor;

    /** @var ?string Manually define a name for this component */
    protected const ?string NAME = null;

    /** @var ?string Define a default `tag` for this component */
    protected const ?string TAG = null;

    public readonly string $name;

    public readonly string $uniqueID;

    public readonly Element $view;

    public readonly Attributes $attributes;

    protected readonly InnerContent $innerContent;

    public function __toString() : string
    {
        return $this->render();
    }

    public function getHtml( bool $string = false ) : Stringable
    {
        return $this->view->getHtml();
    }

    abstract public function getView() : Element;

    /**
     * @param null|Position    $position
     * @param null|ElementNode $parent
     *
     * @return ElementNode
     */
    public function getElementNode(
        ?Position    $position = null,
        ?ElementNode $parent = null,
    ) : ElementNode {
        $view = $this->getView();

        $element = NewNode::element(
            name       : $view->tag->getTagName(),
            position   : $position,
            parent     : $parent,
            attributes : $view->attributes->array,
        );

        \assert( $element->content instanceof FragmentNode );

        $element->content->append( new StaticNode( $view->content->getString() ) );

        return $element;
    }

    /**
     * @param array{'tag': ?string,'attributes' : array<string, null|array<array-key, ?string>|bool|float|int|string>, 'content': null|string} $arguments
     * @param array<string, ?string[]>                                                                                                         $promote
     * @param null|string                                                                                                                      $uniqueId
     *
     * @return $this
     */
    final public function create(
        array   $arguments,
        array   $promote = [],
        ?string $uniqueId = null,
    ) : self {
        $this->name       = $this::componentName();
        $this->view       = new Element();
        $this->attributes = $this->view->attributes;
        $this->prepareArguments( $arguments );
        $this->componentUniqueID( $uniqueId ?? \serialize( [$arguments] ) );
        $this->promoteTaggedProperties( $arguments, $promote );
        $this->assignInnerContent( $arguments );
        $this->assignTag( $arguments );
        $this->assignAttributes( $arguments );

        foreach ( $arguments as $property => $value ) {
            if ( \property_exists( $this, (string) $property ) ) {
                if ( ! isset( $this->{$property} ) ) {
                    $this->{$property} = $value;
                }
                else {
                    $this->{$property} = match ( \gettype( $this->{$property} ) ) {
                        'boolean' => (bool) $value,
                        default   => $value,
                    };
                }

                continue;
            }

            \assert( \is_string( $value ), 'All remaining arguments should be method calls at this point.' );

            $method = 'do'.\ucfirst( $value );

            if ( \method_exists( $this, $method ) ) {
                $this->{$method}();
            }
            else {
                Log::error(
                    'The {component} was provided with undefined property {property}.',
                    ['component' => $this->name, 'property' => $property],
                );
            }
        }

        return $this;
    }

    /**
     * Internally or using the {@see \Core\View\TemplateEngine}.
     *
     * @return string
     */
    protected function render() : string
    {
        return $this->getView()->render();
    }

    /**
     * Process arguments passed to the {@see self::create()} method.
     *
     * @param array{'tag': ?string,'attributes' : array<string, null|array<array-key, ?string>|bool|float|int|string>, 'content': null|string} $arguments
     *
     * @return void
     */
    protected function prepareArguments( array &$arguments ) : void {}

    /**
     * @param array<string, null|array<array-key, string>|bool|int|string> $attributes
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
        $this->uniqueID = key_hash( 'xxh64', $set );
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return void
     */
    private function assignInnerContent( array &$arguments ) : void
    {
        $content = $arguments['content'] ?? [];
        unset( $arguments['content'] );

        if ( ! $content ) {
            return;
        }

        if ( \is_string( $content ) ) {
            $content = [$content];
        }

        \assert( \is_array( $content ) );

        $this->innerContent = new InnerContent( $content );
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return void
     */
    private function assignTag( array &$arguments ) : void
    {
        $tag = (string) ( $arguments['tag'] ?? $this::TAG ?? 'div' );
        unset( $arguments['tag'] );

        $this->view->tag->set( $tag );
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return void
     */
    private function assignAttributes( array &$arguments ) : void
    {
        /** @var array<string, null|array<array-key, string>|bool|int|string> $attributes */
        $attributes = $arguments['attributes'] ?? [];
        unset( $arguments['attributes'] );

        if ( $attributes ) {
            $this->setAttributes( $attributes );
        }
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

        if ( ! $name ) {
            throw new BadMethodCallException( static::class.' name is not defined.' );
        }

        if ( ! \ctype_alnum( \str_replace( ':', '', $name ) ) ) {
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
