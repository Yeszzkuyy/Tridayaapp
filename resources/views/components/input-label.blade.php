@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-content-secondary']) }}>
    {{ $value ?? $slot }}
</label>
