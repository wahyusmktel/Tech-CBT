<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Kurikulum = 'kurikulum';
    case Pengawas = 'pengawas';
    case Siswa = 'siswa';
}
