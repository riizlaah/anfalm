/**
 * Node atom ekspresi matematika beserta popup penyuntingnya (butir 163,
 * REPORT_N_SUGGEST.md).
 *
 * Prinsipnya mengikuti prinsip editor: **apa yang tampil adalah apa yang
 * disimpan**. Dulu ekspresi disimpan sebagai teks mentah `\( ... \)` yang hanya
 * dirender di panel pratinjau terpisah — admin menekan tombol "Pratinjau" untuk
 * tahu hasilnya. Kini ekspresinya menjadi satu node atom yang merender KaTeX
 * langsung di kolom editor, dan penyuntingannya lewat `<dialog>`: tombol `ƒ(x)`
 * menyisipkan ekspresi baru, sedangkan klik pada ekspresi yang sudah ada membuka
 * dialog yang sama berisi kode sumbernya kembali beserta pratinjau yang langsung
 * dihitung.
 *
 * Bentuk simpannya sendiri tidak berubah. `renderHTML()` tetap menulis
 * `<span class="katex-inline">\( ... \)</span>` (atau `katex-display` untuk
 * `\[ ... \]`), sehingga sisi peserta, data lama, dan `bungkusRumus()` tidak
 * perlu tahu bahwa editor kini memakai node khusus (DESIGN §4.3 langkah 3).
 */

import { Node } from '@tiptap/core'
import katex from 'katex'

/**
 * Baca isi pembungkus ekspresi menjadi sumber KaTeX beserta mode-nya.
 *
 * @param {string} teks isi di dalam pembungkus, mis. `\( \frac{1}{2} \)`
 * @returns {{sumber: string, display: boolean}}
 */
export function bacaEkspresi(teks) {
    const bersih = (teks ?? '').trim()
    const display = /^\\\[([\s\S]+)\\\]$/.test(bersih)
    const pola = display ? /^\\\[([\s\S]+)\\\]$/ : /^\\\(([\s\S]+)\\\)$/
    const cocok = pola.exec(bersih)

    // Tanpa delimitter berarti teksnya sudah sumber mentah (kasus pelik: isi
    // pembungkus diganti lewat devtools) — pakai apa adanya, tetap inline.
    return cocok ? { sumber: cocok[1].trim(), display } : { sumber: bersih, display: false }
}

/**
 * Ambil atribut node dari elemen `<span class="katex-inline">` hasil simpanan.
 *
 * Delimitter yang menentukan mode; bila tidak ada, kelas pembungkusnya yang
 * dipakai sebagai pegangan terakhir.
 *
 * @param {HTMLElement} elemen
 * @returns {{sumber: string, display: boolean}}
 */
