import re

def parse_sql_and_generate_seeder(sql_file, out_php):
    with open(sql_file, 'r', encoding='utf-8') as f:
        sql = f.read()

    php_code = """<?php
namespace Database\\Seeders;

use Illuminate\\Database\\Seeder;
use App\\Models\\Product;
use App\\Models\\ProductVariant;

class LegacyDataSeeder extends Seeder
{
    public function run(): void
    {
        ProductVariant::query()->delete();
        Product::query()->delete();
"""
    # Parse Products
    products_block = re.search(r"INSERT INTO `products` .*?VALUES\n(.*?);", sql, re.DOTALL)
    if products_block:
        lines = products_block.group(1).strip().split('\n')
        for line in lines:
            line = line.strip().rstrip(',')
            if not line: continue
            line = line.replace("NULL", "None")
            try:
                vals = eval(line)
            except:
                continue
                
            php_code += f"        Product::create([\n"
            php_code += f"            'id' => {vals[0]},\n"
            php_code += f"            'nombre' => '{str(vals[1]).replace(chr(39), chr(92)+chr(39))}',\n"
            php_code += f"            'slug' => '{str(vals[2]).replace(chr(39), chr(92)+chr(39))}',\n"
            php_code += f"            'descripcion' => '{str(vals[3]).replace(chr(39), chr(92)+chr(39))}',\n"
            php_code += f"            'precio' => {vals[4]},\n"
            php_code += f"            'marca' => " + (f"'{str(vals[5]).replace(chr(39), chr(92)+chr(39))}'" if vals[5] else 'null') + ",\n"
            php_code += f"            'categoria' => " + (f"'{str(vals[6]).replace(chr(39), chr(92)+chr(39))}'" if vals[6] else 'null') + ",\n"
            php_code += f"            'attributes' => " + (f"'{str(vals[7])}'" if vals[7] else 'null') + ",\n"
            php_code += f"            'has_variants' => {1 if str(vals[8]) == '1' else 0},\n"
            php_code += f"            'created_at' => '{vals[9]}',\n"
            php_code += f"            'updated_at' => '{vals[10]}'\n        ]);\n"

    # Parse Variants
    variants_block = re.search(r"INSERT INTO `product_variants` .*?VALUES\n(.*?);", sql, re.DOTALL)
    if variants_block:
        lines = variants_block.group(1).strip().split('\n')
        for line in lines:
            line = line.strip().rstrip(',')
            if not line: continue
            line = line.replace("NULL", "None")
            try:
                vals = eval(line)
            except:
                continue
                
            php_code += f"        ProductVariant::create([\n"
            php_code += f"            'id' => {vals[0]},\n"
            php_code += f"            'product_id' => {vals[1]},\n"
            php_code += f"            'sku' => " + (f"'{str(vals[2]).replace(chr(39), chr(92)+chr(39))}'" if vals[2] else 'null') + ",\n"
            php_code += f"            'price' => {vals[3] if vals[3] is not None else 'null'},\n"
            php_code += f"            'stock' => {vals[4]},\n"
            php_code += f"            'variant_attributes' => " + (f"'{str(vals[5]).replace(chr(39), chr(92)+chr(39))}'" if vals[5] else 'null') + ",\n"
            php_code += f"            'created_at' => '{vals[6]}',\n"
            php_code += f"            'updated_at' => '{vals[7]}'\n        ]);\n"

    php_code += """        echo "✅ Productos y Variantes importados exitosamente vía Seeder.\\n";
    }
}
"""
    with open(out_php, 'w', encoding='utf-8') as f:
        f.write(php_code)

if __name__ == '__main__':
    parse_sql_and_generate_seeder('producto_base.sql', 'database/seeders/LegacyDataSeeder.php')
