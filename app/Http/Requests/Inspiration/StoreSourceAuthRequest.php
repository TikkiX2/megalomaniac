<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspiration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSourceAuthRequest extends FormRequest
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
        $isLogin = $this->input('method') === 'login';

        return [
            'method' => ['required', Rule::in(['cookie', 'login'])],
            'cookie' => ['required_if:method,cookie', 'string', 'min:5', 'max:8000'],
            'email' => ['required_if:method,login', 'email', 'max:255'],
            'password' => ['required_if:method,login', 'string', 'max:255'],
            'acknowledged_login' => $isLogin ? ['required', 'boolean', 'accepted'] : ['nullable'],
        ];
    }
}
