/**
 * Validasi wajib-isi untuk seluruh form aplikasi.
 *
 * Tujuan:
 *   - Menolak submit jika isian WAJIB masih kosong (isian opsional tidak
 *     pernah dipaksa).
 *   - Menjelaskan lokasi isian yang kurang: teks merah di bawah isian,
 *     toast ringkasan, dan fokus otomatis ke isian pertama.
 *   - Memetakan error validasi server (422 / redirect + $errors) ke isian
 *     yang tepat, supaya pesan tidak hanya menumpuk di kotak atas halaman.
 *
 * Catatan: validasi format (email, angka, file) tetap milik server. Modul ini
 * hanya memeriksa "wajib diisi" di sisi klien.
 */

const REQUIRED_SELECTOR = 'input[required], select[required], textarea[required]';
const GLIDE_SELECTOR = '[data-glide-mount][data-required="1"]';
const SERVER_MESSAGE_KEY = 'validationServerMessage';

let messageSequence = 0;
let observer = null;
let observerFrame = null;
let initialSummaryShown = false;

function meta(name, fallback = '') {
    return document.querySelector(`meta[name="${name}"]`)?.content || fallback;
}

function templates() {
    return {
        required: meta('validation-required', ':label is required.'),
        summary: meta('validation-summary', 'Please complete the required fields: :fields'),
        fix: meta('validation-fix', 'Please fix the highlighted fields: :fields'),
        more: meta('validation-more', 'and :count more'),
    };
}

function cleanLabel(value) {
    return String(value ?? '')
        .replace(/\s+/g, ' ')
        .replace(/\s*\*\s*/g, ' ')
        .replace(/\s*\((optional|opsional)\)\s*/gi, ' ')
        .replace(/^\s*[:\-]+\s*|\s*[:\-]+\s*$/g, '')
        .trim();
}

function truncate(value, max = 60) {
    const text = String(value ?? '').trim();
    return text.length > max ? `${text.slice(0, max - 1)}…` : text;
}

function isVisible(element) {
    if (!element || !element.getClientRects().length) return false;
    const style = window.getComputedStyle(element);
    return style.visibility !== 'hidden' && style.display !== 'none';
}

function looksLikeFieldLabel(label) {
    const text = cleanLabel(label?.textContent);
    return text !== '' && text.length <= 80;
}

/** Cari label yang benar-benar milik isian ini. */
function labelFor(field) {
    if (!field) return '';

    const explicit = cleanLabel(field.dataset.fieldLabel);
    if (explicit) return explicit;

    // Tabel: gabungkan nama baris (sel pertama) + judul kolom. Ditaruh
    // sebelum placeholder karena placeholder tabel biasanya contoh
    // ("e.g. 1 year distributor"), bukan nama isian.
    const cell = field.closest('td');
    if (cell) {
        const row = cell.parentElement;
        const index = row ? [...row.children].indexOf(cell) : -1;
        const header = index >= 0
            ? cleanLabel(cell.closest('table')?.querySelectorAll('thead th')[index]?.textContent)
            : '';
        const firstCell = row?.querySelector('td');
        const rowName = cleanLabel(
            firstCell?.querySelector('.font-semibold, p, a, span')?.textContent ||
            firstCell?.textContent,
        );
        const shortRow = truncate(rowName.split(/Last price|Harga terakhir/i)[0], 48);

        if (shortRow && header) return `${shortRow} - ${header}`;
        if (header) return header;
        if (shortRow) return shortRow;
    }

    const ariaLabel = cleanLabel(field.getAttribute('aria-label'));
    if (ariaLabel) return ariaLabel;

    const labelledBy = field.getAttribute('aria-labelledby');
    if (labelledBy) {
        const text = cleanLabel(document.getElementById(labelledBy)?.textContent);
        if (text) return text;
    }

    if (field.id) {
        const byFor = [...document.querySelectorAll('label[for]')].find(
            (label) => label.htmlFor === field.id,
        );
        if (byFor && looksLikeFieldLabel(byFor)) return cleanLabel(byFor.textContent);
    }

    const wrapping = field.closest('label');
    if (wrapping && looksLikeFieldLabel(wrapping)) return cleanLabel(wrapping.textContent);

    // Label di wrapper yang sama (pola Blade: <div><label>..</label><input></div>).
    let scope = field.parentElement;
    for (let depth = 0; depth < 3 && scope; depth += 1, scope = scope.parentElement) {
        const labels = [...scope.querySelectorAll('label')].filter(
            (label) => !label.htmlFor || label.htmlFor === field.id,
        );
        if (labels.length === 1 && looksLikeFieldLabel(labels[0])) {
            return cleanLabel(labels[0].textContent);
        }
    }

    const placeholder = cleanLabel(field.getAttribute('placeholder'));
    if (placeholder && !/^e\.g\./i.test(placeholder)) return placeholder;

    return '';
}

