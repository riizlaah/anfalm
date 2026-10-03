<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Kolom :attribute harus disetujui.',
    'accepted_if' => 'Kolom :attribute harus disetujui ketika :other adalah :value.',
    'active_url' => 'Kolom :attribute harus berupa sebuah URL.',
    'after' => 'Kolom :attribute harus berupa tanggal setelah :date.',
    'after_or_equal' => 'Kolom :attribute harus berupa tanggal setelah atau sama dengan :date.',
    'alpha' => 'Kolom :attribute hanya berupa huruf.',
    'alpha_dash' => 'Kolom :attribute hanya berupa huruf, angka, dash (-), garis bawah (_).',
    'alpha_num' => 'Kolom :attribute hanya berupa huruf dan angka.',
    'any_of' => 'Kolom :attribute tidak valid.',
    'array' => 'Kolom :attribute harus berupa array (sekumpulan data).',
    'array_keys' => 'Kolom :attribute hanya boleh berisi kunci berikut: :values.',
    'ascii' => 'Kolom :attribute harus berupa karakter ASCII.',
    'base64' => 'Kolom :attribute harus berupa string Base64 yang valid.',
    'before' => 'Kolom :attribute harus berupa tanggal sebelum :date.',
    'before_or_equal' => 'Kolom :attribute harus berupa tanggal sebelum atau sama dengan :date.',
    'between' => [
        'array' => 'Kolom :attribute harus di antara :min sampai :max item.',
        'file' => 'Kolom :attribute harus di antara :min sampai :max kilobytes.',
        'numeric' => 'Kolom :attribute harus di antara :min sampai :max.',
        'string' => 'Kolom :attribute harus di antara :min sampai :max karakter.',
    ],
    'boolean' => 'Kolom :attribute harus berupa true atau false.',
    'can' => 'Kolom :attribute mengandung kata yang tidak diijinkan.',
    'confirmed' => 'Konfirmasi kolom :attribute tidak sesuai.',
    'contains' => 'Kolom :attribute kehilangan isi yang dibutuhkan.',
    'current_password' => 'Kolom password tidak sesuai.',
    'date' => 'Kolom :attribute harus berupa tanggal.',
    'date_equals' => 'Kolom :attribute harus berupa tanggal yang sama dengan :date.',
    'date_format' => 'Kolom :attribute harus sesuai dengan format :format.',
    'decimal' => 'Kolom :attribute harus memiliki :decimal di belakang koma.',
    'declined' => 'Kolom :attribute harus tidak disetujui.',
    'declined_if' => 'Kolom :attribute harus tidak disetujui jika kolom :other berisi :value.',
    'different' => 'Kolom :attribute dan kolom :other harus berbeda.',
    'digits' => 'Kolom :attribute harus memiliki panjang :digits digit.',
    'digits_between' => 'Kolom :attribute harus di antara :min sampai :max digit.',
    'dimensions' => 'Kolom :attribute tidak sesuai dengan dimensi gambar yang telah diatur.',
    'distinct' => 'Kolom :attribute memiliki nilai duplikat.',
    'doesnt_contain' => 'Kolom :attribute tidak boleh mengandung: :values.',
    'doesnt_end_with' => 'Kolom :attribute tidak boleh diakhiri dengan: :values.',
    'doesnt_start_with' => 'Kolom :attribute tidak boleh diawali dengan: :values.',
    'email' => 'Kolom :attribute harus berupa email yang valid.',
    'encoding' => 'Kolom :attribute harus di-encode dalam :encoding.',
    'ends_with' => 'Kolom :attribute harus diakhiri dengan: :values.',
    'enum' => 'Kolom :attribute tidak valid.',
    'exists' => 'Kolom :attribute tidak valid.',
    'extensions' => 'Kolom :attribute harus memiliki salah satu dari ekstensi berikut: :values.',
    'file' => 'Kolom :attribute harus berupa file.',
    'filled' => 'Kolom :attribute harus terisi.',
    'gt' => [
        'array' => 'Kolom :attribute harus lebih dari :value item.',
        'file' => 'Kolom :attribute harus lebih besar dari :value kilobytes.',
        'numeric' => 'Kolom :attribute harus lebih besar dari :value.',
        'string' => 'Kolom :attribute harus lebih besar dari :value karakter.',
    ],
    'gte' => [
        'array' => 'Kolom :attribute harus memiliki :value item atau lebih.',
        'file' => 'Kolom :attribute harus lebih besar atau sama dengan :value kilobytes.',
        'numeric' => 'Kolom :attribute harus lebih besar atau sama dengan :value.',
        'string' => 'Kolom :attribute harus lebih besar atau sama dengan :value karakter.',
    ],
    'hex_color' => 'Kolom :attribute harus berupa kode warna hex yang valid.',
    'image' => 'Kolom :attribute harus berupa gambar.',
    'in' => 'Kolom :attribute tidak sesuai.',
    'in_array' => 'Kolom :attribute harus ada di :other.',
    'in_array_keys' => 'Kolom :attribute harus memiliki setidaknya salah satu dari keys: :values.',
    'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
    'ip' => 'Kolom :attribute harus berupa alamat IP yang valid.',
    'ipv4' => 'Kolom :attribute harus berupa format IPv4 yang valid.',
    'ipv6' => 'Kolom :attribute harus berupa format Ipv6 yang valid.',
    'json' => 'Kolom :attribute harus berupa format JSON yang valid.',
    'list' => 'Kolom :attribute harus berupa list.',
    'lowercase' => 'Kolom :attribute harus berupa huruf kecil.',
    'lt' => [
        'array' => 'Kolom :attribute harus kurang dari :value item.',
        'file' => 'Kolom :attribute harus kurang dari :value kilobytes.',
        'numeric' => 'Kolom :attribute harus kurang dari :value.',
        'string' => 'Kolom :attribute harus kurang dari :value karakter.',
    ],
    'lte' => [
        'array' => 'Kolom :attribute tidak boleh lebih dari :value item.',
        'file' => 'Kolom :attribute harus kurang dari atau sama dengan :value kilobytes.',
        'numeric' => 'Kolom :attribute harus kurang dari atau sama dengan :value.',
        'string' => 'Kolom :attribute harus kurang dari atau sama dengan :value karakter.',
    ],
    'mac_address' => 'Kolom :attribute harus berupa alamat MAC yang valid.',
    'max' => [
        'array' => 'Kolom :attribute tidak boleh lebih dari :max item.',
        'file' => 'Kolom :attribute tidak boleh lebih dari :max kilobytes.',
        'numeric' => 'Kolom :attribute tidak boleh lebih dari :max.',
        'string' => 'Kolom :attribute tidak boleh lebih dari :max karakter.',
    ],
    'max_digits' => 'Kolom :attribute tidak boleh melebihi :max digit.',
    'mimes' => 'Kolom :attribute harus berupa tipe file: :values.',
    'mimetypes' => 'Kolom :attribute harus berupa tipe file: :values.',
    'min' => [
        'array' => 'Kolom :attribute harus memiliki minimal :min item.',
        'file' => 'Kolom :attribute harus memiliki minimal :min kilobytes.',
        'numeric' => 'Kolom :attribute harus memiliki minimal :min.',
        'string' => 'Kolom :attribute harus memiliki minimal :min karakter.',
    ],
    'min_digits' => 'Kolom :attribute harus memiliki minimal: :min digit.',
    'missing' => 'Kolom :attribute harus hilang.',
    'missing_if' => 'Kolom :attribute harus hilang jika kolom :other berisi :value.',
    'missing_unless' => 'Kolom :attribute harus hilang kecuali kolom :other berisi :value.',
    'missing_with' => 'Kolom :attribute harus hilang jika :values ada.',
    'missing_with_all' => 'Kolom :attribute harus hilang ketika kolom :values ada.',
    'multiple_of' => 'Kolom :attribute harus berupa kelipatan dari :value.',
    'not_in' => 'Kolom :attribute tidak valid.',
    'not_regex' => 'Kolom :attribute tidak sesuai format.',
    'numeric' => 'Kolom :attribute harus berupa angka.',
    'password' => [
        'letters' => 'Kolom :attribute harus memiliki setidaknya satu huruf.',
        'mixed' => 'Kolom :attribute harus memiliki setidaknya satu huruf besar dan satu huruf kecil.',
        'numbers' => 'Kolom :attribute harus memiliki setidaknya satu angka.',
        'symbols' => 'Kolom :attribute harus memiliki setidaknya satu simbol.',
        'uncompromised' => 'Kolom :attribute telah bocor. Tolong ganti kolom :attribute supaya berbeda.',
    ],
    'present' => 'Kolom :attribute harus ada.',
    'present_if' => 'Kolom :attribute harus ada ketika kolom :other berisi :value.',
    'present_unless' => 'Kolom :attribute harus ada kecuali kolom :other berisi :value.',
    'present_with' => 'Kolom :attribute harus ada ketika kolom :values ada.',
    'present_with_all' => 'Kolom :attribute harus ada ketika kolom :values ada.',
    'prohibited' => 'Kolom :attribute harus kosong.',
    'prohibited_if' => 'Kolom :attribute harus kosong jika kolom :other adalah :value.',
    'prohibited_if_accepted' => 'Kolom :attribute harus kosong jika kolom :other disetujui.',
    'prohibited_if_declined' => 'Kolom :attribute harus kosong jika kolom :other tidak disetujui.',
    'prohibited_unless' => 'Kolom :attribute harus kosong kecuali :values ada di kolom :other.',
    'prohibits' => 'Kolom :attribute harus diisi namun kolom :other harus kosong.',
    'regex' => 'Kolom :attribute harus sesuai format.',
    'required' => 'Kolom :attribute harus diisi.',
    'required_array_keys' => 'Kolom :attribute harus memiliki entri untuk: :values.',
    'required_if' => 'Kolom :attribute harus diisi ketika :other adalah :value.',
    'required_if_accepted' => 'Kolom :attribute harus diisi ketika kolom :other disetujui.',
    'required_if_declined' => 'Kolom :attribute harus diisi ketika kolom :other tidak disetujui.',
    'required_unless' => 'Kolom :attribute harus diisi kecuali :values ada di kolom :other.',
    'required_with' => 'Kolom :attribute harus diisi ketika :values ada.',
    'required_with_all' => 'Kolom :attribute harus diisi ketika :values ada.',
    'required_without' => 'Kolom :attribute harus diisi ketika :values tidak tersedia.',
    'required_without_all' => 'Kolom :attribute harus diisi ketika tidak ada :values yang tersedia.',
    'same' => 'Kolom :attribute harus sama dengan kolom :other.',
    'size' => [
        'array' => 'Kolom :attribute harus memiliki :size item.',
        'file' => 'Kolom :attribute harus berukuran :size kilobytes.',
        'numeric' => 'Kolom :attribute harus :size.',
        'string' => 'Kolom :attribute harus memiliki :size karakter.',
    ],
    'starts_with' => 'Kolom :attribute harus diawali dengan: :values.',
    'string' => 'Kolom :attribute harus berupa kata/kalimat.',
    'timezone' => 'Kolom :attribute harus berupa zona waktu yang valid.',
    'unique' => 'Kolom :attribute sudah ada.',
    'uploaded' => 'Kolom :attribute gagal diupload',
    'uppercase' => 'Kolom :attribute harus berupa huruf besar.',
    'url' => 'Kolom :attribute harus berupa URL yang valid.',
    'ulid' => 'Kolom :attribute harus berupa ULID yang valid.',
    'uuid' => 'Kolom :attribute harus berupa UUID yang valid.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
