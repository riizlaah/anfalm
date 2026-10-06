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

# Berkas catatan kemajuan: tiap anak pencetak menambah satu baris setelah
# berkasnya benar-benar terkirim atau terbuang. Isinya dua kegunaan sekaligus —
# bahan penghitung untuk baris progres, dan jejak yang bisa dibaca ulang untuk
# tahu berkas mana yang sedang dikerjakan saat skrip berhenti.
HITUNG_UNGGAH="$BUILD/unggah.log"
HITUNG_BUANG="$BUILD/buang.log"

# PID pengawas kemajuan. Kosong selama fase tanpa pengawan supaya pembersihan
# tidak pernah membunuh proses yang bukan miliknya.
PENGAWAS=""
MULAI_SKRIP="$(date +%s)"

# Berkas konfigurasi curl berisi kredensial sekaligus seluruh invarian
# transport: `ssl-reqd`, `ipv4`, `disable-epsv`. Ia dibuat bertopeng 600 dan
# dibuang lewat trap pada setiap jalan keluar, termasuk saat gagal di tengah
# jalan. Dua alasan: `ps` memperlihatkan apa pun yang tertulis di baris
# perintah, sedangkan berkas konfigurasi tidak; dan menaruh invarian di sini
# membuatnya mustahil terlewat oleh satu pun pemanggilan curl.
#
# Uji ke server nyata menentukan isinya. ftpupload.net ikut menjawab alamat
# IPv6, dan di jalur itu server menolak `EPSV` dengan `500 Unknown command`
# lalu curl keluar kode 8 tanpa sempat jatuh ke PASV. Di IPv4 ia jatuh ke PASV
# dengan sendirinya, jadi memaksa IPv4 + PASV adalah satu-satunya kombinasi
# yang terbukti membuka saluran data.
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

matikan_pengawas() {
    if [ -z "$PENGAWAS" ]; then
        return 0
    fi
    kill "$PENGAWAS" 2>/dev/null || true
    wait "$PENGAWAS" 2>/dev/null || true
    PENGAWAS=""
}

# Jejak keadaan server — deploy/build/manifest-akhir.txt. Ia ditulis dari satu
# titik saja, di dalam trap EXIT, supaya ikut tercatat pada ketiga jalan keluar
# yang memang mungkin terjadi: run sukses, run berhenti karena berkas gagal,
# dan run dipotong di tengah jalan.
#
# Dulu jejak ditulis lewat salinan manifest keinginan, dan hanya pada jalur
# sukses. Akibatnya satu berkas gagal membuat run berikutnya mengunggah ulang
# seluruh isi paket, dan berkas usang yang pembuangannya gagal terlupakan
# selamanya dari daftar buang. Manifest akhir kini menceritakan isi server,
# bukan isi paket — bedanya itulah yang menentukan apa yang dikirim berikutnya.
TRANSFER_JALAN="tidak"

tulis_jejak() {
    # Sebelum ada kiriman apa pun, isi server tidak berubah sama sekali:
    # jejak lama sudah benar, dan menulisnya ulang hanya membuka peluang salah.
    [ "$TRANSFER_JALAN" = "ya" ] || return 0

    if ! bash "$ROOT/deploy/gabung-manifest.sh" \
        "$MANIFEST_LAMA" "$MANIFEST_BARU" \
        "$HITUNG_UNGGAH" "$HITUNG_BUANG" "$BERSIH" "$MANIFEST_LAMA"
    then
        # Tidak mengubah kode keluar: kegagalan menulis jejak bukan kegagalan
        # sinkronisasi, dan jejak lamanya tetap utuh berkat penulisan atomik.
        printf '\n[GAGAL] jejak keadaan server tidak jadi tertulis; %s dibiarkan apa adanya sehingga sinkron berikutnya tetap memakai keadaan yang sudah terbukti benar.\n' \
            "$MANIFEST_LAMA" >&2
    fi
}

bersihkan() {
    matikan_pengawas
    tulis_jejak
    rm -f "$CFG" "$BUILD/uji-sinkron.txt" "$BUILD/uji-sinkron-balik.txt" \
        "$BUILD/uji-curl.txt" "$BUILD/preflight-curl.txt"
}

trap bersihkan EXIT
# Ctrl-C dan SIGTERM diarahkan lewat `exit` biasa agar trap EXIT di atas pasti
# berjalan. Tanpa keduanya shell bisa terputus tepat sebelum jejak keadaan
# server sempat ditulis, dan jejak yang hilang itu memaksa unggah penuh pada
# sinkron berikutnya.
trap 'exit 130' INT
trap 'exit 143' TERM

