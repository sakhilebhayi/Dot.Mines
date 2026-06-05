#!/usr/bin/env python3
"""
Add void return types to PHP methods that PHPStan flags as missing return types.
Only adds ': void' since these are typically Livewire actions and command handlers.
"""
import re
import subprocess
import os

BASELINE = 'phpstan-baseline.neon'

def get_methods_by_file():
    result = subprocess.run(['grep', '-n', 'no return type specified', BASELINE], capture_output=True, text=True)
    file_methods = {}
    
    for item in result.stdout.strip().split('\n'):
        if not item:
            continue
        parts = item.split(':', 1)
        if len(parts) < 2:
            continue
        linenum = int(parts[0])
        # In subprocess output, :: is encoded as \\:\\: (each \ doubled for Python string)
        # Pattern: \\:methodName\\ 
        m = re.search(r'\\\\:([A-Za-z_]\w*)\\\\', item)
        if not m:
            continue
        method_name = m.group(1).strip()
        
        path_result = subprocess.run(['sed', '-n', f'{linenum+2}p', BASELINE], capture_output=True, text=True)
        path_line = path_result.stdout.strip()
        pm = re.search(r'path:\s+(\S+)', path_line)
        if pm:
            path = pm.group(1)
            if path not in file_methods:
                file_methods[path] = []
            if method_name not in file_methods[path]:
                file_methods[path].append(method_name)
    
    return file_methods

def add_void_return_type(filepath, method_name):
    """Add ': void' to a method that has no return type declared."""
    with open(filepath, 'r') as f:
        content = f.read()
    
    # Match: public/protected/private function methodName(...)
    # WITHOUT an existing return type (no : after closing paren before {)
    # Method signature may span multiple lines
    pattern = re.compile(
        r'((?:public|protected|private)\s+(?:static\s+)?function\s+' + 
        re.escape(method_name) + 
        r'\s*\([^)]*\))\s*(\{)',
        re.DOTALL
    )
    
    def replace_func(m):
        sig = m.group(1)
        brace = m.group(2)
        # Only add if no return type already present (no : after the sig)
        # Check for existing return type by looking for : after )
        return sig + ': void\n    ' + brace
    
    new_content = pattern.sub(replace_func, content, count=1)
    
    if new_content != content:
        with open(filepath, 'w') as f:
            f.write(new_content)
        return True
    return False

def main():
    file_methods = get_methods_by_file()
    
    # Show plan
    total = sum(len(v) for v in file_methods.items())
    sorted_files = sorted(file_methods.items(), key=lambda x: len(x[1]), reverse=True)
    
    print(f"Files to fix: {len(file_methods)}")
    for path, methods in sorted_files[:10]:
        print(f"  {len(methods):3d}  {path}")
    print()
    
    fixed_count = 0
    skip_count = 0
    
    for path, methods in file_methods.items():
        if not os.path.exists(path):
            print(f"SKIP (not found): {path}")
            continue
        
        for method in methods:
            changed = add_void_return_type(path, method)
            if changed:
                fixed_count += 1
            else:
                skip_count += 1
    
    print(f"Fixed: {fixed_count}, Skipped: {skip_count}")

if __name__ == '__main__':
    main()
