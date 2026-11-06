{{-- Textarea yang memanjang otomatis mengikuti isi (tanpa scrollbar manual). --}}
@once
<script>
    (() => {
        const grow = (el) => {
            el.style.height = 'auto';
            el.style.height = el.scrollHeight + 'px';
        };

        document.querySelectorAll('textarea[data-auto-grow]').forEach((el) => {
            grow(el);
            el.addEventListener('input', () => grow(el));
            window.addEventListener('resize', () => grow(el));
        });
    })();
</script>
@endonce