# Satu baris kemajuan yang diperbarui di tempat selama fase yang lama.
#
# Fase unggah berjalan puluhan menit dengan `curl --silent` di dalamnya, jadi
# layar berhenti berubah sama sekali setelah `== Mengunggah ==` dan skrip
# terbaca seperti macet — itulah keluhan yang melahirkan fungsi ini. Pengawas
# ini menghitung baris pada berkas catatan, yang ditambah tiap anak pencetak,
# lalu memperbaruinya tiap detik.
#
# Di luar TTY, `\r` tidak mengulang baris apa pun dan hanya mengotori berkas
# log, jadi di sana keluarannya berupa ringkasan tiap sepuluh detik.
#
# `total` selalu lebih besar dari nol — pengawas hanya dimulai bila memang ada
# berkas yang dikerjakan, sehingga bagi hasilnya tidak pernah dibagi nol.
pengawas_kemajuan() {
    local total="$1" catatan="$2" label="$3"
    local detik=0 n

    while :; do
        # `|| true` wajib: pipefail membuat kegagalan `wc` pada berkas yang
        # belum ada ikut menggagalkan penugasan dan mematikan skrip lewat set -e.
        n="$(wc -l < "$catatan" 2>/dev/null | tr -d ' ' || true)"
        case "$n" in
            '' | *[!0-9]*) n=0 ;;
        esac

        if [ -t 1 ]; then
            printf '\r  %s: %s/%s (%s%%)   ' "$label" "$n" "$total" "$((n * 100 / total))"
        elif [ $((detik % 10)) -eq 0 ]; then
            printf '  %s: %s/%s (%s%%)\n' "$label" "$n" "$total" "$((n * 100 / total))"
        fi

        detik=$((detik + 1))
        sleep 1
    done
}

mulai_pengawas() {
    pengawas_kemajuan "$1" "$2" "$3" &
    PENGAWAS=$!
}

berhenti_pengawas() {
    matikan_pengawas
    # Baris `\r` belum ditutup baris baru. Tanpa ini pesan berikutnya menimpanya
    # dan hanya sisa angkanya yang terbaca.
    if [ -t 1 ]; then
        printf '\n'
    fi
}

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
        {
            printf 'user = "%s:%s"\n' "$(kutip_curl "$PENGGUNA")" "$(kutip_curl "$SANDI")"
            # Bendera boolean harus ditulis polos: `ipv4 = true` membuat curl
            # menolak seluruh berkas dengan "had unsupported trailing garbage".
            printf 'ssl-reqd\nipv4\ndisable-epsv\n'
        } > "$CFG"
    )
    chmod 600 "$CFG"
}

tulis_config

export CFG BASE AKAR PATCH
export DAFTAR_GAGAL DAFTAR_GAGAL_HAPUS
export HITUNG_UNGGAH HITUNG_BUANG

# Kode keluar curl dibaca langsung supaya pesannya menyebut penyebab yang
# sebenarnya. Tanpa pemetaan ini, kegagalan saluran data (kode 8) ikut
# dilaporkan sebagai masalah kredensial dan mengarahkan penyelidikan ke arah
# yang salah — justru ketika kredensialnya sendiri benar.
salah_curl() {
    case "$1" in
        6) printf 'nama host %s tidak dapat diuraikan — periksa HOST di %s' "$HOST" "$KRED" ;;
        7) printf 'gagal menyambung ke %s — periksa HOST dan PORT di %s' "$BASE" "$KRED" ;;
        8) printf 'respons server tidak wajar pada koneksi data (kode curl 8) — jalur PASV/EPSV bermasalah, jalankan lagi' ;;
        67) printf 'kredensial ditolak server (kode curl 67) — periksa PENGGUNA dan SANDI di %s' "$KRED" ;;
        *) printf 'koneksi gagal (kode curl %s)' "$1" ;;
    esac
}

# Satu pemeriksaan dipakai dua tempat: mode `--uji`, dan pembuka tiap
# sinkronisasi. Ia membuktikan dua hal yang berbeda dan sama-sama wajib —
# `AUTH TLS` yang dijawab `234` (kredensial tidak boleh terkirim polos), lalu
# satu LIST yang membuka saluran data lewat jalur PASV yang persis dipakai
# unggah. LIST dipilih karena bersifat baca-saja: kegagalan saluran data bisa
# dibuktikan tanpa menulis apa pun ke server.
periksa_koneksi() {
    local verbose="$1" kode="$2"

    if [ "$kode" -ne 0 ]; then
        tail -n 20 "$verbose" >&2 || true
        gagal "$(salah_curl "$kode")"
    fi

    grep -q 'AUTH TLS' "$verbose" ||
        { tail -n 20 "$verbose" >&2; gagal "curl tidak pernah mengirim AUTH TLS"; }
    grep -qE '< 234' "$verbose" ||
        { tail -n 20 "$verbose" >&2; gagal "AUTH TLS tidak diterima server (bukan 234) — TLS gagal"; }
}

