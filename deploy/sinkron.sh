#!/usr/bin/env bash
#
# Menyelaraskan isi deploy/build/staging/ dengan htdocs di InfinityFree lewat
# curl (FTPS eksplisit), mengunggah hanya berkas yang berubah.
#
# Perubahan diputuskan dari checksum lokal (sha256), bukan dari waktu ubah di
# server. Hosting bisa saja tidak menyimpan waktu yang dikirim klien; bila itu
# terjadi, pemilihan berbasis waktu akan mengunggah ulang seluruh isi paket
# setiap kali dan skrip ini kehilangan gunanya.
#
# Pemakaian:
#   bash deploy/sinkron.sh            unggah berkas baru/berubah
#   bash deploy/sinkron.sh --bersih   plus buang berkas yang sudah tidak ada
#                                     di build; wajib disertai --ya
#   bash deploy/sinkron.sh --uji      buktikan TLS, uji kredensial, lalu
#                                     unggah-balik sebuah berkas percobaan
#                                     tanpa menyentuh berkas aplikasi
#
# Kredensial: salin deploy/ftp.example.ini menjadi deploy/ftp.ini lalu isi.
# Berkas itu di-gitignore dan tidak boleh ikut ter-commit.
#
# Cadangan bila FTP bermasalah: unggah zip hasil deploy/release.sh lewat File
# Manager lalu tekan [Ekstrak]. Zip itu memang ikut dirakit pada tiap kali
# skrip ini dijalankan.

set -euo pipefail
# Diperlukan agar urutan `sort` dan `comm` di bawah selalu memakai aturan yang
# sama, apa pun locale lingkungan pengguna.
export LC_ALL=C

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD="$ROOT/deploy/build"
STAGE="$BUILD/staging"
KRED="${FTP_INI:-$ROOT/deploy/ftp.ini}"

MANIFEST_BARU="$BUILD/manifest.txt"
MANIFEST_LAMA="$BUILD/manifest-akhir.txt"
DAFTAR_UNGGAH="$BUILD/daftar-unggah.txt"
DAFTAR_HAPUS="$BUILD/HAPUS.txt"
DAFTAR_PATCH="$BUILD/daftar-patch.txt"
DAFTAR_GAGAL="$BUILD/GAGAL-unggah.txt"
DAFTAR_GAGAL_HAPUS="$BUILD/GAGAL-hapus.txt"
PATCH="$BUILD/patch"

# Berkas konfigurasi curl berisi satu baris `user = "..."`. Ia dibuat
# bertopeng 600 dan dibuang lewat trap pada setiap jalan keluar, termasuk saat
# gagal di tengah jalan. Alasannya: `ps` memperlihatkan apa pun yang tertulis
# di baris perintah, sedangkan berkas konfigurasi tidak.
CFG="$BUILD/.curl-kredensial-$$.conf"

MODE="sinkron"
BERSIH="tidak"
YA="tidak"

gagal() {
    printf '\n[GAGAL] %s\n' "$*" >&2
    exit 1
}

catat() {
    printf '  %s\n' "$*"
}

bantuan() {
    sed -n '2,24p' "${BASH_SOURCE[0]}" | sed 's/^#\{1\} \{0,1\}//'
}

bersihkan() {
    rm -f "$CFG" "$BUILD/uji-sinkron.txt" "$BUILD/uji-sinkron-balik.txt" "$BUILD/uji-curl.txt"
}

trap bersihkan EXIT

# ---------------------------------------------------------------------------
# 1. Pemeriksaan argumen dan kredensial (semuanya sebelum ada kerja apa pun)
# ---------------------------------------------------------------------------

for arg in "$@"; do
    case "$arg" in
        --uji) MODE="uji" ;;
        --bersih) BERSIH="ya" ;;
        --ya) YA="ya" ;;
        --bantuan | -h) bantuan; exit 0 ;;
        *) gagal "argumen tak dikenal: $arg (lihat --bantuan)" ;;
    esac
done

# `--bersih` membuang berkas produksi, jadi ia menuntut pernyataan niat yang
# kedua dan diuji sebelum koneksi apa pun dibuka.
[ "$BERSIH" = "tidak" ] || [ "$YA" = "ya" ] ||
    gagal "--bersih membuang berkas produksi yang sudah tidak ada di build; jalankan ulang dengan 'bash deploy/sinkron.sh --bersih --ya' bila itu memang dikehendaki"