function fieldHost(field) {
    const glideRoot = field.closest('.glide-select-root');
    if (glideRoot) return glideRoot;

    const cell = field.closest('td');
    if (cell) return cell;

    let host = field.parentElement;
    // Naik dari wrapper dekorasi (mis. .relative untuk prefix Rp) supaya
    // pesan tidak menimpa input.
    let guard = 0;
    while (
        host &&
        host !== field.form &&
        guard < 3 &&
        host.children.length <= 2 &&
        /(^|\s)relative(\s|$)/.test(String(host.className || ''))
    ) {
        host = host.parentElement;
        guard += 1;
    }

    return host || field.parentElement || field;
}

function fieldEntry(field) {
    if (!field) return null;

    const glideMount = field.closest('.glide-select-root')?.querySelector('[data-glide-mount]');
    if (glideMount) {
        return {
            field,
            kind: 'glide',
            label: labelFor(field) || cleanLabel(glideMount.dataset.label || field.getAttribute('aria-label') || ''),
            host: glideMount.closest('.glide-select-root'),
            focus: glideMount.querySelector('.glide-select__trigger') || glideMount,
            error: (() => {
                const error = glideMount.closest('.glide-select-root')?.querySelector('.glide-select-error');
                if (error && !error.dataset.defaultMessage) {
                    error.dataset.defaultMessage = error.textContent.trim();
                }
                return error;
            })(),
            glideMount,
        };
    }

    return {
        field,
        kind: 'field',
        label: labelFor(field),
        host: fieldHost(field),
        focus: field,
        error: null,
        glideMount: null,
    };
}

function isEmpty(field) {
    if (!field || field.disabled || field.readOnly) return false;
    if (field.tagName === 'TEMPLATE') return false;
    if (field.type === 'hidden' || field.type === 'button' || field.type === 'reset') return false;

    if (field.type === 'file') return !field.files || field.files.length === 0;
    if (field.type === 'checkbox') return !field.checked;
    if (field.type === 'radio') return !field.checked;

    if (field.tagName === 'SELECT') {
        if (field.multiple) return !field.selectedOptions || field.selectedOptions.length === 0;
        return String(field.value ?? '').trim() === '';
    }

    return String(field.value ?? '').trim() === '';
}

function isRequired(field) {
    return field.hasAttribute('required') && !field.disabled && !field.readOnly;
}

function describeMissing(field) {
    const entry = fieldEntry(field);
    if (entry && !entry.label) entry.label = cleanLabel(field.name).replace(/[\[\]_]+/g, ' ').trim();
    return entry;
}

function radioGroupField(field) {
    if (field.type !== 'radio' || !field.name) return null;
    const group = [...(field.form?.querySelectorAll('input[type="radio"]') || [])].filter(
        (input) => input.name === field.name,
    );
    if (!group.some((input) => input.checked)) {
        const wrapper = field.closest('fieldset') || field.closest('label')?.parentElement || field.parentElement;
        const entry = fieldEntry(field);
        const groupLabel = cleanLabel(wrapper?.querySelector('legend, label')?.textContent);
        entry.label = groupLabel || entry.label;
        entry.focus = group.find((input) => isVisible(input)) || field;
        entry.host = wrapper || entry.host;
        return entry;
    }
    return null;
}

function checkboxField(field) {
    if (field.type !== 'checkbox') return null;
    if (!field.required) return null;
    if (!field.checked) {
        const entry = fieldEntry(field);
        entry.host = field.closest('label')?.parentElement || field.parentElement;
        return entry;
    }
    return null;
}

/**
 * Kumpulkan semua isian wajib yang masih kosong di satu form.
 * Isian opsional tidak pernah dimasukkan.
 */
