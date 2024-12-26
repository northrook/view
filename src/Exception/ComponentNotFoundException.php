<?php

declare(strict_types=1);

namespace Core\View\Exception;

use Core\View\Interface\ComponentInterface;
use InvalidArgumentException;
use Throwable;

class ComponentNotFoundException extends InvalidArgumentException
{
    public readonly string $interface;

    public function __construct(
        private readonly string $component,
        ?string                 $message = null,
        ?Throwable              $previous = null,
    ) {
        $this->interface = ComponentInterface::class;

        parent::__construct( $this->message( $message ), 500, $previous );
    }

    private function message( ?string $message = null ) : string
    {
        return $message ?? match ( true ) {
            ! \class_exists( $this->component ) => "The class for the component '{$this->component}' does not exist.",
            ! \is_subclass_of(
                $this->component,
                $this->interface,
            )       => "The component class '{$this->component}' does not extend the Abstract {$this->interface} class.",
            default => "The {$this->component} Component was not found",
        };
    }
}
