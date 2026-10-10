<?php

namespace App\Http\Requests\Hoy;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiaItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('item')?->dia?->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', 'in:pendiente,hecho'],
            'nota_cierre' => ['nullable', 'string', 'max:255'],
        ];
    }
}
