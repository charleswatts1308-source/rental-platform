@props([
    'name',
    'show' => false,
    'maxWidth' => 'md'
])

@php
$maxWidthClass = match ($maxWidth) {
    'sm' => 'modal-sm',
    'lg' => 'modal-lg',
    'xl' => 'modal-xl',
    default => '',
};
@endphp

<div class="modal fade" id="{{ $name }}" tabindex="-1" aria-labelledby="{{ $name }}Label" aria-hidden="true">
    <div class="modal-dialog {{ $maxWidthClass }}">
        <div class="modal-content">
            {{ $slot }}
        </div>
    </div>
</div>
