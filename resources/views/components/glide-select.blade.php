@props([
    'name',
    'options' => [],
    'value' => '',
    'placeholder' => null,
    'tags' => false,
    'autosubmit' => false,
    'label' => null,
    'full' => true,
    'emptyLabel' => null,
    'required' => false,
    'error' => null,
    'id' => null,
    'size' => 'md',
])

@php
    $placeholder ??= __('Select…');
    $label ??= $placeholder;
    // Baris kosong di menu agar pilihan bisa dikosongkan lagi (seperti <option value="">).
    $items = array_merge(
        [['value' => '', 'label' => $emptyLabel ?? $placeholder]],
        collect($options)->map(fn ($o) => is_array($o)
            ? ['value' => (string) ($o['value'] ?? ''), 'label' => $o['label'] ?? '', 'tag' => $o['tag'] ?? null]
            : ['value' => (string) $o, 'label' => (string) $o])->all(),
    );
    $value = (string) $value;
@endphp

<div {{ $attributes->merge(['class' => 'glide-select-root']) }}>
    <input type="hidden" name="{{ $name }}" @if($id) id="{{ $id }}" @endif value="{{ $value }}">
    <div data-glide-mount
         data-options='@json($items)'
         data-value="{{ $value }}"
         data-placeholder="{{ $placeholder }}"
         data-label="{{ $label }}"
         data-size="{{ $size }}"
         @if($tags) data-tags="1" @endif
         @if($autosubmit) data-autosubmit="1" @endif
         @if($full) data-full="1" @endif
         @if($required) data-required="1" data-required-message="{{ __('This field is required.') }}" @endif></div>
    @if($required || $error)
        <p class="glide-select-error mt-1 text-sm text-red-600" @if(!$error) hidden @endif>{{ $error ?? __('This field is required.') }}</p>
    @endif
    @vite('resources/js/glide-select.jsx')
    <noscript>
        <select name="{{ $name }}" @if($required) required @endif class="w-full rounded-xl border-slate-300 focus:border-accent-500 focus:ring-accent-500">
            @foreach($items as $item)
                <option value="{{ $item['value'] }}" {{ (string) $value === (string) $item['value'] ? 'selected' : '' }}>{{ $item['label'] }}</option>
            @endforeach
        </select>
    </noscript>
</div>
