<?php

// Attempt to load Composer's autoloader
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Fallback error handling if autoloader is missing
    error_log("FATAL: vendor/autoload.php not found. Please run 'composer install'.");
    // Optionally, you could send a 500 error response if this is a web request context
    // header('HTTP/1.1 500 Internal Server Error');
    // echo json_encode(['success' => false, 'message' => 'Server configuration error: Autoloader missing.']);
    die("Application is not configured correctly. Missing autoloader.");
}

use Dotenv\Dotenv;
use Aws\S3\S3Client;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\Exception\AwsException;

// Load environment variables from .env file in the project root
try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..'); // Project root is one level up from src/
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    error_log("FATAL: .env file not found. " . $e->getMessage());
    die("Application is not configured correctly. Missing .env file.");
}

// --- Database Configuration ---
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'filemanager');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASSWORD'] ?? '');

// --- AWS Configuration ---
define('AWS_ACCESS_KEY_ID', $_ENV['AWS_ACCESS_KEY_ID'] ?? null);
define('AWS_SECRET_ACCESS_KEY', $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null);
define('AWS_REGION', $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1');
define('S3_BUCKET', $_ENV['S3_BUCKET'] ?? null);

// --- CloudWatch Logs Configuration ---
define('LOG_GROUP_NAME', $_ENV['LOG_GROUP_NAME'] ?? '/app/file-manager');
// Ensure LOG_ENABLED is defined before it's used by the CloudWatchLogsClient setup
$logEnabledSetting = $_ENV['LOG_ENABLED'] ?? true;
if (!defined('LOG_ENABLED')) {
    define('LOG_ENABLED', filter_var($logEnabledSetting, FILTER_VALIDATE_BOOLEAN));
}

// --- PDO Database Connection ---
/** @var PDO $pdo */
$pdo = null;
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log("FATAL: Database connection failed: " . $e->getMessage());
    if (!headers_sent()) {
        header('HTTP/1.1 503 Service Unavailable');
        echo json_encode(['success' => false, 'message' => 'Database connection error. Please check server logs.']);
    }
    die(); // Exit script
}

// --- AWS S3 Client ---
/** @var S3Client $s3 */
$s3 = null;
if (AWS_ACCESS_KEY_ID && AWS_SECRET_ACCESS_KEY && S3_BUCKET) {
    try {
        $s3 = new S3Client([
            'version' => 'latest',
            'region' => AWS_REGION,
            'credentials' => [
                'key' => AWS_ACCESS_KEY_ID,
                'secret' => AWS_SECRET_ACCESS_KEY,
            ],
        ]);
    } catch (Exception $e) {
        error_log("ERROR: Failed to initialize AWS S3 client: " . $e->getMessage());
        // Depending on app needs, you might die() here or let it proceed with S3 disabled
    }
} else {
    error_log("WARNING: AWS S3 credentials or S3_BUCKET not fully configured. S3 operations will be disabled.");
}

// --- AWS CloudWatch Logs Client ---
/** @var CloudWatchLogsClient $cloudWatchLogs */
$cloudWatchLogs = null;
if (LOG_ENABLED && AWS_ACCESS_KEY_ID && AWS_SECRET_ACCESS_KEY) {
    try {
        $cloudWatchLogs = new CloudWatchLogsClient([
            'version' => 'latest',
            'region' => AWS_REGION,
            'credentials' => [
                'key' => AWS_ACCESS_KEY_ID,
                'secret' => AWS_SECRET_ACCESS_KEY,
            ],
        ]);
    } catch (Exception $e) {
        error_log("WARNING: Failed to initialize AWS CloudWatch Logs client: " . $e->getMessage() . ". Logging to CloudWatch will be disabled.");
        // Redefine LOG_ENABLED to false if client initialization fails
        if (defined('LOG_ENABLED')) { // Should always be true from above
            $redefineLogEnabled = false; // Temp var to avoid direct constant modification issue for linters
            // This is tricky as constants can't be truly redefined. 
            // The write_log function will check $GLOBALS['cloudWatchLogs'] which will be null.
        }
    }
} else {
    if (LOG_ENABLED) { // Check if it was initially true
        error_log("WARNING: CloudWatch logging was enabled but AWS credentials are not fully configured. Logging to CloudWatch will be disabled.");
        // Similar to above, LOG_ENABLED can't be simply redefined here.
        // The write_log function's check for $GLOBALS['cloudWatchLogs'] handles this.
    }
}

