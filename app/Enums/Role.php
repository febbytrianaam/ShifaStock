<?php

namespace App\Enums;

enum Role: string
{
    case Administrator = 'administrator';
    case Pharmacy = 'petugas_farmasi';
    case Pharmacist = 'kepala_apoteker';
    case Guest = 'guest';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator Sistem',
            self::Pharmacy => 'Petugas Farmasi',
            self::Pharmacist => 'Kepala Apoteker',
            self::Guest => 'Guest',
        };
    }
}
