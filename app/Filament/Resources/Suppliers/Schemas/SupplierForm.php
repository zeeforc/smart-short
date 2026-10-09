<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('company_name'),
                TextInput::make('contact_person'),
                TextInput::make('phone')
                    ->tel(),
                Toggle::make('is_ppn_included')
                    ->label('Pricelist sudah termasuk PPN')
                    ->default(true)
                    ->helperText('Aktifkan jika harga di file Excel/PDF sudah termasuk PPN. Jika dinonaktifkan, sistem akan otomatis menambahkan PPN saat membandingkan harga termurah.'),
                Toggle::make('is_active')
                    ->required(),
                \Filament\Forms\Components\Section::make('Smart Mapping (Kolom Excel)')
                    ->description('Masukkan nama kolom (header) yang dipakai supplier ini. Pisahkan dengan koma jika ada beberapa alternatif kata.')
                    ->schema([
                        TextInput::make('column_mapping.name_keywords')
                            ->label('Kolom Nama Obat')
                            ->placeholder('Contoh: nama barang, deskripsi, produk'),
                        TextInput::make('column_mapping.unit_keywords')
                            ->label('Kolom Satuan')
                            ->placeholder('Contoh: kemasan, satuan, box'),
                        TextInput::make('column_mapping.price_keywords')
                            ->label('Kolom Harga')
                            ->placeholder('Contoh: hna, harga dasar, grosir'),
                        TextInput::make('column_mapping.discount_keywords')
                            ->label('Kolom Diskon')
                            ->placeholder('Contoh: disc 1, diskon, potongan'),
                    ])
                    ->columns(2),
            ]);
    }
}
