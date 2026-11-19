/**
 * Status "sedang menyimpan" untuk tombol submit.
 *
 * Dipakai dua jalur:
 *   - submit-loader.js untuk form biasa (POST + redirect)
 *   - instant-save.js untuk form simpan cepat (AJAX + toast langsung)
 *
 * Yang diubah hanya tampilan sementara: tombol dinonaktifkan, ikon diganti
 * spinner, dan label (bila ada `data-saving-label` / `data-loading-text`)
 * berubah jadi "Menyimpan...". Semua dipulihkan saat server menolak.
 */

const states = new WeakMap();

function makeSpinner() {
    const spinner = document.createElement('span');
    spinner.setAttribute('data-busy-spinner', '');
    spinner.setAttribute('aria-hidden', 'true');
    spinner.className =
        'inline-block h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-current border-t-transparent';

    return spinner;
}

/**
 * @param {HTMLFormElement} form
 * @param {HTMLElement|null} submitter tombol yang diklik (boleh null)
 * @param {string} label teks pengganti, mis. "Menyimpan..."
 */
export function markFormBusy(form, submitter = null, label = '') {
    if (!form || states.has(form)) return;

    const buttons = [...form.querySelectorAll('button[type="submit"]')];
    if (!buttons.length) return;

    const active = submitter && buttons.includes(submitter) ? submitter : buttons[0];

    const records = buttons.map((button) => {
        const record = {
            button,
            spinner: null,
            svg: null,
            svgDisplay: '',
            textEl: null,
            textNode: null,
            text: null,
        };

        if (button === active) {
            record.svg = button.querySelector(':scope > svg');
            if (record.svg) {
                record.svgDisplay = record.svg.style.display;
                record.svg.style.display = 'none';
            }

            record.textEl = button.querySelector('[data-button-label]');

            if (record.textEl) {
                if (label) {
                    record.text = record.textEl.textContent;
                    record.textEl.textContent = label;
                }
            } else if (label) {
                // Tombol tulis tangan (belum memakai komponen): ganti text node
                // terakhir yang terlihat, bukan seluruh innerHTML, supaya ikon
                // dan efek kilau tetap ada.
                const walker = document.createTreeWalker(button, NodeFilter.SHOW_TEXT);
                let node;

                while ((node = walker.nextNode())) {
                    if (!node.nodeValue.trim()) continue;
                    if (node.parentElement?.closest('[aria-hidden="true"]')) continue;
                    record.textNode = node;
                }

                if (record.textNode) {
                    record.text = record.textNode.nodeValue;
                    record.textNode.nodeValue = label;
                }
            }

            record.spinner = makeSpinner();
            if (record.textNode) record.spinner.classList.add('mr-2');
            if (record.textEl) {
                record.textEl.before(record.spinner);
            } else {
                button.prepend(record.spinner);
            }
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');

        return record;
    });

    states.set(form, records);
}

export function releaseFormBusy(form) {
    const records = states.get(form);
    if (!records) return;

    records.forEach(({ button, spinner, svg, svgDisplay, textEl, textNode, text }) => {
        spinner?.remove();
        if (svg) svg.style.display = svgDisplay;
        if (textEl && text !== null) textEl.textContent = text;
        if (textNode && text !== null) textNode.nodeValue = text;

        button.disabled = false;
        button.removeAttribute('aria-busy');
    });

    states.delete(form);

    // View lama masih memasang penjaga sendiri (@submit="submitting = true").
    // Saat server menolak (422/403), penjaga itu harus dilepas lagi supaya
    // tombol tidak terkunci permanen tanpa reload.
    try {
        const scope = window.Alpine?.$data?.(form);
        if (scope && typeof scope === 'object') {
            if ('submitting' in scope) scope.submitting = false;
            if ('sending' in scope) scope.sending = false;
        }
    } catch (error) {
        /* Alpine belum siap — abaikan */
    }
}