function atributDari(elemen) {
    const teks = elemen.textContent.trim()

    if (/^\\[\[(]/.test(teks)) return bacaEkspresi(teks)

    return { sumber: teks, display: elemen.classList.contains('katex-display') }
}

export const Ekspresi = Node.create({
    name: 'ekspresi',
    group: 'inline',
    inline: true,
    atom: true,
    selectable: true,

    addAttributes() {
        return {
            sumber: { default: '' },
            display: { default: false },
        }
    },

    parseHTML() {
        return [
            { tag: 'span.katex-inline', getAttrs: (elemen) => atributDari(elemen) },
            { tag: 'span.katex-display', getAttrs: (elemen) => atributDari(elemen) },
        ]
    },

    /**
     * Simpanan tidak boleh berubah: teks mentah berdelimitter, dibungkus tag
     * khusus yang sama seperti sebelum node ini ada.
     */
    renderHTML({ node }) {
        const display = Boolean(node.attrs.display)
        const tanda = display ? ['\\[', '\\]'] : ['\\(', '\\)']

        return [
            'span',
            { class: display ? 'katex-display' : 'katex-inline' },
            `${tanda[0]}${node.attrs.sumber}${tanda[1]}`,
        ]
    },

    addNodeView() {
        return ({ node }) => {
            const wadah = document.createElement('span')
            wadah.className = 'ekspresi-wadah'
            wadah.contentEditable = 'false'

            let sekarang = node

            function gambar() {
                const display = Boolean(sekarang.attrs.display)
                wadah.className = display ? 'ekspresi-wadah katex-display' : 'ekspresi-wadah'
                wadah.textContent = ''

                try {
                    katex.render(sekarang.attrs.sumber, wadah, {
                        displayMode: display,
                        throwOnError: true,
                    })
                } catch (galat) {
                    // Kode yang salah tetap terbaca apa adanya (DESIGN §6.13),
                    // galatnya sendiri disimpan sebagai tooltip.
                    wadah.classList.add('rumus-galat')
                    wadah.textContent = sekarang.attrs.sumber || '…'
                    wadah.title = galat.message
                }
            }

            gambar()

            return {
                dom: wadah,
                update(simpulBaru) {
                    if (simpulBaru.type.name !== 'ekspresi') return false
                    sekarang = simpulBaru
                    gambar()
                    return true
                },
                // Seluruh DOM-nya dikelola node view; mutasi di dalamnya bukan
                // perubahan isi dokumen.
                ignoreMutation: () => true,
            }
        }
    },
})

/**
 * Pasang popup ekspresi pada satu editor.
 *
 * @param {HTMLElement} wadah elemen bertanda `data-wysiwyg`
 * @param {import('@tiptap/core').Editor} editor
 * @returns {?{buka: (opsi?: {sumber?: string, display?: boolean, posisi?: ?number}) => void}}
 */
export function pasangDialogEkspresi(wadah, editor) {
    const dialog = wadah.querySelector('[data-rumus-dialog]')
    const input = wadah.querySelector('[data-rumus-input]')
    const pratinjau = wadah.querySelector('[data-rumus-pratinjau]')
    const modeBlok = wadah.querySelector('[data-rumus-mode-blok]')
    const tombolSimpan = wadah.querySelector('[data-rumus-simpan]')
    const tombolBatal = wadah.querySelector('[data-rumus-batal]')

    if (!dialog || !input || !pratinjau || !modeBlok || !tombolSimpan) return null

    /** Posisi node yang sedang diedit, atau `null` saat mode menyisipkan. */
    let posisiPerbarui = null

    function lukisPratinjau() {
        const sumber = input.value.trim()

        pratinjau.className = 'wysiwyg-rumus-pratinjau'
        pratinjau.textContent = ''
        tombolSimpan.disabled = sumber === ''

        if (sumber === '') {
            pratinjau.textContent = 'Tulis kode KaTeX untuk melihat pratinjaunya.'
            return
        }

        try {
            katex.render(sumber, pratinjau, { displayMode: modeBlok.checked, throwOnError: true })
        } catch (galat) {
            pratinjau.classList.add('rumus-galat')
            pratinjau.textContent = galat.message
        }
    }

    function tutup() {
        // Pengaturan `posisiPerbarui` dilakukan di sini, bukan pada acara
        // `close`: browser mengantre acara itu sebagai tugas terpisah, sehingga
        // ia bisa berjalan SETELAH popup dibuka kembali dan mereset posisi yang
        // barusan dibaca — jalur edit lalu salah menjadi jalur sisipkan.
        posisiPerbarui = null

        if (dialog.open && typeof dialog.close === 'function') {
            dialog.close()
        } else {
            dialog.removeAttribute('open')
        }
    }

    function simpan() {
        const sumber = input.value.trim()
        if (sumber === '') return

        const attrs = { sumber, display: modeBlok.checked }
        const tujuan = posisiPerbarui

        if (tujuan === null) {
            editor
                .chain()
                .focus()
                .insertContentAt(editor.state.selection.from, { type: 'ekspresi', attrs })
                .run()
        } else {
            editor
                .chain()
                .command(({ tr }) => {
                    const simpul = tr.doc.nodeAt(tujuan)
                    if (!simpul || simpul.type.name !== 'ekspresi') return false
                    tr.setNodeMarkup(tujuan, null, attrs)
                    return true
                })
                .run()
        }

        tutup()
    }

    /**
     * @param {{sumber?: string, display?: boolean, posisi?: ?number}} opsi
     *   `posisi` diisi saat node yang sudah ada diklik; `null` berarti menyisipkan baru.
     */
    function buka(opsi = {}) {
        posisiPerbarui = opsi.posisi ?? null
        input.value = opsi.sumber ?? ''
        modeBlok.checked = opsi.display ?? false
        lukisPratinjau()

        if (typeof dialog.showModal === 'function') {
            dialog.showModal()
        } else {
            dialog.setAttribute('open', '')
        }

        input.focus()
        input.select()
    }

    input.addEventListener('input', lukisPratinjau)
    modeBlok.addEventListener('change', lukisPratinjau)
    tombolSimpan.addEventListener('click', simpan)
    tombolBatal?.addEventListener('click', tutup)
    // Klik pada tirainya (bukan pada dialognya) menutup popup. Posisi node
    // sengaja tidak direset di sini — lihat catatan di dalam `tutup()`.
    dialog.addEventListener('click', (acara) => {
        if (acara.target === dialog) tutup()
    })

    lukisPratinjau()

    return { buka }
}
