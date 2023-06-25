@props([
    'href' => '#',
    'iconClasses' => '',
    'text' => '',
])

<li class="nav-item">
    <a class="nav-link" href="{{ $href }}">
        <i class="nav-icon {{ $iconClasses }}"></i>
        {{ $text }}
    </a>
</li>
