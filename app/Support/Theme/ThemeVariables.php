<?php

namespace App\Support\Theme;

use App\Modules\Settings\Services\SettingsManager;

class ThemeVariables
{
    public function __construct(protected SettingsManager $settings)
    {
    }

    public function base(): array
    {
        $primary = $this->color('theme.primary', '#5b4df6');
        $secondary = $this->color('theme.secondary', '#7c3aed');
        $success = $this->color('theme.success', '#198754');
        $danger = $this->color('theme.danger', '#dc3545');
        $warning = $this->color('theme.warning', '#ffc107');
        $info = $this->color('theme.info', '#0dcaf0');
        $dark = $this->color('theme.dark', '#111827');
        $light = $this->color('theme.light', '#f8f9fa');
        $sidebarBg = $this->color('theme.sidebar_bg', '#1a1d21');
        $sidebarActive = $this->color('theme.sidebar_active', $primary);
        $navbarBg = $this->color('theme.navbar_bg', '#f8fafc');
        $bodyBg = $this->color('theme.body_bg', '#eef2f7');
        $sidebarColor = $this->contrastText($sidebarBg);
        $navbarColor = $this->contrastText($navbarBg);
        $bodyColor = $this->contrastText($bodyBg, '#111827', '#f8fafc');
        $isSidebarDark = $this->isDark($sidebarBg);
        $isNavbarDark = $this->isDark($navbarBg);
        $isBodyDark = $this->isDark($bodyBg);

        return [
            'bs-primary' => $primary,
            'bs-primary-rgb' => $this->rgb($primary),
            'bs-primary-bg-subtle' => $this->rgba($primary, 0.16),
            'bs-primary-border-subtle' => $this->rgba($primary, 0.38),
            'bs-secondary' => $secondary,
            'bs-secondary-rgb' => $this->rgb($secondary),
            'bs-secondary-bg-subtle' => $this->rgba($secondary, 0.14),
            'bs-secondary-border-subtle' => $this->rgba($secondary, 0.34),
            'bs-success' => $success,
            'bs-success-rgb' => $this->rgb($success),
            'bs-success-bg-subtle' => $this->rgba($success, 0.16),
            'bs-success-border-subtle' => $this->rgba($success, 0.34),
            'bs-danger' => $danger,
            'bs-danger-rgb' => $this->rgb($danger),
            'bs-danger-bg-subtle' => $this->rgba($danger, 0.16),
            'bs-danger-border-subtle' => $this->rgba($danger, 0.34),
            'bs-warning' => $warning,
            'bs-warning-rgb' => $this->rgb($warning),
            'bs-warning-bg-subtle' => $this->rgba($warning, 0.18),
            'bs-warning-border-subtle' => $this->rgba($warning, 0.38),
            'bs-info' => $info,
            'bs-info-rgb' => $this->rgb($info),
            'bs-info-bg-subtle' => $this->rgba($info, 0.16),
            'bs-info-border-subtle' => $this->rgba($info, 0.34),
            'bs-dark' => $dark,
            'bs-dark-rgb' => $this->rgb($dark),
            'bs-light' => $light,
            'bs-light-rgb' => $this->rgb($light),
            'app-sidebar-bg' => $sidebarBg,
            'app-sidebar-color' => $sidebarColor,
            'app-sidebar-muted' => $isSidebarDark ? $this->mix('#f8fafc', $sidebarBg, 0.72) : $this->mix('#0f172a', $sidebarBg, 0.58),
            'app-sidebar-active' => $sidebarActive,
            'app-sidebar-active-color' => $this->contrastText($sidebarActive),
            'app-sidebar-hover' => $isSidebarDark ? 'rgba(255,255,255,.07)' : 'rgba(15,23,42,.06)',
            'app-sidebar-tree-active' => $isSidebarDark ? 'rgba(255,255,255,.10)' : 'rgba(15,23,42,.08)',
            'app-sidebar-brand-bg' => $isSidebarDark ? $this->mix('#000000', $sidebarBg, 0.14) : $this->mix('#ffffff', $sidebarBg, 0.18),
            'app-sidebar-border' => $isSidebarDark ? 'rgba(255,255,255,.06)' : 'rgba(15,23,42,.08)',
            'app-sidebar-scrollbar' => $isSidebarDark ? 'rgba(255,255,255,.16)' : 'rgba(15,23,42,.18)',
            'app-navbar-bg' => $navbarBg,
            'app-navbar-color' => $navbarColor,
            'app-navbar-muted' => $isNavbarDark ? $this->mix('#f8fafc', $navbarBg, 0.72) : $this->mix('#0f172a', $navbarBg, 0.58),
            'app-navbar-border' => $isNavbarDark ? 'rgba(255,255,255,.08)' : 'rgba(15,23,42,.08)',
            'app-body-bg' => $bodyBg,
            'app-body-color' => $bodyColor,
            'app-body-muted' => $isBodyDark ? $this->mix('#f8fafc', $bodyBg, 0.74) : $this->mix('#0f172a', $bodyBg, 0.58),
            'app-surface-bg' => $isBodyDark ? $this->mix('#ffffff', $bodyBg, 0.06) : '#ffffff',
            'app-surface-soft' => $isBodyDark ? $this->mix('#ffffff', $bodyBg, 0.1) : $this->mix('#ffffff', $bodyBg, 0.78),
            'app-surface-border' => $isBodyDark ? 'rgba(255,255,255,.10)' : 'rgba(15,23,42,.08)',
            'app-radius' => '0.5rem',
            'app-radius-premium' => '18px',
            'app-border-soft' => 'rgba(15,23,42,.08)',
            'app-shadow-premium' => '0 14px 35px rgba(15,23,42,.05)',
            'app-transition' => 'all .15s ease',
        ];
    }

