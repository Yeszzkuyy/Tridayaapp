@once
<style>
    @keyframes lynk-fade-up {
        from {
            opacity: 0;
            transform: translateY(16px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes lynk-spark-draw {
        from {
            stroke-dashoffset: 100;
        }
        to {
            stroke-dashoffset: 0;
        }
    }

    @keyframes lynk-spark-fill {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @keyframes lynk-progress-grow {
        from {
            width: 0%;
        }
        to {
            width: var(--target-w, 0%);
        }
    }

    .lynk-enter {
        animation: lynk-fade-up 650ms cubic-bezier(0.22, 1, 0.36, 1) both;
        animation-delay: var(--d, 0ms);
    }

    .lynk-spark-line {
        stroke-dasharray: 100;
        stroke-dashoffset: 100;
        animation: lynk-spark-draw 1400ms cubic-bezier(0.22, 1, 0.36, 1) forwards;
        animation-delay: calc(var(--d, 0ms) + 120ms);
        transition: stroke-width 0.3s ease, filter 0.3s ease;
    }

    .lynk-spark-area {
        opacity: 0;
        animation: lynk-spark-fill 1000ms cubic-bezier(0.22, 1, 0.36, 1) forwards;
        animation-delay: calc(var(--d, 0ms) + 300ms);
        transition: opacity 0.3s ease;
    }

    .lynk-kpi-card:hover .lynk-spark-line {
        stroke-width: 2.5;
        filter: brightness(1.15) drop-shadow(0 2px 6px rgba(99, 102, 241, 0.25));
    }

    .lynk-progress-bar {
        width: var(--target-w, 0%);
        animation: lynk-progress-grow 1000ms cubic-bezier(0.22, 1, 0.36, 1) both;
        animation-delay: calc(var(--d, 0ms) + 250ms);
    }

    @media (prefers-reduced-motion: reduce) {
        .lynk-enter,
        .lynk-spark-line,
        .lynk-spark-area,
        .lynk-progress-bar {
            animation: none !important;
            opacity: 1 !important;
            transform: none !important;
            stroke-dashoffset: 0 !important;
        }
    }
</style>

<script>
    (function () {
        function initLynkCountUp() {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            var els = document.querySelectorAll('[data-countup]');
            if (!els.length) return;

            els.forEach(function (el) {
                if (el.dataset.counted === '1') return;
                el.dataset.counted = '1';

                var rawTarget = parseFloat(el.getAttribute('data-countup') || '0');
                if (isNaN(rawTarget) || rawTarget <= 0) return;

                var prefix = el.getAttribute('data-countup-prefix') || '';
                var suffix = el.getAttribute('data-countup-suffix') || '';
                var decimals = parseInt(el.getAttribute('data-countup-decimals') || '0', 10);
                var delay = parseInt(el.getAttribute('data-countup-delay') || '120', 10);
                var duration = 1380;

                function formatNum(val) {
                    if (decimals > 0) {
                        return val.toFixed(decimals);
                    }
                    return Math.round(val).toLocaleString();
                }

                el.textContent = prefix + formatNum(0) + suffix;

                setTimeout(function () {
                    var start = null;
                    function step(ts) {
                        if (!start) start = ts;
                        var p = Math.min((ts - start) / duration, 1);
                        // easeOutExpo / easeOutQuart blend for visible smooth deceleration
                        var ease = 1 - Math.pow(1 - p, 4);
                        var curr = ease * rawTarget;
                        el.textContent = prefix + formatNum(curr) + suffix;
                        if (p < 1) {
                            requestAnimationFrame(step);
                        } else {
                            el.textContent = prefix + formatNum(rawTarget) + suffix;
                        }
                    }
                    requestAnimationFrame(step);
                }, delay);
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initLynkCountUp);
        } else {
            initLynkCountUp();
        }
        document.addEventListener('livewire:navigated', initLynkCountUp);
    })();
</script>
@endonce
