<?php

namespace App\Support\Theme;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

class ThemeManager
{
    public function __construct(
        protected ThemeVariables $variables,
        protected ThemeResolver $resolver,
    ) {
    }

    public function forAdmin(?Authenticatable $user, Request $request): array
    {
        $mode = $this->resolver->resolveMode($request, $user);
        $variables = $this->variablesForMode($mode);

        return $this->payload($mode, $variables);
    }

    public function forTenant(?Authenticatable $user, ?Tenant $tenant, Request $request): array
    {
        $mode = $this->resolver->resolveMode($request, $user);
        $variables = $this->variablesForMode($mode, $tenant);

        return $this->payload($mode, $variables);
    }

    public function variablesForMode(string $mode, ?Tenant $tenant = null): array
    {
        $variables = $this->resolveVariables($mode);

        if ($tenant?->whiteLabelEnabled()) {
            $variables = $this->variables->forWhiteLabel($variables, $tenant->whiteLabelSettings());
        }

        return $variables;
    }

    public function persist(Request $request, string $mode): void
    {
        $request->session()->put('theme_mode', $mode === 'dark' ? 'dark' : 'light');
    }

    public function toCssVariables(array $variables): string
    {
        $chunks = [];

        foreach ($variables as $name => $value) {
            $chunks[] = '--'.$name.': '.$value.';';
        }

        return implode(' ', $chunks);
    }

    protected function resolveVariables(string $mode): array
    {
        return array_merge(
            $this->variables->base(),
            $mode === 'dark' ? $this->variables->darkPalette() : $this->variables->lightPalette(),
        );
    }

    protected function payload(string $mode, array $variables): array
    {
        return [
            'mode' => $mode,
            'is_dark' => $mode === 'dark',
            'variables' => $variables,
            'theme_color' => $this->resolver->themeColor($mode, $variables),
            'css_variables' => $this->toCssVariables($variables),
        ];
    }
}
