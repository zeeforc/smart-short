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
            ]);
    }
}
