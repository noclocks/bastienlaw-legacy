<?php
namespace Codexonics\PrimeMoverFramework\scripts;

/*
 * This file is part of the Codexonics.PrimeMoverFramework package.
 *
 * (c) Codexonics Ltd
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

/**
 * COMMAND LINE SCRIPTS FOR SHELL ARCHIVER
 * CALLED BY THE EXPORTER / IMPORTER TO REQUEST CLI PROCESSING IF SUPPORTED
 * @since 1.0.7
 */

/** @var Type $argv Command Line arguments*/
/** @var Type $argc Command Line arguments count*/

if ( ! primeMoverCLIModeReady($argv, $argc) ) {
    exit;
}

/**
 * @var Type $script_path CLI-script
 * @var Type $error_log Error log path
 * @var Type $loader_data Loader data
 * @var Type $raw_data Raw data
 * @var Type $exporter_auth Exporter auth
 * @var Type $file_auth File auth
 */
list($script_path, $error_log, $loader_data, $raw_data, $exporter_auth, $file_auth) = $argv; 

$error_log = primeMoverValidErrorLog($error_log);
if ( ! $error_log ) {
    exit;
}

/**
 * Attempt to load core environment
 */
if ( ! loadCoreEnvironment($loader_data, $script_path, $error_log) ) {
    @error_log('ERROR: loading core environment in shell.' . PHP_EOL, 3, $error_log);
    exit;
}

/**
 * Validate migration log
 * @param string $error_log
 * @return boolean
 */
function primeMoverValidErrorLog($error_log = '')
{
    if ( ! $error_log) {
        return false;
    }
    $log_decoded = base64_decode($error_log);
    $log_decoded = realpath($log_decoded);
    if ( ! $log_decoded ) {
        return false;
    }
    $ext = pathinfo($log_decoded, PATHINFO_EXTENSION);
    if ('log' === $ext && strpos($log_decoded, '_migration.log' ) !== 0 ) {
        return $log_decoded;
    } 
    
    return false;
}

/**
 * Analyzes if CLI mode is ready
 * @param $argv
 * @param $argc
 * @return boolean
 */
function primeMoverCLIModeReady($argv, $argc)
{
    if ("cli" !== php_sapi_name()) {
        return false;
    }
    if ( ! is_array($argv) ) {
        return false;
    }
    if (6 !== $argc) {
        return false;
    }    
    $params = array_filter($argv, 'strlen');
    if (count($params) !== $argc) {
        return false;
    }    
    return true;
}

/**
 * Maybe set missing headers in CLI
 * @param array $loader_array
 */
function maybeSetMissingHeaders($loader_array = [])
{
    if ( ! isset($_SERVER['HTTP_HOST']) && ! empty($loader_array['http_host'])) {
        $_SERVER['HTTP_HOST'] = $loader_array['http_host'];
    }
    if ( ! isset($_SERVER['REQUEST_METHOD']) && ! empty($loader_array['request_method'])) {
        $_SERVER['REQUEST_METHOD'] = $loader_array['request_method'];
    }
}

/**
 * Define Constants on RunTime
 */
function primeMoverdefineCLIConstantsOnRunTime()
{
    define('WP_BOOTSTRAP_FILE', 'wp-load.php');
    define('SCRIPT_PATH', dirname(__FILE__) );
    define('PRIME_MOVER_DOING_SHELL_ARCHIVE', true);
}

/**
 * Define runtime constants
 * @param number $script_user
 * @param string $ip
 * @param string $user_agent
 * @param string $tmp_file
 * @param string $error_log
 */
function defineRunTimeConstants($script_user = 0, $ip = '', $user_agent = '', $tmp_file = '', $error_log = '', $shell_process_mode = '')
{
    define('PRIME_MOVER_COPY_MEDIA_SHELL_USER', $script_user);
    define('PRIME_MOVER_COPY_MEDIA_SHELL_USER_IP', $ip);
    define('PRIME_MOVER_COPY_MEDIA_SHELL_USER_AGENT', $user_agent);
    define('PRIME_MOVER_COPY_MEDIA_SHELL_TMP_FILE', $tmp_file);
    define('PRIME_MOVER_SHELL_ERROR_LOG_FILE', $error_log);
    define('PRIME_MOVER_SHELL_PROCESS_MODE', $shell_process_mode);
}

