<?php

/**
 * Form notification model.
 *
 * Represents email notification configuration including recipient
 * settings, template content, and conditional logic.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Models;

use ArtisanPackUI\Forms\Database\Factories\FormNotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Form notification model class.
 *
 * Represents email notification configuration including recipient
 * settings, template content, and conditional logic.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @property int $id
 * @property int $form_id
 * @property string $type
 * @property string $name
 * @property string|null $to_email
 * @property string|null $to_field
 * @property string|null $cc_emails
 * @property string|null $bcc_emails
 * @property string|null $reply_to_email
 * @property string|null $reply_to_field
 * @property string|null $from_name
 * @property string|null $from_email
 * @property string $subject
 * @property string $message
 * @property array|null $conditional_logic
 * @property bool $include_submission_data
 * @property bool $is_active
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Form $form
 * @property-read bool $has_conditional_logic
 * @property-read bool $is_autoresponder
 *
 * @since 1.0.0
 */
class FormNotification extends Model
{
    use HasFactory;

    // =========================================
    // Constants
    // =========================================

    /**
     * Admin notification type constant.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const TYPE_ADMIN = 'admin';

    /**
     * Autoresponder notification type constant.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const TYPE_AUTORESPONDER = 'autoresponder';

    /**
     * Custom notification type constant.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const TYPE_CUSTOM = 'custom';

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.0.0
     *
     * @var list<string>
     */
    protected $fillable = [
        'form_id',
        'type',
        'name',
        'to_email',
        'to_field',
        'cc_emails',
        'bcc_emails',
        'reply_to_email',
        'reply_to_field',
        'from_name',
        'from_email',
        'subject',
        'message',
        'conditional_logic',
        'include_submission_data',
        'is_active',
        'sort_order',
    ];

    // =========================================
    // Relationships
    // =========================================

    /**
     * Gets the form that owns this notification.
     *
     * @since 1.0.0
     *
     * @return BelongsTo<Form, $this> The form relationship.
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo( Form::class );
    }

    // =========================================
    // Scopes
    // =========================================

    /**
     * Scopes a query to only include active notifications.
     *
     * @since 1.0.0
     *
     * @param Builder<FormNotification> $query The query builder instance.
     *
     * @return Builder<FormNotification> The modified query builder.
     */
    public function scopeActive( Builder $query ): Builder
    {
        return $query->where( 'is_active', true );
    }

    /**
     * Scopes a query to only include notifications of a specific type.
     *
     * @since 1.0.0
     *
     * @param Builder<FormNotification> $query The query builder instance.
     * @param string                    $type  The notification type to filter by.
     *
     * @return Builder<FormNotification> The modified query builder.
     */
    public function scopeOfType( Builder $query, string $type ): Builder
    {
        return $query->where( 'type', $type );
    }

    /**
     * Scopes a query to only include admin notifications.
     *
     * @since 1.0.0
     *
     * @param Builder<FormNotification> $query The query builder instance.
     *
     * @return Builder<FormNotification> The modified query builder.
     */
    public function scopeAdmin( Builder $query ): Builder
    {
        return $query->ofType( self::TYPE_ADMIN );
    }

    /**
     * Scopes a query to only include autoresponder notifications.
     *
     * @since 1.0.0
     *
     * @param Builder<FormNotification> $query The query builder instance.
     *
     * @return Builder<FormNotification> The modified query builder.
     */
    public function scopeAutoresponder( Builder $query ): Builder
    {
        return $query->ofType( self::TYPE_AUTORESPONDER );
    }

    // =========================================
    // Accessors
    // =========================================

    /**
     * Checks if this notification has conditional logic configured.
     *
     * @since 1.0.0
     *
     * @return bool True if conditional logic is configured.
     */
    public function getHasConditionalLogicAttribute(): bool
    {
        return ! empty( $this->conditional_logic ) &&
               ! empty( $this->conditional_logic['rules'] );
    }

    /**
     * Checks if this is an autoresponder notification.
     *
     * @since 1.0.0
     *
     * @return bool True if this is an autoresponder notification.
     */
    public function getIsAutoresponderAttribute(): bool
    {
        return self::TYPE_AUTORESPONDER === $this->type;
    }

    // =========================================
    // Methods
    // =========================================

    /**
     * Gets all recipient email addresses for this notification.
     *
     * @since 1.0.0
     *
     * @param FormSubmission $submission The submission to get recipient emails from.
     *
     * @return array<int, string> The recipient email addresses.
     */
    public function getRecipientEmails( FormSubmission $submission ): array
    {
        $emails = [];

        // Static emails
        if ( $this->to_email ) {
            $emails = array_merge( $emails, array_map( 'trim', explode( ',', $this->to_email ) ) );
        }

        // Dynamic email from field
        if ( $this->to_field ) {
            $fieldEmail = $submission->getValue( $this->to_field );
            if ( $fieldEmail && filter_var( $fieldEmail, FILTER_VALIDATE_EMAIL ) ) {
                $emails[] = $fieldEmail;
            }
        }

        return array_unique( array_filter( $emails ) );
    }

    /**
     * Gets the reply-to email address for this notification.
     *
     * @since 1.0.0
     *
     * @param FormSubmission $submission The submission to get reply-to from.
     *
     * @return string|null The reply-to email address or null.
     */
    public function getReplyToEmail( FormSubmission $submission ): ?string
    {
        if ( $this->reply_to_field ) {
            return $submission->getValue( $this->reply_to_field );
        }

        return $this->reply_to_email;
    }

    /**
     * Parses the message template with submission data.
     *
     * @since 1.0.0
     *
     * @param FormSubmission $submission The submission to parse the message with.
     *
     * @return string The parsed message.
     */
    public function parseMessage( FormSubmission $submission ): string
    {
        $message = $this->message;

        // Replace {field_name} placeholders
        foreach ( $submission->values as $value ) {
            $placeholder = '{' . $value->field_name . '}';
            $message     = str_replace( $placeholder, $value->display_value, $message );
        }

        // Replace system placeholders
        $message = str_replace( '{submission_date}', $submission->created_at->format( 'F j, Y' ), $message );
        $message = str_replace( '{submission_time}', $submission->created_at->format( 'g:i A' ), $message );
        $message = str_replace( '{submission_number}', $submission->submission_number, $message );
        $message = str_replace( '{form_name}', $submission->form->name, $message );

        return $message;
    }

    /**
     * Parses the subject template with submission data.
     *
     * @since 1.0.0
     *
     * @param FormSubmission $submission The submission to parse the subject with.
     *
     * @return string The parsed subject.
     */
    public function parseSubject( FormSubmission $submission ): string
    {
        $subject = $this->subject;

        // Same placeholder replacement as message
        foreach ( $submission->values as $value ) {
            $placeholder = '{' . $value->field_name . '}';
            $subject     = str_replace( $placeholder, $value->display_value, $subject );
        }

        $subject = str_replace( '{form_name}', $submission->form->name, $subject );
        $subject = str_replace( '{submission_number}', $submission->submission_number, $subject );

        return $subject;
    }

    /**
     * Creates a new factory instance for the model.
     *
     * @since 1.0.0
     *
     * @return FormNotificationFactory The factory instance.
     */
    protected static function newFactory(): FormNotificationFactory
    {
        return FormNotificationFactory::new();
    }

    /**
     * Gets the attributes that should be cast.
     *
     * @since 1.0.0
     *
     * @return array<string, string> The cast definitions.
     */
    protected function casts(): array
    {
        return [
            'conditional_logic'       => 'array',
            'include_submission_data' => 'boolean',
            'is_active'               => 'boolean',
        ];
    }
}
