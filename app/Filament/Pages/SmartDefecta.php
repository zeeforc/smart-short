<?php

namespace App\Filament\Pages;

use App\Models\DefectaItem;
use App\Services\OrderCalculationService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class SmartDefecta extends Page implements HasTable
{
    use InteractsWithTable;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return Heroicon::OutlinedShoppingCart;
    }

    public static function getNavigationLabel(): string
    {
        return 'Smart Defecta (PO)';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Transaksi';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    protected string $view = 'filament.pages.smart-defecta';

    public ?array $calculationResults = null;

    public function getTitle(): string
    {
        return 'Keranjang Belanja Pintar';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import_excel')
                ->label('Upload File Excel')
                ->icon('heroicon-m-arrow-up-tray')
                ->color('success')
                ->form([
                    FileUpload::make('file')
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
                    $filePath = Storage::disk('local')->path($data['file']);
                    
                    $import = new \App\Imports\SmartDefectaImport();
                    Excel::import($import, $filePath);

                    $validItems = collect($import->data)->filter(function ($item) {
                        return !empty($item['product_name']);
                    })->values()->toArray();

                    if (count($validItems) > 0) {
                        foreach ($validItems as $item) {
                            DefectaItem::create([
                                'user_id' => auth()->id(),
                                'product_name' => $item['product_name'],
                                'qty' => $item['qty'] ?: 1,
                            ]);
                        }

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
                
            Action::make('clear_cart')
                ->label('Kosongkan Daftar')
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    DefectaItem::where('user_id', auth()->id())->delete();
                    $this->calculationResults = null;
                    \Filament\Notifications\Notification::make()
                        ->title('Daftar belanja berhasil dikosongkan')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(DefectaItem::query()->where('user_id', auth()->id()))
            ->columns([
                TextColumn::make('product_name')
                    ->label('Nama Obat')
                    ->searchable(),
                TextColumn::make('qty')
                    ->label('Jumlah (QTY)')
                    ->numeric(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Tambah Obat Manual')
                    ->icon('heroicon-m-plus')
                    ->form([
                        TextInput::make('product_name')
                            ->label('Nama Obat')
                            ->required(),
                        TextInput::make('qty')
                            ->label('Jumlah (QTY)')
                            ->numeric()
                            ->default(1)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        DefectaItem::create([
                            'user_id' => auth()->id(),
                            'product_name' => $data['product_name'],
                            'qty' => $data['qty'],
                        ]);
                    }),
                Action::make('kalkulasi')
                    ->label('Kalkulasi Pemenang')
                    ->icon('heroicon-m-calculator')
                    ->color('primary')
                    ->action(fn () => $this->calculate()),
            ])
            ->actions([
                Action::make('delete')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->delete()),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('delete')
                        ->label('Delete')
                        ->icon('heroicon-m-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (\Illuminate\Database\Eloquent\Collection $records) => $records->each->delete()),
                ]),
            ])
            ->emptyStateHeading('Keranjang masih kosong')
            ->emptyStateDescription('Silakan upload file Excel atau tambah obat secara manual.');
    }

    public function calculate()
    {
        $items = DefectaItem::where('user_id', auth()->id())->get();
        
        if ($items->isEmpty()) {
            \Filament\Notifications\Notification::make()
                ->title('Keranjang belanja kosong')
                ->warning()
                ->send();
            return;
        }

        $service = app(OrderCalculationService::class);
        $results = [];

        foreach ($items as $item) {
            $normalizedName = strtoupper(trim($item->product_name));
            $calc = $service->calculateBestSupplier($normalizedName, (int) $item->qty);

            $results[] = [
                'request' => [
                    'product_name' => $item->product_name,
                    'qty' => $item->qty,
                ],
                'calculation' => $calc,
            ];
        }

        $this->calculationResults = $results;
        
        \Filament\Notifications\Notification::make()
            ->title('Kalkulasi berhasil')
            ->success()
            ->send();
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

        return Excel::download(
            new \App\Exports\SmartDefectaExport($this->calculationResults),
            'SP_Defecta_' . date('Ymd_His') . '.xlsx'
        );
    }
}