/**
 * Shutdown function for CLI
 */
function shutDownFunction() {
    if ( ! function_exists('error_get_last')) {
        return;
    }
    if ( ! function_exists('get_temp_dir')) {
        return;
    }
    if ( ! function_exists('trailingslashit')) {
        return;
    }
    if ( ! defined('PRIME_MOVER_COPY_MEDIA_SHELL_TMP_FILE')) {
        return;
    }
    if ( ! PRIME_MOVER_COPY_MEDIA_SHELL_TMP_FILE || ! file_exists(PRIME_MOVER_COPY_MEDIA_SHELL_TMP_FILE)) {
        return;
    }
    $error = error_get_last();
    if ( null === $error ) {
        return;
    }
    
    $tmp_directory = trailingslashit(get_temp_dir());
    $parent = trailingslashit(dirname(PRIME_MOVER_COPY_MEDIA_SHELL_TMP_FILE));
    
    if ($parent !== $tmp_directory) {
        return;
    }
    
    if (E_ERROR === $error['type'] && is_array($error)) {
        file_put_contents(PRIME_MOVER_COPY_MEDIA_SHELL_TMP_FILE, 'prime-mover-shell-shutdown-error: ' . print_r($error, true), LOCK_EX);
    }
}

/**
 * Load Core Environment
 * @param string $loader_array_tmp
 * @param string $script_path
 * @param string $error_log
 * @return boolean
 */
function loadCoreEnvironment($loader_array_tmp = '', $script_path = '', $error_log = '')
{    
    primeMoverdefineCLIConstantsOnRunTime();
    
    $loader_decoded = base64_decode($loader_array_tmp);
    $loader_array = json_decode($loader_decoded, true);
    $loader = $loader_array['loader'];
    $user_agent = $loader_array['user_agent'];    
    
    $loader = realpath($loader);
    $script_path = dirname($script_path);
    if (WP_BOOTSTRAP_FILE !== basename($loader) || $script_path !== SCRIPT_PATH) {
        @error_log('ERROR: Bootstrap file validation failed in shell.' . PHP_EOL, 3, $error_log);
        return false;
    }    
    if ( ! file_exists($loader)) {
        @error_log('ERROR: Core loader file does not exist in shell.' . PHP_EOL, 3, $error_log);
        return false;
    }   
    
    $script_user = (int) $loader_array['user_id'];
    if ( ! $script_user  ) {
        @error_log('ERROR: Core user is not valid in shell.' . PHP_EOL, 3, $error_log);
        return false;
    }
    
    $ip = $loader_array['ip'];
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        @error_log('ERROR: IP is not usable in shell.' . PHP_EOL, 3, $error_log);
        return false;
    }
    
    $tmp_file = $loader_array['shell_tmp_file'];
    if ( ! is_writable($tmp_file)) {
        @error_log('ERROR: Tmp file is not usable in shell.' . PHP_EOL, 3, $error_log);
        return false;
    }
    
    $shell_process_mode = str_replace(' ', '', $loader_array['shell_process_mode']);
    if ( ! ctype_alpha($shell_process_mode)) {
        @error_log('ERROR: Invalid shell process mode' . PHP_EOL, 3, $error_log);
        return false;
    }
    maybeSetMissingHeaders($loader_array);    
    defineRunTimeConstants($script_user, $ip, $user_agent, $tmp_file, $error_log, $shell_process_mode);
    register_shutdown_function('Codexonics\PrimeMoverFramework\scripts\shutDownFunction');
    
    @error_log('Everything ready, loading core..' . PHP_EOL, 3, $error_log);
    require($loader);   
}