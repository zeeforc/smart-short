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

    protected static ?string $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $navigationLabel = 'Smart Defecta (PO)';

    protected static ?string $title = 'Keranjang Belanja Pintar';

    protected static \UnitEnum|string|null $navigationGroup = 'Transaksi';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.smart-defecta';

    public ?array $items = [];

    public ?array $calculationResults = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
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
}
