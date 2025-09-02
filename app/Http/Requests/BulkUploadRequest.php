<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'excel_file' => [
                'required',
                'file',
                'max:10240', // 10MB max
                'mimes:xlsx,xls,csv,txt', // Allow more file types
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv,text/plain,application/csv'
            ]
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'excel_file.required' => 'Please select a file to upload.',
            'excel_file.file' => 'The uploaded file is not valid.',
            'excel_file.max' => 'The file size must not exceed 10MB.',
            'excel_file.mimes' => 'The file must be a valid Excel file (xlsx, xls, csv) or text file.',
            'excel_file.mimetypes' => 'The file must be a valid Excel file (xlsx, xls, csv) or text file.'
        ];
    }
}
