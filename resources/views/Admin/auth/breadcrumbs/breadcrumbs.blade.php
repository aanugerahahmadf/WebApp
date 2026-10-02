@php
    $authCrumbs = method_exists($this, 'getBreadcrumbs') ? $this->getBreadcrumbs() : [];
@endphp
@if (! empty($authCrumbs))
    <nav aria-label="breadcrumb" class="mb-4 flex items-center justify-center gap-1.5 text-sm">
        @foreach ($authCrumbs as $crumbUrl => $crumbLabel)
            @if (! $loop->first)
                <span class="text-gray-400" aria-hidden="true">/</span>
            @endif
            @if (is_string($crumbUrl))
                <a href="{{ $crumbUrl }}" class="text-gray-500 hover:text-gray-800 hover:underline dark:text-gray-400 dark:hover:text-gray-100">
                    {{ $crumbLabel }}
                </a>
            @else
                <span class="font-medium text-gray-800 dark:text-gray-100" aria-current="page">
                    {{ $crumbLabel }}
                </span>
            @endif
        @endforeach
    </nav>
@endif
