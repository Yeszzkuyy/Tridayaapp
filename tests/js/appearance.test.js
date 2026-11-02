import assert from 'node:assert/strict';
import test from 'node:test';
import { applyAppearanceDom, isDarkMode } from '../../resources/js/appearance.js';

function fakeRoot() {
    const classes = new Set();

    return {
        attrs: {},
        classList: {
            contains(name) {
                return classes.has(name);
            },
            toggle(name, force) {
                if (force) classes.add(name);
                else classes.delete(name);
            },
        },
        setAttribute(name, value) {
            this.attrs[name] = value;
        },
    };
}

test('mode dark selalu gelap, mode light selalu terang', () => {
    assert.equal(isDarkMode('dark', false), true);
    assert.equal(isDarkMode('dark', true), true);
    assert.equal(isDarkMode('light', false), false);
    assert.equal(isDarkMode('light', true), false);
});

test('mode system mengikuti skema OS', () => {
    assert.equal(isDarkMode('system', true), true);
    assert.equal(isDarkMode('system', false), false);
});

test('applyAppearanceDom menulis class dark dan atribut mode/accent', () => {
    const root = fakeRoot();

    assert.equal(applyAppearanceDom(root, { mode: 'dark', accent: 'terracotta' }), true);
    assert.equal(root.classList.contains('dark'), true);
    assert.equal(root.attrs['data-mode'], 'dark');
    assert.equal(root.attrs['data-theme'], 'terracotta');

    assert.equal(applyAppearanceDom(root, { mode: 'light', accent: 'emerald', prefersDark: true }), false);
    assert.equal(root.classList.contains('dark'), false);
    assert.equal(root.attrs['data-mode'], 'light');
    assert.equal(root.attrs['data-theme'], 'emerald');
});

test('applyAppearanceDom memakai skema OS untuk mode system', () => {
    const root = fakeRoot();

    applyAppearanceDom(root, { mode: 'system', accent: 'ocean', prefersDark: true });
    assert.equal(root.classList.contains('dark'), true);

    applyAppearanceDom(root, { mode: 'system', accent: 'ocean', prefersDark: false });
    assert.equal(root.classList.contains('dark'), false);
});
