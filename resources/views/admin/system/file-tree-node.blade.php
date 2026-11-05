@if($node['is_dir'])
    @php
        $targetId = 'folder-' . Str::slug($node['path']);
    @endphp
    <div class="mb-1">
        <div class="tree-folder d-flex align-items-center" data-target="{{ $targetId }}">
            <i class="bi bi-folder-fill text-warning folder-icon me-2"></i>
            <span class="small fw-medium">{{ $node['name'] }}</span>
        </div>
        <div id="{{ $targetId }}" class="folder-children d-none mt-1">
            @foreach($node['children'] as $child)
                @include('admin.system.file-tree-node', ['node' => $child])
            @endforeach
        </div>
    </div>
@else
    <a href="#" class="tree-file small mb-1 d-flex align-items-center" 
       data-path="{{ $node['path'] }}" 
       data-name="{{ $node['name'] }}">
        @php
            $ext = strtolower(pathinfo($node['name'], PATHINFO_EXTENSION));
            $icon = 'bi-file-earmark-code text-info';
            if ($ext === 'php') $icon = 'bi-filetype-php text-primary';
            else if ($ext === 'json') $icon = 'bi-filetype-json text-warning';
            else if ($ext === 'css') $icon = 'bi-filetype-css text-danger';
            else if ($ext === 'env') $icon = 'bi-key text-warning';
        @endphp
        <i class="bi {{ $icon }} me-2"></i>
        <span>{{ $node['name'] }}</span>
    </a>
@endif