    public function darkPalette(): array
    {
        $primary = $this->color('theme.primary', '#5b4df6');
        $sidebarActive = $this->color('theme.sidebar_active', $primary);
        $bodyColor = '#e5e7eb';
        $muted = '#8b97ad';
        $border = '#1e293b';

        return [
            'bs-body-bg' => '#020617',
            'bs-body-bg-rgb' => '2, 6, 23',
            'bs-body-color' => $bodyColor,
            'bs-body-color-rgb' => $this->rgb($bodyColor),
            'bs-secondary-color' => $muted,
            'bs-secondary-bg' => '#0b1120',
            'bs-tertiary-bg' => '#0f172a',
            'bs-border-color' => $border,
            'bs-border-color-translucent' => 'rgba(148, 163, 184, .16)',
            'bs-emphasis-color' => '#f8fafc',
            'bs-link-color' => $this->shade($primary, 18),
            'bs-link-hover-color' => $this->shade($primary, 26),
            'app-sidebar-bg' => '#111827',
            'app-sidebar-active' => $sidebarActive,
            'app-border-soft' => '#1e293b',
            'app-shadow-premium' => 'none',
            'app-body-color' => $bodyColor,
            'app-body-muted' => $muted,
            'app-navbar-color' => $bodyColor,
            'app-navbar-muted' => $muted,
            'app-navbar-border' => 'rgba(255,255,255,.08)',
            'app-surface-bg' => '#0b1020',
            'app-surface-soft' => '#0f172a',
            'app-surface-border' => 'rgba(255,255,255,.08)',
            'admin-dark-bg' => '#020617',
            'admin-dark-surface' => '#0b1020',
            'admin-dark-surface-soft' => '#0f172a',
            'admin-dark-field' => '#050a18',
            'admin-dark-border' => $border,
            'admin-dark-muted' => $muted,
            'tenant-dark-bg' => '#020617',
            'tenant-dark-surface' => '#0b1020',
            'tenant-dark-surface-soft' => '#0f172a',
            'tenant-dark-field' => '#050a18',
            'tenant-dark-border' => $border,
            'tenant-dark-muted' => $muted,
        ];
    }

    public function lightPalette(): array
    {
        $light = $this->color('theme.light', '#f8f9fa');
        $bodyBg = $this->color('theme.body_bg', '#eef2f7');
        $dark = '#212529';
        $isBodyDark = $this->isDark($bodyBg);
        $bodyColor = $this->contrastText($bodyBg, '#111827', '#f8fafc');
        $secondaryColor = $isBodyDark ? $this->mix('#f8fafc', $bodyBg, 0.7) : '#6c757d';
        $border = $isBodyDark ? $this->mix('#ffffff', $bodyBg, 0.18) : '#dee2e6';
        $surface = $isBodyDark ? $this->mix('#ffffff', $bodyBg, 0.06) : '#ffffff';
        $surfaceSoft = $isBodyDark ? $this->mix('#ffffff', $bodyBg, 0.1) : $this->mix('#ffffff', $bodyBg, 0.78);

        return [
            'bs-body-bg' => $bodyBg,
            'bs-body-bg-rgb' => $this->rgb($bodyBg),
            'bs-body-color' => $bodyColor,
            'bs-body-color-rgb' => $this->rgb($bodyColor),
            'bs-secondary-color' => $secondaryColor,
            'bs-secondary-bg' => $light,
            'bs-tertiary-bg' => $light,
            'bs-border-color' => $border,
            'bs-border-color-translucent' => $isBodyDark ? 'rgba(255, 255, 255, .10)' : 'rgba(0, 0, 0, .08)',
            'bs-emphasis-color' => $bodyColor,
            'bs-link-color' => $this->color('theme.primary', '#5b4df6'),
            'bs-link-hover-color' => $this->shade($this->color('theme.primary', '#5b4df6'), -8),
            'app-border-soft' => $isBodyDark ? 'rgba(255,255,255,.08)' : 'rgba(15,23,42,.08)',
            'app-shadow-premium' => '0 14px 35px rgba(15,23,42,.05)',
            'app-body-color' => $bodyColor,
            'app-body-muted' => $secondaryColor,
            'app-surface-bg' => $surface,
            'app-surface-soft' => $surfaceSoft,
            'app-surface-border' => $isBodyDark ? 'rgba(255,255,255,.10)' : 'rgba(15,23,42,.08)',
            'admin-dark-surface' => $surface,
            'admin-dark-field' => $surface,
            'admin-dark-border' => $border,
            'admin-dark-muted' => $secondaryColor,
            'tenant-dark-surface' => $surface,
            'tenant-dark-field' => $surface,
            'tenant-dark-border' => $border,
            'tenant-dark-muted' => $secondaryColor,
        ];
    }

