<?php
/**
 * Add privacy snapshot columns to the submission values and uploads tables.
 *
 * A value's "Mask in admin" and "Redact after N days" settings were read live
 * from its field, so deleting the field (which nulls `field_id`) showed its
 * values in full and kept them forever. The settings are now copied onto each
 * value and upload when it is written, the same way `field_admin_label` is,
 * and used once the field is gone. Existing rows are filled in from their
 * current fields.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 * @author     Jacob Martella <support@artisanpackui.dev>
 * @since      1.6.0
 */

declare(strict_types=1);

use ArtisanPackUI\Forms\Models\FormField;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add privacy snapshot columns migration class.
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
            $table->boolean('is_masked')->nullable()->after('upload_id');
            $table->unsignedSmallInteger('redact_after_days')->nullable()->after('is_masked');
        });

        Schema::table('form_uploads', function (Blueprint $table) {
            $table->boolean('is_masked')->nullable()->after('size');
        });

        // field_config is JSON, so the settings are read in PHP to stay portable across databases
        foreach (FormField::query()->whereNotNull('field_config')->lazyById() as $field) {
            $masked = $field->isMaskedInAdmin();
            $days   = $field->getRedactAfterDays();

            if (! $masked && null === $days) {
                continue;
            }

            DB::table('form_submission_values')
                ->where('field_id', $field->getKey())
                ->update(['is_masked' => $masked, 'redact_after_days' => $days]);

            if ($masked) {
                DB::table('form_uploads')
                    ->where('field_id', $field->getKey())
                    ->update(['is_masked' => true]);
            }
        }
    }

    /**
     * Reverses the migrations.
     *
     * @since 1.6.0
     */
    public function down(): void
    {
        Schema::table('form_uploads', function (Blueprint $table) {
            $table->dropColumn('is_masked');
        });

        Schema::table('form_submission_values', function (Blueprint $table) {
            $table->dropColumn(['is_masked', 'redact_after_days']);
        });
    }
};
