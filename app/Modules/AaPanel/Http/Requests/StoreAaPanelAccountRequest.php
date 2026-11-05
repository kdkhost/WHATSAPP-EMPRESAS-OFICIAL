<?php

namespace App\Modules\AaPanel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAaPanelAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (request()->is('painel') || request()->is('painel/*')) {
            return auth('web')->check();
        }
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'server_id' => ['required', 'exists:aapanel_servers,id'],
            'plan_id' => ['nullable', 'exists:aapanel_plans,id'],
            'customer_id' => ['nullable', 'integer'],
            'domain' => ['required', 'string', 'max:255', 'unique:aapanel_accounts,domain'],
            'username' => ['nullable', 'string', 'max:60'],
        ];
    }
}
