import assert from 'node:assert/strict';
import test from 'node:test';
import { isAuthPath } from '../../resources/js/workspace-tabs.js';

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
