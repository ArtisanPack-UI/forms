<?php

/**
 * Form deleted event.
 *
 * Dispatched after a form is successfully deleted.
 * This event can be used for native Laravel event listeners.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Events;

use ArtisanPackUI\Forms\Models\Form;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Form deleted event class.
 *
 * Dispatched after a form is successfully deleted.
 * This event can be used for native Laravel event listeners.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class FormDeleted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * The form instance (before deletion).
     *
     * @since 1.0.0
     *
     * @var Form
     */
    public Form $form;

    /**
     * Creates a new event instance.
     *
     * @since 1.0.0
     *
     * @param Form $form The deleted form.
     */
    public function __construct( Form $form )
    {
        $this->form = $form;
    }
}
