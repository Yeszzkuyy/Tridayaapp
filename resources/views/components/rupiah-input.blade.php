@props([
    'name',
    'value' => null,
    'id' => null,
    'required' => false,
    'placeholder' => '',
    'ariaLabel' => null,
    'inputClass' => '',
    'wrapperClass' => '',
])

@php
    if (is_numeric($value)) {
        $number = (int) round((float) $value);
    } else {
        $digits = preg_replace('/[^0-9]/', '', (string) ($value ?? ''));
        $number = $digits !== '' ? (int) $digits : null;
    }
    $formatted = $number !== null ? number_format($number, 0, ',', '.') : '';
    $ariaLabel ??= __('Amount');
@endphp

<div class="relative {{ $wrapperClass }}">
    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-500 dark:text-slate-300" aria-hidden="true">Rp</span>
    <input type="text" inputmode="numeric" name="{{ $name }}"
           @if($id) id="{{ $id }}" @endif
           value="{{ $formatted }}"
           @if($required) required @endif
           placeholder="{{ $placeholder }}"
           aria-label="{{ $ariaLabel }}"
           oninput="formatRupiahInput(this)"
           class="h-10 w-full rounded-xl border-slate-300 pl-9 pr-3 text-right text-sm font-semibold tabular-nums text-slate-900 placeholder:text-slate-400 focus:border-accent-500 focus:ring-accent-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 {{ $inputClass }}">
</div>

@once
    <script>
        if (!window.formatRupiahInput) {
            window.parseRupiah = function (value) {
                return Number(String(value || '').replace(/[^0-9]/g, '')) || 0;
            };

            window.formatRupiah = function (value) {
                const number = window.parseRupiah(value);
                return number ? number.toLocaleString('id-ID') : '';
            };

            window.formatRupiahInput = function (input) {
                const caretFromEnd = input.value.length - (input.selectionStart ?? input.value.length);
                const digits = input.value.replace(/[^0-9]/g, '');
                input.value = digits ? Number(digits).toLocaleString('id-ID') : '';
                const caret = Math.max(0, input.value.length - caretFromEnd);
                input.setSelectionRange(caret, caret);
                window.recalcEstimatorTotal?.();
            };

            window.setRupiahValue = function (input, value) {
                if (!input) return;
                input.value = window.formatRupiah(value);
                window.recalcEstimatorTotal?.();
            };
        }
    </script>
@endonce
