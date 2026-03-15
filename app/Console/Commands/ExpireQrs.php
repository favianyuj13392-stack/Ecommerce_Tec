<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Qr;
use Illuminate\Support\Facades\DB;

class ExpireQrs extends Command
{
    protected $signature = 'qrs:expire';
    protected $description = 'Libera stock y cancela órdenes de QRs vencidos';

    public function handle()
    {
        $now = now();
        $query = Qr::where('status', 'new')->where('expiration_date', '<', $now);
        $totalProcessed = 0;

        // Utilizamos chunkById para manejar grandes volúmenes y evitar desbordamiento de RAM
        $query->chunkById(100, function ($qrs) use (&$totalProcessed) {
            foreach ($qrs as $qrTemplate) {
                DB::transaction(function () use ($qrTemplate, &$totalProcessed) {
                    // Bloqueo idempotente: Nos aseguramos de que otro proceso no lo haya cancelado ya
                    $qr = Qr::where('id', $qrTemplate->id)
                        ->where('status', 'new')
                        ->lockForUpdate()
                        ->first();

                    if (! $qr) {
                        return; // Ya fue procesado
                    }

                    $order = $qr->order()->with(['items.variant', 'items.product'])->first();

                    if ($order && $order->status !== 'cancelled') {
                        // Restauramos stock meticulosamente
                        foreach ($order->items as $item) {
                            if ($item->variant_id && $item->variant) {
                                $item->variant->increment('stock', $item->quantity);
                            } elseif ($item->product) {
                                // Para productos sin variante.
                                $item->product->increment('stock', $item->quantity);
                            }
                        }
                        $order->update(['status' => 'cancelled']);
                    }
                    
                    $qr->update(['status' => 'expired']);
                    $totalProcessed++;
                });
            }
        });

        $this->info('Proceso terminado, '.$totalProcessed.' códigos expirados liberados.');
    }
}
