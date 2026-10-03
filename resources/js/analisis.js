/**
 * Grafik halaman Analisis Kompetensi (DESIGN 3.9 butir 3–4).
 *
 * Modul ini hanya diimpor ketika halamannya punya kanvas bertanda
 * `data-grafik`, sehingga bobot Chart.js tidak ikut ke halaman lain.
 *
 * Ketiga grafik kini bersatuan sama: Skor IRT. Sebutan theta sengaja tidak
 * muncul di sini karena tidak pernah dibaca peserta (laporan: "stop info dump").
 */

import Chart from 'chart.js/auto'

/**
 * Warna tiap seri garis perkembangan per KD, berputar pada panjang daftar.
 * Dipilih agar tetap terbedakan satu sama lain pada latar putih dan tetap
 * terbaca ketika garisnya berdekatan — jumlah KD satu mapel bisa mencapai
 * belasan, jadi warna tunggal tidak lagi membedakan.
 *
 * @type {string[]}
 */
const WARNA_SERI = [
    '#2563eb', '#f59e0b', '#0e7490', '#f43f5e',
    '#10b981', '#7c3aed', '#ea580c', '#0891b2',
    '#65a30d', '#db2777', '#4f46e5', '#a16207',
]

const WARNA_TINTA = '#0f172a'
const WARNA_BIRU = '#2563eb'
const WARNA_TEAL = '#0e7490'

/**
 * Lebar kanvas minimum agar nama mapel penuh masih muat sebagai label sumbu
 * radar. Panjang sisi kiri "Pendidikan Pancasila dan Kewarganegaraan" saja
 * sudah melebihi lebar kanvas 360px, sehingga ujung labelnya terpotong tepat di
 * tepi kanvas. Di bawah ambang ini label ditukar dengan kode mapel (mis. PPKN),
 * sementara nama lengkap tetap terbaca lewat tooltip.
 */
const LEBAR_LABEL_PENUH = 520

/** Opsi yang dipakai ketiga grafik: kotaknya menyesuaikan kontainer `h-64`. */
const OPSI_DASAR = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
    },
}

/**
 * Render seluruh grafik analisis berdasarkan payload `#grafik-analisis`.
 */
export function mulaiGrafikAnalisis() {
    const wadah = document.getElementById('grafik-analisis')
    if (!wadah) return

    /** @type {{radar: {labels: string[], singkat?: string[], skor: (number|null)[], batas: number[]}, garis: {labels: string[], seri: {kode: string, nilai: (number|null)[]}[], batas: number[]}, riwayat: {labels: string[], skor: number[], batas: number[]}}} */
    const data = JSON.parse(wadah.textContent)

    const pasang = (nama, pembuat) => {
        const kanvas = document.querySelector(`[data-grafik="${nama}"]`)
        if (!kanvas) return

        if (data[nama]?.labels?.length) {
            pembuat(kanvas, data[nama])
            return
        }

        // Tanpa label, grafiknya kosong dan hanya membingungkan — sembunyikan
        // seluruh figure-nya.
        kanvas.closest('figure')?.setAttribute('hidden', '')
    }

    pasang('radar', grafikRadar)
    pasang('garis', grafikGaris)
    pasang('riwayat', grafikRiwayat)
}

/**
 * Perbandingan skor IRT antar mapel (3.9 butir 3).
 *
 * Label sumbu memakai `singkat` (kode mapel) begitu kanvas terlalu sempit bagi
 * nama penuh, supaya labelnya tidak terpotong di tepi kanvas. `data.labels`
 * tetap berisi nama lengkap dan dipakai tooltip, jadi informasinya tidak hilang.
 *
 * `batas` mengunci rentang sumbu pada skala pelaporan akun. Tanpa itu Chart.js
 * menyesuaikan radar pada rentang data yang tampil, sehingga selisih tipis
 * antar mapel ikut terlihat selebar selisih yang lebar.
 *
 * @param {HTMLCanvasElement} kanvas
 * @param {{labels: string[], singkat?: string[], skor: (number|null)[], batas: number[]}} data
 */
