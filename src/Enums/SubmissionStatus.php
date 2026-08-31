<?php

/**
 * Submission status enum.
 *
 * Represents the lifecycle status of a form submission, including the
 * quarantined state used by spam-quarantine integrations to hold
 * submissions for review before they enter the normal notification flow.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.5.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Enums;

/**
 * Submission status enum.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.5.0
 */
enum SubmissionStatus: string
{
    /**
     * Gets the translatable, human-readable label for the status.
     *
     * @since 1.5.0
     *
     * @return string The status label.
     */
    public function label(): string
    {
        return match ( $this ) {
            self::Received    => __( 'Received' ),
            self::Quarantined => __( 'Quarantined' ),
            self::Archived    => __( 'Archived' ),
        };
    }
    case Received    = 'received';
    case Quarantined = 'quarantined';
    case Archived    = 'archived';
}
