import assert from 'node:assert/strict';
import test from 'node:test';
import { installSubmitLoader } from '../../resources/js/submit-loader.js';

class FakeElement {
    constructor() {
        this.style = {};
        this.dataset = {};
        this.textContent = '';
        this.disabled = false;
    }

    set innerHTML(markup) {
        this.markup = markup;
        this.text = new FakeElement();
        this.card = new FakeElement();
        this.card.text = this.text;
        this.card.querySelector = (selector) => selector === '[data-text]' ? this.text : null;
    }

    querySelector(selector) {
        if (selector === '[data-card]') return this.card;
        if (selector === '[data-text]') return this.card?.text ?? null;
        return null;
    }
}

function createHarness({ dark = false } = {}) {
    const documentEvents = new Map();
    const windowEvents = new Map();
    const elements = new Map();

    const document = {
        documentElement: {
            dataset: {},
            classList: { contains: (name) => name === 'dark' && dark },
        },
        body: {
            appendChild(element) {
                elements.set(element.id, element);
            },
        },
        addEventListener(name, callback) {
            documentEvents.set(name, [...(documentEvents.get(name) ?? []), callback]);
        },
        createElement() {
            return new FakeElement();
        },
        getElementById(id) {
            return elements.get(id) ?? null;
        },
    };

    const window = {
        addEventListener(name, callback) {
            windowEvents.set(name, [...(windowEvents.get(name) ?? []), callback]);
        },
    };

    function createForm({ loadingText, ajax = false, glideSelect = false, buttons = [] } = {}) {
        const attributes = new Set();
        if (loadingText !== undefined) attributes.add('data-loading-text');
        if (ajax) attributes.add('data-ajax');

        return {
            dataset: loadingText === undefined ? {} : { loadingText },
            attributes,
            buttons,
            querySelector: (selector) => selector === '[data-glide-mount]' && glideSelect ? {} : null,
            querySelectorAll: () => buttons,
            hasAttribute(name) { return attributes.has(name); },
            setAttribute(name, value) { this.attributes.add(name); this[name] = value; },
            removeAttribute(name) { this.attributes.delete(name); delete this[name]; },
            closest(selector) {
                if (selector === 'form') return this;
                if (selector === 'form[data-loading-text]' && this.hasAttribute('data-loading-text')) return this;
                return null;
            },
        };
    }

    function submit(form, { defaultPrevented = false } = {}) {
        const event = { target: form, defaultPrevented };
        for (const listener of documentEvents.get('submit') ?? []) listener(event);
    }

    function pageshow() {
        for (const listener of windowEvents.get('pageshow') ?? []) listener();
    }

    return { document, window, elements, documentEvents, windowEvents, createForm, submit, pageshow };
}

test('shows a loader for a form with loading text even without a Glide Select', () => {
    const harness = createHarness();
    const button = new FakeElement();
    const form = harness.createForm({ loadingText: 'Saving request…', buttons: [button] });

    installSubmitLoader(harness.document, harness.window);
    harness.submit(form);

    const overlay = harness.elements.get('gs-submit-overlay');
    assert.ok(overlay);
    assert.equal(overlay.style.display, 'flex');
    assert.equal(overlay.card.text.textContent, 'Saving request…');
    assert.equal(button.disabled, true);
    assert.equal(form['aria-busy'], 'true');
});

test('uses the fallback loader text for Glide Select forms without an explicit message', () => {
    const harness = createHarness();
    const form = harness.createForm({ glideSelect: true });

    installSubmitLoader(harness.document, harness.window);
    harness.submit(form);

    assert.equal(harness.elements.get('gs-submit-overlay').card.text.textContent, 'Loading...');
});

test('does not show a loader for prevented or AJAX submissions', () => {
    const harness = createHarness();
    const preventedForm = harness.createForm({ loadingText: 'Saving…' });
    const invalidGlideForm = harness.createForm({ glideSelect: true });
    const ajaxForm = harness.createForm({ loadingText: 'Saving…', ajax: true });

    installSubmitLoader(harness.document, harness.window);
    harness.submit(preventedForm, { defaultPrevented: true });
    harness.submit(invalidGlideForm, { defaultPrevented: true });
    harness.submit(ajaxForm);

    assert.equal(harness.elements.has('gs-submit-overlay'), false);
});

test('pageshow hides the loader and restores submit button state', () => {
    const harness = createHarness({ dark: true });
    const enabledButton = new FakeElement();
    const disabledButton = new FakeElement();
    disabledButton.disabled = true;
    disabledButton.style.opacity = '0.3';
    const form = harness.createForm({ loadingText: 'Sending…', buttons: [enabledButton, disabledButton] });

    installSubmitLoader(harness.document, harness.window);
    harness.submit(form);
    harness.pageshow();

    assert.equal(harness.elements.get('gs-submit-overlay').style.display, 'none');
    assert.equal(enabledButton.disabled, false);
    assert.equal(disabledButton.disabled, true);
    assert.equal(disabledButton.style.opacity, '0.3');
    assert.equal(form.attributes.has('aria-busy'), false);
});

test('binds its delegated handlers only once per document', () => {
    const harness = createHarness();

    installSubmitLoader(harness.document, harness.window);
    installSubmitLoader(harness.document, harness.window);

    assert.equal(harness.documentEvents.get('submit').length, 1);
    assert.equal(harness.windowEvents.get('pageshow').length, 1);
});