// --- Helper function to write logs ---
function write_log(string $message, string $logStreamName = 'application-logs', array $context = [])
{
    // Check if logging is truly possible (client initialized)
    if (!defined('LOG_ENABLED') || !LOG_ENABLED || !isset($GLOBALS['cloudWatchLogs']) || !$GLOBALS['cloudWatchLogs']) {
        $logHeader = date('[Y-m-d H:i:s]') . " [{$logStreamName}]";
        $logOutput = "{$logHeader} {$message}";
        if (!empty($context)) {
            $logOutput .= " | Context: " . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }
        error_log($logOutput);
        return;
    }

    /** @var CloudWatchLogsClient $cwClient */
    $cwClient = $GLOBALS['cloudWatchLogs'];
    $logGroupName = LOG_GROUP_NAME;
    $sequenceToken = null;

    $logStreamName = preg_replace('/[^a-zA-Z0-9_.-]/ ', '_', $logStreamName);
    if (empty($logStreamName))
        $logStreamName = 'default-stream';

    $logOutput = $message;
    if (!empty($context)) {
        $logOutput .= " | Context: " . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    try {
        $streamsResult = $cwClient->describeLogStreams([
            'logGroupName' => $logGroupName,
            'logStreamNamePrefix' => $logStreamName,
            'limit' => 1
        ]);
        if (!empty($streamsResult['logStreams'][0]['uploadSequenceToken'])) {
            $sequenceToken = $streamsResult['logStreams'][0]['uploadSequenceToken'];
        }
    } catch (AwsException $e) {
        if ($e->getAwsErrorCode() !== 'ResourceNotFoundException') { // It's okay if stream/group not found yet
            error_log("CloudWatch DescribeLogStreams API error for {$logGroupName}/{$logStreamName}: " . $e->getMessage());
        }
    }

    $logEventPayload = [
        'logGroupName' => $logGroupName,
        'logStreamName' => $logStreamName,
        'logEvents' => [
            [
                'timestamp' => round(microtime(true) * 1000),
                'message' => $logOutput
            ],
        ],
    ];
    if ($sequenceToken) {
        $logEventPayload['sequenceToken'] = $sequenceToken;
    }

    try {
        $cwClient->putLogEvents($logEventPayload);
    } catch (AwsException $e) {
        $errorCode = $e->getAwsErrorCode();
        if ($errorCode === 'ResourceNotFoundException') {
            try {
                $cwClient->createLogStream(['logGroupName' => $logGroupName, 'logStreamName' => $logStreamName]);
                unset($logEventPayload['sequenceToken']);
                $cwClient->putLogEvents($logEventPayload);
            } catch (AwsException $ex) {
                error_log("CloudWatch CreateLogStream or subsequent PutLogEvents failed for {$logGroupName}/{$logStreamName}: " . $ex->getMessage() . " | Original Log: {$logOutput}");
            }
        } elseif ($e instanceof \Aws\CloudWatchLogs\Exception\InvalidSequenceTokenException || $errorCode === 'DataAlreadyAcceptedException') {
            preg_match('/The next expected sequenceToken is: (\S+)/', (string) $e->getMessage(), $matches);
            if (isset($matches[1])) {
                $logEventPayload['sequenceToken'] = $matches[1];
                try {
                    $cwClient->putLogEvents($logEventPayload);
                } catch (AwsException $ex) {
                    error_log("CloudWatch PutLogEvents retry failed for {$logGroupName}/{$logStreamName}: " . $ex->getMessage() . " | Original Log: {$logOutput}");
                }
            }
        } else {
            error_log("CloudWatch PutLogEvents failed for {$logGroupName}/{$logStreamName}: " . $e->getMessage() . " | Original Log: {$logOutput}");
        }
    }
}

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    $context = ['file' => $file, 'line' => $line, 'severity' => $severity];
    write_log("PHP Error: {$message}", 'php-errors', $context);
    return true; // Suppress default PHP error handler to avoid duplicate logging if not FATAL
});

set_exception_handler(function (Throwable $exception) {
    $context = [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'code' => $exception->getCode(),
        // 'trace' => $exception->getTraceAsString() // Log full trace only if absolutely needed, can be very verbose
    ];
    write_log("PHP Exception: " . $exception->getMessage(), 'php-exceptions', $context);

    $isApiRequest = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) ||
        (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') !== false);

    if (!headers_sent()) {
        if ($isApiRequest) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'An unexpected server error occurred. Please check logs.',
            ]);
        } else {
            http_response_code(500);
            echo "<html><body><h1>Error</h1><p>An unexpected server error occurred. Please try again later or contact support.</p></body></html>";
        }
    }
    // For critical unhandled exceptions, it's often best to exit to prevent further issues.
    exit;
});

// Ensure output buffering is off or managed if you plan to send headers after output
// ini_set('output_buffering', 'Off'); 