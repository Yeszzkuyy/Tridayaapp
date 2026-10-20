const SUBMIT_BUTTON_SELECTOR = 'button[type="submit"], input[type="submit"]';
const OVERLAY_ID = 'gs-submit-overlay';
const boundDocuments = new WeakSet();

const LOADER_HTML = `<div class="text-center" role="status">
  <svg class="mx-auto animate-spin text-indigo-600" width="32" height="32" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
  </svg>
  <p class="mt-4 font-medium text-gray-700" data-text>Loading...</p>
</div>`;

function getOverlay(document) {
    let overlay = document.getElementById(OVERLAY_ID);
    if (overlay) return overlay;

    overlay = document.createElement('div');
    overlay.id = OVERLAY_ID;
    overlay.className = 'status-card-backdrop';
    overlay.style.display = 'none';
    overlay.innerHTML = `<div data-card>${LOADER_HTML}</div>`;
    document.body.appendChild(overlay);

    return overlay;
}

function showOverlay(document, form) {
    const overlay = getOverlay(document);
    const dark = document.documentElement.classList.contains('dark');

    // Let the shared backdrop CSS control its navbar offset and viewport position.
    Object.assign(overlay.style, {
        position: 'fixed',
        right: '0',
        bottom: '0',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '16px',
        background: dark ? 'rgba(2, 6, 23, .55)' : 'rgba(15, 23, 42, .4)',
        zIndex: '60',
    });

    const card = overlay.querySelector('[data-card]');
    Object.assign(card.style, {
        width: '100%',
        maxWidth: '384px',
        borderRadius: '16px',
        padding: '32px 40px',
        background: dark ? '#1e293b' : '#ffffff',
        boxShadow: '0 10px 30px rgba(0, 0, 0, .35)',
    });

    const text = overlay.querySelector('[data-text]');
    text.textContent = form.dataset.loadingText || 'Loading...';
    text.style.color = dark ? '#f1f5f9' : '';
}

export function installSubmitLoader(document = globalThis.document, window = globalThis.window) {
    if (!document?.addEventListener || !window?.addEventListener || boundDocuments.has(document)) return;
    boundDocuments.add(document);

    const activeForms = new Set();
    const buttonStates = new WeakMap();

    function unlock(form) {
        for (const { button, disabled, opacity } of buttonStates.get(form) ?? []) {
            button.disabled = disabled;
            button.style.opacity = opacity;
        }
        buttonStates.delete(form);
        delete form.dataset.submitLoaderActive;
        form.removeAttribute('aria-busy');
        activeForms.delete(form);
    }

    document.addEventListener('submit', (event) => {
        const form = event.target?.closest?.('form');
        if (!form || event.defaultPrevented || form.hasAttribute('data-ajax')) return;
        if (form.dataset.submitLoaderActive === 'true') return;

        const hasLoadingText = form.hasAttribute('data-loading-text');
        const hasGlideSelect = Boolean(form.querySelector('[data-glide-mount]'));
        if (!hasLoadingText && !hasGlideSelect) return;

        const states = [...form.querySelectorAll(SUBMIT_BUTTON_SELECTOR)].map((button) => ({
            button,
            disabled: button.disabled,
            opacity: button.style.opacity,
        }));
        buttonStates.set(form, states);
        form.dataset.submitLoaderActive = 'true';
        form.setAttribute('aria-busy', 'true');
        activeForms.add(form);

        for (const { button } of states) {
            button.disabled = true;
            button.style.opacity = '.65';
        }

        showOverlay(document, form);
    });

    // A page restored from the browser's back-forward cache must not retain a stale overlay.
    window.addEventListener('pageshow', () => {
        for (const form of activeForms) unlock(form);
        const overlay = document.getElementById(OVERLAY_ID);
        if (overlay) overlay.style.display = 'none';
    });
}

installSubmitLoader();
