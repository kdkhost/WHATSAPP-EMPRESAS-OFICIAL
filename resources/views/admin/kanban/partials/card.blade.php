@php
    $rawContent = $card->content ?? '';
    $plainContent = trim(strip_tags($rawContent));
    $excerpt = \Illuminate\Support\Str::limit($plainContent, 100);
@endphp
<div class="kanban-card-premium" draggable="true" data-card-id="{{ $card->id }}"
     data-content="{{ e($plainContent) }}">
    @if($card->image)
        <img src="{{ $card->image }}" alt="{{ $card->title }}">
    @endif
    <strong class="kanban-card-title">{{ $card->title }}</strong>
    @if($excerpt)
        <div class="kanban-card-content">{{ $excerpt }}</div>
    @endif
</div>
