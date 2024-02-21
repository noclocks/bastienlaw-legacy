<?php
namespace Codexonics\PrimeMoverFramework\classes;

/*
 * This file is part of the Codexonics.PrimeMoverFramework package.
 *
 * (c) Codexonics Ltd
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Codexonics\PrimeMoverFramework\utilities\PrimeMoverShutdownUtilities;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Error handling class
 *
 * The Prime Mover Error Handling Class handles the fatal runtime errors originated within the plugin and offers a systematic handling and reporting methods.
 *
 */
class PrimeMoverErrorHandlers
{
    
    private $shutdown_utilities;
    
    /**
     *
     * Constructor
     */
    public function __construct(
        PrimeMoverShutdownUtilities $shutdown_utilities
        ) 
    {
            $this->shutdown_utilities = $shutdown_utilities;
    }

    /**
     * Get shutdown utilities
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverShutdownUtilities
     * @compatible 5.6
     */
    public function getShutDownUtilities() 
    {
        return $this->shutdown_utilities;        
    }
    
    /**
     * Gets System authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     * @compatible 5.6
     */
    public function getSystemAuthorization()
    {
        return $this->getShutDownUtilities()->getSystemAuthorization();
    }
    
    /**
     *
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     * @compatible 5.6
     */
    public function getSystemFunctions()
    {
        return $this->getShutDownUtilities()->getSystemFunctions();
    }
    
    /**
     *
     * Get System Initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     * @compatible 5.6
     */
    public function getSystemInitialization()
    {
        return $this->getShutDownUtilities()->getSystemInitialization();
    }
    
    /**
     * Initialize error handling hooks
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itAddsInitHooks() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itChecksIfHooksAreOutdated() 
     * 
     */
    public function initHooks()
    {
        add_action('prime_mover_maintenance_cron_tasks', [$this, 'deleteOldLogs']);
        
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        add_action('wp_ajax_prime_mover_check_if_error_log_exist', [$this,'primeMoverLogExist']);        
        add_action('shutdown', [$this,'errorHandler']);
        add_action('prime_mover_shutdown_actions', [$this, 'primeMoverDeleteMaintenanceFile'], 10, 1 );
        add_action('prime_mover_shutdown_actions', [$this, 'primeMoverDeletePackagePath'], 15, 1 );
        
        add_action('prime_mover_shutdown_actions', [$this, 'primeMoverLogErrors'], 20, 1 );
        add_action('prime_mover_before_doing_import', [$this, 'primeMoverDeleteErrorLog'], 10, 2 );        
        
        add_action('init', [$this, 'streamError'], 11);
        add_action('prime_mover_before_doing_export', [$this, 'primeMoverDeleteErrorLog'], 10, 2 );
        add_action('prime_mover_log_processed_events', [$this, 'primeMoverLogEvents'], 10, 7);
        
        add_action('prime_mover_bootup', [$this,'maybeRefreshLogEventFile'], 0, 3);
        add_filter('prime_mover_filter_error_output', [$this, 'appendSiteIdentityOnErrorLog'], 10, 2);
        add_filter('prime_mover_filter_error_output', [$this, 'appendCoreAndWordPressInfoOnLog'], 0, 1);
        
        add_filter('prime_mover_filter_error_output', [$this, 'appendNetworkActivePluginsOnLog'], 100, 1);
        add_filter('prime_mover_filter_error_output', [$this, 'appendPHPInfoArrayOnLog'], 300, 1);
        
        add_filter('prime_mover_ajax_rendered_js_object', [$this, 'setTotalWaitingSecondsOnError'], 85, 1 );
        add_action('prime_mover_after_error_is_logged', [$this, 'appendMigrationLogToErrorLog'], 10, 1);
        add_action('prime_mover_shutdown_actions', [$this, 'deleteMasterTmpFileOnError'], 25);
    }
 
