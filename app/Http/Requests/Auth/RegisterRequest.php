<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:7', 'max:50'],
            'username' => ['required', 'string', 'min:7', 'max:80'],
            'date' => ['required', 'date'],
            'photo' => ['required', 'string'],
            'phone' => ['required', 'string', 'unique:users,phone'],
            'email' => ['required', 'email', 'string', 'unique:users,email'],
            'code' => ['required', 'numeric'],
            'address' => ['required', 'array'],
            'password' => ['required', 'confirmed', 'min:10', 'max:100'],
        ];
    }
}