command -v curl >/dev/null 2>&1 ||
    gagal "curl belum terpasang — misalnya 'sudo dnf install curl'"

[ -f "$KRED" ] ||
    gagal "kredensial tidak ada: salin deploy/ftp.example.ini ke deploy/ftp.ini lalu isi ($KRED)"

nilai() {
    local kunci="$1" bawaan="${2-}" baris
    baris="$(grep -E "^${kunci}=" "$KRED" 2>/dev/null | tr -d '\r' | tail -n 1 || true)"
    if [ -n "$baris" ]; then
        printf '%s' "${baris#*=}"
    else
        printf '%s' "$bawaan"
    fi
}

HOST="$(nilai HOST)"
PENGGUNA="$(nilai PENGGUNA)"
SANDI="$(nilai SANDI)"
REMOTE="$(nilai REMOTE /htdocs)"
PORT="$(nilai PORT 21)"
PARALEL="$(nilai PARALEL 4)"

[ -n "$HOST" ] && [ -n "$PENGGUNA" ] && [ -n "$SANDI" ] ||
    gagal "HOST, PENGGUNA, dan SANDI wajib terisi di $KRED"

case "$PARALEL" in
    '' | *[!0-9]*) gagal "PARALEL harus bilangan bulat positif di $KRED" ;;
esac
[ "$PARALEL" -gt 0 ] || gagal "PARALEL harus lebih besar dari nol"

AKAR="$REMOTE"
BASE="ftp://$HOST:$PORT$AKAR"

# Nilai dikutip mengikuti aturan berkas konfigurasi curl, bukan shell: balik
# dan kutip ganda perlu dielakkan supaya sandi aneh tetap terkirim utuh.
kutip_curl() {
    printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g'
}

tulis_config() {
    (
        umask 077
        printf 'user = "%s:%s"\n' "$(kutip_curl "$PENGGUNA")" "$(kutip_curl "$SANDI")" > "$CFG"
    )
    chmod 600 "$CFG"
}

tulis_config

export CFG BASE AKAR PATCH
export DAFTAR_GAGAL DAFTAR_GAGAL_HAPUS

# ---------------------------------------------------------------------------
# 2. Mode uji: bukti TLS, kredensial, lalu bolak-balik sebuah berkas
# ---------------------------------------------------------------------------

if [ "$MODE" = "uji" ]; then
    printf '\n== Mode uji koneksi ==\n'
    catat "tujuan : $BASE"
    catat "akun   : $PENGGUNA"

    verbose="$BUILD/uji-curl.txt"
    set +e
    curl -K "$CFG" --ssl-reqd --silent --show-error --verbose \
        --list-only -o /dev/null "$BASE/" 2> "$verbose"
    kode="$?"
    set -e

    # Koneksi yang berhasil saja tidak membuktikan apa-apa: klien bisa saja
    # jatuh ke koneksi polos. Yang dibutuhkan adalah `AUTH TLS` yang dijawab
    # `234` sebelum kredensial dipakai.
    grep -q 'AUTH TLS' "$verbose" ||
        { tail -n 20 "$verbose" >&2; gagal "curl tidak pernah mengirim AUTH TLS"; }
    grep -qE '< 234' "$verbose" ||
        { tail -n 20 "$verbose" >&2; gagal "AUTH TLS tidak diterima server (bukan 234) — TLS gagal"; }
    catat "TLS    : AUTH TLS dijawab 234"

    [ "$kode" -eq 0 ] ||
        { tail -n 20 "$verbose" >&2; gagal "kredensial atau akses ditolak (kode curl $kode)"; }
    catat "akses  : daftar isi terbaca"

    uji_lokal="$BUILD/uji-sinkron.txt"
    uji_balik="$BUILD/uji-sinkron-balik.txt"
    printf 'diunggah-pada=%s\n' "$(date -Is)" > "$uji_lokal"

    curl -K "$CFG" --ssl-reqd --silent --show-error --ftp-create-dirs \
        -T "$uji_lokal" "$BASE/.uji-sinkron.txt"
    curl -K "$CFG" --ssl-reqd --silent --show-error -o "$uji_balik" \
        "$BASE/.uji-sinkron.txt"
    cmp -s "$uji_lokal" "$uji_balik" ||
        gagal "isi berkas uji tidak sama setelah dibaca kembali — pindah data tidak utuh"
    catat "uji    : unggah + unduh cocok"

    curl -K "$CFG" --ssl-reqd --silent --show-error \
        --quote "DELE $AKAR/.uji-sinkron.txt" --list-only -o /dev/null "$BASE/"
    catat "uji    : berkas percobaan terhapus dari server"

    printf '\nSemua lulus. Tidak ada berkas aplikasi yang disentuh.\n'
    exit 0
