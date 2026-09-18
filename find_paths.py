import os, re

def fix_paths(directory):
    for root, dirs, files in os.walk(directory):
        for f in files:
            if f.endswith(('.html', '.php', '.css', '.js')):
                path = os.path.join(root, f)
                with open(path, 'r', encoding='utf-8', errors='ignore') as file:
                    content = file.read()
                
                # Replace absolute paths starting with /
                # Pattern: href="/path" -> href="path" (or ../ depending on depth)
                # If we just remove the leading slash, it will be relative to current directory.
                # However, the user says "is either file path walang / or kung aatras ../ ganyan lang dapat"
                # If a file is in TITLE 1/sub/file.html and references /assets/css/style.css, removing / makes it assets/css/style.css which looks in TITLE 1/sub/assets/css/style.css. That might be wrong.
                # The correct relative path would need to compute the depth.
                # Assuming the root of the app is "TITLE 1" or "TITLE 2".
                
                # First let's just log what we found to understand the paths
                lines = content.split('\n')
                for i, line in enumerate(lines):
                    if re.search(r'(href|src)=[\'\"]/', line) or re.search(r'url\([\'\"]?/', line):
                        print(f'{path}:{i+1}: {line.strip()}')

if __name__ == "__main__":
    fix_paths("TITLE 1")
    fix_paths("TITLE 2")
