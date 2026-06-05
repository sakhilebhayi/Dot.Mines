#!/usr/bin/env python3
"""
Fix methods incorrectly typed as void when they actually return values.
Uses php -l to find problematic files and updates the return type.
"""
import re
import subprocess
import os

FILES_TO_CHECK = [
    'app/Livewire/Alerts.php',
    'app/Livewire/BillingPortal.php',
    'app/Livewire/Feed.php',
    'app/Livewire/FuelManagement.php',
    'app/Livewire/LiveMap.php',
    'app/Livewire/MaintenanceDashboard.php',
    'app/Livewire/ProductionDashboard.php',
    'app/Livewire/ReportGenerator.php',
    'app/Livewire/Reports.php',
    'app/Http/Controllers/Api/NotificationController.php',
    'app/Http/Controllers/Api/ReportController.php',
    'app/Services/AuthorizationService.php',
    'app/Services/QueryCacheService.php',
    'app/Events/ComplianceViolationDetected.php',
    'app/Events/MaintenanceAlertTriggered.php',
    'app/Events/SensorReadingRecorded.php',
    'app/Events/SensorStatusChanged.php',
    'app/Mail/ReportReadyMail.php',
    'app/Mail/WelcomeMail.php',
    'app/Console/Commands/GenerateRoadsPathCoordinates.php',
    'app/Console/Commands/PerformShiftChange.php',
]

def get_error_line(filepath):
    """Run php -l and return the line number causing the void error."""
    result = subprocess.run(['php', '-l', filepath], capture_output=True, text=True)
    output = result.stderr + result.stdout
    m = re.search(r'void function must not return.*?on line (\d+)', output)
    if m:
        return int(m.group(1))
    # Also match "function with return type must return"
    m2 = re.search(r'function with return type must return.*?on line (\d+)', output)
    if m2:
        return int(m2.group(1))
    return None

def find_function_start(lines, error_line):
    """Walk backward from error line to find the function signature."""
    # Look backward up to 30 lines for the function definition
    for i in range(error_line - 1, max(0, error_line - 30), -1):
        line = lines[i]
        if re.search(r'(public|protected|private)\s+(static\s+)?function\s+\w+', line):
            return i
    return None

def fix_file(filepath):
    """Fix void return type errors in a file iteratively."""
    iterations = 0
    while iterations < 20:
        err_line = get_error_line(filepath)
        if err_line is None:
            break
        
        with open(filepath, 'r') as f:
            lines = f.readlines()
        
        func_line_idx = find_function_start(lines, err_line)
        if func_line_idx is None:
            print(f"  Could not find function start in {filepath} near line {err_line}")
            break
        
        # The function signature may span multiple lines
        # Join lines until we find the opening brace
        sig_start = func_line_idx
        sig_end = func_line_idx
        combined = ''
        for k in range(func_line_idx, min(func_line_idx + 10, len(lines))):
            combined += lines[k]
            if '{' in lines[k]:
                sig_end = k
                break
        
        # Check what the function returns based on the error context
        # Look at lines around err_line to understand the return
        context_before = ''.join(lines[max(0, err_line-5):err_line])
        error_line_content = lines[err_line - 1] if err_line <= len(lines) else ''
        
        # Determine correct return type
        if ': void' in combined:
            # Check if the return is null/bare or has a value
            has_return_null = bool(re.search(r'return\s+null\s*;', error_line_content))
            has_return_value = bool(re.search(r'return\s+[^\s;]', error_line_content))
            
            if has_return_null:
                # Change void to ?mixed or remove :void to allow null
                # For null return from void, use: remove return type or use mixed
                new_combined = combined.replace('): void', '): mixed', 1)
                # Also fix bare returns in this method
            elif has_return_value or 'return [' in error_line_content or 'return $' in error_line_content:
                new_combined = combined.replace('): void', '): mixed', 1)
            else:
                # bare return; in a void function — change return; to return null;
                lines[err_line - 1] = lines[err_line - 1].replace('return;', 'return null;')
                with open(filepath, 'w') as f:
                    f.writelines(lines)
                iterations += 1
                continue
            
            # Replace the signature lines
            new_lines = lines[:sig_start] + [new_combined] + lines[sig_end + 1:]
            with open(filepath, 'w') as f:
                f.writelines(new_lines)
        
        iterations += 1
    
    return iterations

def main():
    for filepath in FILES_TO_CHECK:
        if not os.path.exists(filepath):
            continue
        
        count = fix_file(filepath)
        err = get_error_line(filepath)
        status = 'OK' if err is None else f'STILL BROKEN at line {err}'
        print(f"{status}: {filepath} (iterations: {count})")

if __name__ == '__main__':
    main()