fi

# ---------------------------------------------------------------------------
# 3. Merakit paket, lalu menimbang mana yang berubah
# ---------------------------------------------------------------------------

# Jejak sinkron sebelumnya adalah satu-satunya dasar pemilihan berkas. `comm`
# menolak masukan yang tidak terurut dengan keluaran kode 1, yang pada `set -e`
# mematikan skrip di tengah jalan tanpa pesan yang bisa ditindaklanjuti.
# Periksa di sini — sebelum merakit paket yang mahal — supaya kegagalannya jelas
# dan langkah pemulihannya tertulis.
if [ -f "$MANIFEST_LAMA" ]; then
    LC_ALL=C sort -c "$MANIFEST_LAMA" 2>/dev/null ||
        gagal "deploy/build/manifest-akhir.txt tidak terurut — kemungkinan rusak atau pernah diedit tangan. Hapus berkas itu lalu jalankan ulang; sinkron berikutnya akan mengunggah ulang seluruh isi paket sekali"
fi

printf '\n== Merakit paket rilis ==\n'
bash "$ROOT/deploy/release.sh"

printf '\n== Menghitung perubahan ==\n'
[ -d "$STAGE" ] || gagal "folder staging tidak ada setelah release.sh"

strip_hash() {
    sed 's/^[0-9a-f]\{64\}  //'
}

( cd "$STAGE" && find . -type f -print0 | sort -z | xargs -0 -r sha256sum ) | sort > "$MANIFEST_BARU"

if [ -f "$MANIFEST_LAMA" ]; then
    # Baris identik berarti tak berubah. Yang hanya muncul di build baru adalah
    # berkas baru atau berubah; yang path-nya hanya ada di build lama adalah
    # berkas usang.
    #
    # Dua perintah `comm` dijaga bersama: bila satu saja gagal, daftar
    # perubahan setengah jadi tidak boleh pernah dipakai untuk memutuskan apa
    # yang dikirim — apalagi untuk membuang berkas produksi.
    LOG_COMM="$BUILD/perbandingan.log"
    : > "$LOG_COMM"
    if ! comm -13 "$MANIFEST_LAMA" "$MANIFEST_BARU" 2>> "$LOG_COMM" > "$DAFTAR_UNGGAH" ||
        ! comm -23 <(strip_hash < "$MANIFEST_LAMA" | sort) \
            <(strip_hash < "$MANIFEST_BARU" | sort) 2>> "$LOG_COMM" > "$DAFTAR_HAPUS"
    then
        cat "$LOG_COMM" >&2 || true
        gagal "perbandingan manifest gagal — daftar perubahan tidak dapat dipercaya, jadi tidak ada berkas yang dikirim"
    fi
else
    sed 's/^[0-9a-f]\{64\}  //' "$MANIFEST_BARU" > "$DAFTAR_UNGGAH"
    : > "$DAFTAR_HAPUS"
    catat "manifest sebelumnya belum ada — seluruh isi paket diunggah sekali ini"
fi

