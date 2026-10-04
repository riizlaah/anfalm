#!/usr/bin/env bash
#
# Merakit paket rilis untuk InfinityFree. Hosting itu tidak punya SSH, jadi
# segala hal yang biasanya dikerjakan composer/artisan di server (composer
# install, membuang dependensi pengujian, mengoptimalkan autoloader) harus
# selesai di sini sebelum berkas dikirim.
#
# Pemakaian : bash deploy/release.sh
# Hasil     : deploy/build/anfalm-<waktu>.zip
#             diunggah lewat File Manager ke htdocs/ lalu ditekan [Ekstrak]

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD="$ROOT/deploy/build"
STAGE="$BUILD/staging"
STAMP="$(date +%Y%m%d-%H%M)"
ZIP="$BUILD/anfalm-$STAMP.zip"

# InfinityFree menghapus file PHP yang melewati 1 MB. Dipakai satuan desimal
# (1.000.000 byte) agar tetap aman walau host menghitung MB sebagai 1.000.000.
BATAS_PHP=1000000
# Batas berkas biasa di hosting itu: 10 MB.
BATAS_BIASA=10000000

gagal() {
    printf '\n[GAGAL] %s\n' "$*" >&2
    exit 1
}

catat() {
    printf '  %s\n' "$*"
}

cd "$ROOT"

printf '\n== 1/5 Aset frontend ==\n'
npm run build

printf '\n== 2/5 Menyalin berkas ke staging ==\n'
rm -rf "$STAGE"
mkdir -p "$STAGE"
# Folder yang tidak dibutuhkan server dibuang supaya hemat inode (batas ±30.000
# berkas). Pola /* dipakai agar folder dan .gitignore-nya tetap ikut.
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
    --exclude='.spec.ts' \
    --exclude='specs/' \
    --exclude='smoke/' \
    --exclude='seed.spec.ts' \
    --exclude='contoh-pg-kategori.png' \
    --exclude='public/storage' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='bootstrap/cache/*.php' \
    "$ROOT/" "$STAGE/"

# InfinityFree tidak mendukung symlink, jadi pengganti public/storage ini
# diletakkan langsung di root paket.
cp "$ROOT/deploy/htdocs.htaccess" "$STAGE/.htaccess"

printf '\n== 3/5 Dependensi produksi ==\n'
composer install --no-dev --optimize-autoloader --no-interaction --working-dir="$STAGE"

printf '\n== 4/5 Pemeriksaan ==\n'

[ -f "$STAGE/public/build/manifest.json" ] ||
    gagal "aset Vite tidak ada di public/build — jalankan npm run build"

symlink="$(find "$STAGE" -type l)"
[ -z "$symlink" ] || gagal "masih ada symlink yang dibawa (server tak mendukungnya): $symlink"

[ ! -d "$STAGE/tests" ] || gagal "folder tests ikut terbawa"
[ ! -e "$STAGE/.env" ] || gagal ".env lokal ikut terbawa — berisi kredensial"

while IFS= read -r -d '' berkas; do
    ukuran="$(stat -c%s "$berkas")"
    [ "$ukuran" -lt "$BATAS_PHP" ] ||
        gagal "file PHP melewati $BATAS_PHP byte (dihapus otomatis host): ${berkas#"$STAGE"/} = $ukuran"
done < <(find "$STAGE" -name '*.php' -size +900k -print0)

while IFS= read -r -d '' berkas; do
    ukuran="$(stat -c%s "$berkas")"
    [ "$ukuran" -lt "$BATAS_BIASA" ] ||
        gagal "berkas melewati 10 MB: ${berkas#"$STAGE"/} = $ukuran"
done < <(find "$STAGE" -type f -size +9000k -print0)

catat "file PHP terbesar : $(find "$STAGE" -name '*.php' -printf '%s %p\n' | sort -rn | head -1 | cut -d' ' -f1) byte"
catat "jumlah berkas     : $(find "$STAGE" -type f | wc -l)"

printf '\n== 5/5 Mengemas ==\n'
rm -f "$ZIP"
( cd "$STAGE" && zip -q -r -X "$ZIP" . )

catat "arsip : $ZIP"
catat "ukuran: $(du -h "$ZIP" | cut -f1)"

printf '\nSelesai.\n'
