import os, re

def find_literal_absolute_paths(directory):
    for root, dirs, files in os.walk(directory):
        for f in files:
            if f.endswith(('.html', '.php', '.css', '.js')):
                path = os.path.join(root, f)
                with open(path, 'r', encoding='utf-8', errors='ignore') as file:
                    content = file.read()
                
                lines = content.split('\n')
                for i, line in enumerate(lines):
                    if re.search(r'(href|src)\s*=\s*[\'\"]/(?!\?|/)', line):
                        print(f'{path}:{i+1}: {line.strip()}')
                    elif re.search(r'url\([\'\"]?/(?!\?|/)', line):
                        print(f'{path}:{i+1}: {line.strip()}')

if __name__ == "__main__":
    print("--- TITLE 1 ---")
    find_literal_absolute_paths("TITLE 1")
    print("--- TITLE 2 ---")
    find_literal_absolute_paths("TITLE 2")
