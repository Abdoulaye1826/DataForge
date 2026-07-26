<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class ImportDatasetRequest extends FormRequest
{
    private const ALLOWED_EXTENSIONS = ['csv', 'xlsx', 'xls', 'json', 'parquet'];

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1'],
            'files.*' => [
                'required',
                'file',
                'max:102400',
                function (string $attribute, mixed $value, callable $fail): void {
                    if ($value instanceof UploadedFile
                        && ! in_array(strtolower($value->getClientOriginalExtension()), self::ALLOWED_EXTENSIONS, true)) {
                        $fail('Le fichier :attribute doit être un CSV, Excel, JSON ou Parquet.');
                    }
                },
            ],
        ];
    }
}
