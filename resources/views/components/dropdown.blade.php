@props(['align' => 'right', 'width' => '48'])

@php
$alignmentClasses = match ($align) {
    'left' => 'dropdown-menu-start',
    default => 'dropdown-menu-end',
};
@endphp

<div class="dropdown">
    <div data-bs-toggle="dropdown" aria-expanded="false">
        {{ $trigger }}
    </div>

    <ul class="dropdown-menu {{ $alignmentClasses }}">
        {{ $content }}
    </ul>
</div>
