import assert from 'node:assert/strict';
import test from 'node:test';
import {
    dismissAllToasts,
    dismissToast,
    getToasts,
    installLegacyToast,
    pushToast,
    subscribeToast,
} from '../../resources/js/toast-store.js';

test('pushToast menambah kartu dan subscribe langsung menerima isi antrean', () => {
    dismissAllToasts();

    let snapshot = null;
    const unsubscribe = subscribeToast((next) => {
        snapshot = next;
    });

    pushToast({ variant: 'success', message: 'Data tersimpan' });

    assert.equal(getToasts().length, 1);
    assert.equal(getToasts()[0].variant, 'success');
    assert.equal(getToasts()[0].message, 'Data tersimpan');
    assert.equal(snapshot.length, 1);
    assert.ok(getToasts()[0].duration > 0);

    unsubscribe();
});

test('nama varian lama dinormalkan: danger jadi error', () => {
    dismissAllToasts();

    const id = pushToast({ variant: 'danger', message: 'gagal' });
    const toast = getToasts().find((item) => item.id === id);

    assert.equal(toast.variant, 'error');
});

test('varian tidak dikenal jatuh ke default dan durasi bawaan 8 detik', () => {
    dismissAllToasts();

    const unknownId = pushToast({ variant: 'ngawur', message: 'x' });
    const unknown = getToasts().find((toast) => toast.id === unknownId);
    assert.equal(unknown.variant, 'default');
    assert.equal(unknown.duration, 8000);

    const errorId = pushToast({ variant: 'error', message: 'gagal' });
    const error = getToasts().find((toast) => toast.id === errorId);
    assert.equal(error.duration, 8000);

    const customId = pushToast({ message: 'khusus', duration: 3000 });
    const custom = getToasts().find((toast) => toast.id === customId);
    assert.equal(custom.duration, 3000);
});

test('antrean dibatasi tiga kartu, yang tertua dibuang', () => {
    dismissAllToasts();

    pushToast({ message: 'satu' });
    pushToast({ message: 'dua' });
    pushToast({ message: 'tiga' });
    pushToast({ message: 'empat' });

    const messages = getToasts().map((toast) => toast.message);
    assert.deepEqual(messages, ['dua', 'tiga', 'empat']);
});

test('dismissToast menghapus kartu dan memanggil onDismiss sekali', () => {
    dismissAllToasts();

    let dismissed = 0;
    const id = pushToast({ message: 'tutup', onDismiss: () => { dismissed += 1; } });

    dismissToast(id);
    dismissToast(id);

    assert.equal(getToasts().length, 0);
    assert.equal(dismissed, 1);
});

test('window.toast lama tetap jalan: ok=true hijau, ok=false merah', () => {
    dismissAllToasts();

    globalThis.window = {};
    installLegacyToast();

    globalThis.window.toast('Berhasil simpan', true);
    globalThis.window.toast('Gagal simpan', false);
    globalThis.window.toast('Sesi habis', false, { label: 'Masuk lagi', href: '/login' });

    const toasts = getToasts();
    assert.equal(toasts[0].variant, 'success');
    assert.equal(toasts[1].variant, 'error');
    assert.equal(toasts[2].action.label, 'Masuk lagi');
    assert.equal(toasts[2].action.href, '/login');

    delete globalThis.window;
});