# ---------------------------------------------------------------------------
# 2. Mode uji: bukti TLS, kredensial, lalu bolak-balik sebuah berkas
# ---------------------------------------------------------------------------

if [ "$MODE" = "uji" ]; then
    printf '\n== Mode uji koneksi ==\n'
    catat "tujuan : $BASE"
    catat "akun   : $PENGGUNA"

    verbose="$BUILD/uji-curl.txt"
    set +e
    curl -K "$CFG" --silent --show-error --verbose \
        --list-only -o /dev/null "$BASE/" 2> "$verbose"
    kode="$?"
    set -e

    periksa_koneksi "$verbose" "$kode"
    catat "TLS    : AUTH TLS dijawab 234"
    catat "akses  : daftar isi terbaca, saluran data terbuka"

    uji_lokal="$BUILD/uji-sinkron.txt"
    uji_balik="$BUILD/uji-sinkron-balik.txt"
    printf 'diunggah-pada=%s\n' "$(date -Is)" > "$uji_lokal"

    curl -K "$CFG" --silent --show-error --ftp-create-dirs \
        -T "$uji_lokal" "$BASE/.uji-sinkron.txt"
    curl -K "$CFG" --silent --show-error -o "$uji_balik" \
        "$BASE/.uji-sinkron.txt"
    cmp -s "$uji_lokal" "$uji_balik" ||
        gagal "isi berkas uji tidak sama setelah dibaca kembali — pindah data tidak utuh"
    catat "uji    : unggah + unduh cocok"

    curl -K "$CFG" --silent --show-error \
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
# dan langkah pemulihannya tertulis. Pemeriksaan ini lokal dan gratis, jadi ia
# didahulukan atas pemeriksaan koneksi yang harus menyeberang jaringan.
if [ -f "$MANIFEST_LAMA" ]; then
    LC_ALL=C sort -c "$MANIFEST_LAMA" 2>/dev/null ||
        gagal "deploy/build/manifest-akhir.txt tidak terurut — kemungkinan rusak atau pernah diedit tangan. Hapus berkas itu lalu jalankan ulang; sinkron berikutnya akan mengunggah ulang seluruh isi paket sekali"
fi

# Koneksi diperiksa sebelum paket dirakit. Pemeriksaan ini memakan waktu kurang
# dari dua detik, tetapi kalau TLS, kredensial, atau saluran data sedang
# bermasalah skrip berhenti di sini — bukan setelah ribuan berkas terkirim
# sebagian dan menyisakan isi server yang setengah baru. Yang diuji adalah LIST,
# jalur PASV yang sama dengan yang dipakai unggah, jadi kegagalan EPSV ketahuan
# sebelum menyentuh satu berkas pun.
printf '\n== Pemeriksaan koneksi ==\n'
catat "tujuan : $BASE"
preflight="$BUILD/preflight-curl.txt"
set +e
curl -K "$CFG" --silent --show-error --verbose --list-only -o /dev/null "$BASE/" 2> "$preflight"
kode="$?"
set -e
periksa_koneksi "$preflight" "$kode"
catat "TLS + saluran data : siap"

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

# Dari titik ini isi server boleh berubah, sehingga jejak harus ikut ditulis
# ulang walau skrip berhenti sebentar lagi. Keempat catatan dipotong bersama di
# sini: dua yang pertama adalah bukti apa yang benar-benar terkirim dan terbuang
# yang dibaca penggabung jejak, sedangkan catatan yang tersisa dari run
# sebelumnya akan mengaku sesuatu yang tidak pernah terjadi pada run ini.
TRANSFER_JALAN="ya"
: > "$HITUNG_UNGGAH"
: > "$HITUNG_BUANG"
: > "$DAFTAR_GAGAL"
: > "$DAFTAR_GAGAL_HAPUS"

printf '\n== Mengunggah ==\n'

