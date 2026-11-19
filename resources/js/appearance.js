/**
 * Appearance — mode (light/dark/system) x accent.
 *
 * Sumber kebenaran preferensi adalah server (users.preferences). Helper di
 * sini hanya menerjemahkan nilai itu ke DOM, tanpa menyimpan salinan di
 * localStorage: salinan browser pernah menimpa pilihan yang tersimpan
 * sehingga user melihat tema milik sesi/akun lain.
 */

/** true bila mode + skema OS menghasilkan tampilan gelap. */
export function isDarkMode(mode, prefersDark = false) {
    return mode === 'dark' || (mode === 'system' && prefersDark);
}

/**
 * Terapkan mode + accent ke elemen <html>.
 * Mengembalikan true bila hasilnya gelap.
 */
export function applyAppearanceDom(root, { mode, accent, prefersDark = false }) {
    const dark = isDarkMode(mode, prefersDark);

    root.classList.toggle('dark', dark);
    root.setAttribute('data-mode', mode);
    root.setAttribute('data-theme', accent);

    return dark;
}
