/**
 * Editor konten kaya TipTap untuk form admin (DESIGN §4.2).
 *
 * Prinsip penyimpanannya: yang diserahkan ke form adalah input tersembunyi
 * berisi HTML hasil `getHTML()`, sudah dibersihkan DOMPurify lalu dibungkus
 * tag khusus KaTeX. Sumber LaTeX tidak pernah disentuh editor sehingga yang
 * tampil di kolom teks selalu kode aslinya; hasil render KaTeX hanya muncul
 * di panel preview (DESIGN §3.3).
 */

import { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Image from '@tiptap/extension-image'
import DOMPurify from 'dompurify'
import { pasangKompresiGambar } from './gambar'
import { bungkusRumus, renderRumusDi } from './rumus'

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

/** Editor yang sedang terakhir menerima fokus, tujuan penyisipan gambar. */
let editorTerakhir = null

/**
 * @param {HTMLElement} wadah elemen bertanda `data-wysiwyg`
 */
export function inisialisasiWysiwyg(wadah) {
    const input = wadah.querySelector('[data-wysiwyg-input]')
    const area = wadah.querySelector('[data-wysiwyg-editor]')
    const preview = wadah.querySelector('[data-wysiwyg-preview]')
    const pesanGalat = wadah.querySelector('[data-wysiwyg-galat]')
    const berkas = wadah.querySelector('input[type="file"]')

    if (!input || !area) return

    let tampilPreview = false

    const editor = new Editor({
        element: area,
        extensions: [StarterKit, Image.configure({ inline: false })],
        content: input.value || '',
        editorProps: {
            attributes: {
                class: 'wysiwyg-kolom',
                'aria-label': wadah.dataset.wysiwygNama || 'Isi konten',
            },
        },
        onUpdate: () => {
            sinkron()
            if (tampilPreview) lukisPreview()
        },
        onFocus: () => {
            editorTerakhir = editor
        },
    })

    wadah.__wysiwyg = editor
    editorTerakhir = editor

    if (berkas instanceof HTMLInputElement) {
        pasangKompresiGambar(wadah, berkas)
    }

    function draf() {
        return DOMPurify.sanitize(bungkusRumus(editor.getHTML()))
    }

    function sinkron() {
        input.value = draf()
    }

    function lukisPreview() {
        preview.innerHTML = DOMPurify.sanitize(editor.getHTML())
        // Kedua galat dipakai bersama: pesan ditampilkan, teks mentah tetap ada
        // sebagai fallback (DESIGN §6.13).
        renderRumusDi(preview, true)
    }

    // Isi awal sudah berupa HTML tersimpan; serahkan apa adanya ke form.
    sinkron()

    wadah.querySelectorAll('[data-tugas]').forEach((tombol) => {
        const tugas = tombol.dataset.tugas

        tombol.addEventListener('click', (acara) => {
            acara.preventDefault()

            if (tugas === 'preview') {
                tampilPreview = !tampilPreview
                preview.classList.toggle('hidden', !tampilPreview)
                tombol.setAttribute('aria-pressed', String(tampilPreview))
                if (tampilPreview) lukisPreview()
                return
            }

            if (tugas === 'rumus') {
                // Admin menulis sendiri kodenya (DESIGN §3.3); tombol ini hanya
                // menyisipkan kerangka `\(  \)` lalu menaruh kursor di antaranya.
                const posisi = editor.state.selection.from
                editor.chain().focus().insertContent('\\(  \\)').run()
                editor.commands.setTextSelection(posisi + 3)
                return
            }

            if (tugas === 'gambar') {
                berkas?.click()
                return
            }

            const jalankan = TUGAS_FORMAT[tugas]
            if (jalankan) jalankan(editor)
        })
    })

    // Sorot tombol yang sedang aktif mengikuti posisi kursor.
    editor.on('selectionUpdate', () => {
        wadah.querySelectorAll('[data-tugas]').forEach((tombol) => {
            const cek = KEADAAN_FORMAT[tombol.dataset.tugas]
            if (!cek) return
            tombol.classList.toggle('is-aktif', cek(editor))
        })
    })

    // Sisipkan gambar yang baru diunggah komponen Livewire di dalam form ini.
    document.addEventListener('gambarTerunggah', (acara) => {
        const url = acara.detail?.url
        if (!url) return

        const pemilik = acara.target?.closest?.('[data-wysiwyg]')
        const tujuan = pemilik?.__wysiwyg ?? editorTerakhir
        if (tujuan) {
            tujuan.chain().focus().setImage({ src: url, alt: '' }).run()
        }
    })
}

/** Inisialisasi seluruh editor dalam dokumen (dipanggil dari app.js). */
export function inisialisasiSemuaWysiwyg() {
    const kandidat = Array.from(document.querySelectorAll('[data-wysiwyg]'))
        .filter((wadah) => !wadah.__wysiwyg)

    if (kandidat.length === 0) return

    // Editor disiapkan saat ia tampil, bukan seluruh halaman sekaligus.
    // Halaman kurasi memuat dua editor per soal (pertanyaan + pembahasan) dan
    // hanya satu soal yang tampil pada satu waktu, jadi menyala semua di awal
    // hanya membuang waktu untuk editor yang sedang tersembunyi. Nilai editor
    // yang belum tersentuh tetap dibaca dari input tersembunyinya oleh form.
    if (!('IntersectionObserver' in window)) {
        kandidat.forEach((wadah) => inisialisasiWysiwyg(wadah))

        return
    }

    const pengamat = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return

            pengamat.unobserve(entry.target)
            inisialisasiWysiwyg(entry.target)
        })
    })

    kandidat.forEach((wadah) => pengamat.observe(wadah))
}