function collectMissing(form) {
    const missing = [];
    const seenRadio = new Set();
    const seenNames = new Set();

    form.querySelectorAll(REQUIRED_SELECTOR).forEach((field) => {
        if (!isRequired(field)) return;
        if (field.type === 'radio' && field.name) {
            if (seenRadio.has(field.name)) return;
            seenRadio.add(field.name);
            const groupMissing = radioGroupField(field);
            if (groupMissing) missing.push(groupMissing);
            return;
        }

        if (field.type === 'checkbox') {
            const checkboxMissing = checkboxField(field);
            if (checkboxMissing) missing.push(checkboxMissing);
            return;
        }

        if (!isEmpty(field)) return;
        if (seenNames.has(field.name)) return;
        seenNames.add(field.name);
        const entry = describeMissing(field);
        if (entry) missing.push(entry);
    });

    form.querySelectorAll(GLIDE_SELECTOR).forEach((mount) => {
        const hidden = mount.closest('.glide-select-root')?.querySelector('input[type="hidden"]');
        if (!hidden || hidden.disabled || String(hidden.value ?? '').trim() !== '') return;
        const entry = fieldEntry(hidden);
        if (entry) missing.push(entry);
    });

    return missing;
}

function ensureMessageId(entry) {
    if (entry.field.dataset.validationErrorId) return entry.field.dataset.validationErrorId;

    const id = `validation-error-${++messageSequence}`;
    entry.field.dataset.validationErrorId = id;

    return id;
}

function existingMessage(host, message) {
    if (!host || !message) return null;

    let scope = host;
    for (let depth = 0; depth < 3 && scope; depth += 1, scope = scope.parentElement) {
        const found = [...scope.querySelectorAll('p, li, span, div')].find(
            (element) => element.children.length === 0 && element.textContent.trim() === message.trim(),
        );
        if (found) return found;
    }

    return null;
}

function showError(entry, message) {
    if (!entry || !message) return;

    const field = entry.field;
    const id = ensureMessageId(entry);

    field.classList.add('form-validation-invalid');
    field.setAttribute('aria-invalid', 'true');

    if (entry.focus && entry.focus !== field) {
        entry.focus.setAttribute('aria-invalid', 'true');
        entry.focus.classList.add('form-validation-invalid');
    }

    if (entry.kind === 'glide') {
        const trigger = entry.focus;
        if (trigger && !trigger.getAttribute('aria-describedby')) {
            trigger.setAttribute('aria-describedby', id);
        }

        if (entry.error) {
            entry.error.id = id;
            entry.error.textContent = message;
            entry.error.removeAttribute('hidden');
        }
        return;
    }

    const describedBy = String(field.getAttribute('aria-describedby') || '')
        .split(/\s+/)
        .filter(Boolean);
    if (!describedBy.includes(id)) {
        field.setAttribute('aria-describedby', [...describedBy, id].join(' '));
    }

    if (entry.field.dataset.validationErrorId) {
        const current = entry.host?.querySelector(`#${CSS.escape(id)}`);
        if (current) {
            current.textContent = message;
            return;
        }
    }

    // Form yang sudah punya @error dari Blade: jangan tampilkan pesan ganda.
    const rendered = existingMessage(entry.host, message);
    if (rendered) {
        entry.field.dataset[SERVER_MESSAGE_KEY] = message;
        rendered.removeAttribute('hidden');
        return;
    }

    const node = document.createElement('p');
    node.id = id;
    node.dataset.validationError = '1';
    node.className = 'form-validation-message';
    node.setAttribute('role', 'alert');
    node.textContent = message;

    (entry.host || field.parentElement || field).appendChild(node);
}

function clearError(entry) {
    if (!entry) return;

    const field = entry.field;
    const id = field.dataset.validationErrorId;

    field.classList.remove('form-validation-invalid');
    field.removeAttribute('aria-invalid');

    if (entry.focus && entry.focus !== field) {
        entry.focus.classList.remove('form-validation-invalid');
        entry.focus.removeAttribute('aria-invalid');
    }

    if (entry.kind === 'glide') {
        if (entry.error) {
            entry.error.setAttribute('hidden', '');
            entry.error.textContent = entry.error.dataset.defaultMessage || entry.error.textContent;
        }
        return;
    }

    if (id) {
        entry.host?.querySelector(`#${CSS.escape(id)}`)?.remove();
        delete field.dataset.validationErrorId;
    }

    const serverMessage = field.dataset[SERVER_MESSAGE_KEY];
    if (serverMessage) {
        const rendered = existingMessage(entry.host, serverMessage);
        if (rendered) rendered.setAttribute('hidden', '');
        delete field.dataset[SERVER_MESSAGE_KEY];
    }

    if (id) {
        const remaining = String(field.getAttribute('aria-describedby') || '')
            .split(/\s+/)
            .filter((value) => value && value !== id);
        if (remaining.length) {
            field.setAttribute('aria-describedby', remaining.join(' '));
        } else {
            field.removeAttribute('aria-describedby');
        }
    }
}

