<x-filament::card class="flex flex-col h-[600px]">
    <div 
        class="flex-1 overflow-y-auto p-4 space-y-4"
        wire:poll.3s
    >
        @foreach($messages as $message)
            <div class="flex w-full {{ $message->direction === 'inbound' ? 'justify-start' : 'justify-end' }}">
                <div class="max-w-[75%] rounded-lg p-3 {{ 
                    $message->direction === 'inbound' 
                        ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100' 
                        : ($message->source === 'ai' ? 'bg-primary-500 text-white' : 'bg-success-500 text-white') 
                }}">
                    <div class="text-xs opacity-75 mb-1 flex justify-between">
                        <span>{{ $message->source === 'user' ? 'Cliente' : ($message->source === 'ai' ? 'IA' : 'Tú') }} • {{ $message->created_at->format('H:i') }}</span>
                    </div>
                    <div class="whitespace-pre-wrap text-sm">
                        {{ $message->body }}
                    </div>
                    @if($message->source === 'ai' && $message->tokens_used)
                        <div class="text-xs opacity-75 mt-1 text-right flex items-center justify-end gap-1">
                            <x-heroicon-o-bolt class="w-3 h-3"/> {{ $message->tokens_used }} tokens
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
        
        @if($messages->isEmpty())
            <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                No hay mensajes aún en esta conversación.
            </div>
        @endif
    </div>
</x-filament::card>
