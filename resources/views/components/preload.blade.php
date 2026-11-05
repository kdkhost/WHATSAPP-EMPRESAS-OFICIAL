@php
    $settings = \App\Models\PreloadSettings::active();
    $appName = app(\App\Modules\Settings\Services\SettingsManager::class)->get('app.name', config('app.name'));
    $appLogo = app(\App\Modules\Settings\Services\SettingsManager::class)->get('app.logo');
    if (!$settings || !$settings->enabled) {
        // Fallback para preload premium padrão
        $settings = new \App\Models\PreloadSettings([
            'style' => 'gradient',
            'background_type' => 'gradient',
            'background_color' => '#667eea',
            'background_gradient_start' => '#667eea',
            'background_gradient_end' => '#764ba2',
            'background_gradient_angle' => 135,
            'show_text' => true,
            'text' => $appName,
            'text_color' => '#ffffff',
            'spinner_color' => '#ffffff',
            'accent_color' => '#764ba2',
            'animation_speed' => 1200,
            'font_family' => 'system-ui, -apple-system, sans-serif',
            'font_size' => 18,
            'show_progress_bar' => true,
            'progress_bar_color' => '#ffffff',
            'logo' => $appLogo,
            'show_logo' => !empty($appLogo),
        ]);
    }
    
    $bgStyle = '';
    if ($settings->background_type === 'solid') {
        $bgStyle = "background: {$settings->background_color};";
    } elseif ($settings->background_type === 'gradient') {
        $start = $settings->background_gradient_start ?? $settings->background_color;
        $end = $settings->background_gradient_end ?? $settings->accent_color;
        $angle = $settings->background_gradient_angle ?? 135;
        $bgStyle = "background: linear-gradient({$angle}deg, {$start}, {$end});";
    } elseif ($settings->background_type === 'image' && $settings->background_image) {
        $bgStyle = "background: url('{$settings->background_image}') center/cover no-repeat;";
        if ($settings->blur_background) {
            $bgStyle .= "backdrop-filter: blur({$settings->blur_amount}px);";
        }
    }
    
    $animDuration = ($settings->animation_speed / 1000) . 's';
@endphp

<div id="preload" style="position:fixed;inset:0;{{ $bgStyle }}display:flex;align-items:center;justify-content:center;z-index:9999;transition:opacity .4s;font-family:{{ $settings->font_family }};">
    @if($settings->background_type === 'video' && $settings->background_video)
    <video autoplay muted loop playsinline style="position:absolute;width:100%;height:100%;object-fit:cover;opacity:0.5;z-index:0;">
        <source src="{{ $settings->background_video }}" type="video/mp4">
    </video>
    @endif

    <div style="position:relative;z-index:1;text-align:center;">
        @if($settings->show_logo && $settings->logo && $settings->style !== 'logo')
        <img src="{{ $settings->logo }}" alt="Logo" style="max-width:120px;max-height:120px;margin-bottom:20px;">
        @endif

        @if($settings->style === 'spinner')
        <div class="preload-spinner" style="display:inline-block;width:3rem;height:3rem;border:0.25em solid {{ $settings->spinner_color }};border-right-color:transparent;border-radius:50%;animation:preload-spin {{ $animDuration }} linear infinite;" role="status"></div>
        
        @elseif($settings->style === 'pulse')
        <div style="width:60px;height:60px;background:{{ $settings->spinner_color }};border-radius:50%;animation:preload-pulse {{ $animDuration }} ease-in-out infinite;"></div>
        
        @elseif($settings->style === 'dots')
        <div style="display:flex;gap:10px;justify-content:center;">
            <div style="width:15px;height:15px;background:{{ $settings->spinner_color }};border-radius:50%;animation:preload-bounce {{ $animDuration }} ease-in-out infinite;"></div>
            <div style="width:15px;height:15px;background:{{ $settings->spinner_color }};border-radius:50%;animation:preload-bounce {{ $animDuration }} ease-in-out 0.2s infinite;"></div>
            <div style="width:15px;height:15px;background:{{ $settings->spinner_color }};border-radius:50%;animation:preload-bounce {{ $animDuration }} ease-in-out 0.4s infinite;"></div>
        </div>
        
        @elseif($settings->style === 'wave')
        <div style="display:flex;gap:5px;align-items:flex-end;height:40px;justify-content:center;">
            @for($i = 0; $i < 5; $i++)
            <div style="width:8px;background:{{ $settings->spinner_color }};animation:preload-wave {{ $animDuration }} ease-in-out {{ $i * 0.1 }}s infinite;"></div>
            @endfor
        </div>
        
        @elseif($settings->style === 'gradient')
        <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,{{ $settings->spinner_color }},{{ $settings->accent_color }});animation:preload-rotate {{ $animDuration }} linear infinite;"></div>
        
        @elseif($settings->style === 'particles')
        <div style="position:relative;width:100px;height:100px;">
            @foreach([0, 45, 90, 135, 180, 225, 270, 315] as $i => $angle)
            <div style="position:absolute;width:10px;height:10px;background:{{ $settings->spinner_color }};border-radius:50%;top:50%;left:50%;transform:rotate({{ $angle }}deg) translateX(30px);animation:preload-particle {{ $animDuration }} ease-in-out {{ $i * 0.1 }}s infinite;"></div>
            @endforeach
        </div>
        
        @elseif($settings->style === 'logo' && $settings->logo)
        <img src="{{ $settings->logo }}" alt="Logo" style="max-width:120px;max-height:120px;animation:preload-pulse {{ $animDuration }} ease-in-out infinite;">
        @endif

        @if($settings->show_text && $settings->text)
        <p style="color:{{ $settings->text_color }};font-size:{{ $settings->font_size }}px;margin-top:20px;margin-bottom:0;">
            {{ $settings->text }}
        </p>
        @endif
    </div>

    @if($settings->show_progress_bar)
    <div style="position:absolute;bottom:0;left:0;right:0;height:4px;background:rgba(255,255,255,0.2);z-index:2;">
        <div style="height:100%;background:{{ $settings->progress_bar_color }};animation:preload-progress 2s ease-in-out infinite;"></div>
    </div>
    @endif
</div>

<style>
@keyframes preload-spin {
    to { transform: rotate(360deg); }
}
@keyframes preload-pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.1); opacity: 0.7; }
}
@keyframes preload-bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-20px); }
}
@keyframes preload-wave {
    0%, 100% { height: 10px; }
    50% { height: 40px; }
}
@keyframes preload-rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
@keyframes preload-particle {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.3; transform: scale(0.5); }
}
@keyframes preload-progress {
    0% { width: 0%; }
    100% { width: 100%; }
}
{{ $settings->custom_css }}
</style>

@if($settings->custom_js)
<script>
{!! $settings->custom_js !!}
</script>
@endif

<script>
// Remover preload após carregamento
window.addEventListener('load', () => {
    setTimeout(() => {
        const p = document.getElementById('preload');
        if (p) { 
            p.style.opacity = '0'; 
            setTimeout(() => p.remove(), 400); 
        }
    }, 300);
});
</script>
