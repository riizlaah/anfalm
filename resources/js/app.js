import { pasangProteksiKonten } from './proteksi'
import { renderRumusDi } from './rumus'

// Proteksi 7.10 dipasang secepatnya; modulnya sendiri yang menilai apakah body
// bertanda `terlindungi`, sehingga halaman editor admin tak tersentuh.
pasangProteksiKonten()

document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-dialog-open]')
    if (opener) {
        event.preventDefault()
        document.getElementById(opener.dataset.dialogOpen)?.showModal()

        return
    }

    const closer = event.target.closest('[data-dialog-close]')
    if (closer) {
        event.preventDefault()
        closer.closest('dialog')?.close()

        return
    }

    // Klik area backdrop (di luar kotak dialog) ikut menutup dialog.
    const dialog = event.target.closest('dialog')
    if (dialog && event.target === dialog) {
        const rect = dialog.getBoundingClientRect()
        const diDalam = event.clientX >= rect.left && event.clientX <= rect.right
            && event.clientY >= rect.top && event.clientY <= rect.bottom

        if (!diDalam) {
            dialog.close()
        }
    }
})

document.addEventListener('DOMContentLoaded', () => {
    // Render ekspresi KaTeX pada konten tersimpan. Cakupannya dibatasi elemen
    // bertanda `data-rumus` agar nilai field isian (textarea) tak tersentuh.
    document.querySelectorAll('[data-rumus]').forEach((el) => renderRumusDi(el))

    // TipTap hanya dibutuhkan di form admin; dimuat terpisah supaya halaman
    // peserta tidak ikut menyeret bobot editornya.
    if (document.querySelector('[data-wysiwyg]')) {
        import('./wysiwyg').then(({ inisialisasiSemuaWysiwyg }) => inisialisasiSemuaWysiwyg())
    }

    // Chart.js hanya ada di halaman Analisis Kompetensi.
    if (document.querySelector('[data-grafik]')) {
        import('./analisis').then(({ mulaiGrafikAnalisis }) => mulaiGrafikAnalisis())
    }
})
