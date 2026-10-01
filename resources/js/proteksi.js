/**
 * Proteksi konten soal peserta (DESIGN §7.10).
 *
 * Bersifat client-side dan memang dasar: mencegah klik kanan serta pintasan
 * salin/seluruh/cari sumber. Pemasangannya dihentikan seketika ketika body takut
 * bertanda `terlindungi`, sehingga editor admin (yang membutuhkan seleksi dan
 * salin) tidak pernah terganggu.
 */

/** Pintasan yang dicegah, sesuai 7.10. */
const PINTASAN = new Set(['c', 'a', 'u'])

export function pasangProteksiKonten() {
    if (!document.body?.classList.contains('terlindungi')) return

    document.addEventListener('contextmenu', (acara) => {
        if (bolehDicegah(acara)) acara.preventDefault()
    })

    document.addEventListener('keydown', (acara) => {
        if (!acara.ctrlKey && !acara.metaKey) return
        if (!PINTASAN.has(acara.key.toLowerCase())) return
        if (bolehDicegah(acara)) acara.preventDefault()
    })
}

/**
 * Pencegahan tidak dilakukan pada kolom isian: menyalin di dalam kolom tidak
 * membocorkan soal, sedangkan memblokirnya akan merusak pengalaman mengetik.
 */
function bolehDicegah(acara) {
    const sasaran = acara.target

    return !(sasaran instanceof Element)
        || !sasaran.matches('input, textarea, select, [contenteditable="true"]')
}