# Pengaman daftar hapus. Berkas berikut tidak boleh pernah terbuang lewat
# pembaruan: kredensial produksi, unggahan peserta, log, isi runtime storage,
# dan .htaccess pengganti document root.
aman_dihapus() {
    case "$1" in
        ./.env | ./.env.* | */.env | */.env.*) return 1 ;;
        ./storage/app/public/*) return 1 ;;
        ./storage/app/*) return 1 ;;
        ./storage/logs/*) return 1 ;;
        ./storage/framework/*) return 1 ;;
        ./.htaccess) return 1 ;;
    esac
    return 0
}

DAFTAR_HAPUS_AMAN="$BUILD/HAPUS-aman.txt"
: > "$DAFTAR_HAPUS_AMAN"
dilindungi=0
while IFS= read -r rel; do
    [ -n "$rel" ] || continue
    if aman_dihapus "$rel"; then
        printf '%s\n' "${rel#./}" >> "$DAFTAR_HAPUS_AMAN"
    else
        dilindungi=$((dilindungi + 1))
    fi
done < "$DAFTAR_HAPUS"
mv "$DAFTAR_HAPUS_AMAN" "$DAFTAR_HAPUS"

# ---------------------------------------------------------------------------
# 4. Menyiapkan berkas lalu mengirimnya
# ---------------------------------------------------------------------------

printf '\n== Menyiapkan berkas ==\n'
rm -rf "$PATCH"
mkdir -p "$PATCH"

while IFS= read -r baris; do
    [ -n "$baris" ] || continue
    rel="${baris#*  }"
    rel="${rel#./}"
    [ -n "$rel" ] || continue
    mkdir -p "$PATCH/$(dirname "$rel")"
    # Hardlink supaya puluhan MB dependensi tidak ikut tersalin; jatuh ke
    # salinan biasa bila hardlink tidak dimungkinkan partisi.
    ln -f "$STAGE/$rel" "$PATCH/$rel" 2>/dev/null || cp -p "$STAGE/$rel" "$PATCH/$rel"
done < "$DAFTAR_UNGGAH"

find "$PATCH" -type f -printf '%P\0' > "$DAFTAR_PATCH"

n_unggah="$(tr -cd '\0' < "$DAFTAR_PATCH" | wc -c | tr -d ' ')"
n_hapus="$(wc -l < "$DAFTAR_HAPUS" | tr -d ' ')"

catat "berkas berubah : $n_unggah"
catat "berkas usang   : $n_hapus"
[ "$dilindungi" -eq 0 ] || catat "dilindungi dari hapus: $dilindungi"
catat "paralelisme    : $PARALEL"

: > "$DAFTAR_GAGAL"
: > "$DAFTAR_GAGAL_HAPUS"

if [ "$n_unggah" -gt 0 ]; then
    printf '\n== Mengunggah ==\n'
    # `--ftp-create-dirs` membuat folder tujuan yang belum ada. Tiap berkas
    # berdiri sendiri supaya kegagalannya bisa dicatat per nama, bukan membuat
    # satu unggahan raksasa gagal sekaligus.
    xargs -0 -P "$PARALEL" -n 1 bash -c '
        rel="$1"
        curl -K "$CFG" --silent --show-error --retry 2 --ftp-create-dirs \
            --ssl-reqd -T "$PATCH/$rel" "$BASE/$rel" ||
            printf "%s\n" "$rel" >> "$DAFTAR_GAGAL"
    ' _ < "$DAFTAR_PATCH"
fi

if [ "$BERSIH" = "ya" ] && [ "$n_hapus" -gt 0 ]; then
    printf '\n== Membuang berkas usang ==\n'
    tr '\n' '\0' < "$DAFTAR_HAPUS" |
        xargs -0 -P "$PARALEL" -n 1 bash -c '
            rel="$1"
            curl -K "$CFG" --silent --show-error --ssl-reqd \
                --quote "DELE $AKAR/$rel" --list-only -o /dev/null "$BASE/" ||
                printf "%s\n" "$rel" >> "$DAFTAR_GAGAL_HAPUS"
        ' _
fi

# Kegagalan menahan penulisan manifest, supaya berkas yang belum terkirim tetap
# dihitung berubah pada kali berikutnya dan tidak pernah dianggap selesai.
if [ -s "$DAFTAR_GAGAL" ]; then
    printf '\n[GAGAL] %s berkas tidak terkirim:\n' "$(wc -l < "$DAFTAR_GAGAL" | tr -d ' ')" >&2
    head -n 20 "$DAFTAR_GAGAL" >&2
    exit 1
fi

if [ -s "$DAFTAR_GAGAL_HAPUS" ]; then
    printf '\n[GAGAL] %s berkas gagal dibuang:\n' "$(wc -l < "$DAFTAR_GAGAL_HAPUS" | tr -d ' ')" >&2
    head -n 20 "$DAFTAR_GAGAL_HAPUS" >&2
    exit 1
fi

cp "$MANIFEST_BARU" "$MANIFEST_LAMA"

printf '\nSelesai.\n'
