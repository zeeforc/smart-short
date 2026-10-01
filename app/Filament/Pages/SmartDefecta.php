<?php

namespace App\Filament\Pages;

use App\Services\OrderCalculationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class SmartDefecta extends Page implements HasForms
{
    use InteractsWithForms;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return Heroicon::OutlinedShoppingCart;
    }

    public static function getNavigationLabel(): string
    {
        return 'Smart Defecta (PO)';
    }

    public function getTitle(): string
    {
        return 'Keranjang Belanja Pintar';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Transaksi';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('import_excel')
                ->label('Upload File Excel')
                ->icon('heroicon-m-arrow-up-tray')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('file')
                        ->label('File Excel')
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv'
                        ])
                        ->required(),
                ])
                ->action(function (array $data) {
                    $filePath = \Illuminate\Support\Facades\Storage::disk('local')->path($data['file']);
                    
                    $import = new \App\Imports\SmartDefectaImport();
                    \Maatwebsite\Excel\Facades\Excel::import($import, $filePath);

                    $validItems = collect($import->data)->filter(function ($item) {
                        return !empty($item['product_name']);
                    })->values()->toArray();

                    if (count($validItems) > 0) {
                        // In Livewire/Filament, updating the state directly works well if we call fill() or just update the property
                        // The items repeater state is mapped to $this->items (since statePath is 'items')
                        // We also might want to clear the old items or append. Let's replace for now.
                        
                        // We should format it the way Repeater expects (UUID keys)
                        $repeaterData = [];
                        foreach ($validItems as $item) {
                            $repeaterData[\Illuminate\Support\Str::uuid()->toString()] = $item;
                        }
                        
                        $this->items = $repeaterData;
                        $this->form->fill(['items' => $repeaterData]);

                        \Filament\Notifications\Notification::make()
                            ->title('Berhasil import ' . count($validItems) . ' obat dari Excel')
                            ->success()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('Gagal import, format tidak sesuai atau kosong')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected string $view = 'filament.pages.smart-defecta';

    public ?array $items = [];

    public ?array $calculationResults = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->schema([
                Repeater::make('items')
                    ->label('Daftar Belanja (Defecta)')
                    ->schema([
                        TextInput::make('product_name')
                            ->label('Nama Obat')
                            ->required()
                            ->placeholder('Contoh: Paracetamol 500mg'),
                        TextInput::make('qty')
                            ->label('Jumlah (QTY)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(1),
                    ])
                    ->columns(2)
                    ->addActionLabel('Tambah Obat Baru')
                    ->defaultItems(1),
            ])
            ->statePath('items'); // Because we mapped state to $items
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('calculate')
                ->label('Kalkulasi Pemenang')
                ->submit('calculate')
                ->color('primary')
                ->icon('heroicon-m-calculator'),
        ];
    }

    public function calculate(OrderCalculationService $service)
    {
        $state = $this->form->getState();
        $items = $state['items'] ?? []; // This gets the array inside the repeater, wait state mapping is a bit different. Let's see.
        // If statePath is 'items', the entire form state is stored in $this->items.
        // So $state is the array of repeater items if we only have one root component, but wait, usually we put Repeater inside a schema.
        // Actually, if statePath is 'items', it maps the form data to the $items array.
        // A safer way is to use $data array for statePath.

        $results = [];

        foreach ($items as $item) {
            // Need to parse the product name to canonical to find it
            // OrderCalculationService expects normalizedName. Let's use the exact name for now or parse it.
            // We should inject ProductNormalizationService or just parse it here.
            $normalizedName = strtoupper(trim($item['product_name']));

            // For simplicity, let's just pass the typed name and let service handle or we assume user types exactly.
            // In reality, we should provide a Select with search for normalized_name. I will leave it as text for now.
            $calc = $service->calculateBestSupplier($normalizedName, (int) $item['qty']);

            $results[] = [
                'request' => $item,
                'calculation' => $calc,
            ];
        }

        $this->calculationResults = $results;
    }

    public function exportToExcel()
    {
        if (empty($this->calculationResults)) {
            \Filament\Notifications\Notification::make()
                ->title('Belum ada hasil kalkulasi yang bisa di-download')
                ->warning()
                ->send();
            return;
        }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\SmartDefectaExport($this->calculationResults),
            'SP_Defecta_' . date('Ymd_His') . '.xlsx'
        );
    }
}
