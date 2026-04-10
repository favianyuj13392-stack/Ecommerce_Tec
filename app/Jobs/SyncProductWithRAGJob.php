<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncProductWithRAGJob implements ShouldQueue
{
    use Queueable;

    public $product;
    public $tries = 3; 
    public $backoff = 10; 

    /**
     * Create a new job instance.
     */
    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Sincronizando Producto ID: {$this->product->id} con RAG...");

        $this->product->load('variants');
        $variantes_text = $this->product->variants->map(function($v) {
            return "- Variante: {$v->atributos}, Precio: {$v->precio} Bs, Stock: {$v->stock}";
        })->implode("\n");

        $vector_text = "Producto: {$this->product->nombre} | Precio Base: {$this->product->precio} Bs | Categoría: {$this->product->categoria}\n" .
                       "Descripción: {$this->product->descripcion}\n" .
                       "Variantes Disponibles:\n{$variantes_text}";

        Http::timeout(10)->post('http://localhost:8001/internal/sync-products', [
            'id' => $this->product->id,
            'nombre' => $this->product->nombre,
            'categoria' => $this->product->categoria,
            'vector_text' => $vector_text
        ])->throw();
    }
}
