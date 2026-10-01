/**
 * Kompresi gambar sebelum Livewire mengunggahnya (DESIGN §3.3 butir 5).
 *
 * Livewire membaca `input.files` tepat di listener `change` miliknya sendiri,
 * sedangkan kompresi Canvas bersifat asinkron. Karena itu pengupayaan ini
 * memasang listener fase capture pada wadah — yang selalu berjalan sebelum
 * listener mana pun di elemen target — lalu menghentikan propagasi, melakukan
 * kompresi, mengganti daftar berkas, dan memancarkan ulang `change` agar
 * Livewire membaca berkas yang sudah dikompresi.
 */

/** Batas unggahan sesuai validasi server (500 KB). */
const BATAS_BAIT = 500 * 1024;

/**
 * Percobaan pengecilan berurutan; berhenti begitu hasilnya muat di batas.
 *
 * @var array{sisi: int, kualitas: float}[]
 */
const LANGKAH = [
    { sisi: 2000, kualitas: 0.85 },
    { sisi: 1600, kualitas: 0.78 },
    { sisi: 1280, kualitas: 0.7 },
    { sisi: 1024, kualitas: 0.62 },
    { sisi: 800, kualitas: 0.55 },
    { sisi: 640, kualitas: 0.5 },
    { sisi: 512, kualitas: 0.45 },
    { sisi: 400, kualitas: 0.4 },
    { sisi: 320, kualitas: 0.35 },
];

/**
 * Pasang perangkap kompresi pada satu input berkas di dalam wadah editor.
 *
 * @param {HTMLElement} wadah elemen bertanda `data-wysiwyg`
 * @param {HTMLInputElement} input input berkas `wire:model` milik komponen
 */
export function pasangKompresiGambar(wadah, input) {
    let menungguPancarUlang = false;

    wadah.addEventListener('change', async (acara) => {
        const sasaran = acara.target;
        if (!(sasaran instanceof HTMLInputElement)) return;
        if (sasaran.type !== 'file' || !sasaran.files?.length) return;

        // Pancaran kedua: berkasnya sudah hasil kompresi, biarkan Livewire
        // membacanya tanpa diganggu.
        if (menungguPancarUlang) {
            menungguPancarUlang = false;
            return;
        }

        // Berhenti di sini agar listener Livewire di elemen target tak sempat
        // membaca berkas yang masih mentah.
        acara.stopPropagation();

        const hasil = await kompresi(sasaran.files[0]);
        if (hasil) {
            const daftar = new DataTransfer();
            daftar.items.add(hasil);
            sasaran.files = daftar.files;
        }

        menungguPancarUlang = true;
        sasaran.dispatchEvent(new Event('change', { bubbles: true }));
        menungguPancarUlang = false;
    }, true);
}

/**
 * Kompres satu berkas menuju WebP.
 *
 * @param {File} berkas berkas hasil pilihan pengguna
 * @return {?Promise<File>} berkas pengganti, atau null bila mentah lebih baik
 */
async function kompresi(berkas) {
    if (!berkas.type.startsWith('image/')) return null;
    if (berkas.type === 'image/webp' && berkas.size <= BATAS_BAIT) return null;

    const url = URL.createObjectURL(berkas);

    try {
        const gambar = await muatGambar(url);
        if (!gambar.naturalWidth || !gambar.naturalHeight) return null;

        let terbaik = null;

        for (const langkah of LANGKAH) {
            // WebP lebih dulu; bila browser tak mendukung penukaran WebP,
            // `toBlob` menghasilkan null dan kita jatuh ke JPEG.
            const kandidat = await encode(gambar, langkah, 'image/webp')
                ?? await encode(gambar, langkah, 'image/jpeg');

            if (!kandidat) continue;
            if (!terbaik || kandidat.size < terbaik.size) terbaik = kandidat;
            if (kandidat.size <= BATAS_BAIT) break;
        }

        if (!terbaik) return null;
        // Jangan pernah memperberat: hasil kompresi dipakai hanya bila lebih
        // kecil, atau bila berkas mentahnya sendiri sudah melewati batas.
        if (terbaik.size >= berkas.size && berkas.size <= BATAS_BAIT) return null;

        return new File([terbaik], gantiNama(berkas.name, terbaik.type), {
            type: terbaik.type,
            lastModified: berkas.lastModified,
        });
    } catch {
        return null;
    } finally {
        URL.revokeObjectURL(url);
    }
}

/**
 * @param {string} url objek blob gambar
 * @return {Promise<HTMLImageElement>}
 */
function muatGambar(url) {
    return new Promise((selesai, gagal) => {
        const gambar = new Image();
        gambar.decoding = 'async';
        gambar.onload = () => selesai(gambar);
        gambar.onerror = gagal;
        gambar.src = url;
    });
}

/**
 * @param {HTMLImageElement} gambar
 * @param {{sisi: int, kualitas: float}} langkah
 * @param {string} format mime yang diminta
 * @return {Promise<?Blob>}
 */
async function encode(gambar, langkah, format) {
    const sisiTerbesar = Math.max(gambar.naturalWidth, gambar.naturalHeight);
    const skala = Math.min(1, langkah.sisi / sisiTerbesar);
    const lebar = Math.max(1, Math.round(gambar.naturalWidth * skala));
    const tinggi = Math.max(1, Math.round(gambar.naturalHeight * skala));

    const kanvas = document.createElement('canvas');
    kanvas.width = lebar;
    kanvas.height = tinggi;

    const konteks = kanvas.getContext('2d');
    if (format === 'image/jpeg') {
        // JPEG tak punya kanal alfa; latar transparan jadi hitam tanpa ini.
        konteks.fillStyle = '#ffffff';
        konteks.fillRect(0, 0, lebar, tinggi);
    }
    konteks.drawImage(gambar, 0, 0, lebar, tinggi);

    return new Promise((selesai) => kanvas.toBlob(selesai, format, langkah.kualitas));
}

/**
 * @param {string} nama berkas asal
 * @param {string} tipe mime hasil encode
 * @return {string} nama berkas dengan ekstensi yang cocok
 */
function gantiNama(nama, tipe) {
    const ekstensi = tipe === 'image/webp' ? '.webp' : '.jpg';
    const dasar = nama.replace(/\.[^.]+$/, '');
    return `${dasar}${ekstensi}`;
}
