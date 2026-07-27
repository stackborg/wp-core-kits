<?php

/**
 * Base for WordPress personal-data exporters and erasers.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Privacy;

use Stackborg\WPCoreKits\WordPress\Database;

/**
 * Wires a plugin table into WordPress's privacy tools.
 *
 * Every Stackborg plugin that stores something against a visitor's email had
 * the same four methods copied into its Plugin class — register the exporter,
 * export, register the eraser, erase. That copy carried two defects into each
 * copy of it:
 *
 *   1. The query was `LIMIT 100` while the response always said
 *      `'done' => true`, so a data subject with more than 100 rows silently
 *      received a truncated export. WordPress passes a $page argument for
 *      exactly this reason and it was ignored.
 *   2. The eraser reported `items_removed` from a raw query result without
 *      distinguishing "deleted nothing" from "query failed".
 *
 * Subclasses describe *what* to export; batching, the response envelope and
 * the WordPress registration shape live here once.
 *
 * Usage:
 *   final class LogPrivacyHandler extends PersonalDataHandler
 *   {
 *       protected function slug(): string  { return 'sb-mailpress'; }
 *       protected function label(): string { return __('MailPress — Email Logs', 'sb-mailpress'); }
 *       protected function table(): string { return 'sb_mailpress_logs'; }
 *
 *       protected function fields(object $row): array
 *       {
 *           return [
 *               ['name' => __('Date', 'sb-mailpress'),    'value' => $row->created_at ?? ''],
 *               ['name' => __('Subject', 'sb-mailpress'), 'value' => $row->subject ?? ''],
 *           ];
 *       }
 *   }
 *
 *   // in the plugin's hook table
 *   $privacy = new LogPrivacyHandler();
 *   Hooks::filter('wp_privacy_personal_data_exporters', [$privacy, 'registerExporter']);
 *   Hooks::filter('wp_privacy_personal_data_erasers', [$privacy, 'registerEraser']);
 */
abstract class PersonalDataHandler
{
    /** Rows handled per WordPress batch. */
    protected const PER_PAGE = 100;

    /** Unique key for this data source, e.g. 'sb-mailpress'. */
    abstract protected function slug(): string;

    /** Human-readable name shown in the privacy tools UI. */
    abstract protected function label(): string;

    /** Table name without prefix — Database::table() resolves it. */
    abstract protected function table(): string;

    /**
     * One exported row, in WordPress's name/value shape.
     *
     * @return array<int, array{name: string, value: mixed}>
     */
    abstract protected function fields(object $row): array;

    /**
     * Column holding the data subject's email address.
     *
     * Override when the table names it something other than `user_email`.
     */
    protected function emailColumn(): string
    {
        return 'user_email';
    }

    /**
     * Label for the group these rows appear under in the export.
     *
     * Defaults to the same string as the exporter itself.
     */
    protected function groupLabel(): string
    {
        return $this->label();
    }

    // ── WordPress registration ────────────────────────────

    /**
     * @param array<string, mixed> $exporters
     * @return array<string, mixed>
     */
    public function registerExporter(array $exporters): array
    {
        $exporters[$this->slug()] = [
            'exporter_friendly_name' => $this->label(),
            'callback'               => [$this, 'export'],
        ];

        return $exporters;
    }

    /**
     * @param array<string, mixed> $erasers
     * @return array<string, mixed>
     */
    public function registerEraser(array $erasers): array
    {
        $erasers[$this->slug()] = [
            'eraser_friendly_name' => $this->label(),
            'callback'             => [$this, 'erase'],
        ];

        return $erasers;
    }

    // ── Callbacks ─────────────────────────────────────────

    /**
     * Export one batch.
     *
     * `done` is true only when this page returned fewer rows than the page
     * size — that is what tells WordPress to stop asking. Reporting done on
     * the first page, as the copied implementations did, truncates the export.
     *
     * @return array{data: array<int, mixed>, done: bool}
     */
    public function export(string $email, int $page = 1): array
    {
        $page   = max(1, $page);
        $offset = ($page - 1) * static::PER_PAGE;
        $table  = Database::table($this->table());
        $column = $this->emailColumn();

        $rows = Database::getResults(
            "SELECT * FROM {$table} WHERE {$column} = %s ORDER BY id ASC LIMIT %d OFFSET %d",
            $email,
            static::PER_PAGE,
            $offset
        ) ?: [];

        $data = [];
        foreach ($rows as $row) {
            $row = (object) $row;
            $data[] = [
                'group_id'    => $this->slug(),
                'group_label' => $this->groupLabel(),
                'item_id'     => $this->slug() . '-' . ($row->id ?? count($data)),
                'data'        => $this->fields($row),
            ];
        }

        return [
            'data' => $data,
            'done' => count($rows) < static::PER_PAGE,
        ];
    }

    /**
     * Erase one batch.
     *
     * @return array{items_removed: int, items_retained: bool, messages: array<int, string>, done: bool}
     */
    public function erase(string $email, int $page = 1): array
    {
        $table  = Database::table($this->table());
        $column = $this->emailColumn();

        $deleted = Database::query(
            "DELETE FROM {$table} WHERE {$column} = %s LIMIT %d",
            $email,
            static::PER_PAGE
        );

        // A failed query returns false, which is not the same as deleting
        // nothing — surfacing that distinction is what lets WordPress show the
        // request as incomplete rather than silently successful.
        $removed = is_int($deleted) ? $deleted : 0;

        return [
            'items_removed'  => $removed,
            'items_retained' => false,
            'messages'       => $deleted === false
                ? [sprintf(
                    /* translators: %s: the data source name shown in the privacy tools */
                    __('Could not remove records from %s.', 'wp-core-kits'),
                    $this->label()
                )]
                : [],
            'done' => $removed < static::PER_PAGE,
        ];
    }
}
