<?php

namespace App\Http\Requests;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('employee.documents.*')
            ? ($this->user()?->hasRole('employee') ?? false)
            : ($this->user()?->hasRole('admin') ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['title' => is_string($this->title) ? trim($this->title) : $this->title]);
        if ($this->routeIs('employee.documents.*') && ! $this->exists('employee_id')) {
            $this->merge(['employee_id' => $this->user()->id]);
        }
        if ($this->routeIs('admin.employees.documents.store')) {
            $this->merge(['employee_id' => $this->route('employee')->id, 'retention_category' => 'Standard (6 years)']);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'employee_id' => [$this->routeIs('employee.documents.*') ? 'required' : 'nullable', 'integer', ...($this->routeIs('employee.documents.*') ? [Rule::in([$this->user()->id])] : []), function ($attribute, $value, $fail) {
                if (! User::role('employee')->whereKey($value)->exists()) {
                    $fail('Choose a valid employee.');
                }
            }],
            'category' => ['required', Rule::in(Document::CATEGORIES)],
            'access_classification' => ['required', Rule::in(Document::CLASSIFICATIONS)],
            'issue_date' => ['nullable', 'date_format:Y-m-d'],
            'expiry_date' => ['nullable', 'date_format:Y-m-d', ...($this->filled('issue_date') ? ['after_or_equal:issue_date'] : [])],
            'retention_category' => ['required', Rule::in(Document::RETENTION_CATEGORIES)],
            'retention_until' => ['nullable', 'date_format:Y-m-d', ...($this->filled('issue_date') ? ['after_or_equal:issue_date'] : [])],
            'retention_reason' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'extensions:pdf,doc,docx', 'max:10240'],
        ];
    }
}
