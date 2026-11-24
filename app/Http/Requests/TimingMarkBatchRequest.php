<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TimingMarkBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorización la hace el token (ability device:marks)
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'marks' => ['required', 'array', 'min:1', 'max:100'],
            'marks.*.client_mark_id' => ['required', 'uuid'],
            'marks.*.bib' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'marks.*.device_time_ms' => ['required', 'integer', 'min:0'],
            'marks.*.clock_offset_ms' => ['nullable', 'integer'],
            'marks.*.source' => ['nullable', 'string', 'in:station,chip_reader,manual'],
        ];
    }
}
