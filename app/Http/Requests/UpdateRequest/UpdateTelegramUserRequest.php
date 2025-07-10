<?php

namespace App\Http\Requests\UpdateRequest;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTelegramUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
            'username' => ['nullable', 'string'],
            'name' => ['nullable', 'string'],
            'surname' => ['nullable', 'string'],
            'age' => ['nullable', 'integer', 'min:1', 'max:120'],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'language' => ['nullable', 'string', 'max:3'],
        ];
    }
}
