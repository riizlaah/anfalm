#!/usr/bin/env bash
#
# Menyalin isi proyek ke folder staging, menyisakan hanya yang diperlukan di
# server produksi.
#
# Dipisah dari release.sh supaya aturannya bisa diuji langsung terhadap pohon
# uji kecil — tanpa npm, composer, maupun jaringan. Pemisahan ini lahir dari
# cacat nyata pada paket sebelumnya: `storage/framework/{cache,sessions,views}`
# ikut terkemas dalam keadaan kosong melompong, sebab pola `folder/*` ikut
# membuang `.gitignore` di dalamnya. Direktori kosong di zip sering dilewati
# File Manager saat ekstraksi, dan begitu folder itu hilang Laravel menolak
# menulis compiled view, sesi, dan cache.
#
# Pemakaian : bash deploy/staging.sh <sumber> <tujuan>
#             dipanggil release.sh — bukan untuk dijalankan sendiri.

set -euo pipefail

gagal() {
    printf '\n[GAGAL] %s\n' "$*" >&2
    exit 1
}

SUMBER="${1:-}"
TUJUAN="${2:-}"

[ -n "$SUMBER" ] && [ -n "$TUJUAN" ] ||
    gagal "pemakaian: bash deploy/staging.sh <sumber> <tujuan>"

[ -d "$SUMBER" ] || gagal "folder sumber tidak ada: $SUMBER"

rm -rf "$TUJUAN"
mkdir -p "$TUJUAN"

# Daftar ini satu-satunya sumber kebenaran tentang isi paket; pengecualian baru
# ditaruh di sini, bukan di release.sh. Bentuk polanya berbeda maksudnya:
# `nama/` membuang folder beserta isinya, `nama/*` menyisakan folder itu sendiri
# (yang kemudian diisi ulang di blok berikutnya), dan `/nama` menambatkan pola
# ke akar proyek supaya tidak ikut membuang berkas serupa di dalam vendor.
#
# Yang dibuang: jejak mesin pengembang (git, editor, konfigurasi agen AI,
# berkas pengujian), berkas yang hanya dipakai saat merakit aset (npm dan
# Vite sudah selesai berjalan sebelum langkah ini), serta sisa runtime lama
# yang tidak boleh ikut menempel ke server.
rsync -a \
    --exclude='.git/' \
    --exclude='node_modules/' \
    --exclude='tests/' \
    --exclude='deploy/' \
    --exclude='.ai/' \
    --exclude='.agents/' \
    --exclude='.claude/' \
    --exclude='.opencode/' \
    --exclude='.playwright-mcp/' \
    --exclude='*.spec.ts' \
    --exclude='specs/' \
    --exclude='smoke/' \
    --exclude='contoh-pg-kategori.png' \
    --exclude='public/storage' \
    --exclude='.env' \
    --exclude='.env.production' \
    --exclude='.env.backup' \
    --exclude='auth.json' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='storage/framework/testing/' \
    --exclude='bootstrap/cache/*.php' \
    --exclude='/AGENTS.md' \
    --exclude='/CLAUDE.md' \
    --exclude='/DESIGN.md' \
    --exclude='/PHASES.md' \
    --exclude='/README.md' \
    --exclude='/REPORT_N_SUGGEST.md' \
    --exclude='/boost.json' \
    --exclude='/.mcp.json' \
    --exclude='/opencode.json' \
    --exclude='/package.json' \
    --exclude='/package-lock.json' \
    --exclude='/vite.config.js' \
    --exclude='/.npmrc' \
    --exclude='/.editorconfig' \
    --exclude='/.gitattributes' \
    --exclude='/.gitignore' \
    --exclude='/phpunit.xml' \
    --exclude='/database/' \
    --exclude='resources/js/' \
    --exclude='resources/css/' \
    "$SUMBER/" "$TUJUAN/"

# Empat direktori runtime tadi sengaja dikosongkan oleh pola di atas, lalu
# diisi ulang dengan `.gitignore` bawaan Laravel. Satu berkas kecil itu sudah
# cukup: yang dibutuhkan ekstraksi adalah tanda bahwa direktorinya memang ada,
# isinya tetap dibersihkan tiap kali dan ditempati sendiri oleh Laravel.
for dir in storage/logs storage/framework/cache storage/framework/sessions storage/framework/views; do
    mkdir -p "$TUJUAN/$dir"
    if [ -f "$SUMBER/$dir/.gitignore" ]; then
        cp "$SUMBER/$dir/.gitignore" "$TUJUAN/$dir/.gitignore"
    fi
done

printf '  staging: %s berkas\n' "$(find "$TUJUAN" -type f | wc -l | tr -d ' ')"
