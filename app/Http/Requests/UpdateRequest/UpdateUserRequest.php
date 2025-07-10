<?php

namespace App\Http\Requests\UpdateRequest;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'unique'],
            'phone' => ['required', 'string', 'unique'],
            'address' => ['required', 'array'],
            'password' => ['required', 'confirmed', 'min:10', 'max:100'],
            'roles' => ['required', 'array'],
            'roles.*.role_code' => ['required', 'string'],
            'roles.*.status' => ['required', 'boolean'], 
        ];
    }
}
