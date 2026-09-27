<?php

namespace App\Http\Requests\Skills;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ImportSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:256'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'El archivo no puede superar los 256 KB.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $file = $this->file('file');

                if ($file === null || $validator->errors()->has('file')) {
                    return;
                }

                if (! in_array(strtolower($file->getClientOriginalExtension()), ['md', 'markdown', 'txt'], true)) {
                    $validator->errors()->add('file', 'Subí un archivo .md, .markdown o .txt.');
                }
            },
        ];
    }
}
