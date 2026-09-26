<?php

namespace App\Http\Requests\Ai;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ApproveChatTurnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*' => ['required', 'bail', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_bool($value)) {
                    return;
                }

                if (! is_array($value)) {
                    $fail('Cada decisión debe ser un objeto o un booleano.');

                    return;
                }

                if (! in_array($value['action'] ?? null, ['approve', 'reject', 'edit'], true)) {
                    $fail('La acción de la decisión no es válida.');

                    return;
                }

                if (isset($value['result']) && ! is_string($value['result'])) {
                    $fail('El resultado debe ser texto.');
                } elseif (isset($value['result']) && mb_strlen($value['result']) > 4000) {
                    $fail('El resultado no puede superar los 4000 caracteres.');
                }

                if (isset($value['arguments']) && ! is_array($value['arguments'])) {
                    $fail('Los argumentos deben ser un objeto.');
                }
            }],
        ];
    }
}
