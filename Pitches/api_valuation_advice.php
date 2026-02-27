<?php
// Pitches/api_valuation_advice.php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep JSON clean, but log errors
header('Content-Type: application/json');
session_start();

// Allow both Entrepreneurs (user_id) and Admins (admin_id) to use the AI advisor for testing
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please log in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_data = file_get_contents('php://input');
    $python_script = realpath(__DIR__ . '/../AI/valuation_advisor.py');
    
    if (!$python_script) {
        echo json_encode(['status' => 'error', 'message' => 'AI script not found. Path mapping error.']);
        exit;
    }
    // Find Python executable effectively
    $python_cmd = "python"; 
    
    // Windows specific search - Priority on known working path
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $explicit_paths = [
            realpath(__DIR__ . '/../.venv/Scripts/python.exe'),
            "C:\\Users\\wishh\\AppData\\Local\\Programs\\Python\\Python312\\python.exe",
            "C:\\Python312\\python.exe",
            "python.exe"
        ];
        
        $found = false;
        foreach ($explicit_paths as $path) {
            if (file_exists($path)) {
                $python_cmd = $path;
                $found = true;
                break;
            }
        }

        if (!$found) {
            // Check if python is in PATH
            $path_check = shell_exec("where python 2>NUL");
            if ($path_check) {
                $python_cmd = trim(explode("\n", $path_check)[0]);
                $found = true;
            } else {
                exec("python --version 2>&1", $output, $return_var);
                if ($return_var !== 0) {
                    exec("py --version 2>&1", $output, $return_var);
                    if ($return_var === 0) {
                        $python_cmd = "py";
                    }
                }
            }
        }
    }
    
    $descriptorspec = array(
       0 => array("pipe", "r"),
       1 => array("pipe", "w"),
       2 => array("pipe", "w")
    );

    // Optimized command for Windows - ensure full absolute paths are used
    $cmd = "\"$python_cmd\" \"$python_script\"";
    $cwd = realpath(__DIR__ . '/..'); // Run from project root
    $process = proc_open($cmd, $descriptorspec, $pipes, $cwd);

    if (is_resource($process)) {
        fwrite($pipes[0], $raw_data);
        fflush($pipes[0]);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $return_value = proc_close($process);

        $clean_stdout = trim($stdout);
        
        // Extract JSON if warnings exist
        if (preg_match('/\{.*\}/s', $clean_stdout, $matches)) {
            $clean_stdout = $matches[0];
        }

        $result = json_decode($clean_stdout, true);

        if ($result && !isset($result['error'])) {
            echo json_encode(['status' => 'success', 'data' => $result]);
        } else {
            $err_msg = isset($result['error']) ? $result['error'] : trim($stderr);
            if (empty($err_msg) && $return_value !== 0) {
                $err_msg = "Python process exited with code $return_value";
            }
            echo json_encode([
                'status' => 'error', 
                'message' => 'AI Engine: ' . ($err_msg ?: 'Process returned no data or invalid JSON'),
                'debug' => [
                    'cmd' => $cmd,
                    'stdout' => $stdout,
                    'stderr' => $stderr,
                    'return' => $return_value
                ]
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Could not start AI process.',
            'debug_info' => [
                'cmd' => $cmd,
                'php_user' => get_current_user(),
                'script_exists' => file_exists($python_script)
            ]
        ]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Method']);
}
?>
