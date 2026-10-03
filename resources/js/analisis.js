/**
 * Grafik halaman Analisis Kompetensi (DESIGN 3.9 butir 3–4).
 *
 * Modul ini hanya diimpor ketika halamannya punya kanvas bertanda
 * `data-grafik`, sehingga bobot Chart.js tidak ikut ke halaman lain.
 */

import Chart from 'chart.js/auto'

/**
 * Warna batang per level; urutan indeksnya sama dengan ordinal di
 * `KompetensiLevel::urut()`.
 *
 * @type {string[]}
 */
const WARNA_LEVEL = ['#94a3b8', '#f43f5e', '#f59e0b', '#0ea5e9', '#10b981']

/**
 * Nama singkat tiap level untuk label sumbu-y (nama lengkapnya terlalu panjang
 * dan sudah tampil di tabel maupun tooltip).
 *
 * @type {string[]}
 */
const LABEL_LEVEL = ['Belum', 'Bimbingan', 'Dasar', 'Menengah', 'Mahir']

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

    /** @type {{radar: {labels: string[], singkat?: string[], theta: (number|null)[]}, level: {labels: string[], nilai: number[], level: string[]}, riwayat: {labels: string[], theta: number[]}}} */
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
    pasang('level', grafikLevel)
    pasang('riwayat', grafikRiwayat)
}

/**
 * Perbandingan theta antar mapel (3.9 butir 3).
 *
 * Label sumbu memakai `singkat` (kode mapel) begitu kanvas terlalu sempit bagi
 * nama penuh, supaya labelnya tidak terpotong di tepi kanvas. `data.labels`
 * tetap berisi nama lengkap dan dipakai tooltip, jadi informasinya tidak hilang.
 *
 * @param {HTMLCanvasElement} kanvas
 * @param {{labels: string[], singkat?: string[], theta: (number|null)[]}} data
 */
function grafikRadar(kanvas, data) {
    const singkat = data.singkat ?? data.labels
    let pendek = kanvas.clientWidth < LEBAR_LABEL_PENUH

    const chart = new Chart(kanvas, {
        type: 'radar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Theta',
                data: data.theta,
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
                    suggestedMin: -3,
                    suggestedMax: 3,
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
 * Perbandingan level tiap KD pada satu mapel (3.9 butir 3).
 *
 * @param {HTMLCanvasElement} kanvas
 * @param {{labels: string[], nilai: number[], level: string[]}} data
 */
function grafikLevel(kanvas, data) {
    new Chart(kanvas, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Level',
                data: data.nilai,
                backgroundColor: data.nilai.map(
                    (nilai) => WARNA_LEVEL[nilai] ?? WARNA_LEVEL[0],
                ),
                borderRadius: 4,
            }],
        },
        options: {
            ...OPSI_DASAR,
            scales: {
                y: {
                    min: 0,
                    max: 4,
                    ticks: {
                        stepSize: 1,
                        color: WARNA_TINTA,
                        callback: (nilai) => LABEL_LEVEL[nilai] ?? '',
                    },
                },
                x: {
                    ticks: { color: WARNA_TINTA, maxRotation: 45, minRotation: 0 },
                },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: (item) => data.labels[item[0].dataIndex] ?? '',
                        label: (item) => data.level[item.dataIndex] ?? '',
                    },
                },
            },
        },
    })
}

/**
 * Perkembangan theta akhir tiap tryout dari waktu ke waktu (3.9 butir 4).
 *
 * @param {HTMLCanvasElement} kanvas
 * @param {{labels: string[], theta: number[]}} data
 */
function grafikRiwayat(kanvas, data) {
    new Chart(kanvas, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Theta akhir',
                data: data.theta,
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
                y: { suggestedMin: -3, suggestedMax: 3, ticks: { color: WARNA_TINTA } },
                x: { ticks: { color: WARNA_TINTA, maxRotation: 45 } },
            },
        },
    })
}
