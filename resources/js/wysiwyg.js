/**
 * Editor konten kaya TipTap untuk form admin (DESIGN §4.2).
 *
 * Prinsip penyimpanannya: yang diserahkan ke form adalah input tersembunyi
 * berisi HTML hasil `getHTML()`, sudah dibersihkan DOMPurify lalu dibungkus
 * tag khusus KaTeX. Sumber LaTeX tidak pernah disentuh editor sehingga yang
 * tampil di kolom teks selalu kode aslinya; ekspresi matematikanya dirender
 * langsung oleh node `ekspresi` — tidak ada panel pratinjau terpisah, karena
 * apa yang diketik di kolom memang itulah yang akan tersimpan (butir 163).
 *
 * Dua mode (butir 165): `penuh` untuk pertanyaan/pembahasan, dan `inline`
 * untuk baris opsi/pernyataan yang dirender di dalam `<label>` pada halaman
 * peserta — hanya penanda di dalam baris, tanpa konten blok.
 */

import { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Image from '@tiptap/extension-image'
import DOMPurify from 'dompurify'
import { pasangKompresiGambar } from './gambar'
import { bungkusRumus } from './rumus'
import { Ekspresi, pasangDialogEkspresi } from './ekspresi'

const TUGAS_FORMAT = {
    bold: (e) => e.chain().focus().toggleBold().run(),
    italic: (e) => e.chain().focus().toggleItalic().run(),
    underline: (e) => e.chain().focus().toggleUnderline().run(),
    strike: (e) => e.chain().focus().toggleStrike().run(),
    'daftar-bulat': (e) => e.chain().focus().toggleBulletList().run(),
    'daftar-bernomor': (e) => e.chain().focus().toggleOrderedList().run(),
    kutipan: (e) => e.chain().focus().toggleBlockquote().run(),
    kode: (e) => e.chain().focus().toggleCodeBlock().run(),
}

const KEADAAN_FORMAT = {
    bold: (e) => e.isActive('bold'),
    italic: (e) => e.isActive('italic'),
    underline: (e) => e.isActive('underline'),
    strike: (e) => e.isActive('strike'),
    'daftar-bulat': (e) => e.isActive('bulletList'),
    'daftar-bernomor': (e) => e.isActive('orderedList'),
    kutipan: (e) => e.isActive('blockquote'),
    kode: (e) => e.isActive('codeBlock'),
}

/** Editor mode penuh yang sedang terakhir menerima fokus, tujuan sisipan gambar. */
let editorTerakhir = null

/**
 * Ubah HTML dokumen menjadi konten frasa untuk penyimpanan opsi/pernyataan.
 *
 * Keduanya dirender di dalam `<label>` pada halaman peserta, yang menurut
 * spesifikasi tidak boleh memuat konten blok: paragraf pembungkus dibuang,
 * beberapa paragraf (misal hasil tempelan) disambung `<br>`, dan teks tanpa
 * satu pun tag dikembalikan apa adanya — kalau `&` atau `<` dibiarkan
 * ter-encode, halaman peserta meng-esc-nya dua kali saat merender
 * (lihat KontenSanitizer::untukTampilan()).
 *
 * @param {string} html hasil `bungkusRumus()` + DOMPurify
 * @returns {string}
 */
function keFrasa(html) {
    const dokumen = document.createElement('div')
    dokumen.innerHTML = html

    const pembungkus = /^(P|DIV|H[1-6]|BLOCKQUOTE|PRE|UL|OL)$/
    const frasa = Array.from(dokumen.children)
        .map((anak) => (pembungkus.test(anak.tagName) ? anak.innerHTML : anak.outerHTML))
        .join('<br>')
        .replace(/(<br>)+$/i, '')

    if (/<[a-z!\/]/i.test(frasa)) {
        return frasa
    }

    const teks = document.createElement('div')
    teks.innerHTML = frasa
    return teks.textContent || ''
}

/**
 * @param {HTMLElement} wadah elemen bertanda `data-wysiwyg`
 */
export function inisialisasiWysiwyg(wadah) {
    const input = wadah.querySelector('[data-wysiwyg-input]')
    const area = wadah.querySelector('[data-wysiwyg-editor]')
    const pesanGalat = wadah.querySelector('[data-wysiwyg-galat]')
    const berkas = wadah.querySelector('input[type="file"]')

    if (!input || !area) return

    // Mode inline (butir 165): baris opsi/pernyataan dirender di dalam <label>,
    // sehingga isinya harus tetap konten frasa — tanpa daftar, kutipan, blok
    // kode, heading, atau gambar. Kode tetap tersedia sebagai penanda di dalam
    // baris, sekaligus satu-satunya "kode" yang berarti di sana.
    const inline = wadah.dataset.wysiwygMode === 'inline'
    const tugasFormat = { ...TUGAS_FORMAT }
    const keadaanFormat = { ...KEADAAN_FORMAT }

    if (inline) {
        tugasFormat.kode = (e) => e.chain().focus().toggleCode().run()
        keadaanFormat.kode = (e) => e.isActive('code')
    }

    // Popup ekspresi menuntut `editor` saat dipasang, sedangkan editor-nya
    // sendiri butuh popup untuk menangani klik node — keduanya bertemu lewat
    // variabel yang dibaca saat acara terjadi, bukan saat inisialisasi.
    let dialogEkspresi = null

    const editor = new Editor({
        element: area,
        extensions: [
            StarterKit.configure(inline
                ? {
                    blockquote: false,
                    bulletList: false,
                    codeBlock: false,
                    heading: false,
                    horizontalRule: false,
                    listItem: false,
                    orderedList: false,
                }
                : {}),
            ...(inline ? [] : [Image.configure({ inline: false })]),
            Ekspresi,
        ],
        // Simpanan lama boleh masih berupa teks mentah `\( ... \)` yang belum
        // pernah lewat `bungkusRumus()`; bungkuskan dulu agar ikut terbaca
        // sebagai node dan ikut ter-render (idempoten, lihat rumus.js).
        content: bungkusRumus(input.value || ''),
        editorProps: {
            attributes: {
                class: 'wysiwyg-kolom',
                'aria-label': wadah.dataset.wysiwygNama || 'Isi konten',
                // Palang tulis diambil dari atribut ini; hanya dipasang bila
                // barisnya memang menyediakan placeholder.
                ...(area.dataset.placeholder ? { 'data-placeholder': area.dataset.placeholder } : {}),
            },
            // Klik langsung pada ekspresi membukakan popup penyuntingnya,
            // lengkap dengan kode sumber yang sekarang dipakainya.
            handleClickOn: (view, pos, node, nodePos, acara, langsung) => {
                if (!langsung || node.type.name !== 'ekspresi') return false
                dialogEkspresi?.buka({ ...node.attrs, posisi: nodePos })
                return true
            },
            // Penyunting lewat papan ketik: sorot ekspresi lalu tekan Enter.
            handleKeyDown: (view, acara) => {
                if (acara.key !== 'Enter' && acara.key !== ' ') return false

                const simpul = view.state.selection.node
                if (simpul?.type?.name !== 'ekspresi') return false

                dialogEkspresi?.buka({ ...simpul.attrs, posisi: view.state.selection.from })
                return true
            },
        },
        onUpdate: () => sinkron(),
        onFocus: () => {
            if (!inline) editorTerakhir = editor
        },
    })

    wadah.__wysiwyg = editor
    // Penerima cadangan sisipan gambar hanya editor mode penuh; mode inline
    // tidak memasang ekstensi gambar sama sekali, jadi `setImage` tidak ada.
    if (!inline) editorTerakhir = editor
    dialogEkspresi = pasangDialogEkspresi(wadah, editor)

    if (berkas instanceof HTMLInputElement) {
        pasangKompresiGambar(wadah, berkas)
    }

    function draf() {
        const html = DOMPurify.sanitize(bungkusRumus(editor.getHTML()))
        return inline ? keFrasa(html) : html
    }

    function sinkron() {
        input.value = draf()
        // Palang tulis hanya muncul pada mode inline (lihat app.css);
        // `<p></p>` sudah dibuang `keFrasa()`, jadi nilai kosong benar-benar
        // kosong dan tetap terbaca `required` di server.
        area.classList.toggle('is-kosong', editor.isEmpty)
    }

    // Isi awal sudah berupa HTML tersimpan; serahkan apa adanya ke form.
    sinkron()

    wadah.querySelectorAll('[data-tugas]').forEach((tombol) => {
        const tugas = tombol.dataset.tugas

        tombol.addEventListener('click', (acara) => {
            acara.preventDefault()

            if (tugas === 'rumus') {
                dialogEkspresi?.buka()
                return
            }

            if (tugas === 'gambar') {
                berkas?.click()
                return
            }

            const jalankan = tugasFormat[tugas]
            if (jalankan) jalankan(editor)
        })
    })

    // Sorot tombol yang sedang aktif mengikuti posisi kursor.
    editor.on('selectionUpdate', () => {
        wadah.querySelectorAll('[data-tugas]').forEach((tombol) => {
            const cek = keadaanFormat[tombol.dataset.tugas]
            if (!cek) return
            tombol.classList.toggle('is-aktif', cek(editor))
        })
    })
}

/** Pengamat keterlihatan bersama seluruh editor dalam halaman. */
let pengamat = null

/**
 * Siapkan satu wadah menyala saat ia tampil pertama kali, bukan seluruh
 * halaman sekaligus. Halaman kurasi memuat dua editor per soal dan hanya
 * satu soal yang tampil pada satu waktu, jadi menyala semua di awal hanya
 * membuang waktu untuk editor yang sedang tersembunyi. Nilai editor yang
 * belum tersentuh tetap dibaca dari input tersembunyinya oleh form.
 *
 * @param {HTMLElement} wadah
 */
function pantauWysiwyg(wadah) {
    if (wadah.__wysiwyg) return

    if (!('IntersectionObserver' in window)) {
        inisialisasiWysiwyg(wadah)

        return
    }

    if (!pengamat) {
        pengamat = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return

                pengamat.unobserve(entry.target)
                inisialisasiWysiwyg(entry.target)
            })
        })
    }

    pengamat.observe(wadah)
}

