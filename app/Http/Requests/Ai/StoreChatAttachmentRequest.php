<?php

namespace App\Http\Requests\Ai;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreChatAttachmentRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:25600', 'mimes:jpg,jpeg,png,webp,txt,md,docx'],
            'thread_id' => ['nullable', 'string', 'size:36'],
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

                if (str_starts_with((string) $file->getMimeType(), 'image/') && $file->getSize() > 10240 * 1024) {
                    $validator->errors()->add('file', 'Las imágenes no pueden superar los 10 MB.');
                }
            },
        ];
    }
}
