<?php

namespace App\Http\Requests\Content;

use App\Support\Content\ContentPagesRegistry;
use Illuminate\Foundation\Http\FormRequest;

class CreateStaticPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('slug')) {
            $this->merge([
                'slug' => ContentPagesRegistry::normalizeSlug((string) $this->input('slug')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'max:64',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $slug = (string) $value;
                    if (ContentPagesRegistry::isBuiltInSlug($slug)) {
                        $fail('This slug is reserved for a built-in page.');

                        return;
                    }
                    foreach (ContentPagesRegistry::customPages() as $page) {
                        if ($page['slug'] === $slug) {
                            $fail('A page with this slug already exists.');

                            return;
                        }
                    }
                },
            ],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:240'],
            'title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'breadcrumb' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
