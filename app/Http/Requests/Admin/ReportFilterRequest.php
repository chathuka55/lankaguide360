<?php

namespace App\Http\Requests\Admin;

use App\Enums\Tier;
use App\Services\ReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin');
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'tier' => ['nullable', Rule::enum(Tier::class)],
        ];
    }

    public function service(): ReportService
    {
        return ReportService::forPeriod($this->validated('from'), $this->validated('to'), $this->validated('tier'));
    }
}
