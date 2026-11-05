<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — proibida cópia, distribuição ou uso não autorizado.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreloadSettings extends Model
{
    protected $fillable = [
        'style',
        'background_type',
        'background_color',
        'background_gradient_start',
        'background_gradient_end',
        'background_gradient_angle',
        'background_image',
        'background_video',
        'logo',
        'show_logo',
        'show_text',
        'text',
        'text_color',
        'spinner_color',
        'accent_color',
        'animation_speed',
        'font_family',
        'font_size',
        'show_progress_bar',
        'progress_bar_color',
        'blur_background',
        'blur_amount',
        'custom_css',
        'custom_js',
        'enabled',
    ];

    protected $casts = [
        'show_logo' => 'boolean',
        'show_text' => 'boolean',
        'show_progress_bar' => 'boolean',
        'blur_background' => 'boolean',
        'enabled' => 'boolean',
        'animation_speed' => 'integer',
        'font_size' => 'integer',
        'blur_amount' => 'integer',
        'background_gradient_angle' => 'integer',
    ];

    /**
     * Obter configurações ativas (singleton pattern)
     */
    public static function active(): ?self
    {
        return static::where('enabled', true)->first() ?? static::first();
    }

    /**
     * Gerar CSS inline para o preload
     */
    public function generateCss(): string
    {
        $css = '';

        // Background
        if ($this->background_type === 'solid') {
            $css .= "background: {$this->background_color};";
        } elseif ($this->background_type === 'gradient') {
            $start = $this->background_gradient_start ?? $this->background_color;
            $end = $this->background_gradient_end ?? $this->accent_color;
            $angle = $this->background_gradient_angle ?? 135;
            $css .= "background: linear-gradient({$angle}deg, {$start}, {$end});";
        } elseif ($this->background_type === 'image' && $this->background_image) {
            $css .= "background: url('{$this->background_image}') center/cover no-repeat;";
        }

        // Blur
        if ($this->blur_background && $this->background_type === 'image') {
            $css .= "backdrop-filter: blur({$this->blur_amount}px);";
        }

        return $css;
    }

    /**
     * Obter configuração de animação
     */
    public function getAnimationDuration(): string
    {
        return ($this->animation_speed / 1000) . 's';
    }
}
