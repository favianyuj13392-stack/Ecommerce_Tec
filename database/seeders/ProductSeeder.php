<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $productos = [
            // Fundas
            ['nombre' => 'Funda de Silicona Ultrafina', 'categoria' => 'Fundas', 'marca' => 'Spigen', 'precio' => 120.00],
            ['nombre' => 'Funda Transparente Antichoque', 'categoria' => 'Fundas', 'marca' => 'OtterBox', 'precio' => 180.00],
            ['nombre' => 'Funda de Cuero Premium', 'categoria' => 'Fundas', 'marca' => 'Apple', 'precio' => 250.00],
            ['nombre' => 'Funda Rígida Mate', 'categoria' => 'Fundas', 'marca' => 'Nillkin', 'precio' => 140.00],
            // Protectores
            ['nombre' => 'Protector de Pantalla Cristal Templado', 'categoria' => 'Protectores', 'marca' => 'Belkin', 'precio' => 80.00],
            ['nombre' => 'Protector Hidrogel Flexible', 'categoria' => 'Protectores', 'marca' => 'RockSpace', 'precio' => 60.00],
            ['nombre' => 'Protector Lente de Cámara', 'categoria' => 'Protectores', 'marca' => 'Baseus', 'precio' => 45.00],
            ['nombre' => 'Protector Privacidad Anti-Espía', 'categoria' => 'Protectores', 'marca' => 'Spigen', 'precio' => 110.00],
            // Cables
            ['nombre' => 'Cable USB-C a Lightning 1m', 'categoria' => 'Cables', 'marca' => 'Anker', 'precio' => 90.00],
            ['nombre' => 'Cable Tipo C a Tipo C Carga Rápida 2m', 'categoria' => 'Cables', 'marca' => 'Ugreen', 'precio' => 75.00],
            ['nombre' => 'Cable Trenzado Magnético 3 en 1', 'categoria' => 'Cables', 'marca' => 'Essager', 'precio' => 55.00],
            // Audífonos
            ['nombre' => 'Audífonos Inalámbricos Pro', 'categoria' => 'Audífonos', 'marca' => 'Samsung', 'precio' => 850.00],
            ['nombre' => 'Auriculares In-Ear Básicos', 'categoria' => 'Audífonos', 'marca' => 'Sony', 'precio' => 150.00],
            // Cargadores
            ['nombre' => 'Cargador Rápido 20W USB-C', 'categoria' => 'Cargadores', 'marca' => 'Apple', 'precio' => 190.00],
            ['nombre' => 'Cargador Inalámbrico MagSafe', 'categoria' => 'Cargadores', 'marca' => 'Anker', 'precio' => 220.00]
        ];

        foreach ($productos as $item) {
            $product = Product::create([
                'nombre' => $item['nombre'],
                'slug' => Str::slug($item['nombre'] . '-' . Str::random(4)),
                'descripcion' => 'Excelente ' . strtolower($item['nombre']) . ' de la marca ' . $item['marca'] . '. Protege y mejora tu dispositivo.',
                'precio' => $item['precio'],
                'marca' => $item['marca'],
                'categoria' => $item['categoria'],
                'attributes' => ['material' => 'Premium', 'garantia' => '6 meses'],
                'has_variants' => true,
            ]);

            $modelos_compatibles = ['iPhone 13', 'iPhone 14', 'iPhone 15', 'Samsung S23', 'Samsung S24'];
            $colores = ['Negro', 'Azul', 'Rojo', 'Blanco', 'Transparente'];

            if (in_array($item['categoria'], ['Fundas', 'Protectores'])) {
                // Al menos 5 variantes para Fundas y Protectores
                for ($i = 0; $i < 5; $i++) {
                    $product->variants()->create([
                        'sku' => strtoupper(Str::random(8)),
                        'price' => $item['precio'],
                        'stock' => rand(5, 50),
                        'variant_attributes' => [
                            'color' => $colores[array_rand($colores)],
                            'modelo_compatible' => $modelos_compatibles[array_rand($modelos_compatibles)],
                        ],
                    ]);
                }
            } else {
                // 2 variantes para los demás
                for ($i = 0; $i < 2; $i++) {
                    $product->variants()->create([
                        'sku' => strtoupper(Str::random(8)),
                        'price' => $item['precio'],
                        'stock' => rand(5, 50),
                        'variant_attributes' => [
                            'color' => $colores[array_rand($colores)],
                            'modelo_compatible' => 'Universal',
                        ],
                    ]);
                }
            }
        }
    }
}
