<?php
/**
 * Add redacted at column to the form submission values table.
 *
 * Records when `forms:prune-submissions` cleared a value whose field has a
 * "Redact after N days" setting, so the admin can show it as redacted and the
 * command skips it next time.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 * @author     Jacob Martella <support@artisanpackui.dev>
 * @since      1.6.0
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add redacted at column migration class.
 *
 * @since 1.6.0
 */
return new class extends Migration
{
    /**
     * Runs the migrations.
     *
     * @since 1.6.0
     */
    public function up(): void
    {
        Schema::table('form_submission_values', function (Blueprint $table) {
            $table->timestamp('redacted_at')->nullable()->after('upload_id');
        });
    }

    /**
     * Reverses the migrations.
     *
     * @since 1.6.0
     */
    public function down(): void
    {
        Schema::table('form_submission_values', function (Blueprint $table) {
            $table->dropColumn('redacted_at');
        });
    }
};
