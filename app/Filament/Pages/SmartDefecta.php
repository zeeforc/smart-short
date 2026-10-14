<?php

namespace App\Filament\Pages;

use App\Exports\SmartDefectaExport;
use App\Imports\SmartDefectaImport;
use App\Models\DefectaItem;
use App\Models\NormalizedProduct;
use App\Models\ProductAlias;
use App\Services\OrderCalculationService;
use App\Services\ProductNormalizationService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
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
                ->label('Import Data Pesanan (Excel)')
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
                            'text/csv',
                        ])
                        ->required(),
                ])
                ->action(function (array $data) {
                    $filePath = Storage::disk('local')->path($data['file']);

                    $import = new SmartDefectaImport;
                    Excel::import($import, $filePath);

                    $validItems = collect($import->data)->filter(function ($item) {
                        return ! empty($item['product_name']);
                    })->values()->toArray();

                    if (count($validItems) > 0) {
                        foreach ($validItems as $item) {
                            DefectaItem::create([
                                'user_id' => auth()->id(),
                                'product_name' => $item['product_name'],
                                'qty' => $item['qty'] ?: 1,
                            ]);
                        }

                        Notification::make()
                            ->title('Berhasil import '.count($validItems).' obat dari Excel')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
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
                    Notification::make()
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
                BulkActionGroup::make([
                    BulkAction::make('delete')
                        ->label('Delete')
                        ->icon('heroicon-m-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->delete()),
                ]),
            ])
            ->emptyStateHeading('Keranjang masih kosong')
            ->emptyStateDescription('Silakan import data pesanan (Excel) atau tambah obat secara manual.');
    }

    public function calculate()
    {
        $items = DefectaItem::where('user_id', auth()->id())->get();

        if ($items->isEmpty()) {
            Notification::make()
                ->title('Keranjang belanja kosong')
                ->warning()
                ->send();

            return;
        }

        $service = app(OrderCalculationService::class);
        $results = [];

        foreach ($items as $item) {
            $calc = $service->calculateBestSupplier($item->product_name, (int) $item->qty);

            $results[] = [
                'request' => [
                    'product_name' => $item->product_name,
                    'qty' => $item->qty,
                ],
                'calculation' => $calc,
            ];
        }

        $this->calculationResults = $results;

        $totalItems = count($results);
        $foundCount = collect($results)->filter(fn ($r) => ! empty($r['calculation']['winner']))->count();
        $missingCount = $totalItems - $foundCount;
        $autoCount = collect($results)->filter(fn ($r) => ($r['calculation']['match_info']['status'] ?? '') === 'auto_matched')->count();

        if ($missingCount > 0) {
            Notification::make()
                ->title("Kalkulasi selesai: {$foundCount} obat cocok, {$missingCount} butuh dicocokkan")
                ->body($autoCount > 0 ? "⚡ {$autoCount} obat berhasil dicocokkan otomatis oleh sistem." : null)
                ->warning()
                ->send();
        } else {
            Notification::make()
                ->title("Kalkulasi berhasil: Semua ({$foundCount}) obat terhubung!")
                ->body($autoCount > 0 ? "⚡ {$autoCount} obat dicocokkan secara otomatis." : null)
                ->success()
                ->send();
        }
    }

    public function exportToExcel()
    {
        if (empty($this->calculationResults)) {
            Notification::make()
                ->title('Belum ada hasil kalkulasi yang bisa di-download')
                ->warning()
                ->send();

            return;
        }

        return Excel::download(
            new SmartDefectaExport($this->calculationResults),
            'SP_Defecta_'.date('Ymd_His').'.xlsx'
        );
    }

    public function jodohkanAction(): Action
    {
        return Action::make('jodohkan')
            ->label('Cocokkan Manual / Tentukan Status')
            ->modalHeading('Konfirmasi & Cocokkan Obat')
            ->modalDescription(function (Action $action) {
                $args = $action->getArguments();
                $raw = $args['raw_name'] ?? 'Obat ini';
                $count = (int) ($args['candidates_count'] ?? 0);
                if ($count > 1) {
                    return "Ditemukan {$count} produk cocok di katalog distributor untuk '{$raw}'. Rekomendasi terdekat telah otomatis dipilih.";
                } elseif ($count === 1) {
                    return "Ditemukan 1 produk rekomendasi di katalog distributor untuk '{$raw}'.";
                }

                return "Tentukan apakah '{$raw}' dicocokkan ke master PBF, atau ditandai sebagai non-PBF/belum ada distributor.";
            })
            ->form([
                Hidden::make('candidate_ids'),

                TextInput::make('raw_name')
                    ->label('Nama Asli di Defecta')
                    ->disabled(),

                Radio::make('action_type')
                    ->label('Keputusan untuk Item Ini')
                    ->options([
                        'match' => 'Cocokkan ke Obat Master PBF',
                        'not_stocked' => 'Bukan Stok PBF (Belanja Mandiri / Swalayan / Alkes)',
                        'unlisted' => 'Obat Belum Dijual PBF Rekanan Manapun',
                    ])
                    ->default('match')
                    ->live()
                    ->required(),

                Select::make('normalized_product_id')
                    ->label('Pilih Obat Asli di Master PBF')
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search): array {
                        $search = trim($search);
                        if (mb_strlen($search) < 2) {
                            return [];
                        }

                        $terms = array_filter(explode(' ', $search));
                        $query = NormalizedProduct::query();

                        foreach ($terms as $term) {
                            $query->where(function ($q) use ($term) {
                                $q->where('parsed_name', 'like', "%{$term}%")
                                    ->orWhere('normalized_name', 'like', "%{$term}%")
                                    ->orWhere('parsed_strength', 'like', "%{$term}%");
                            });
                        }

                        return $query->limit(30)
                            ->get()
                            ->mapWithKeys(function ($p) {
                                $strength = $p->parsed_strength ? " {$p->parsed_strength}" : '';

                                return [$p->id => trim("{$p->parsed_name}{$strength}")];
                            })
                            ->all();
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        if (! $value) {
                            return null;
                        }

                        $p = NormalizedProduct::find($value);
                        if (! $p) {
                            return null;
                        }

                        $strength = $p->parsed_strength ? " {$p->parsed_strength}" : '';

                        return trim("{$p->parsed_name}{$strength}");
                    })
                    ->options(function ($get): array {
                        $ids = array_filter(explode(',', (string) $get('candidate_ids')));
                        if ($selectedId = $get('normalized_product_id')) {
                            $ids[] = $selectedId;
                        }
                        $ids = array_unique(array_filter(array_map('intval', $ids)));

                        if (empty($ids)) {
                            return [];
                        }

                        return NormalizedProduct::query()
                            ->whereIn('id', $ids)
                            ->get()
                            ->mapWithKeys(function ($p) {
                                $strength = $p->parsed_strength ? " {$p->parsed_strength}" : '';

                                return [$p->id => trim("{$p->parsed_name}{$strength}")];
                            })
                            ->all();
                    })
                    ->visible(fn ($get) => $get('action_type') === 'match')
                    ->required(fn ($get) => $get('action_type') === 'match')
                    ->helperText(fn ($get) => $get('normalized_product_id')
                        ? 'Rekomendasi terbaik telah dipilih otomatis. Anda dapat menggantinya jika perlu.'
                        : 'Ketik nama obat untuk mencari di katalog distributor atau pilih dari rekomendasi.'),
            ])
            ->fillForm(function (array $arguments) {
                return [
                    'candidate_ids' => $arguments['candidate_ids'] ?? '',
                    'raw_name' => $arguments['raw_name'] ?? '',
                    'action_type' => 'match',
                    'normalized_product_id' => ! empty($arguments['suggested_id']) ? (int) $arguments['suggested_id'] : null,
                ];
            })
            ->action(function (array $data, array $arguments) {
                $rawName = $arguments['raw_name'] ?? '';
                if (! $rawName) {
                    return;
                }

                $normalizer = app(ProductNormalizationService::class);
                $parsed = $normalizer->parseDrugName($rawName, '');
                $actionType = $data['action_type'] ?? 'match';

                if ($actionType === 'not_stocked') {
                    ProductAlias::updateOrCreate(
                        ['alias_normalized' => $parsed['canonical']],
                        [
                            'alias_raw' => $rawName,
                            'status' => 'not_stocked',
                            'normalized_product_id' => null,
                        ]
                    );

                    Notification::make()
                        ->title('Ditandai sebagai Bukan Stok PBF (Belanja Mandiri)')
                        ->success()
                        ->send();
                } elseif ($actionType === 'unlisted') {
                    ProductAlias::updateOrCreate(
                        ['alias_normalized' => $parsed['canonical']],
                        [
                            'alias_raw' => $rawName,
                            'status' => 'unlisted',
                            'normalized_product_id' => null,
                        ]
                    );

                    Notification::make()
                        ->title('Ditandai sebagai Belum Tersedia di PBF Manapun')
                        ->success()
                        ->send();
                } else {
                    $targetProduct = NormalizedProduct::find($data['normalized_product_id']);
                    ProductAlias::updateOrCreate(
                        ['alias_normalized' => $parsed['canonical']],
                        [
                            'alias_raw' => $rawName,
                            'status' => 'matched',
                            'normalized_product_id' => $data['normalized_product_id'],
                            'target_canonical' => $targetProduct?->normalized_name,
                        ]
                    );

                    Notification::make()
                        ->title('Berhasil dicocokkan!')
                        ->success()
                        ->send();
                }

                $this->recalculateSingleItem($rawName);
            });
    }

    public function tandaiNonPbfAction(): Action
    {
        return Action::make('tandaiNonPbf')
            ->label('Tandai Bukan Stok PBF')
            ->requiresConfirmation()
            ->modalHeading('Tandai Sebagai Bukan Stok PBF?')
            ->modalDescription('Barang ini akan ditandai sebagai belanja mandiri / non-PBF dan dipisahkan dari Surat Pesanan distributor.')
            ->action(function (array $arguments) {
                $rawName = $arguments['raw_name'] ?? '';
                if (! $rawName) {
                    return;
                }

                $normalizer = app(ProductNormalizationService::class);
                $parsed = $normalizer->parseDrugName($rawName, '');

                ProductAlias::updateOrCreate(
                    ['alias_normalized' => $parsed['canonical']],
                    [
                        'alias_raw' => $rawName,
                        'status' => 'not_stocked',
                        'normalized_product_id' => null,
                    ]
                );

                Notification::make()
                    ->title("{$rawName} ditandai sebagai Bukan Stok PBF")
                    ->success()
                    ->send();

                $this->recalculateSingleItem($rawName);
            });
    }

    public function recalculateSingleItem(string $rawName): void
    {
        if (empty($this->calculationResults)) {
            $this->calculate();

            return;
        }

        $service = app(OrderCalculationService::class);
        $found = false;

        foreach ($this->calculationResults as $idx => $res) {
            if (($res['request']['product_name'] ?? '') === $rawName) {
                $qty = (int) ($res['request']['qty'] ?? 1);
                $calc = $service->calculateBestSupplier($rawName, $qty);

                $this->calculationResults[$idx]['calculation'] = $calc;
                $found = true;
            }
        }

        if (! $found) {
            $this->calculate();
        }
    }

    public function searchNormalizedProducts(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $terms = array_filter(explode(' ', $query));
        $dbQuery = NormalizedProduct::query();

        foreach ($terms as $term) {
            $dbQuery->where(function ($q) use ($term) {
                $q->where('parsed_name', 'like', "%{$term}%")
                    ->orWhere('normalized_name', 'like', "%{$term}%")
                    ->orWhere('parsed_strength', 'like', "%{$term}%");
            });
        }

        return $dbQuery->limit(25)
            ->get()
            ->map(function ($p) {
                $strength = $p->parsed_strength ? " {$p->parsed_strength}" : '';

                return [
                    'id' => $p->id,
                    'name' => trim("{$p->parsed_name}{$strength}"),
                ];
            })
            ->all();
    }

    public function saveBatchMatching(array $decisions): void
    {
        if (empty($decisions)) {
            return;
        }

        $normalizer = app(ProductNormalizationService::class);
        $matchedCount = 0;
        $notStockedCount = 0;
        $unlistedCount = 0;
        $recalculateNames = [];

        DB::transaction(function () use ($decisions, $normalizer, &$matchedCount, &$notStockedCount, &$unlistedCount, &$recalculateNames) {
            foreach ($decisions as $item) {
                $rawName = $item['raw_name'] ?? '';
                $actionType = $item['action_type'] ?? 'skip';
                $productId = ! empty($item['normalized_product_id']) ? (int) $item['normalized_product_id'] : null;

                if (! $rawName || $actionType === 'skip') {
                    continue;
                }

                $parsed = $normalizer->parseDrugName($rawName, '');
                $canonical = $parsed['canonical'];

                if ($actionType === 'not_stocked') {
                    ProductAlias::updateOrCreate(
                        ['alias_normalized' => $canonical],
                        [
                            'alias_raw' => $rawName,
                            'status' => 'not_stocked',
                            'normalized_product_id' => null,
                        ]
                    );
                    $notStockedCount++;
                    $recalculateNames[] = $rawName;
                } elseif ($actionType === 'unlisted') {
                    ProductAlias::updateOrCreate(
                        ['alias_normalized' => $canonical],
                        [
                            'alias_raw' => $rawName,
                            'status' => 'unlisted',
                            'normalized_product_id' => null,
                        ]
                    );
                    $unlistedCount++;
                    $recalculateNames[] = $rawName;
                } elseif ($actionType === 'match' && $productId) {
                    $targetProduct = NormalizedProduct::find($productId);
                    ProductAlias::updateOrCreate(
                        ['alias_normalized' => $canonical],
                        [
                            'alias_raw' => $rawName,
                            'status' => 'matched',
                            'normalized_product_id' => $productId,
                            'target_canonical' => $targetProduct?->normalized_name,
                        ]
                    );
                    $matchedCount++;
                    $recalculateNames[] = $rawName;
                }
            }
        });

        $totalUpdated = $matchedCount + $notStockedCount + $unlistedCount;
        if ($totalUpdated > 0) {
            $this->recalculateMultipleItems($recalculateNames);

            Notification::make()
                ->title("Berhasil memproses {$totalUpdated} obat!")
                ->body("{$matchedCount} obat dicocokkan, {$notStockedCount} bukan stok PBF, {$unlistedCount} belum ada distributor.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Tidak ada perubahan yang disimpan')
                ->info()
                ->send();
        }
    }

    public function recalculateMultipleItems(array $rawNames): void
    {
        if (empty($this->calculationResults)) {
            $this->calculate();

            return;
        }

        $service = app(OrderCalculationService::class);
        $namesSet = array_flip($rawNames);

        foreach ($this->calculationResults as $idx => $res) {
            $productName = $res['request']['product_name'] ?? '';
            if (isset($namesSet[$productName])) {
                $qty = (int) ($res['request']['qty'] ?? 1);
                $this->calculationResults[$idx]['calculation'] = $service->calculateBestSupplier($productName, $qty);
            }
        }
    }
}