function grafikRadar(kanvas, data) {
    const singkat = data.singkat ?? data.labels
    let pendek = kanvas.clientWidth < LEBAR_LABEL_PENUH

    const chart = new Chart(kanvas, {
        type: 'radar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Skor IRT',
                data: data.skor,
                backgroundColor: 'rgba(37, 99, 235, 0.16)',
                borderColor: WARNA_BIRU,
                pointBackgroundColor: WARNA_BIRU,
                spanGaps: true,
            }],
        },
        options: {
            ...OPSI_DASAR,
            scales: {
                r: {
                    min: data.batas[0],
                    max: data.batas[1],
                    ticks: { backdropColor: 'transparent', color: WARNA_TINTA },
                    pointLabels: {
                        callback: (label, index) => (pendek ? singkat[index] ?? label : label),
                    },
                },
            },
        },
    })

    // Kanvas bisa melewati ambang saat layar diputar atau ukuran panel berubah;
    // tanpa pengamat ini labelnya terkunci pada lebar saat dirender pertama
    // kali. `chart.update()` membangun ulang label sumbu.
    const sesuaikan = () => {
        const baru = kanvas.clientWidth < LEBAR_LABEL_PENUH
        if (baru === pendek) return
        pendek = baru
        chart.update()
    }

    if (typeof ResizeObserver !== 'undefined') {
        new ResizeObserver(sesuaikan).observe(kanvas)
    }
}

/**
 * Perkembangan skor IRT tiap kompetensi dasar pada satu mapel (3.9 butir 3).
 *
 * Menggantikan grafik batang level. Batang menjawab "kini seberapa tinggi";
 * pertanyaan yang sebenarnya muncul dari peserta adalah "bagaimana ia naik",
 * dan `tracking_kompetensi` tidak menyimpan riwayatnya — hanya satu baris per
 * KD yang ditulis ulang tiap percobaan ditutup. Deretnya dipulihkan server-side
 * dari `riwayat_pengerjaan` dan tiap titik adalah penghitungan ulang pada batas
 * tanggal, jadi titik terakhirnya identik dengan theta pada kartu KD.
 *
 * Sumbu-y dikunci pada rentang skala pelaporan akun lewat `batas`, sama seperti
 * radar dan riwayat tryout: tanpa itu Chart.js menyesuaikan jangkauannya pada
 * data yang tampil, lalu kenaikan kecil antar tanggal terlihat seperti lompatan.
 *
 * @param {HTMLCanvasElement} kanvas
 * @param {{labels: string[], seri: {kode: string, nilai: (number|null)[]}[], batas: number[]}} data
 */
function grafikGaris(kanvas, data) {
    new Chart(kanvas, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: data.seri.map((baris, indeks) => ({
                label: baris.kode,
                data: baris.nilai,
                borderColor: WARNA_SERI[indeks % WARNA_SERI.length],
                backgroundColor: WARNA_SERI[indeks % WARNA_SERI.length],
                pointRadius: 3,
                pointHoverRadius: 5,
                // Tanpa `tension`: tiap titik adalah hasil penghitungan pada
                // tanggal tertentu, dan lengkung kubik di antaranya akan
                // menggambar nilai yang tak pernah ada.
                spanGaps: true,
                fill: false,
            })),
        },
        options: {
            ...OPSI_DASAR,
            scales: {
                y: { min: data.batas[0], max: data.batas[1], ticks: { color: WARNA_TINTA } },
                x: { ticks: { color: WARNA_TINTA, maxRotation: 45, minRotation: 0 } },
            },
            plugins: {
                // Legenda di sini wajib: bedanya dengan grafik lain, satu
                // sumbu-x dipakai belasan seri sekaligus, sehingga warna adalah
                // satu-satunya pembeda. Kode KD — bukan deskripsi penuh — yang
                // dipakai, karena legenda belasan baris tidak muat di 360px.
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        boxWidth: 10,
                        boxHeight: 10,
                        usePointStyle: true,
                        color: WARNA_TINTA,
                        font: { size: 11 },
                    },
                },
            },
        },
    })
}

/**
 * Perkembangan skor IRT tiap tryout dari waktu ke waktu (3.9 butir 4).
 *
 * Seperti radar, sumbu-y dikunci pada rentang skala pelaporan akun lewat
 * `batas`, bukan dibiarkan mengikuti data — grafik riwayat yang melayang di
 * sekitar nilai tertingginya membuat kenaikan kecil terlihat seperti lompatan.
 *
 * @param {HTMLCanvasElement} kanvas
 * @param {{labels: string[], skor: number[], batas: number[]}} data
 */
function grafikRiwayat(kanvas, data) {
    new Chart(kanvas, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Skor IRT',
                data: data.skor,
                borderColor: WARNA_TEAL,
                backgroundColor: 'rgba(14, 116, 144, 0.15)',
                fill: true,
                tension: 0.25,
                spanGaps: true,
            }],
        },
        options: {
            ...OPSI_DASAR,
            scales: {
                y: { min: data.batas[0], max: data.batas[1], ticks: { color: WARNA_TINTA } },
                x: { ticks: { color: WARNA_TINTA, maxRotation: 45 } },
            },
        },
    })
}
