<?php

declare(strict_types=1);

namespace App\Http\Requests\Journal;

use App\Models\JournalMoment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePhotoRequest extends FormRequest
{
    public const int MAX_PHOTOS = 8;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'La foto no puede superar los 10 MB.',
            'file.image' => 'Solo se aceptan imágenes JPG, PNG o WebP.',
            'file.mimetypes' => 'Solo se aceptan imágenes JPG, PNG o WebP.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $moment = $this->route('moment');

            if (! $moment instanceof JournalMoment) {
                return;
            }

            if ($moment->getMedia('photos')->count() >= self::MAX_PHOTOS) {
                $validator->errors()->add('file', 'Máximo 8 fotos por momento.');
            }
        });
    }
}