function summaryMessage(template, labels) {
    if (!labels.length) return '';

    const unique = [...new Set(labels.filter(Boolean))];
    const shown = unique.slice(0, 4);
    let fields = shown.join(', ');

    if (unique.length > shown.length) {
        const more = templates().more.replace(':count', String(unique.length - shown.length));
        fields += `, ${more}`;
    }

    return template.replace(':fields', fields);
}

function showSummary(labels, template, variant = 'error') {
    const message = summaryMessage(template || templates().summary, labels);
    if (!message) return;

    if (typeof window.toastVariant === 'function') {
        window.toastVariant(message, variant, 8000);
        return;
    }

    if (typeof window.toast === 'function') {
        window.toast(message, false);
    }
}

function focusEntry(entry) {
    if (!entry) return;

    const target = entry.focus || entry.field;
    if (!target || !isVisible(target)) return;

    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    try {
        target.focus({ preventScroll: true });
    } catch (error) {
        /* elemen tidak bisa difokuskan — tetap gulir */
    }

    target.scrollIntoView({ block: 'center', behavior: reduce ? 'auto' : 'smooth' });
}

export function validateForm(form) {
    if (!form) return true;

    const missing = collectMissing(form);
    if (!missing.length) return true;

    missing.forEach((entry) => {
        const label = entry.label || 'This field';
        showError(entry, templates().required.replace(':label', label));
    });

    showSummary(missing.map((entry) => entry.label || 'This field'), templates().summary, 'warning');
    focusEntry(missing.find((entry) => isVisible(entry.focus || entry.field)));

    return false;
}

function fieldFromName(form, name) {
    return [...form.querySelectorAll('[name]')].find((field) => field.name === name) || null;
}

function nameFromErrorKey(key) {
    const parts = String(key).split('.');
    if (parts.length === 1) return parts[0];

    const base = parts.shift();
    return `${base}${parts.map((part) => `[${part}]`).join('')}`;
}

/**
 * Tampilkan error validasi server pada isian yang tepat.
 * Dipakai saat halaman dimuat (redirect + $errors) dan saat simpan cepat
 * menerima 422 tanpa reload.
 *
 * @returns {{mapped: number, unmapped: string[]}}
 */
export function applyServerErrors(form, errors) {
    const mapped = [];
    const unmapped = [];
    const messages = errors && typeof errors === 'object' ? errors : {};

    // Bersihkan pesan dari percobaan sebelumnya supaya tidak ada pesan basi.
    if (form) {
        form.querySelectorAll('[data-validation-error]').forEach((element) => element.remove());
        form.querySelectorAll('.form-validation-invalid').forEach((element) => {
            element.classList.remove('form-validation-invalid');
            element.removeAttribute('aria-invalid');
        });
    }

    Object.entries(messages).forEach(([key, value]) => {
        const message = Array.isArray(value) ? value[0] : value;
        if (!message) return;

        const name = nameFromErrorKey(key);
        const field = form ? fieldFromName(form, name) : null;

        if (!field) {
            unmapped.push(String(message));
            return;
        }

        const entry = fieldEntry(field);
        if (!entry) {
            unmapped.push(String(message));
            return;
        }

        entry.label = entry.label || cleanLabel(name).replace(/[\[\]_]+/g, ' ').trim();
        showError(entry, String(message));
        mapped.push(entry);
    });

    if (mapped.length) {
        showSummary(mapped.map((entry) => entry.label || 'This field'), templates().fix);
        focusEntry(mapped.find((entry) => isVisible(entry.focus || entry.field)));
    }

    return { mapped: mapped.length, unmapped };
}

function initialServerErrors() {
    const errors = window.__formErrors;
    if (!errors || typeof errors !== 'object' || !Object.keys(errors).length) return;

    const forms = [...document.querySelectorAll('form')];
    const mapped = [];
    const unmapped = [];

    Object.entries(errors).forEach(([key, value]) => {
        const message = Array.isArray(value) ? value[0] : value;
        if (!message) return;

        const name = nameFromErrorKey(key);
        const form = forms.find((candidate) => fieldFromName(candidate, name));
        const field = form ? fieldFromName(form, name) : null;

        if (!field) {
            unmapped.push(String(message));
            return;
        }

        const entry = fieldEntry(field);
        if (!entry) {
            unmapped.push(String(message));
            return;
        }

        entry.label = entry.label || cleanLabel(name).replace(/[\[\]_]+/g, ' ').trim();
        showError(entry, String(message));
        mapped.push(entry);
    });

    if (mapped.length && !initialSummaryShown) {
        initialSummaryShown = true;
        showSummary(mapped.map((entry) => entry.label || 'This field'), templates().fix);
        focusEntry(mapped.find((entry) => isVisible(entry.focus || entry.field)));
    }

    // Kotak error umum di atas halaman hanya disisakan bila ada pesan yang
    // tidak bisa ditempelkan ke isian tertentu.
    if (!unmapped.length) {
        document.querySelector('[data-server-error-summary]')?.remove();
    }
}

