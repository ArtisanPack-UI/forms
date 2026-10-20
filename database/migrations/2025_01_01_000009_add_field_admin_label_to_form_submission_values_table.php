<?php
/**
 * Add field admin label column to the form submission values table.
 *
 * Denormalizes a field's admin label alongside its label, so submissions,
 * exports and notifications keep showing it after the field changes.
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
 * Add field admin label column migration class.
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
            $table->string('field_admin_label')->nullable()->after('field_label');
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
            $table->dropColumn('field_admin_label');
        });
    }
};
