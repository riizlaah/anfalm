#!/usr/bin/env bash
#
# Merakit deploy/produksi.sql — satu berkas yang diimpor lewat phpMyAdmin.
# Isinya: skema seluruh tabel, data master (mapel, kompetensi dasar, bank soal,
# paket tryout), baris migrations, dan akun admin tunggal.
#
# Tidak ada satu pun data peserta uji yang ikut: riwayat, hasil tryout,
# tracking, percobaan masuk, dan akun selain id=1 dibuang.
#
# Pemakaian : bash deploy/basis-data.sh [sandi-admin]
#             sandi diacak bila tidak diberikan, dan selalu dicetak di akhir.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="$ROOT/deploy/produksi.sql"
TMP="$ROOT/deploy/build"
SCHEMA="$TMP/schema.sql"
DATA="$TMP/data.sql"
STAMP="$(date +%Y-%m-%d\ %H:%M)"

SANDI="${1:-$(php -r 'echo bin2hex(random_bytes(12));')}"

gagal() {
    printf '\n[GAGAL] %s\n' "$*" >&2
    exit 1
}

mkdir -p "$TMP"

# Kredensial database dev dibaca dari .env tanpa menampilkannya.
set -a
# shellcheck disable=SC1091
source <(grep -E '^DB_(HOST|PORT|DATABASE|USERNAME|PASSWORD)=' "$ROOT/.env")
set +a
export MYSQL_PWD="${DB_PASSWORD:-}"

[ -n "${DB_DATABASE:-}" ] || gagal "DB_DATABASE tidak ditemukan di .env"

printf '== 1/3 Skema ==\n'
php artisan schema:dump --path="$SCHEMA" --silent
[ -s "$SCHEMA" ] || gagal "schema:dump tidak menghasilkan berkas"

printf '== 2/3 Data master ==\n'
# Satu baris per pernyataan supaya hasilnya mudah dibaca dan tidak kena
# max_allowed_packet. --complete-insert mencantumkan nama kolom.
mysqldump \
    --no-create-info \
    --complete-insert \
    --skip-extended-insert \
    --hex-blob \
    --single-transaction \
    --no-tablespaces \
    -h "${DB_HOST:-127.0.0.1}" \
    -P "${DB_PORT:-3306}" \
    -u "$DB_USERNAME" \
    "$DB_DATABASE" \
    mapel kompetensi_dasar soal opsi_jawaban pernyataan_kategori \
    paket_soal detail_paket_soal paket_tryout paket_tryout_mapel \
    > "$DATA"

# Akun selain id=1 adalah data uji, tetapi id=1 wajib ada karena seluruh
# konten memakai created_by = 1.
mysqldump \
    --no-create-info \
    --complete-insert \
    --skip-extended-insert \
    --single-transaction \
    --no-tablespaces \
    --where='id=1' \
    -h "${DB_HOST:-127.0.0.1}" \
    -P "${DB_PORT:-3306}" \
    -u "$DB_USERNAME" \
    "$DB_DATABASE" \
    users > "$TMP/users.sql"

HASH="$(php -r 'echo password_hash($argv[1], PASSWORD_BCRYPT, ["cost" => 12]);' "$SANDI")"

printf '== 3/3 Menyusun ==\n'
{
    cat <<BANNER
-- ANFALM — paket database produksi
-- Dibuat: $STAMP
-- Isi   : skema seluruh tabel + data master + akun admin tunggal
-- Impor : phpMyAdmin InfinityFree -> pilih database -> Import -> pilih berkas ini
--
-- Akun admin : admin@anfalm.test
-- Sandi      : $SANDI
-- Ganti sandi segera setelah masuk pertama kali (Profil -> Ganti Kata Sandi).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

BANNER
    cat "$SCHEMA"
    echo
    echo '-- Data master (tanpa riwayat peserta uji).'
    cat "$DATA"
    echo
    echo '-- Akun admin tunggal; seluruh konten memakai created_by = 1.'
    cat "$TMP/users.sql"
    cat <<KUNCI

-- Sandi akun admin produksi.
UPDATE \`users\`
   SET \`password\`='$HASH',
       \`session_token\`=NULL,
       \`remember_token\`=NULL,
       \`email_verified_at\`=NULL,
       \`updated_at\`=NOW()
 WHERE \`id\`=1;

SET FOREIGN_KEY_CHECKS=1;
KUNCI
} > "$OUT"

rm -f "$SCHEMA" "$DATA" "$TMP/users.sql"

printf '\nSelesai: %s (%s)\n' "$OUT" "$(du -h "$OUT" | cut -f1)"
printf '\n>>> SANDI ADMIN: %s\n' "$SANDI"
printf '>>> Simpan sekarang — sandi ini hanya dicetak sekali.\n\n'