    /**
     * Delete outdated logs from clogging up server
     * This is called by WP Cron.
     */
    public function deleteOldLogs()
    {
        $log_path = $this->getSystemInitialization()->getMultisiteExportFolder();
        if ( ! $this->getSystemFunctions()->nonCachedFileExists($log_path)) {
            return;
        }
        
        $log_path = trailingslashit($log_path);
        $this->getSystemInitialization()->multisiteInitializeWpFilesystemApiCli(false);
        $files = list_files($log_path , 1, ['index.html', '.htaccess', '.export_identity']);
        if ( ! is_array($files) ) {
            return;
        }
        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $filename = basename($file);
            if ($this->isLogFile($extension, $filename) && ((time()-filectime($file)) > PRIME_MOVER_CRON_DELETE_TMP_INTERVALS && $this->getSystemFunctions()->nonCachedFileExists($file))) {                
                $this->getSystemFunctions()->primeMoverDoDelete($file, false);
            }
        }         
    }
    
    /**
     * Checks if Prime Mover log file filename format
     * @param string $extension
     * @param string $filename
     * @return boolean
     */
    protected function isLogFile($extension = '', $filename = '')
    {
        $hash_to_check = strstr($filename, '_', true);         
        return ('log' === $extension && $this->getSystemFunctions()->isShaString($hash_to_check, 256));       
    }
    
    /**
     * Delete master tmp file on runtime errors.
     */
    public function deleteMasterTmpFileOnError()
    {
        $blog_id = $this->getShutDownUtilities()->primeMoverGetProcessedID();
        if ( ! $blog_id ) {
            return;
        }
        
        $option = $this->getSystemInitialization()->getCliMasterTmpFilesOptions() . "_" . $blog_id;
        $values = $this->getSystemFunctions()->getSiteOption($option);
        if (!is_array($values) || empty($values)) {
            return;
        }
        
        list($file_tmp, $dir_tmp) = $values;
        
        if ($this->getSystemFunctions()->nonCachedFileExists($file_tmp)) {
            $this->getSystemFunctions()->primeMoverDoDelete($file_tmp);
        }
        if ($this->getSystemFunctions()->nonCachedFileExists($dir_tmp)) {
            $this->getSystemFunctions()->primeMoverDoDelete($dir_tmp);
        }
        delete_site_option($option);
    }
    
    /**
     * Append migration log to error log in event of runtime error.
     * @param string $error_log_file
     */
    public function appendMigrationLogToErrorLog($error_log_file = '')
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if ( ! $this->getSystemFunctions()->nonCachedFileExists($error_log_file) ) {
            return;
        }
        $troubleshooting_path = $this->getSystemInitialization()->getTroubleShootingLogPath('migration'); 
        if ( ! $this->getSystemFunctions()->nonCachedFileExists($troubleshooting_path) ) {
            return;
        }
        $migration_log_handle = fopen($troubleshooting_path, 'rb');
        $error_log_handle = fopen($error_log_file, 'ab');
        
        if (false === $migration_log_handle || false === $error_log_handle) {
            return;
        }
        stream_copy_to_stream($migration_log_handle, $error_log_handle);
        
        fclose($migration_log_handle);
        fclose($error_log_handle);
    }
    
    /**
     * Set total waitning seconds on error
     * @param array $args
     * @return array
     */
    public function setTotalWaitingSecondsOnError($args = [])
    {
        $default = 120;
        if (defined('PRIME_MOVER_TOTAL_WAITING_ERROR_SECONDS')) {
            $default = PRIME_MOVER_TOTAL_WAITING_ERROR_SECONDS;
        }
        $args['prime_mover_totalwaiting_seconds_error'] = apply_filters('prime_mover_filter_totalwaiting_seconds_error', $default);
        return $args;
    }
    
    /**
     * Append network active plugins on error logs
     * @param array $error_output
     * @return array
     */
    public function appendNetworkActivePluginsOnLog($error_output = [])
    {
        if ( ! is_array($error_output) ) {
            return $error_output;
        } 
        if ( ! is_multisite() ) {
            return $error_output;
        }
        $plugins = [];
        $network_active = $this->getSystemFunctions()->getSiteOption('active_sitewide_plugins');
        if ( ! is_array($network_active) || empty($network_active) ) {
            $error_output['active_sitewide_plugins'] = $plugins;
            return $error_output;
        }
        $keys = array_keys($network_active);
        
        foreach ($keys as $plugin) {
            $plugin_full_path	= PRIME_MOVER_PLUGIN_CORE_PATH . $plugin;            
            if ( ! file_exists($plugin_full_path)) {
                continue;
            }            
            $plugins[] = get_plugin_data($plugin_full_path);
        }
        $error_output['active_sitewide_plugins'] = $plugins;
        return $error_output;
    }
    
    /**
     * Append core and WordPress info on log
     * @param array $error_output
     * @return array
     */
    public function appendPHPInfoArrayOnLog($error_output = [])
    {
        if ( ! is_array($error_output) ) {
            return $error_output;
        }
        /**
         * @var Type $output phpinfo CLI output
         */
        if ('cli' === php_sapi_name()) {
            exec("php -i", $output);
            $phpinfoarray = $output;
        } else {
            $phpinfoarray = $this->getShutDownUtilities()->phpinfo2array();
        }        
        
        $error_output = array_merge($error_output, $phpinfoarray);        
        
        return $error_output;
    }
    
    /**
     * Append core and WordPress info on log
     * @param array $error_output
     * @return array
     */
    public function appendCoreAndWordPressInfoOnLog($error_output = [])
    {
        if ( ! is_array($error_output) ) {
            return $error_output;
        }
        
        $error_output['prime_mover_version'] = PRIME_MOVER_VERSION;
        $error_output['wordpress_core_version'] = get_bloginfo('version');
        
        return $error_output;
    }
    
    /**
     * Append site identity on error log
     * @param array $error_output
     * @param number $blog_id
     * @return array
     */
    public function appendSiteIdentityOnErrorLog($error_output = [], $blog_id = 0)
    {
        if ( ! is_array($error_output) ) {
            return $error_output;
        }
        if ($blog_id) {
            $error_output['blog_id'] = $blog_id;
            $error_output['site_title'] = $this->getSystemFunctions()->getBlogOption($blog_id, 'blogname');
        }
        $error_output['super_admin_id'] = get_current_user_id();        
        return $error_output;
    }
    
    /**
     * Maybe refresh log event file
     * @param string $process_id
     * @param number $blog_id
     * @param boolean $diffmode
     */
    public function maybeRefreshLogEventFile($process_id = '', $blog_id = 0, $diffmode = false)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $troubleshooting_path = $this->getSystemInitialization()->getTroubleShootingLogPath('migration'); 
        if ( ! $troubleshooting_path ) {
            return;
        }
        if ( ! $this->isLogPathValid($troubleshooting_path) ) {
            return;
        }
        $persist = false;
        if ( defined('PRIME_MOVER_LOG_PERSIST') && true === PRIME_MOVER_LOG_PERSIST ) {
            $persist = true;
        }
        $persist = apply_filters('prime_mover_persist_troubleshooting_logs', $persist);
        
        if (file_exists($troubleshooting_path) && ! $diffmode && ! $persist) {
            unlink($troubleshooting_path);
        }
    }

    /**
     * Checks if user wants to enable user log.
     * @return boolean
     */
    protected function enableUserLogging()
    {
        return (defined('PRIME_MOVER_LOG_USER_IMPORT') && true === PRIME_MOVER_LOG_USER_IMPORT);
    }
    
    /**
     * Log events as requested
     * @param mixed $log
     * @param number $blog_id
     * @param string $mode
     * @param string $source
     * @param mixed $object
     * @param boolean $low_level_log
     * @param boolean $pii_log
     */
    public function primeMoverLogEvents($log, $blog_id = 0, $mode = '', $source = '', $object = null, $low_level_log = false, $pii_log = false)
    {
        if (defined('PRIME_MOVER_ENABLE_EVENT_LOG') && false === PRIME_MOVER_ENABLE_EVENT_LOG) {
            return;
        }
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if ($this->getSystemFunctions()->isRefererBackupMenu()) {
            return;
        }
        if ($low_level_log && ! $this->enableFileLogging() ) {
            return;
        }
        if ($pii_log && ! $this->enableUserLogging() ) {
            return;
        }
        $troubleshooting_path = $this->getSystemInitialization()->getTroubleShootingLogPath('migration');        
        if ( ! $troubleshooting_path || ! $mode || ! $source ) {
            return;
        }
        if ( ! $this->isLogPathValid($troubleshooting_path) ) {
            return;
        }
        
        $disabled_by_default = false;
        if ($troubleshooting_path) {
            $disabled_by_default = false;
        }
        if (apply_filters('prime_mover_disable_serverside_log', $disabled_by_default)) {
            return;
        }
        if (is_object($log)) {
            $log = (array)$log;
        }
        if (is_array($log)) {
            $log = $this->printError($log);
        }
        $time = date("Y-m-d H:i:s");
        if (is_object($object)) {
            $object_logged = get_class($object);
        } else {
            $object_logged = $object;
        }
        
        $log = "$time => Logged $mode event for blog ID $blog_id from $object_logged::$source method: " . $log . PHP_EOL;
        $this->errorLog(apply_filters('prime_mover_filter_error_log', $log, $source), $troubleshooting_path);
    }
    
    /**
     * Checks if user wants a detailed file log
     * @return boolean
     */
    protected function enableFileLogging()
    {
        return (defined('PRIME_MOVER_ENABLE_FILE_LOG') && true === PRIME_MOVER_ENABLE_FILE_LOG);
    }
    
    /**
     * Checks if log path is valid
     * @param string $path
     * @return boolean
     */
    protected function isLogPathValid($path = '')
    {
        if ( ! $path ) {
            return false;
        }
        $basename = basename($path);
        if ($path === $basename) {
            return false;
        }
        $input = strtolower($path);
        return ('log' === pathinfo($input, PATHINFO_EXTENSION));
    }
    
    /**
     * Error log downloader
     * Hooked to `init`
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itStreamsErrorLog() 
     */
    public function streamError()
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        $params = $this->getShutDownUtilities()->getParameters();
        if (empty($params['prime_mover_errornonce']) || empty($params['prime_mover_blogid'])) {
            return;
        }
        
        $blog_id = $params['prime_mover_blogid'];
        if (! $this->getSystemFunctions()->primeMoverVerifyNonce($params['prime_mover_errornonce'], 'prime_mover_errornonce' . $blog_id)) {
            return;
        }
        
        $mainsite_blog_id = $this->getSystemInitialization()->getMainSiteBlogId(true);
        $this->getSystemFunctions()->switchToBlog($mainsite_blog_id);
        $error_hash_option = $this->getSystemInitialization()->getErrorHashOption();
        
        $errorlogfile = $this->getShutDownUtilities()->getErrorHash($blog_id, $error_hash_option);
        $this->getSystemFunctions()->restoreCurrentBlog();       
        do_action('prime_mover_before_streaming_errorlog', $blog_id);
        
        if (file_exists($errorlogfile)) {
            $generatedFilename = $this->getSystemInitialization()->getErrorLogFile($blog_id);
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="'. $generatedFilename .'"');
            header('Content-Length: ' . filesize($errorlogfile));
            
            flush();
            $this->getSystemFunctions()->readfileChunked($errorlogfile, false);
        }
 
        $this->getSystemFunctions()->wpDie();
    }
    
    /**
     * AJAX handler for error log reporting
     * Hooked to `wp_ajax_prime_mover_check_if_error_log_exist`
     * @compatible 5.6
     */
    public function primeMoverLogExist()
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        //Initialize response
        $response = [];
        $response['logexist'] = false;
        
        //Initialize args
        $args = [
            'prime_mover_errorlog_nonce' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'error_blog_id' => FILTER_SANITIZE_NUMBER_INT
        ];
        
        $error_post = $this->getSystemInitialization()->getUserInput('post', $args, 'errorlog_exist_check', '', 0, true);        
        if ( ! isset($error_post['error_blog_id']) ) {
            wp_die();
        }
        
        if ( ! isset($error_post['prime_mover_errorlog_nonce'] ) ) {
            wp_die();
        }
        
        if (!$this->getSystemFunctions()->primeMoverVerifyNonce($error_post['prime_mover_errorlog_nonce'], 'prime_mover_errorlog_nonce')) {
            wp_die();
        }
        $blog_id = (int)$error_post['error_blog_id'];
        $error_log = $this->getSystemInitialization()->getErrorLogFile($blog_id);
        if ( $this->getShutDownUtilities()->primeMoverErrorLogExist( false, $blog_id, $error_log) ) {
            $errorlog_url = $this->getShutDownUtilities()->getDownloadErrorLogURL( $blog_id );
            
            $response['logexist'] = true;
            $response['error_msg'] = esc_html__('Runtime Error : ', 'prime-mover' ) . '  ' . '<a href="' . esc_url($errorlog_url) . '">' .
                esc_html__( 'Report error', 'prime-mover') . '</a>';
        }
        
        //Update client of the status        
        wp_send_json($response);
    }
    
    /**
     * Prime Mover log errors
     * Designed to work even WP_DEBUG is false
     * Hooked to `prime_mover_shutdown_actions`
     * @param array $error
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itLogsPrimeMoverErrors()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itDoesNotLogMigrationErrorsIfNotAuthorized()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itDoesNotLogErrorIfNoError()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itDoesNotLogErrorIfIdIsNotSet()
     */
    public function primeMoverLogErrors(array $error)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        if ( ! is_array($error) || empty($error) ) {
            return;
        }
        $blog_id = $this->getShutDownUtilities()->primeMoverGetProcessedID();
        if ( ! $blog_id ) {
            return;
        }
        global $wp_filesystem;
        if ( ! $this->getSystemFunctions()->isWpFileSystemUsable($wp_filesystem)) {
            return;
        }
        $error_log = $this->getSystemInitialization()->getErrorLogFile($blog_id);
        $error_log_file = $this->getShutDownUtilities()->getPrimeMoverErrorPath($blog_id, $error_log);
        $source = 'WordPress admin';
        if (is_multisite()) {
            $source = 'WordPress network admin';
        }
        $report_instructions = "### Please submit this bug report via $source -> Prime Mover -> Contact us and select bug report. ###";
        $report_instructions .= PHP_EOL;
        $report_instructions .= '### Zip this log and upload to somewhere accessible. ###';
        $report_instructions .= PHP_EOL;
        $report_instructions .= '### After uploading, paste the URL to this log as relevant links in your bug report. ###';
        $report_instructions .= PHP_EOL;
        $report_instructions .= '### Developers will analyze your log and provide you with tips and hotfix. ###';
        $report_instructions .= PHP_EOL;
        $report_instructions .= '### This report is meant only to developers and to you. Please do not share this log with anyone or post publicly. ###';
        $report_instructions .= PHP_EOL;
        $report_instructions .= '### Alternatively, you can also use https://codexonics.com/contact/ to send the bug report log. ###';
        $report_instructions .= PHP_EOL;        
        
        if ( ! $wp_filesystem->exists($error_log_file)) {
            $wp_filesystem->put_contents($error_log_file, $report_instructions, FS_CHMOD_FILE);
        }

        $error_output = apply_filters('prime_mover_filter_error_output', $error, $blog_id);
        
        $error_string = $this->printError($error_output); 
        $error_hash_option = $this->getSystemInitialization()->getErrorHashOption();
        
        $this->getShutDownUtilities()->logErrorHash($blog_id, $error_log_file, $error_hash_option);
        $this->errorLog($error_string, $error_log_file);
        
        do_action('prime_mover_after_error_is_logged', $error_log_file);
    }
    
    /**
     * Print error
     * @param array $error_output
     * @codeCoverageIgnore
     */
    protected function printError($error_output = [])
    {
        return print_r($error_output,true);
    }
    
    /**
     * Error log
     * @param string $error_string
     * @param string $error_log_file
     * @codeCoverageIgnore
     */
    protected function errorLog($error_string = '', $error_log_file = '')
    {        
        file_put_contents($error_log_file, $error_string, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Delete error log before starting any processes
     * Hooked to `prime_mover_before_doing_import`
     * Hooked to `prime_mover_before_doing_export`
     * @param number $blog_id
     * @param boolean $process_initiated
     * @compatible 5.6
     */
    public function primeMoverDeleteErrorLog($blog_id = 0, $process_initiated = true)
    {        
        $this->getShutDownUtilities()->primeMoverDeleteErrorLog($blog_id, $process_initiated);
    }
    
    /**
     * Deletes temporary packages in the event of runtime errors to prevent clogging up the server
     * Hooked to `prime_mover_shutdown_actions`
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itDeletesPackageOnFatalError() 
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itDoesNotDeletePackageUserCreatedPackage() 
     * @param array $error
     */
    public function primeMoverDeletePackagePath($error = [])
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        global $wp_filesystem;
        if ( ! $this->getSystemFunctions()->isWpFileSystemUsable($wp_filesystem)) {
            return;
        }
        $blog_id = $this->getShutDownUtilities()->primeMoverGetProcessedID();
        $processed_path = $this->getShutDownUtilities()->primeMoverGetProcessedPath($blog_id);        
        if ( is_array( $processed_path ) ) {
            foreach ( $processed_path as $path_to_delete ) {
                if ( $wp_filesystem->exists($path_to_delete) && apply_filters('prime_mover_delete_package_on_error', true, $path_to_delete, $blog_id)) {                    
                    $this->reallyDeletePackage($path_to_delete, $error, $blog_id);
                }
            }
        }
    }
    
    /**
     * Verify if package needs to be deleted
     * @param string $path_to_delete
     * @param array $error
     * @param number $blog_id
     */
    protected function reallyDeletePackage($path_to_delete = '', $error = [], $blog_id = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        global $wp_filesystem;
        if ( ! $this->getSystemFunctions()->isWpFileSystemUsable($wp_filesystem)) {
            return;
        }
        
        $del = false;
        if ($wp_filesystem->is_dir($path_to_delete)) {
            $del = true;           
        } 
        
        if (!$del && isset($error['diskfull'])) {
            $del = true; 
        }
        
        if (!$del && !$this->getSystemFunctions()->isReallyValidFormat($path_to_delete)) {
            $del = true; 
        }
        
        if ($del) {
            $this->getSystemFunctions()->primeMoverDoDelete($path_to_delete);
            do_action('prime_mover_after_reallydeleting_package', $path_to_delete, $blog_id);
        }       
    }
    
    /**
     * Prime Mover error handlers on shutdown
     * This fires successfully when a fatal error is detected inside Prime Mover plugin
     * Hooked to `shutdown`
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itRunsErrorHandlerWhenItsFatalError() 
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itDoesNotRunErrorHandlerWhenErrorIsNotFatal()
     */
    public function errorHandler()
    {
        if ( ! is_admin() ) {
            return;
        }
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $error = $this->primeMoverGetErrorLast();
        if( is_null( $error ) ) {
            return;
        }
        if ( ! is_array( $error ) || empty( $error['file'] ) || empty( $error['type'] ) ) {
            return;
        }
        $bailout = false;
        if (E_ERROR !== $error['type']) {
            $bailout = true;
        }        
        if (apply_filters('prime_mover_bailout_shutdown_procedure', $bailout, $error)) {
            return;    
        }
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit();
        }  
        
        $error = apply_filters('prime_mover_filter_runtime_error', $error);
        do_action('prime_mover_shutdown_actions', $error);
    }
    
    /**
     * Get error last
     * @return array
     * @codeCoverageIgnore
     */
    protected function primeMoverGetErrorLast()
    {
        return error_get_last();
    }
    
    /**
     /* Prime Mover deletes maintenance file
      * Hooked to `prime_mover_shutdown_actions`
      * @param array $error
      * @compatible 5.6
     */
    public function primeMoverDeleteMaintenanceFile(array $error) {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if ( ! $this->getShutDownUtilities()->maybeDeleteMaintenanceFile( $error['type'] ) ) {
            return;
        }
        $this->getSystemFunctions()->disableMaintenanceDuringImport();
    }
}