function prepareForm(form) {
    if (!form || form.dataset.validationReady === '1') return;
    if ((form.getAttribute('method') || 'get').toLowerCase() === 'get') return;
    if (form.hasAttribute('data-native-validation')) return;
    if (form.hasAttribute('data-validation-off')) return;

    form.dataset.validationReady = '1';
    // Ganti tooltip bawaan browser dengan pesan aplikasi (label + lokasi).
    form.noValidate = true;
}

function prepareForms() {
    document.querySelectorAll('form').forEach(prepareForm);
}

function entryForField(field) {
    if (!field) return null;

    const form = field.form;
    if (!form) return null;

    if (field.type === 'radio' && field.name) {
        return collectMissing(form).find(
            (entry) => entry.field.type === 'radio' && entry.field.name === field.name,
        );
    }

    return fieldEntry(field);
}

function handleFieldEdited(event) {
    const field = event.target;
    if (!field || !field.form) return;
    if (!field.form.matches('form')) return;

    prepareForm(field.form);

    if (field.type === 'radio' && field.checked) {
        const wrapper = field.closest('fieldset') || field.closest('label')?.parentElement || field.parentElement;
        wrapper?.querySelectorAll('.form-validation-invalid').forEach((element) => {
            element.classList.remove('form-validation-invalid');
            element.removeAttribute('aria-invalid');
        });
        wrapper?.querySelectorAll('[data-validation-error]').forEach((element) => element.remove());
        return;
    }

    if (field.type === 'checkbox' && field.checked) {
        const entry = fieldEntry(field);
        clearError(entry);
        return;
    }

    const entry = entryForField(field);
    if (!entry) return;

    if (!isEmpty(field)) {
        clearError(entry);
    }
}

function handleGlideClick(event) {
    const form = event.target?.closest?.('form');
    if (form) prepareForm(form);

    const root = event.target?.closest?.('.glide-select-root');
    if (!root) return;

    window.setTimeout(() => {
        const mount = root.querySelector('[data-glide-mount]');
        const hidden = root.querySelector('input[type="hidden"]');
        if (!mount || !hidden) return;

        const entry = fieldEntry(hidden);
        if (entry && String(hidden.value ?? '').trim() !== '') clearError(entry);
    }, 0);
}

function schedulePrepare() {
    if (observerFrame) return;
    observerFrame = window.requestAnimationFrame(() => {
        observerFrame = null;
        prepareForms();
    });
}

function observeForms() {
    if (observer || !document.body) return;

    observer = new MutationObserver(schedulePrepare);
    observer.observe(document.body, { childList: true, subtree: true });
}

export function installFormValidation(documentRef = globalThis.document, windowRef = globalThis.window) {
    if (!documentRef?.addEventListener) return;

    // Capture: jalankan sebelum handler form/konfirmasi/loader lain.
    documentRef.addEventListener(
        'submit',
        (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (form.hasAttribute('wire:submit') || form.hasAttribute('data-native-validation')) return;
            if (form.hasAttribute('data-validation-off')) return;
            if ((form.getAttribute('method') || 'get').toLowerCase() === 'get') return;

            prepareForm(form);

            if (!validateForm(form)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        },
        true,
    );

    documentRef.addEventListener('input', handleFieldEdited, true);
    documentRef.addEventListener('change', handleFieldEdited, true);
    documentRef.addEventListener('click', handleGlideClick, true);

    documentRef.addEventListener('livewire:navigated', () => {
        schedulePrepare();
        window.setTimeout(initialServerErrors, 80);
    });

    const boot = () => {
        prepareForms();
        observeForms();
        initialServerErrors();

        // Baris dinamis (Alpine x-for) baru ada setelah Alpine start.
        window.setTimeout(initialServerErrors, 120);
        window.setTimeout(initialServerErrors, 450);
    };

    if (documentRef.readyState === 'loading') {
        documentRef.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }

    documentRef.addEventListener('alpine:initialized', () => window.setTimeout(initialServerErrors, 80));
}
