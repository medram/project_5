<?php

namespace App\Http\Controllers;

use App\Models\LinkPage;
use App\Models\LinkPageBlock;
use App\Models\LinkPageLead;
use App\Models\LinkPageVariant;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\LinkPages\LinkPagePresentation;
use App\Services\LinkPages\LinkPageVariantAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class PublicLinkPageLeadController extends Controller
{
    public function __construct(
        private readonly LinkPagePresentation $presentation,
        private readonly LinkPageVariantAssignment $variants,
        private readonly AnalyticsTracker $analytics,
    ) {}

    public function __invoke(Request $request, string $slug, LinkPageBlock $linkPageBlock): RedirectResponse
    {
        $linkPage = LinkPage::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with('user:id')
            ->firstOrFail();
        $variant = $this->variants->assigned($request, $linkPage);
        abort_unless(
            config('app.env') !== 'production'
                || filled(config('services.linkpilot.analytics.privacy_url')),
            404,
        );

        return DB::transaction(
            fn (): RedirectResponse => $this->submit($request, $linkPage, $linkPageBlock->id, $variant),
        );
    }

    private function submit(Request $request, LinkPage $linkPage, int $blockId, LinkPageVariant $variant): RedirectResponse
    {
        $linkPageBlock = LinkPageBlock::query()
            ->lockForUpdate()
            ->findOrFail($blockId);
        abort_unless(
            $linkPageBlock->link_page_id === $linkPage->id
                && $linkPageBlock->type === 'form'
                && $this->presentation->eligible($linkPageBlock)
                && $linkPageBlock->link_page_variant_id === $variant->id,
            404,
        );

        $fields = $this->fields($linkPageBlock);
        abort_if($fields === [], 404);

        $body = $request->request->all();
        if (array_diff(array_keys($body), ['lead_consent', 'values', '_token']) !== []) {
            return back()->withErrors(['values' => 'Only the saved form fields may be submitted.']);
        }

        $fieldIds = array_column($fields, 'id');
        $rules = [
            'lead_consent' => ['required', 'accepted'],
            'values' => ['required', 'array:'.implode(',', $fieldIds)],
        ];
        foreach ($fields as $field) {
            $fieldRules = [$field['required'] ? 'required' : 'nullable', 'string'];
            if ($field['type'] === 'email') {
                $fieldRules[] = 'email';
                $fieldRules[] = 'max:254';
            } elseif ($field['type'] === 'textarea') {
                $fieldRules[] = 'max:5000';
            } else {
                $fieldRules[] = 'max:250';
            }
            if ($field['type'] === 'select') {
                $fieldRules[] = Rule::in($field['options']);
            }
            $rules["values.{$field['id']}"] = $fieldRules;
        }

        $validator = Validator::make($body, $rules);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }
        $validated = $validator->validated();
        $consentedAt = CarbonImmutable::now('UTC');
        $tracksAnalytics = $this->analytics->canTrack($request);

        LinkPageLead::query()->create([
            'workspace_id' => $linkPage->workspace_id,
            'link_page_id' => $linkPage->id,
            'link_page_block_id' => $linkPageBlock->id,
            'responses' => $validated['values'],
            'consented_at' => $consentedAt,
            'retention_expires_at' => $consentedAt->addDays(30),
            'link_page_variant_id' => $tracksAnalytics ? $variant->id : null,
        ]);
        if ($tracksAnalytics) {
            try {
                $this->analytics->record($request, 'link_page.lead_submitted', $linkPage->user, [
                    'linkPage' => $linkPage,
                    'linkPageVariant' => $variant,
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }


        return to_route('link-pages.public', $linkPage->slug)
            ->with('success', 'Your response was submitted.');
    }

    /** @return list<array{id: string, type: string, required: bool, options: list<string>}> */
    private function fields(LinkPageBlock $block): array
    {
        $fields = $block->content['fields'] ?? null;
        if (! is_array($fields) || ! array_is_list($fields) || count($fields) < 1 || count($fields) > 20) {
            return [];
        }

        $valid = [];
        $seen = [];
        foreach ($fields as $field) {
            if (! is_array($field)) {
                return [];
            }
            $id = $field['id'] ?? null;
            $type = $field['type'] ?? null;
            $required = $field['required'] ?? null;
            $options = $field['options'] ?? [];
            if (
                ! is_string($id)
                || preg_match('/\A[a-z][a-z0-9_-]{0,39}\z/', $id) !== 1
                || isset($seen[$id])
                || ! in_array($type, ['text', 'email', 'textarea', 'select'], true)
                || ! is_bool($required)
                || ! is_array($options)
            ) {
                return [];
            }
            if ($type === 'select') {
                if ($options === [] || count($options) > 20) {
                    return [];
                }
                foreach ($options as $option) {
                    if (! is_string($option) || $option === '' || mb_strlen($option) > 120) {
                        return [];
                    }
                }
            } elseif ($options !== []) {
                return [];
            }

            $seen[$id] = true;
            $valid[] = [
                'id' => $id,
                'type' => $type,
                'required' => $required,
                'options' => array_values($options),
            ];
        }

        return $valid;
    }
}
