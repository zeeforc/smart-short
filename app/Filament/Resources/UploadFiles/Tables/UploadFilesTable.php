<?php

namespace App\Filament\Resources\UploadFiles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UploadFilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('3s')
            ->columns([
                TextColumn::make('upload_batch_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('supplier_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('file_path')
                    ->searchable(),
                TextColumn::make('progress')
                    ->label('Progress (%)')
                    ->getStateUsing(function ($record) {
                        if ($record->total_rows == 0) {
                            return 0;
                        }
                        $progress = ($record->processed_rows / $record->total_rows) * 100;

                        return min(100, round($progress));
                    })
                    ->formatStateUsing(fn ($state) => "
                        <div class='flex items-center gap-2' style='min-width: 150px;'>
                            <div class='w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700 overflow-hidden'>
                                <div class='bg-primary-600 h-2.5 rounded-full' style='width: {$state}%'></div>
                            </div>
                            <span class='text-sm text-gray-500 font-medium'>{$state}%</span>
                        </div>
                    ")
                    ->html(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'parsing' => 'warning',
                        'matched' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