/** Lepas editor baris yang dihapus dari DOM beserta pengamatnya. */
function buangWysiwyg(wadah) {
    pengamat?.unobserve(wadah)
    wadah.__wysiwyg?.destroy?.()
    delete wadah.__wysiwyg
}

let pengamatDom = null

/**
 * Baris opsi/pernyataan baru datang lewat `innerHTML` saat tombol "+ Tambah"
 * ditekan, sehingga editornya harus disusul terpisah — pengamat keterlihatan
 * di atas hanya menerima yang ada di awal. Baris yang dihapus juga dilepas
 * dari pengamat supaya editor TipTap-nya ikut hancur, bukan menggantung.
 */
function pasangPengamatTambahan() {
    if (pengamatDom || !('MutationObserver' in window)) return

    pengamatDom = new MutationObserver((mutations) => {
        mutations.forEach((mutasi) => {
            mutasi.addedNodes.forEach((node) => {
                if (node.nodeType !== Node.ELEMENT_NODE) return
                if (node.matches('[data-wysiwyg]')) pantauWysiwyg(node)
                node.querySelectorAll('[data-wysiwyg]').forEach((wadah) => pantauWysiwyg(wadah))
            })

            mutasi.removedNodes.forEach((node) => {
                if (node.nodeType !== Node.ELEMENT_NODE) return
                if (node.matches('[data-wysiwyg]')) buangWysiwyg(node)
                node.querySelectorAll('[data-wysiwyg]').forEach((wadah) => buangWysiwyg(wadah))
            })
        })
    })

    pengamatDom.observe(document.body, { childList: true, subtree: true })
}

/** Inisialisasi seluruh editor dalam dokumen (dipanggil dari app.js). */
export function inisialisasiSemuaWysiwyg() {
    document.querySelectorAll('[data-wysiwyg]').forEach((wadah) => pantauWysiwyg(wadah))
    pasangPengamatTambahan()
}

// Sisipkan gambar yang baru diunggah komponen Livewire. Satu listener untuk
// seluruh halaman: pendaftaran per editor membuat gambar yang sama disisipkan
// sebanyak jumlah editor, dan jumlah itu meledak sejak setiap baris opsi juga
// memakai editor (butir 165).
document.addEventListener('gambarTerunggah', (acara) => {
    const url = acara.detail?.url
    if (!url) return

    const pemilik = acara.target?.closest?.('[data-wysiwyg]')
    const tujuan = pemilik?.__wysiwyg ?? editorTerakhir
    if (tujuan) {
        tujuan.chain().focus().setImage({ src: url, alt: '' }).run()
    }
})
