<!-- resources/views/components/nav-link.blade.php -->

@props(['href' => '#', 'iconClasses' => 'fa-solid fa-money-bill', 'text' => ''])

<li class="nav-item">
    <a class="nav-link" href="{{ $href }}">
        <i class="nav-icon {{ $iconClasses }}"></i>
        {{ $text }}
    </a>
</li>
