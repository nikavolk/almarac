<?php

// attempt to load composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // fallback error handling if autoloader missing
    error_log("FATAL: vendor/autoload.php not found. Please run 'composer install'.");
    http_response_code(500);
    die("Application is not configured correctly. Missing autoloader.");
}

use Dotenv\Dotenv;
use Aws\S3\S3Client;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\Rekognition\RekognitionClient;
use Aws\Exception\AwsException;

// load variables from .env file
try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    error_log("FATAL: .env file not found. " . $e->getMessage());
    die("Application is not configured correctly. Missing .env file.");
}

// --- database config ---
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'filemanager');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASSWORD'] ?? '');

// --- aws config ---
define('AWS_ACCESS_KEY_ID', $_ENV['AWS_ACCESS_KEY_ID'] ?? null);
define('AWS_SECRET_ACCESS_KEY', $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null);
define('AWS_REGION', $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1');
define('S3_BUCKET', $_ENV['S3_BUCKET'] ?? null);

// --- cloudwatch logs config ---
define('LOG_GROUP_NAME', $_ENV['LOG_GROUP_NAME'] ?? '/app/file-manager');
$logEnabledSetting = $_ENV['LOG_ENABLED'] ?? true;
if (!defined('LOG_ENABLED')) {
    define('LOG_ENABLED', filter_var($logEnabledSetting, FILTER_VALIDATE_BOOLEAN));
}

// --- pdo database connection ---
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
    die(); // exit script ;(
}

// --- aws s3 client ---
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
    }
} else {
    error_log("WARNING: AWS S3 credentials or S3_BUCKET not fully configured. S3 operations will be disabled.");
}

// --- aws cloudwatch logs client ---
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
        // redefine LOG_ENABLED to false if client initialization fails
        if (defined('LOG_ENABLED')) { // should always be true from above
            $redefineLogEnabled = false; // temp var to avoid direct constant modification issue for linters
        }
    }
} else {
    if (LOG_ENABLED) { // check if initially true
        error_log("WARNING: CloudWatch logging was enabled but AWS credentials are not fully configured. Logging to CloudWatch will be disabled.");
    }
}

// --- aws rekognition client ---
/** @var RekognitionClient $rekognition */
$rekognition = null;
if (AWS_ACCESS_KEY_ID && AWS_SECRET_ACCESS_KEY) {
    try {
        $rekognition = new RekognitionClient([
            'version' => 'latest',
            'region' => AWS_REGION,
            'credentials' => [
                'key' => AWS_ACCESS_KEY_ID,
                'secret' => AWS_SECRET_ACCESS_KEY,
            ],
        ]);
    } catch (Exception $e) {
        error_log("WARNING: Failed to initialize AWS Rekognition client: " . $e->getMessage() . ". Rekognition features will be disabled.");
    }
} else {
    error_log("WARNING: AWS Rekognition credentials not fully configured. Rekognition features will be disabled.");
}

// --- helper function to write logs ---
function write_log(string $message, string $logStreamName = 'application-logs', array $context = [])
{
    // check if logging is truly possible (client initialized)
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
        if ($e->getAwsErrorCode() !== 'ResourceNotFoundException') {
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
    return true; // suppress default php error handler to avoid duplicate logging if not FATAL
});

set_exception_handler(function (Throwable $exception) {
    $context = [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'code' => $exception->getCode(),
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
    exit;
});