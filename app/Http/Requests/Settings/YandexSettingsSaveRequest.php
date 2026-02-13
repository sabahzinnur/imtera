<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class YandexSettingsSaveRequest extends FormRequest
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
            'maps_url' => ['required', 'url', 'regex:/yandex\.(ru|com)\/maps/'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'maps_url.required' => __('Введите ссылку'),
            'maps_url.url' => __('Введите корректную ссылку'),
            'maps_url.regex' => __('Ссылка должна быть с Яндекс Карт'),
        ];
    }
}
