<?php

namespace App\Modules\AaPanel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAaPanelServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'security_entry' => ['nullable', 'string', 'max:60'],
            'use_ssl' => ['nullable', 'boolean'],
            'api_key' => ['required_without:id', 'nullable', 'string', 'max:500'],
        ];
    }
}
