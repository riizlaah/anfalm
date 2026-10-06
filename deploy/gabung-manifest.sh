#!/usr/bin/env bash
#
# Menyusun ulang keadaan server sesudah satu run sinkron.sh, lalu menuliskannya
# sebagai manifest berikutnya — misalnya deploy/build/manifest-akhir.txt.
#
# Manifest hasilnya bukan salinan mentah manifest keinginan, sebab keduanya
# menceritakan hal yang berbeda: manifest keinginan menceritakan isi paket,
# manifest akhir menceritakan isi server. Aturannya:
#
#   - berkas yang berhasil terkirim memakai hash terkini;
#   - berkas yang berubah tetapi gagal diunggah mempertahankan hash lamanya,
#     karena di server masih ada isinya yang lama;
#   - berkas baru yang gagal diunggah tidak dicatat sama sekali, supaya run
#     berikutnya mengunggahnya lagi;
#   - berkas usang tetap dicatat selama pembuangannya belum berhasil, supaya
#     `--bersih` berikutnya masih menemukannya.
#
# Tanpa aturan ini, satu berkas gagal memaksa run berikutnya mengunggah ulang
# seluruh isi paket, dan berkas usang terlupakan selamanya dari daftar buang.
#
# Pemakaian : bash deploy/gabung-manifest.sh <lama> <baru> <terkirim> <terbuang> <ya|tidak> <keluaran>
#               <lama>       manifest sebelum run — boleh tidak ada
#               <baru>       manifest keinginan hasil tahap perakitan
#               <terkirim>   path yang berhasil diunggah, satu per baris
#               <terbuang>   path yang berhasil dibuang, satu per baris
#               <ya|tidak>   apakah fase pembuangan dijalankan pada run ini
#               <keluaran>   tujuan ditulisnya manifest berikutnya

set -euo pipefail

# Kolasi C diwajibkan sejak awal: sinkron.sh memeriksa jejak dengan
# `LC_ALL=C sort -c`. Manifest yang diurut di bawah kolasi lain akan ditolak
# pemeriksaan itu, dan pesan pemulihannya menyuruh file dihapus — full unggah,
# kebalikan persis dari tujuan berkas ini.
export LC_ALL=C

gagal() {
    printf '\n[GAGAL] %s\n' "$*" >&2
    exit 1
}

[ "$#" -eq 6 ] ||
    gagal "pemakaian: bash deploy/gabung-manifest.sh <lama> <baru> <terkirim> <terbuang> <ya|tidak> <keluaran>"

LAMA="$1"
BARU="$2"
TERKIRIM="$3"
TERBUANG="$4"
HAPUS="$5"
KELUARAN="$6"

case "$HAPUS" in
    ya|tidak) ;;
    *) gagal "argumen kelima harus 'ya' atau 'tidak', bukan: $HAPUS" ;;
esac

[ -f "$BARU" ] || gagal "manifest keinginan tidak ada: $BARU"
[ -s "$BARU" ] || gagal "manifest keinginan kosong: $BARU"

TUJUAN_DIR="$(dirname "$KELUARAN")"
[ -d "$TUJUAN_DIR" ] || gagal "folder tujuan tidak ada: $TUJUAN_DIR"

# Tiga berkas pendukung boleh tidak ada pada run pertama atau pada run tanpa
# pembuangan; kosong adalah jawaban yang benar untuk keduanya.
[ -f "$LAMA" ] || LAMA=/dev/null
[ -f "$TERKIRIM" ] || TERKIRIM=/dev/null
[ -f "$TERBUANG" ] || TERBUANG=/dev/null

MASUKAN="$(mktemp "${TMPDIR:-/tmp}/gabung-masukan.XXXXXX")"
SISIR="$(mktemp "$TUJUAN_DIR/.gabung-manifest.XXXXXX")"
trap 'rm -f "$MASUKAN" "$SISIR"' EXIT

# Keempat sumber digabung jadi satu berkas dengan penanda peran di tiap baris.
# awk tidak memiliki penanda argumen bawaan yang portabel, dan membandingkan
# FILENAME berbahaya bila dua jalur sama — misalnya ketiganya /dev/null.
{
    awk '{ printf "K\t%s\n", $0 }' "$TERKIRIM"
    awk '{ printf "B\t%s\n", $0 }' "$TERBUANG"
    awk '{ printf "L\t%s\n", $0 }' "$LAMA"
    awk '{ printf "N\t%s\n", $0 }' "$BARU"
} > "$MASUKAN"

awk -v hapus="$HAPUS" '
    # K = path terkirim, B = path terbuang — keduanya murni path tanpa ./
    $1 == "K" { kirim[substr($0, 3)] = 1; next }
    $1 == "B" { buang[substr($0, 3)] = 1; next }

    # L = baris manifest lama, N = baris manifest keinginan
    $1 == "L" || $1 == "N" {
        baris = substr($0, 3)
        p = baris
        sub(/^[0-9a-f]+  /, "", p)
        sub(/^\.\//, "", p)
        if (p == "") next

        if ($1 == "L") { lamap[p] = 1; lama[p] = baris }
        else           { barup[p] = 1; baru[p] = baris }
        next
    }

    END {
        # 1. Seluruh isi paket yang diinginkan.
        for (p in barup) {
            if (p in kirim)      print baru[p]
            else if (p in lamap) print lama[p]
            # Baru, belum terkirim: sengaja tidak dicatat agar diunggah lagi.
        }

        # 2. Sisa jejak server yang tidak ada lagi di paket.
        for (p in lamap) {
            if (p in barup) continue
            if (hapus == "ya" && p in buang) continue
            print lama[p]
        }
    }
' "$MASUKAN" | LC_ALL=C sort > "$SISIR"

LC_ALL=C sort -c "$SISIR" ||
    gagal "hasil penggabungan tidak terurut kolasi C — jejak tidak jadi ditulis"

[ -s "$SISIR" ] || gagal "hasil penggabungan kosong — jejak tidak jadi ditulis"

printf '  manifest akhir: %s baris (%s)\n' \
    "$(wc -l < "$SISIR" | tr -d ' ')" "$KELUARAN"

# Tulis atomik: bila skrip terhenti di tengah, jejak lama tetap utuh dan run
# berikutnya kembali memakai keadaan yang sudah terbukti benar.
mv "$SISIR" "$KELUARAN"
