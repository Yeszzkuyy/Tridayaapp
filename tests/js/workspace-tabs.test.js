import assert from 'node:assert/strict';
import test from 'node:test';
import { isAuthPath, broadcastTabChanged } from '../../resources/js/workspace-tabs.js';

test('halaman auth dikenali sebagai halaman di luar workspace', () => {
    for (const path of [
        '/login',
        '/login/',
        '/register',
        '/forgot-password',
        '/reset-password/abc123',
        '/verify-email',
        '/confirm-password',
        '/pending-approval',
    ]) {
        assert.equal(isAuthPath(path), true, path);
    }
});

test('halaman aplikasi tidak dianggap halaman auth', () => {
    for (const path of [
        '/',
        '/dashboard',
        '/admin/invoices',
        '/sales/my-leads',
        '/login-activity',
        '',
    ]) {
        assert.equal(isAuthPath(path), false, path);
    }
});

test('pindah tab mengirim sinyal agar menu header tertutup', () => {
    const events = [];
    const previousWindow = globalThis.window;
    globalThis.window = {
        dispatchEvent(event) {
            events.push(event);
            return true;
        },
    };

    try {
        broadcastTabChanged({ id: 'sales/leads', path: '/sales/leads' });
    } finally {
        if (previousWindow === undefined) delete globalThis.window;
        else globalThis.window = previousWindow;
    }

    assert.equal(events.length, 1);
    assert.equal(events[0].type, 'workspace-tab-changed');
    assert.deepEqual(events[0].detail, { id: 'sales/leads', path: '/sales/leads' });
});

test('sinyal pindah tab tetap aman tanpa data tab', () => {
    const events = [];
    const previousWindow = globalThis.window;
    globalThis.window = {
        dispatchEvent(event) {
            events.push(event);
            return true;
        },
    };

    try {
        broadcastTabChanged(null);
    } finally {
        if (previousWindow === undefined) delete globalThis.window;
        else globalThis.window = previousWindow;
    }

    assert.equal(events.length, 1);
    assert.deepEqual(events[0].detail, { id: null, path: null });
});
