<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Illuminate\View\Component;

/**
 * Alert Stub Component
 *
 * A stub component that replaces the artisanpack-alert component
 * in tests where the livewire-ui-components package is not available.
 *
 * @since 1.0.0
 */
class AlertStub extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public ?string $title = null,
        public ?string $message = null,
        public ?string $type = 'info',
        public ?string $icon = null,
        public bool $dismissible = false,
    ) {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): string
    {
        $escapedTitle = htmlspecialchars((string) $this->title, ENT_QUOTES, 'UTF-8');
        $escapedMessage = htmlspecialchars((string) $this->message, ENT_QUOTES, 'UTF-8');
        $escapedType = htmlspecialchars((string) $this->type, ENT_QUOTES, 'UTF-8');

        return "<div class=\"alert-stub alert-{$escapedType}\"><strong>{$escapedTitle}</strong><span>{$escapedMessage}</span></div>";
    }
}
