import re

def convert_mysql_to_postgres(input_file, output_file):
    with open(input_file, 'r', encoding='utf-8') as f_in, open(output_file, 'w', encoding='utf-8') as f_out:
        for line in f_in:
            # We ONLY care about INSERT INTO statements because Laravel already created the schema (tables)
            if line.startswith('INSERT INTO') or line.startswith('\t(') or line.startswith('('):
                # Replace backticks with double quotes
                line = line.replace('`', '"')
                # Replace escaped single quotes \' with standard SQL ''
                line = line.replace("\\'", "''")
                # Replace escaped newlines if any
                line = line.replace("\\n", "\n")
                
                f_out.write(line)
            elif line.strip() == ");" or line.strip() == ";":
                 f_out.write(line)

if __name__ == '__main__':
    convert_mysql_to_postgres('producto_base.sql', 'postgres_inserts.sql')
    print("Conversion complete: postgres_inserts.sql created.")
