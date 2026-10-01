/**
 * Ekspresi KaTeX dalam konten soal.
 *
 * Sumber selalu disimpan sebagai mentah — `\( ... \)` untuk inline dan
 * `\[ ... \]` untuk display (DESIGN §3.3, §4.3 langkah 1). Dua pekerjaan di
 * sini:
 *
 * 1. `bungkusRumus()` — membungkus setiap ekspresi dengan tag khusus
 *    `<span class="katex-inline">` / `<span class="katex-display">` sesuai
 *    DESIGN §4.3 langkah 3, dipanggil sekali saat HTML editor diserahkan ke
 *    form. Idempoten: ekspresi yang sudah berbungkus dilewati.
 * 2. `renderRumusDi()` — me-render ekspresi jadi KaTeX di dalam sebuah elemen.
 *    Bila sintaksnya salah, teks mentahnya dipertahankan sebagai fallback
 *    (DESIGN §6.13).
 */

import katex from 'katex'
import 'katex/dist/katex.min.css'

/**
 * @param {string} html hasil `editor.getHTML()`
 * @returns {string} html dengan setiap ekspresi dibungkus tag khusus
 */
export function bungkusRumus(html) {
    if (!html || !html.includes('\\(') && !html.includes('\\[')) {
        return html
    }

    // Template bersifat inert: memasukkan HTML ke dalamnya tidak mengeksekusi
    // skrip apa pun, sehingga aman meski isi HTML berasal dari pengguna.
    const template = document.createElement('template')
    template.innerHTML = html

    const perambat = document.createTreeWalker(template.content, NodeFilter.SHOW_TEXT)
    const antrean = []
    let teks
    while ((teks = perambat.nextNode())) {
        if (teks.nodeValue.includes('\\(') || teks.nodeValue.includes('\\[')) {
            antrean.push(teks)
        }
    }

    antrean.forEach((t) => bungkusTeks(t))

    return template.innerHTML
}

/**
 * @param {Node} teks simpul teks yang mungkin memuat satu atau lebih ekspresi
 */
function bungkusTeks(teks) {
    const induk = teks.parentElement
    if (induk?.matches?.('span.katex-inline, span.katex-display')) {
        return
    }

    const hasil = pecahEkspresi(teks.nodeValue)
    if (hasil === null) return

    const frag = document.createDocumentFragment()
    for (const potongan of hasil) {
        if (potongan.utuh === undefined) {
            frag.append(document.createTextNode(potongan.teks))
            continue
        }

        // Delimiternya ikut disimpan di dalam tag khusus, bukan dibuang:
        // editor memuatnya kembali sebagai kode mentah yang bisa diedit, dan
        // peserta tetap punya sumber yang bisa di-render ulang (§4.3 langkah 3).
        const pembungkus = document.createElement('span')
        pembungkus.className = potongan.display ? 'katex-display' : 'katex-inline'
        pembungkus.textContent = potongan.utuh
        frag.append(pembungkus)
    }

    teks.parentNode.replaceChild(frag, teks)
}

/**
 * @param {string} nilai teks sumber
 * @returns {?Array<{teks?: string, utuh?: string, sumber?: string, display: boolean}>}
 */
function pecahEkspresi(nilai) {
    const pola = /\\\(([\s\S]+?)\\\)|\\\[(.+?)\\\]/g
    const potongan = []
    let terakhir = 0
    let cocok

    while ((cocok = pola.exec(nilai)) !== null) {
        if (cocok.index > terakhir) {
            potongan.push({ teks: nilai.slice(terakhir, cocok.index) })
        }

        potongan.push({
            utuh: cocok[0],
            sumber: cocok[1] ?? cocok[2],
            display: cocok[1] === undefined,
        })
        terakhir = cocok.index + cocok[0].length
    }

    if (terakhir === 0) return null

    if (terakhir < nilai.length) {
        potongan.push({ teks: nilai.slice(terakhir) })
    }

    return potongan
}

/**
 * Render seluruh ekspresi KaTeX di dalam elemen, menggantikan teks mentahnya.
 *
 * @param {Element} el kontainer yang akan dirender
 * @param {boolean} tampilkanGalat tampilkan pesan galat (dipakai preview editor)
 */
export function renderRumusDi(el, tampilkanGalat = false) {
    if (!el) return

    const perambat = document.createTreeWalker(el, NodeFilter.SHOW_TEXT)
    const antrean = []
    let teks
    while ((teks = perambat.nextNode())) {
        if (teks.nodeValue.includes('\\(') || teks.nodeValue.includes('\\[')) {
            antrean.push(teks)
        }
    }

    antrean.forEach((simpul) => renderTeks(simpul, tampilkanGalat))
}

function renderTeks(simpul, tampilkanGalat) {
    const potongan = pecahEkspresi(simpul.nodeValue)
    if (potongan === null) return

    const frag = document.createDocumentFragment()
    for (const bagi of potongan) {
        if (bagi.utuh === undefined) {
            frag.append(document.createTextNode(bagi.teks))
            continue
        }

        frag.append(buatHasilRender(bagi, tampilkanGalat))
    }

    simpul.parentNode.replaceChild(frag, simpul)
}

/**
 * @param {{utuh: string, sumber: string, display: boolean}} bagi potongan ekspresi
 * @param {boolean} tampilkanGalat
 */
function buatHasilRender(bagi, tampilkanGalat) {
    const wadah = document.createElement(bagi.display ? 'div' : 'span')
    if (bagi.display) wadah.className = 'katex-display'

    try {
        katex.render(bagi.sumber, wadah, { displayMode: bagi.display, throwOnError: true })
        return wadah
    } catch (galat) {
        // Fallback teks mentah (DESIGN §6.13): ekspresi tetap terbaca apa adanya.
        wadah.className = `${wadah.className} rumus-galat`.trim()
        wadah.textContent = bagi.utuh

        if (tampilkanGalat) {
            wadah.title = galat.message
            // Dipasang sebagai anak, bukan saudara: pada titik ini `wadah` masih
            // berada di dalam DocumentFragment, sehingga `.after()` akan no-op.
            const pesan = document.createElement('span')
            pesan.className = 'rumus-galat-pesan'
            pesan.textContent = galat.message
            wadah.append(pesan)
        }

        return wadah
    }
}
