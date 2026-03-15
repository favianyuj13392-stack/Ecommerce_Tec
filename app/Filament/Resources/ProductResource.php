<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('descripcion')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('precio')
                    ->required()
                    ->numeric(),
                Forms\Components\Toggle::make('has_variants')
                    ->live()
                    ->required(),
                Forms\Components\KeyValue::make('attributes')
                    ->label('Atributos (Ej: Material -> Cuero)')
                    ->columnSpanFull(),
                Forms\Components\Repeater::make('variants')
                    ->relationship('variants')
                    ->schema([
                        Forms\Components\TextInput::make('sku'),
                        Forms\Components\TextInput::make('price')
                            ->numeric()
                            ->label('Precio (Opcional)'),
                        Forms\Components\TextInput::make('stock')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Forms\Components\KeyValue::make('variant_attributes')
                            ->label('Atributos Específicos (Ej: Talla -> M, Color -> Rojo)'),
                    ])
                    ->hidden(fn (\Filament\Forms\Get $get): bool => ! $get('has_variants'))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('precio')
                    ->numeric()
                    ->sortable()
                    ->money('USD'),
                Tables\Columns\IconColumn::make('has_variants')
                    ->boolean()
                    ->label('Tiene Variantes'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
