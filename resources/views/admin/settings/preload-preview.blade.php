<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview - Tela de Pré-carregamento</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { overflow: hidden; }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.7; }
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        @keyframes wave {
            0%, 100% { height: 10px; }
            50% { height: 40px; }
        }
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes particle {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.5); }
        }
        @keyframes progress {
            0% { width: 0%; }
            100% { width: 100%; }
        }
        @keyframes spinner {
            to { transform: rotate(360deg); }
        }
        
        .spinner-border {
            display: inline-block;
            border: 0.25em solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spinner 0.75s linear infinite;
        }
        
        {{ $settings->custom_css }}
    </style>
</head>
<body>
    @php
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

    <div style="{{ $bgStyle }} width: 100vw; height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; font-family: {{ $settings->font_family }}; position: relative;">

        @if($settings->background_type === 'video' && $settings->background_video)
        <video autoplay muted loop style="position: absolute; width: 100%; height: 100%; object-fit: cover; opacity: 0.5; z-index: 0;">
            <source src="{{ $settings->background_video }}" type="video/mp4">
        </video>
        @endif

        <div style="position: relative; z-index: 1; text-align: center;">
            @if($settings->show_logo && $settings->logo && $settings->style !== 'logo')
            <img src="{{ $settings->logo }}" alt="Logo" style="max-width: 150px; max-height: 150px; margin-bottom: 30px;">
            @endif

            @if($settings->style === 'spinner')
            <div class="spinner-border" style="color: {{ $settings->spinner_color }}; width: 4rem; height: 4rem; animation-duration: {{ $animDuration }};" role="status"></div>
            
            @elseif($settings->style === 'pulse')
            <div style="width: 80px; height: 80px; background: {{ $settings->spinner_color }}; border-radius: 50%; animation: pulse {{ $animDuration }} ease-in-out infinite;"></div>
            
            @elseif($settings->style === 'dots')
            <div style="display: flex; gap: 15px; justify-content: center;">
                <div style="width: 20px; height: 20px; background: {{ $settings->spinner_color }}; border-radius: 50%; animation: bounce {{ $animDuration }} ease-in-out infinite;"></div>
                <div style="width: 20px; height: 20px; background: {{ $settings->spinner_color }}; border-radius: 50%; animation: bounce {{ $animDuration }} ease-in-out 0.2s infinite;"></div>
                <div style="width: 20px; height: 20px; background: {{ $settings->spinner_color }}; border-radius: 50%; animation: bounce {{ $animDuration }} ease-in-out 0.4s infinite;"></div>
            </div>
            
            @elseif($settings->style === 'wave')
            <div style="display: flex; gap: 8px; align-items: flex-end; height: 50px; justify-content: center;">
                @for($i = 0; $i < 5; $i++)
                <div style="width: 10px; background: {{ $settings->spinner_color }}; animation: wave {{ $animDuration }} ease-in-out {{ $i * 0.1 }}s infinite;"></div>
                @endfor
            </div>
            
            @elseif($settings->style === 'gradient')
            <div style="width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, {{ $settings->spinner_color }}, {{ $settings->accent_color }}); animation: rotate {{ $animDuration }} linear infinite;"></div>
            
            @elseif($settings->style === 'particles')
            <div style="position: relative; width: 120px; height: 120px;">
                @foreach([0, 45, 90, 135, 180, 225, 270, 315] as $i => $angle)
                <div style="position: absolute; width: 12px; height: 12px; background: {{ $settings->spinner_color }}; border-radius: 50%; 
                    top: 50%; left: 50%; transform: rotate({{ $angle }}deg) translateX(40px); 
                    animation: particle {{ $animDuration }} ease-in-out {{ $i * 0.1 }}s infinite;"></div>
                @endforeach
            </div>
            
            @elseif($settings->style === 'logo' && $settings->logo)
            <img src="{{ $settings->logo }}" alt="Logo" style="max-width: 150px; max-height: 150px; animation: pulse {{ $animDuration }} ease-in-out infinite;">
            @endif

            @if($settings->show_text && $settings->text)
            <p style="color: {{ $settings->text_color }}; font-size: {{ $settings->font_size }}px; margin-top: 30px; margin-bottom: 0;">
                {{ $settings->text }}
            </p>
            @endif
        </div>

        @if($settings->show_progress_bar)
        <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 5px; background: rgba(255,255,255,0.2); z-index: 2;">
            <div style="height: 100%; background: {{ $settings->progress_bar_color }}; animation: progress 3s ease-in-out infinite;"></div>
        </div>
        @endif
    </div>

    @if($settings->custom_js)
    <script>
        {!! $settings->custom_js !!}
    </script>
    @endif
</body>
</html>
