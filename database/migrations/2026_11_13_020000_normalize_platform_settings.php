<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_settings') || ! Schema::hasTable('platform_setting_sections')) {
            return;
        }

        $this->normalizeBiolinks();
        $this->normalizeSubscriptions();
        $this->normalizeEmail();
        $this->normalizeUploading();

        foreach ([
            'localization' => ['date_format'],
            'authentication' => ['email_verification_required'],
            'captcha' => ['on_public_actions'],
            'gdpr' => ['analytics_default_denied'],
        ] as $section => $keys) {
            $this->mutateSection($section, function () use ($section, $keys): bool {
                return DB::table('platform_settings')
                    ->where('section', $section)
                    ->whereIn('key', $keys)
                    ->delete() > 0;
            });
        }
    }

    public function down(): void
    {
        // Intentionally data-preserving: removed inert controls cannot be reconstructed truthfully.
    }

    private function normalizeBiolinks(): void
    {
        $this->mutateSection('biolinks', function (): bool {
            $rows = DB::table('platform_settings')
                ->where('section', 'biolinks')
                ->whereIn('key', ['available_blocks', 'available_widgets'])
                ->lockForUpdate()
                ->get()
                ->keyBy('key');

            if ($rows->isEmpty()) {
                return false;
            }

            $blocks = $rows->has('available_blocks')
                ? $this->decode($rows['available_blocks']->value)
                : ['links', 'image', 'embed', 'divider'];
            $widgets = $rows->has('available_widgets')
                ? $this->decode($rows['available_widgets']->value)
                : null;
            $allowed = [];

            foreach (is_array($blocks) ? $blocks : [] as $block) {
                $mapped = $block === 'links' ? 'link' : $block;
                if (in_array($mapped, ['link', 'image', 'embed'], true)) {
                    $allowed[] = $mapped;
                }
            }
            $allowed[] = 'text';
            if ($widgets === null || (is_array($widgets) && in_array('social', $widgets, true))) {
                $allowed[] = 'social';
            }
            $allowed = array_values(array_filter(
                ['link', 'text', 'image', 'social', 'embed'],
                fn (string $block): bool => in_array($block, $allowed, true),
            ));

            $encoded = json_encode($allowed, JSON_THROW_ON_ERROR);
            $existing = $rows->get('available_blocks');
            $changed = ! is_object($existing) || $existing->value !== $encoded;

            if (is_object($existing)) {
                DB::table('platform_settings')->where('id', $existing->id)->update([
                    'value' => $encoded,
                    'is_secret' => false,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('platform_settings')->insert([
                    'section' => 'biolinks',
                    'key' => 'available_blocks',
                    'value' => $encoded,
                    'is_secret' => false,
                    'updated_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $deleted = DB::table('platform_settings')
                ->where('section', 'biolinks')
                ->where('key', 'available_widgets')
                ->delete() > 0;

            return $changed || $deleted;
        });
    }

    private function normalizeSubscriptions(): void
    {
        $this->mutateSection('subscriptions', function (): bool {
            $rows = DB::table('platform_settings')
                ->where('section', 'subscriptions')
                ->whereIn('key', ['invoice_address', 'invoice_notes', 'billing_address_collection'])
                ->lockForUpdate()
                ->get()
                ->keyBy('key');
            $changed = false;

            if ($rows->has('invoice_address') && ! $rows->has('billing_address_collection')) {
                $collection = $this->decode($rows['invoice_address']->value) === 'required' ? 'required' : 'auto';
                DB::table('platform_settings')->insert([
                    'section' => 'subscriptions',
                    'key' => 'billing_address_collection',
                    'value' => json_encode($collection, JSON_THROW_ON_ERROR),
                    'is_secret' => false,
                    'updated_by' => $rows['invoice_address']->updated_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $changed = true;
            }

            $deleted = DB::table('platform_settings')
                ->where('section', 'subscriptions')
                ->whereIn('key', ['invoice_address', 'invoice_notes'])
                ->delete() > 0;

            return $changed || $deleted;
        });
    }

    private function normalizeEmail(): void
    {
        $this->mutateSection('email', function (): bool {
            $rows = DB::table('platform_settings')
                ->where('section', 'email')
                ->whereIn('key', ['mailer', 'contact_recipient'])
                ->lockForUpdate()
                ->get()
                ->keyBy('key');
            $changed = false;

            if ($rows->has('mailer') && $this->decode($rows['mailer']->value) === 'array') {
                DB::table('platform_settings')->where('id', $rows['mailer']->id)->update([
                    'value' => json_encode('log', JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
                $changed = true;
            }

            $deleted = DB::table('platform_settings')
                ->where('section', 'email')
                ->where('key', 'contact_recipient')
                ->delete() > 0;

            return $changed || $deleted;
        });
    }

    private function normalizeUploading(): void
    {
        $this->mutateSection('uploading', function (): bool {
            $row = DB::table('platform_settings')
                ->where('section', 'uploading')
                ->where('key', 'disk')
                ->lockForUpdate()
                ->first();

            if ($row === null || $this->decode($row->value) !== 'public') {
                return false;
            }

            DB::table('platform_settings')->where('id', $row->id)->update([
                'value' => json_encode('local', JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

            return true;
        });
    }

    private function mutateSection(string $section, callable $mutation): void
    {
        DB::transaction(function () use ($section, $mutation): void {
            $state = DB::table('platform_setting_sections')
                ->where('section', $section)
                ->lockForUpdate()
                ->first();

            if ($state === null || ! $mutation()) {
                return;
            }

            DB::table('platform_setting_sections')
                ->where('section', $section)
                ->update([
                    'revision' => (int) $state->revision + 1,
                    'updated_at' => now(),
                ]);
        });
    }

    private function decode(?string $value): mixed
    {
        return $value === null ? null : json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }
};
