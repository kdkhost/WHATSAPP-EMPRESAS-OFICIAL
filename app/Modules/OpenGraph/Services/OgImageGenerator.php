<?php

namespace App\Modules\OpenGraph\Services;

use App\Modules\Settings\Services\SettingsManager;

/**
 * Gerador de imagens Open Graph usando GD (nativo PHP).
 * Produz imagens 1200x630px otimizadas para compartilhamento em redes sociais.
 */
class OgImageGenerator
{
    protected SettingsManager $settings;

    public function __construct(SettingsManager $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Gera imagem OG e retorna o path relativo ao public/.
     */
    public function generate(string $title, ?string $subtitle = null, ?string $slug = null): string
    {
        $width = 1200;
        $height = 630;

        // Cores do tema
        $bgColor = $this->hexToRgb($this->settings->get('og.bg_color', $this->settings->get('theme.primary', '#0d6efd')));
        $textColor = $this->hexToRgb($this->settings->get('og.text_color', '#ffffff'));
        $accentColor = $this->hexToRgb($this->settings->get('og.accent_color', $this->settings->get('theme.secondary', '#6610f2')));

        // Criar imagem
        $img = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($img, $bgColor[0], $bgColor[1], $bgColor[2]);
        $text = imagecolorallocate($img, $textColor[0], $textColor[1], $textColor[2]);
        $accent = imagecolorallocate($img, $accentColor[0], $accentColor[1], $accentColor[2]);

        // Fundo gradiente
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $r = (int) ($bgColor[0] * (1 - $ratio) + $accentColor[0] * $ratio);
            $g = (int) ($bgColor[1] * (1 - $ratio) + $accentColor[1] * $ratio);
            $b = (int) ($bgColor[2] * (1 - $ratio) + $accentColor[2] * $ratio);
            $lineColor = imagecolorallocate($img, $r, $g, $b);
            imageline($img, 0, $y, $width, $y, $lineColor);
        }

        // Barra decorativa no topo
        imagefilledrectangle($img, 0, 0, $width, 8, $accent);

        // Logo/nome da empresa
        $appName = $this->settings->get('app.name', config('app.name', 'Cobranca WhatsApp'));
        $fontPath = $this->getFontPath();

        if ($fontPath && function_exists('imagettftext')) {
            // Com fonte TrueType
            imagettftext($img, 18, 0, 60, 80, $text, $fontPath, strtoupper($appName));

            // Titulo principal
            $titleLines = $this->wrapText($title, 28, 30);
            $yPos = 200;
            foreach ($titleLines as $line) {
                imagettftext($img, 42, 0, 60, $yPos, $text, $fontPath, $line);
                $yPos += 60;
            }

            // Subtitulo
            if ($subtitle) {
                $subtitleColor = imagecolorallocatealpha($img, $textColor[0], $textColor[1], $textColor[2], 40);
                imagettftext($img, 20, 0, 60, $yPos + 30, $subtitleColor, $fontPath, $subtitle);
            }

            // URL no rodape
            $url = rtrim(config('app.url', ''), '/');
            imagettftext($img, 14, 0, 60, $height - 40, $text, $fontPath, $url);
        } else {
            // Fallback sem TTF (fontes built-in)
            imagestring($img, 5, 60, 60, strtoupper($appName), $text);
            imagestring($img, 5, 60, 200, $this->truncate($title, 50), $text);
            if ($subtitle) {
                imagestring($img, 4, 60, 240, $this->truncate($subtitle, 60), $text);
            }
            $url = rtrim(config('app.url', ''), '/');
            imagestring($img, 3, 60, $height - 40, $url, $text);
        }

        // Salvar
        $dir = public_path('og-images');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = ($slug ?: md5($title)) . '.png';
        $path = $dir . '/' . $filename;
        imagepng($img, $path, 7); // compressao 7 (bom balanco qualidade/tamanho)
        imagedestroy($img);

        return '/og-images/' . $filename;
    }

    /**
     * Retorna URL da imagem OG para uma pagina. Gera se nao existir.
     */
    public function getOrGenerate(string $title, ?string $subtitle = null, ?string $slug = null): string
    {
        $filename = ($slug ?: md5($title)) . '.png';
        $path = public_path('og-images/' . $filename);

        if (file_exists($path)) {
            $maxAge = (int) $this->settings->get('og.cache_hours', 24) * 3600;
            if ((time() - filemtime($path)) < $maxAge) {
                return '/og-images/' . $filename;
            }
        }

        return $this->generate($title, $subtitle, $slug);
    }

    /**
     * Limpa todas as imagens OG geradas (cache).
     */
    public function clearCache(): int
    {
        $dir = public_path('og-images');
        if (!is_dir($dir)) return 0;

        $files = glob($dir . '/*.png');
        foreach ($files as $f) {
            @unlink($f);
        }
        return count($files);
    }

    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    protected function getFontPath(): ?string
    {
        // Tentar fontes comuns
        $candidates = [
            storage_path('app/fonts/Inter-Bold.ttf'),
            storage_path('app/fonts/Roboto-Bold.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu-sans-fonts/DejaVuSans-Bold.ttf',
        ];
        foreach ($candidates as $f) {
            if (is_file($f)) return $f;
        }
        return null;
    }

    protected function wrapText(string $text, int $maxCharsPerLine, int $maxLines = 3): array
    {
        $words = explode(' ', $text);
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if (strlen($current . ' ' . $word) > $maxCharsPerLine) {
                $lines[] = trim($current);
                $current = $word;
                if (count($lines) >= $maxLines) break;
            } else {
                $current .= ' ' . $word;
            }
        }
        if (count($lines) < $maxLines && trim($current)) {
            $lines[] = trim($current);
        }

        return $lines;
    }

    protected function truncate(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 3) . '...' : $text;
    }
}