    public function forWhiteLabel(array $variables, array $whiteLabel): array
    {
        return array_merge($variables, array_filter([
            'bs-primary' => $whiteLabel['primary_color'] ?? null,
            'bs-primary-rgb' => isset($whiteLabel['primary_color']) ? $this->rgb((string) $whiteLabel['primary_color']) : null,
            'bs-primary-bg-subtle' => isset($whiteLabel['primary_color']) ? $this->rgba((string) $whiteLabel['primary_color'], 0.16) : null,
            'bs-primary-border-subtle' => isset($whiteLabel['primary_color']) ? $this->rgba((string) $whiteLabel['primary_color'], 0.38) : null,
            'bs-secondary' => $whiteLabel['secondary_color'] ?? null,
            'app-sidebar-bg' => $whiteLabel['sidebar_color'] ?? null,
            'app-sidebar-active' => $whiteLabel['primary_color'] ?? null,
        ], fn ($value) => filled($value)));
    }

    protected function color(string $key, string $fallback): string
    {
        return $this->normalizeHex((string) $this->settings->get($key, $fallback), $fallback);
    }

    protected function normalizeHex(string $value, string $fallback): string
    {
        $candidate = trim($value);

        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $candidate) !== 1) {
            return strtolower($fallback);
        }

        if (strlen($candidate) === 4) {
            $candidate = '#'.$candidate[1].$candidate[1].$candidate[2].$candidate[2].$candidate[3].$candidate[3];
        }

        return strtolower($candidate);
    }

    protected function rgb(string $hex): string
    {
        [$r, $g, $b] = $this->rgbArray($hex);

        return $r.', '.$g.', '.$b;
    }

    protected function rgba(string $hex, float $alpha): string
    {
        return 'rgba('.$this->rgb($hex).', '.number_format(max(0, min(1, $alpha)), 2, '.', '').')';
    }

    protected function shade(string $hex, int $percent): string
    {
        $target = $percent >= 0 ? '#ffffff' : '#000000';
        $ratio = min(1, abs($percent) / 100);

        return $this->mix($target, $hex, $ratio);
    }

    protected function mix(string $foreground, string $background, float $ratio): string
    {
        $ratio = max(0, min(1, $ratio));
        [$fr, $fg, $fb] = $this->rgbArray($foreground);
        [$br, $bg, $bb] = $this->rgbArray($background);

        $mix = fn (int $from, int $to): int => (int) round(($from * $ratio) + ($to * (1 - $ratio)));

        return sprintf('#%02x%02x%02x', $mix($fr, $br), $mix($fg, $bg), $mix($fb, $bb));
    }

    protected function rgbArray(string $hex): array
    {
        $normalized = ltrim($this->normalizeHex($hex, '#000000'), '#');

        return [
            hexdec(substr($normalized, 0, 2)),
            hexdec(substr($normalized, 2, 2)),
            hexdec(substr($normalized, 4, 2)),
        ];
    }

    protected function contrastText(string $background, string $dark = '#0f172a', string $light = '#f8fafc'): string
    {
        return $this->isDark($background) ? strtolower($light) : strtolower($dark);
    }

    protected function isDark(string $hex): bool
    {
        [$r, $g, $b] = $this->rgbArray($hex);

        $normalize = static function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928
                ? $value / 12.92
                : (($value + 0.055) / 1.055) ** 2.4;
        };

        $luminance = (0.2126 * $normalize($r)) + (0.7152 * $normalize($g)) + (0.0722 * $normalize($b));

        return $luminance < 0.42;
    }
}
