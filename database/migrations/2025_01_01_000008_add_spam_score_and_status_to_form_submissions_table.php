<?php
/**
 * Add spam score and status columns to the form submissions table.
 *
 * Adds a numeric spam_score column and a lifecycle status column
 * (defaulting to "received") so spam-quarantine integrations can score
 * submissions and hold them in a "quarantined" state for review.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 * @author     Jacob Martella <support@artisanpackui.dev>
 * @since      1.5.0
 */

declare(strict_types=1);

use ArtisanPackUI\Forms\Enums\SubmissionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add spam score and status columns migration class.
 *
 * @since 1.5.0
 */
return new class extends Migration
{
    /**
     * Runs the migrations.
     *
     * @since 1.5.0
     */
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->decimal('spam_score', 8, 4)->nullable()->after('is_spam');
            $table->string('status', 50)->default(SubmissionStatus::Received->value)->after('spam_score');

            $table->index('spam_score');
            $table->index('status');
        });
    }

    /**
     * Reverses the migrations.
     *
     * @since 1.5.0
     */
    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropIndex(['spam_score']);
            $table->dropIndex(['status']);
            $table->dropColumn(['spam_score', 'status']);
        });
    }
};