if [ "$n_unggah" -gt 0 ]; then
    mulai_pengawas "$n_unggah" "$HITUNG_UNGGAH" "mengunggah"

    # `--ftp-create-dirs` membuat folder tujuan yang belum ada. Tiap berkas
    # berdiri sendiri supaya kegagalannya bisa dicatat per nama, bukan membuat
    # satu unggahan raksasa gagal sekaligus.
    #
    # Penulisan ke catatan setelah curl sukses adalah penghitung kemajuan yang
    # dibaca pengawas, sekaligus jejak berkas terakhir yang dikirim. Kegagalan
    # dicetak ke layar juga, bukan hanya ke berkas — tanpa itu skrip bisa keluar
    # dengan layar bersih padahal ada berkas yang tidak terkirim.
    xargs -0 -P "$PARALEL" -n 1 bash -c '
        rel="$1"
        if curl -K "$CFG" --silent --show-error --retry 2 --ftp-create-dirs \
            -T "$PATCH/$rel" "$BASE/$rel"; then
            printf "%s\n" "$rel" >> "$HITUNG_UNGGAH"
        else
            printf "  gagal: %s\n" "$rel" >&2
            printf "%s\n" "$rel" >> "$DAFTAR_GAGAL"
        fi
    ' _ < "$DAFTAR_PATCH"

    berhenti_pengawas

    # Angkanya dihitung dari catatan, bukan dari jumlah yang diminta: bila ada
    # berkas gagal, "Berhasil" tidak boleh tercetak lebih dulu baru disusul
    # daftar kegagalan — dua pernyataan yang saling membantah di layar yang sama.
    n_terkirim="$(wc -l < "$HITUNG_UNGGAH" | tr -d ' ')"
    if [ "$n_terkirim" -eq "$n_unggah" ]; then
        printf '  Berhasil mengunggah %s berkas.\n' "$n_unggah"
    else
        printf '  %s dari %s berkas terkirim — rincian kegagalannya di bawah.\n' \
            "$n_terkirim" "$n_unggah"
    fi
else
    printf '  Tidak ada berkas yang berubah — tidak ada yang diunggah.\n'
fi

n_dibuang=0

if [ "$BERSIH" = "ya" ]; then
    printf '\n== Membuang berkas usang ==\n'

    if [ "$n_hapus" -gt 0 ]; then
        mulai_pengawas "$n_hapus" "$HITUNG_BUANG" "membuang"

        # Fase ini sama senyapnya dengan unggah — bisa ratusan DELE yang
        # masing-masing menyeberang saluran data — jadi ia mendapat pengawas
        # dan pesan keberhasilan yang sama.
        tr '\n' '\0' < "$DAFTAR_HAPUS" |
            xargs -0 -P "$PARALEL" -n 1 bash -c '
                rel="$1"
                if curl -K "$CFG" --silent --show-error \
                    --quote "DELE $AKAR/$rel" --list-only -o /dev/null "$BASE/"; then
                    printf "%s\n" "$rel" >> "$HITUNG_BUANG"
                else
                    printf "  gagal membuang: %s\n" "$rel" >&2
                    printf "%s\n" "$rel" >> "$DAFTAR_GAGAL_HAPUS"
                fi
            ' _

        berhenti_pengawas

        # Sama seperti unggah: jumlah yang dipakai adalah yang benar-benar
        # terbuang, sehingga ringkasan penutup tidak menghitung berkas yang
        # gagal sebagai berhasil.
        n_dibuang="$(wc -l < "$HITUNG_BUANG" | tr -d ' ')"
        if [ "$n_dibuang" -eq "$n_hapus" ]; then
            printf '  Berhasil membuang %s berkas usang.\n' "$n_dibuang"
        else
            printf '  %s dari %s berkas usang terbuang — rincian kegagalannya di bawah.\n' \
                "$n_dibuang" "$n_hapus"
        fi
    else
        printf '  Tidak ada berkas usang.\n'
    fi
fi

# Kegagalan tidak lagi menahan penulisan jejak. Keduanya tetap keluar kode 1
# supaya run ini terbaca gagal, tetapi jejaknya sudah jujur tanpa perlu
# ditahan: penggabung mempertahankan hash lama bagi berkas yang gagal dan
# tidak mencatat berkas baru yang belum terkirim, sehingga sinkron berikutnya
# menyebut kembali berkas-berekas tersisa itu — bukan seluruh isi paket.
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

# Ringkasan penutup. `Selesai.` semata tidak menjawab apa pun: ia tercetak
# setelah dua fase yang sama-sama senyap, sehingga tidak terlihat apa yang
# betul-betul dikerjakan atau berapa lama waktunya.
durasi=$(($(date +%s) - MULAI_SKRIP))
if [ "$durasi" -lt 60 ]; then
    lama="${durasi} detik"
else
    lama="$((durasi / 60)) menit $((durasi % 60)) detik"
fi

printf '\nSelesai dalam %s — %s berkas diunggah, %s dibuang.\n' \
    "$lama" "$n_unggah" "$n_dibuang"
