<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240'
        ];
    }

    public function messages(): array
    {
        return [
            'excel_file.required' => 'Please select an Excel file to upload.',
            'excel_file.file' => 'The uploaded file is invalid.',
            'excel_file.mimes' => 'The file must be a valid Excel file (xlsx, xls, csv).',
            'excel_file.max' => 'The file size cannot exceed 10MB.'
        ];
    }
}
