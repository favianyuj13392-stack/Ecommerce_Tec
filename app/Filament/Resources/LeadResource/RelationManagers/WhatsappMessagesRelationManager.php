<?php

namespace App\Filament\Resources\LeadResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WhatsappMessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'whatsappMessages';

    protected static ?string $title = 'Chat de WhatsApp';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('body')
                    ->required()
                    ->maxLength(65535),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->columns([]) // Escondemos las columnas por defecto
            ->content(fn () => view('filament.resources.lead-resource.relation-managers.chat', [
                'messages' => $this->getOwnerRecord()->whatsappMessages()->orderBy('created_at', 'asc')->get(),
            ]))
            ->headerActions([
                Tables\Actions\Action::make('toggle_ai')
                    ->label(fn () => $this->getOwnerRecord()->is_ai_enabled ? 'Desactivar IA' : 'Activar IA')
                    ->color(fn () => $this->getOwnerRecord()->is_ai_enabled ? 'danger' : 'success')
                    ->icon('heroicon-o-cpu-chip')
                    ->action(function () {
                        $lead = $this->getOwnerRecord();
                        $lead->update(['is_ai_enabled' => !$lead->is_ai_enabled]);
                    }),
                Tables\Actions\Action::make('responder')
                    ->label('Responder Manualmente')
                    ->icon('heroicon-o-paper-airplane')
                    ->form([
                        Forms\Components\Textarea::make('message')
                            ->label('Tu Mensaje')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (array $data) {
                        $lead = $this->getOwnerRecord();
                        
                        // Enviar por Meta API
                        $whatsappService = app(\App\Services\WhatsAppService::class);
                        $whatsappService->sendMessage($lead->whatsapp_id, $data['message']);
                        
                        // Persistir Outbound Manual
                        $lead->whatsappMessages()->create([
                            'body' => $data['message'],
                            'direction' => 'outbound',
                            'source' => 'manual',
                        ]);

                        // Silenciar automáticamente a la IA si intervenimos
                        $lead->update(['is_ai_enabled' => false]);
                    }),
            ]);
    }
}
