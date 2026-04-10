<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductVariant;

class LegacyDataSeeder extends Seeder
{
    public function run(): void
    {
        ProductVariant::query()->delete();
        Product::query()->delete();
        Product::create([
            'id' => 1,
            'nombre' => 'Funda de Silicona Ultrafina',
            'slug' => 'funda-de-silicona-ultrafina-l2kj',
            'descripcion' => 'Excelente funda de silicona ultrafina de la marca Spigen. Protege y mejora tu dispositivo.',
            'precio' => 800.0,
            'marca' => 'Spigen',
            'categoria' => 'Fundas',
            'attributes' => '{"garantia": "6 meses", "material": "Premium"}',
            'has_variants' => 1,
            'created_at' => '2026-03-16 19:46:50',
            'updated_at' => '2026-04-05 00:25:21'
        ]);
        Product::create([
            'id' => 2,
            'nombre' => 'Funda Transparente Antichoque',
            'slug' => 'funda-transparente-antichoque-o0pp',
            'descripcion' => 'Excelente funda transparente antichoque de la marca OtterBox. Protege y mejora tu dispositivo.',
            'precio' => 180.0,
            'marca' => 'OtterBox',
            'categoria' => 'Fundas',
            'attributes' => '{"garantia": "6 meses", "material": "Premium"}',
            'has_variants' => 1,
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        Product::create([
            'id' => 3,
            'nombre' => 'Funda de Cuero Premium',
            'slug' => 'funda-de-cuero-premium-kxw6',
            'descripcion' => 'Excelente funda de cuero premium de la marca Apple. Protege y mejora tu dispositivo.',
            'precio' => 250.0,
            'marca' => 'Apple',
            'categoria' => 'Fundas',
            'attributes' => '{"garantia": "6 meses", "material": "Premium"}',
            'has_variants' => 1,
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        Product::create([
            'id' => 4,
            'nombre' => 'Funda Rígida Mate',
            'slug' => 'funda-rigida-mate-5fvk',
            'descripcion' => 'Excelente funda rígida mate de la marca Nillkin. Protege y mejora tu dispositivo.',
            'precio' => 140.0,
            'marca' => 'Nillkin',
            'categoria' => 'Fundas',
            'attributes' => '{"garantia": "6 meses", "material": "Premium"}',
            'has_variants' => 1,
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        Product::create([
            'id' => 5,
            'nombre' => 'Protector de Pantalla Cristal Templado',
            'slug' => 'protector-de-pantalla-cristal-templado-a5tu',
            'descripcion' => 'Excelente protector de pantalla cristal templado de la marca Belkin. Protege y mejora tu dispositivo.',
            'precio' => 80.0,
            'marca' => 'Belkin',
            'categoria' => 'Protectores',
            'attributes' => '{"garantia": "6 meses", "material": "Premium"}',
            'has_variants' => 1,
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        Product::create([
            'id' => 6,
            'nombre' => 'Protector Hidrogel Flexible',
            'slug' => 'protector-hidrogel-flexible-0ene',
            'descripcion' => 'Excelente protector hidrogel flexible de la marca RockSpace. Protege y mejora tu dispositivo.',
            'precio' => 60.0,
            'marca' => 'RockSpace',
            'categoria' => 'Protectores',
            'attributes' => '{"garantia": "6 meses", "material": "Premium"}',
            'has_variants' => 1,
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        Product::create([
            'id' => 7,
            'nombre' => 'Protector Lente de Cámara',
            'slug' => 'protector-lente-de-camara-npvq',
            'descripcion' => 'Excelente protector lente de cámara de la marca Baseus. Protege y mejora tu dispositivo.',
            'precio' => 45.0,
            'marca' => 'Baseus',
            'categoria' => 'Protectores',
            'attributes' => '{"garantia": "6 meses", "material": "Premium"}',
            'has_variants' => 1,
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        Product::create([
            'id' => 8,
            'nombre' => 'Protector Privacidad Anti-Espía',
            'slug' => 'protector-privacidad-anti-espia-atjc',
            'descripcion' => 'Excelente protector privacidad anti-espía de la marca Spigen. Protege y mejora tu dispositivo.',
            'precio' => 110.0,
            'marca' => 'Spigen',
            'categoria' => 'Protectores',
            'attributes' => '{"garantia": "6 meses", "material": "Premium"}',
            'has_variants' => 1,
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        Product::create([
            'id' => 9,
            'nombre' => 'Cable USB-C a Lightning 1m',
            'slug' => 'cable-usb-c-a-lightning-1m-ppkh',
            'descripcion' => 'Excelente cable usb-c a lightning 1m de la marca Anker. Protege y mejora tu dispositivo.',
            'precio' => 90.0,
            'marca' => 'Anker',
            'categoria' => 'Cables',
            'attributes' => '{"garantia": "6 meses", "material": "Premium"}',
            'has_variants' => 1,
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        Product::create([
            'id' => 10,
            'nombre' => 'Cable Tipo C a Tipo C Carga Rápida 2m',
            'slug' => 'cable-tipo-c-a-tipo-c-carga-rapida-2m-icqy',
            'descripcion' => 'Excelente cable tipo c a tipo c carga rápida 2m de la marca Ugreen. Protege y mejora tu dispositivo.',
            'precio' => 75.0,
            'marca' => 'Ugreen',
            'categoria' => 'Cables',
            'attributes' => '{"garantia": "6 meses", "material": "Premium"}',
            'has_variants' => 1,
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        Product::create([
            'id' => 11,
            'nombre' => 'Cable Trenzado Magnético 3 en 1',
            'slug' => 'cable-trenzado-magnetico-3-en-1-ur23',
            'descripcion' => 'Excelente cable trenzado magnético 3 en 1 de la marca Essager. Protege y mejora tu dispositivo.',
            'precio' => 55.0,
            'marca' => 'Essager',
            'categoria' => 'Cables',
            'attributes' => '{"garantia": "6 meses", "material": "Premium"}',
            'has_variants' => 1,
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        Product::create([
            'id' => 12,
            'nombre' => 'Audífonos Inalámbricos Pro',
            'slug' => 'audifonos-inalambricos-pro-b4jh',
            'descripcion' => 'Excelente audífonos inalámbricos pro de la marca Samsung. Protege y mejora tu dispositivo.',
            'precio' => 850.0,
            'marca' => 'Samsung',
            'categoria' => 'Audífonos',
            'attributes' => '{"garantia": "6 meses", "material": "Premium"}',
            'has_variants' => 1,
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        Product::create([
            'id' => 13,
            'nombre' => 'Auriculares In-Ear Básicos',
            'slug' => 'auriculares-in-ear-basicos-fd3d',
            'descripcion' => 'Excelente auriculares in-ear básicos de la marca Sony. Protege y mejora tu dispositivo.',
            'precio' => 150.0,
            'marca' => 'Sony',
            'categoria' => 'Audífonos',
            'attributes' => '{"garantia": "6 meses", "material": "Premium"}',
            'has_variants' => 1,
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        Product::create([
            'id' => 14,
            'nombre' => 'Cargador Rápido 20W USB-C',
            'slug' => 'cargador-rapido-20w-usb-c-tfhx',
            'descripcion' => 'Excelente cargador rápido 20w usb-c de la marca Apple. Protege y mejora tu dispositivo.',
            'precio' => 190.0,
            'marca' => 'Apple',
            'categoria' => 'Cargadores',
            'attributes' => '{"garantia": "6 meses", "material": "Premium"}',
            'has_variants' => 1,
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        Product::create([
            'id' => 15,
            'nombre' => 'Cargador Inalámbrico MagSafe',
            'slug' => 'cargador-inalambrico-magsafe-eoga',
            'descripcion' => 'Excelente cargador inalámbrico magsafe de la marca Anker. Protege y mejora tu dispositivo.',
            'precio' => 220.0,
            'marca' => 'Anker',
            'categoria' => 'Cargadores',
            'attributes' => '{"garantia": "6 meses", "material": "Premium"}',
            'has_variants' => 1,
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 1,
            'product_id' => 1,
            'sku' => 'GCWHMAZB',
            'price' => 120.0,
            'stock' => 20,
            'variant_attributes' => '{"color": "Rojo", "modelo_compatible": "iPhone 14"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 2,
            'product_id' => 1,
            'sku' => '2AA7X3KI',
            'price' => 120.0,
            'stock' => 22,
            'variant_attributes' => '{"color": "Transparente", "modelo_compatible": "iPhone 13"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 3,
            'product_id' => 1,
            'sku' => 'NQZB4IAK',
            'price' => 120.0,
            'stock' => 28,
            'variant_attributes' => '{"color": "Transparente", "modelo_compatible": "Samsung S23"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 4,
            'product_id' => 1,
            'sku' => 'RIUIXBXV',
            'price' => 120.0,
            'stock' => 29,
            'variant_attributes' => '{"color": "Blanco", "modelo_compatible": "iPhone 13"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 5,
            'product_id' => 1,
            'sku' => 'BWY7CGHP',
            'price' => 120.0,
            'stock' => 26,
            'variant_attributes' => '{"color": "Azul", "modelo_compatible": "iPhone 14"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 6,
            'product_id' => 2,
            'sku' => 'C5WMKBMP',
            'price' => 180.0,
            'stock' => 27,
            'variant_attributes' => '{"color": "Rojo", "modelo_compatible": "iPhone 14"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 7,
            'product_id' => 2,
            'sku' => 'RCVAEQ59',
            'price' => 180.0,
            'stock' => 25,
            'variant_attributes' => '{"color": "Rojo", "modelo_compatible": "iPhone 13"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 8,
            'product_id' => 2,
            'sku' => 'PGQPEPOQ',
            'price' => 180.0,
            'stock' => 20,
            'variant_attributes' => '{"color": "Negro", "modelo_compatible": "iPhone 15"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 9,
            'product_id' => 2,
            'sku' => 'KYXDCWIK',
            'price' => 180.0,
            'stock' => 34,
            'variant_attributes' => '{"color": "Transparente", "modelo_compatible": "iPhone 14"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 10,
            'product_id' => 2,
            'sku' => 'CNBVWNVH',
            'price' => 180.0,
            'stock' => 32,
            'variant_attributes' => '{"color": "Azul", "modelo_compatible": "iPhone 13"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 11,
            'product_id' => 3,
            'sku' => 'TWW6QTBB',
            'price' => 250.0,
            'stock' => 39,
            'variant_attributes' => '{"color": "Transparente", "modelo_compatible": "Samsung S23"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 12,
            'product_id' => 3,
            'sku' => 'PO0WUUN3',
            'price' => 250.0,
            'stock' => 5,
            'variant_attributes' => '{"color": "Negro", "modelo_compatible": "Samsung S24"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 13,
            'product_id' => 3,
            'sku' => 'JSUMPVO8',
            'price' => 250.0,
            'stock' => 29,
            'variant_attributes' => '{"color": "Negro", "modelo_compatible": "iPhone 15"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 14,
            'product_id' => 3,
            'sku' => 'FVODAGEE',
            'price' => 250.0,
            'stock' => 50,
            'variant_attributes' => '{"color": "Blanco", "modelo_compatible": "iPhone 13"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 15,
            'product_id' => 3,
            'sku' => 'ZH66ET6Z',
            'price' => 250.0,
            'stock' => 22,
            'variant_attributes' => '{"color": "Rojo", "modelo_compatible": "iPhone 15"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 16,
            'product_id' => 4,
            'sku' => 'PS5LZMFF',
            'price' => 140.0,
            'stock' => 20,
            'variant_attributes' => '{"color": "Blanco", "modelo_compatible": "Samsung S24"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 17,
            'product_id' => 4,
            'sku' => 'ZPWYEH0I',
            'price' => 140.0,
            'stock' => 11,
            'variant_attributes' => '{"color": "Rojo", "modelo_compatible": "iPhone 14"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 18,
            'product_id' => 4,
            'sku' => 'A14YMJQS',
            'price' => 140.0,
            'stock' => 24,
            'variant_attributes' => '{"color": "Azul", "modelo_compatible": "iPhone 14"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 19,
            'product_id' => 4,
            'sku' => '3CJPCJOD',
            'price' => 140.0,
            'stock' => 49,
            'variant_attributes' => '{"color": "Azul", "modelo_compatible": "Samsung S24"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 20,
            'product_id' => 4,
            'sku' => 'U1TDZ1KZ',
            'price' => 140.0,
            'stock' => 7,
            'variant_attributes' => '{"color": "Blanco", "modelo_compatible": "iPhone 15"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 21,
            'product_id' => 5,
            'sku' => 'Q9BXTNOZ',
            'price' => 80.0,
            'stock' => 31,
            'variant_attributes' => '{"color": "Blanco", "modelo_compatible": "iPhone 15"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 22,
            'product_id' => 5,
            'sku' => 'PBHY66DW',
            'price' => 80.0,
            'stock' => 32,
            'variant_attributes' => '{"color": "Azul", "modelo_compatible": "iPhone 15"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 23,
            'product_id' => 5,
            'sku' => 'DVYVWICX',
            'price' => 80.0,
            'stock' => 31,
            'variant_attributes' => '{"color": "Transparente", "modelo_compatible": "iPhone 14"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 24,
            'product_id' => 5,
            'sku' => 'G8JMXHTN',
            'price' => 80.0,
            'stock' => 24,
            'variant_attributes' => '{"color": "Azul", "modelo_compatible": "iPhone 13"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 25,
            'product_id' => 5,
            'sku' => 'GK7AVFJW',
            'price' => 80.0,
            'stock' => 26,
            'variant_attributes' => '{"color": "Transparente", "modelo_compatible": "Samsung S23"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 26,
            'product_id' => 6,
            'sku' => '1WOTUJGX',
            'price' => 60.0,
            'stock' => 46,
            'variant_attributes' => '{"color": "Transparente", "modelo_compatible": "Samsung S23"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 27,
            'product_id' => 6,
            'sku' => 'K530XZ5Q',
            'price' => 60.0,
            'stock' => 39,
            'variant_attributes' => '{"color": "Rojo", "modelo_compatible": "iPhone 14"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 28,
            'product_id' => 6,
            'sku' => 'QMDDVDB6',
            'price' => 60.0,
            'stock' => 47,
            'variant_attributes' => '{"color": "Negro", "modelo_compatible": "iPhone 15"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 29,
            'product_id' => 6,
            'sku' => 'CPQTRRE6',
            'price' => 60.0,
            'stock' => 23,
            'variant_attributes' => '{"color": "Transparente", "modelo_compatible": "iPhone 14"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 30,
            'product_id' => 6,
            'sku' => 'XCJYV9XI',
            'price' => 60.0,
            'stock' => 29,
            'variant_attributes' => '{"color": "Rojo", "modelo_compatible": "Samsung S23"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 31,
            'product_id' => 7,
            'sku' => 'OBVRSSUZ',
            'price' => 45.0,
            'stock' => 50,
            'variant_attributes' => '{"color": "Negro", "modelo_compatible": "Samsung S23"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 32,
            'product_id' => 7,
            'sku' => 'ZQWUIQZ0',
            'price' => 45.0,
            'stock' => 33,
            'variant_attributes' => '{"color": "Rojo", "modelo_compatible": "Samsung S24"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 33,
            'product_id' => 7,
            'sku' => 'NLFYGAYD',
            'price' => 45.0,
            'stock' => 47,
            'variant_attributes' => '{"color": "Negro", "modelo_compatible": "iPhone 14"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 34,
            'product_id' => 7,
            'sku' => 'YH1OE2OI',
            'price' => 45.0,
            'stock' => 48,
            'variant_attributes' => '{"color": "Rojo", "modelo_compatible": "iPhone 13"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 35,
            'product_id' => 7,
            'sku' => 'GA6TQ5E6',
            'price' => 45.0,
            'stock' => 35,
            'variant_attributes' => '{"color": "Rojo", "modelo_compatible": "iPhone 15"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 36,
            'product_id' => 8,
            'sku' => 'MFOHN0HS',
            'price' => 110.0,
            'stock' => 42,
            'variant_attributes' => '{"color": "Rojo", "modelo_compatible": "iPhone 14"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 37,
            'product_id' => 8,
            'sku' => 'B77ZYZXO',
            'price' => 110.0,
            'stock' => 39,
            'variant_attributes' => '{"color": "Rojo", "modelo_compatible": "iPhone 14"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 38,
            'product_id' => 8,
            'sku' => 'FXG2FNJJ',
            'price' => 110.0,
            'stock' => 21,
            'variant_attributes' => '{"color": "Blanco", "modelo_compatible": "Samsung S23"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 39,
            'product_id' => 8,
            'sku' => 'I4WVMR0F',
            'price' => 110.0,
            'stock' => 28,
            'variant_attributes' => '{"color": "Azul", "modelo_compatible": "iPhone 15"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 40,
            'product_id' => 8,
            'sku' => 'R1FUFTFC',
            'price' => 110.0,
            'stock' => 33,
            'variant_attributes' => '{"color": "Azul", "modelo_compatible": "Samsung S24"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 41,
            'product_id' => 9,
            'sku' => 'GOIG97JU',
            'price' => 90.0,
            'stock' => 36,
            'variant_attributes' => '{"color": "Blanco", "modelo_compatible": "Universal"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 42,
            'product_id' => 9,
            'sku' => 'GKIEMTFM',
            'price' => 90.0,
            'stock' => 37,
            'variant_attributes' => '{"color": "Transparente", "modelo_compatible": "Universal"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 43,
            'product_id' => 10,
            'sku' => 'UFBE3L31',
            'price' => 75.0,
            'stock' => 5,
            'variant_attributes' => '{"color": "Blanco", "modelo_compatible": "Universal"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 44,
            'product_id' => 10,
            'sku' => 'CIIJUMSK',
            'price' => 75.0,
            'stock' => 33,
            'variant_attributes' => '{"color": "Transparente", "modelo_compatible": "Universal"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 45,
            'product_id' => 11,
            'sku' => 'OKGGDXUP',
            'price' => 55.0,
            'stock' => 7,
            'variant_attributes' => '{"color": "Negro", "modelo_compatible": "Universal"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 46,
            'product_id' => 11,
            'sku' => 'AORWQ8ST',
            'price' => 55.0,
            'stock' => 34,
            'variant_attributes' => '{"color": "Rojo", "modelo_compatible": "Universal"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 47,
            'product_id' => 12,
            'sku' => 'PXQXP3OM',
            'price' => 850.0,
            'stock' => 16,
            'variant_attributes' => '{"color": "Azul", "modelo_compatible": "Universal"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 48,
            'product_id' => 12,
            'sku' => '3211C6Q6',
            'price' => 850.0,
            'stock' => 23,
            'variant_attributes' => '{"color": "Blanco", "modelo_compatible": "Universal"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 49,
            'product_id' => 13,
            'sku' => '2103ISLI',
            'price' => 150.0,
            'stock' => 45,
            'variant_attributes' => '{"color": "Transparente", "modelo_compatible": "Universal"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 50,
            'product_id' => 13,
            'sku' => 'HSIRYA8T',
            'price' => 150.0,
            'stock' => 42,
            'variant_attributes' => '{"color": "Rojo", "modelo_compatible": "Universal"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 51,
            'product_id' => 14,
            'sku' => 'KG6EFFVQ',
            'price' => 190.0,
            'stock' => 48,
            'variant_attributes' => '{"color": "Transparente", "modelo_compatible": "Universal"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 52,
            'product_id' => 14,
            'sku' => 'LDHUF9Z4',
            'price' => 190.0,
            'stock' => 9,
            'variant_attributes' => '{"color": "Transparente", "modelo_compatible": "Universal"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 53,
            'product_id' => 15,
            'sku' => '6NFKDFKN',
            'price' => 220.0,
            'stock' => 10,
            'variant_attributes' => '{"color": "Blanco", "modelo_compatible": "Universal"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        ProductVariant::create([
            'id' => 54,
            'product_id' => 15,
            'sku' => 'Y5JZBJXO',
            'price' => 220.0,
            'stock' => 50,
            'variant_attributes' => '{"color": "Transparente", "modelo_compatible": "Universal"}',
            'created_at' => '2026-03-16 19:46:51',
            'updated_at' => '2026-03-16 19:46:51'
        ]);
        echo "✅ Productos y Variantes importados exitosamente vía Seeder.\n";
    }
}
