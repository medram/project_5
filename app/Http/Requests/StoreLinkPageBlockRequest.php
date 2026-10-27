<?php

namespace App\Http\Requests;

use App\Models\LinkPageBlock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLinkPageBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['text', 'alt_text'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
        $fields = $this->input('fields');
        if (is_array($fields)) {
            foreach ($fields as $index => $field) {
                if (! is_array($field)) {
                    continue;
                }
                if (is_string($field['label'] ?? null)) {
                    $field['label'] = trim($field['label']);
                }
                if (is_array($field['options'] ?? null)) {
                    $field['options'] = array_map(
                        fn (mixed $option): mixed => is_string($option) ? trim($option) : $option,
                        $field['options'],
                    );
                }
                $fields[$index] = $field;
            }
            $this->merge(['fields' => $fields]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $type = $this->input('type');
        $existing = $this->route('linkPageBlock');
        $hasStoredImage = $existing instanceof LinkPageBlock
            && $existing->type === 'image'
            && $existing->contentMedia()->exists();
        $hasStoredFile = $existing instanceof LinkPageBlock
            && $existing->type === 'file'
            && $existing->contentMedia()->exists();

        return [
            'type' => ['required', Rule::in(['text', 'image', 'form', 'file'])],
            'fields' => [$type === 'form' ? 'required' : 'prohibited', 'array', 'min:1', 'max:20'],
            'fields.*' => ['array:id,label,type,required,options'],
            'fields.*.id' => ['required', 'string', 'regex:/\A[a-z][a-z0-9_-]{0,39}\z/', 'distinct:strict'],
            'fields.*.label' => ['required', 'string', 'max:120'],
            'fields.*.type' => ['required', Rule::in(['text', 'email', 'textarea', 'select'])],
            'fields.*.required' => ['required', 'boolean'],
            'fields.*.options' => ['sometimes', 'array', 'min:1', 'max:20'],
            'fields.*.options.*' => ['required', 'string', 'max:120', 'distinct:strict'],
            'is_active' => ['required', 'boolean'],
            'link_page_variant_id' => ['sometimes', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'image' => [
                ...($type === 'image' ? [$hasStoredImage ? 'sometimes' : 'required'] : ['prohibited']),
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'file' => [
                ...($type === 'file' ? [$hasStoredFile ? 'sometimes' : 'required'] : ['prohibited']),
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf',
                'max:25600',
            ],
            'text' => [$type === 'text' ? 'required' : 'prohibited', 'string', 'max:5000'],
            'alt_text' => [$type === 'image' ? 'nullable' : 'prohibited', 'string', 'max:250'],
            'content' => ['prohibited'],
            'content_media_id' => ['prohibited'],
            'media_id' => ['prohibited'],
            'image_url' => ['prohibited'],
            'disk' => ['prohibited'],
            'path' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $text = $this->input('text');
            if (
                $this->input('type') === 'text'
                && is_string($text)
                && preg_match('/<\/?[a-z][^>]*>/i', $text)
            ) {
                $validator->errors()->add('text', 'HTML markup is not supported.');
            }
            $fields = $this->input('fields');
            if ($this->input('type') !== 'form' || ! is_array($fields)) {
                return;
            }
            if (! array_is_list($fields)) {
                $validator->errors()->add('fields', 'Form fields must be an ordered list.');

                return;
            }
            foreach ($fields as $index => $field) {
                if (! is_array($field)) {
                    continue;
                }
                $fieldType = $field['type'] ?? null;
                $options = $field['options'] ?? null;
                if ($fieldType === 'select' && (! is_array($options) || $options === [])) {
                    $validator->errors()->add("fields.{$index}.options", 'Select fields need at least one fixed option.');
                }
                if ($fieldType !== 'select' && array_key_exists('options', $field)) {
                    $validator->errors()->add("fields.{$index}.options", 'Only select fields can define options.');
                }
                if (is_string($field['label'] ?? null) && preg_match('/<\\/?[a-z][^>]*>/i', $field['label'])) {
                    $validator->errors()->add("fields.{$index}.label", 'HTML markup is not supported.');
                }
                if (is_array($options)) {
                    foreach ($options as $optionIndex => $option) {
                        if (is_string($option) && preg_match('/<\\/?[a-z][^>]*>/i', $option)) {
                            $validator->errors()->add("fields.{$index}.options.{$optionIndex}", 'HTML markup is not supported.');
                        }
                    }
                }
            }
        });
    }
}
