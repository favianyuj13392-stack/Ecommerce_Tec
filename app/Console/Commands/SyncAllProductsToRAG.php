<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Product;
use App\Jobs\SyncProductWithRAGJob;
use Illuminate\Support\Facades\Log;

class SyncAllProductsToRAG extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rag:sync-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispara el primer pipeline para crear todos los Vectores Semánticos en FastAPI';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🔄 Iniciando Carga Masiva RAG hacia FastAPI...");

        $products = Product::all();
        $bar = $this->output->createProgressBar(count($products));

        $bar->start();

        foreach ($products as $product) {
            // Despachamos síncronamente en CLI para ver progreso al instante (opcional)
            SyncProductWithRAGJob::dispatchSync($product);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ ¡Todos los productos han sido Hidratados y Vectorizados en bot_rag_db!");
    }
}
