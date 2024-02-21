<?php
namespace Codexonics\PrimeMoverFramework\cli;

/*
 * This file is part of the Codexonics.PrimeMoverFramework package.
 *
 * (c) Codexonics Ltd
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemChecks;
use Codexonics\PrimeMoverFramework\classes\PrimeMoverProgressHandlers;

/**
 * ZIP ARCHIVER CLASS FOR COMMAND LINE
 * @since 1.0.7
 */

if (! defined('ABSPATH')) {
    exit;
}

class PrimeMoverCLIArchive
{
 
    private $system_checks;
    private $progress_handlers;
    
    /**
     * Constructor
     * @param PrimeMoverSystemChecks $system_checks
     * @param PrimeMoverProgressHandlers $progress_handlers
     */
    public function __construct(PrimeMoverSystemChecks $system_checks, PrimeMoverProgressHandlers $progress_handlers)
    {
        $this->system_checks = $system_checks;
        $this->progress_handlers = $progress_handlers;
    }    
    
    /**
     * Get shutdown utilities
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverShutdownUtilities
     */
    public function getShutDownUtilities()
    {
        return $this->getProgressHandlers()->getShutDownUtilities();
    }
    /**
     * Get system checks
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemChecks
     */
    public function getSystemChecks()
    {
        return $this->system_checks;
    }
    
    /**
     * Get system authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     */
    public function getSystemAuthorization()    
    {
        return $this->getSystemChecks()->getSystemAuthorization();
    }
    
    /**
     * Get system initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     */
    public function getSystemInitialization()
    {
        return $this->getSystemChecks()->getSystemInitialization();
    }
    
    /**
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     */
    public function getSystemFunctions()
    {
        return $this->getSystemChecks()->getSystemFunctions();
    }
    
    /**
     * Get progress handlers
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverProgressHandlers
     */
    public function getProgressHandlers()
    {
        return $this->progress_handlers;
    }
    
    /**
     * Generate Media archiving arguments
     * @param boolean $encrypt_media
     * @param string $media_source_path
     * @param string $zippath
     * @param array $exclusion_rules
     * @param array $blogid_to_export
     * @param string $error_log_path
     * @param boolean $delete_source
     * @param string $mode
     * @param string $action
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCLIArchive::itGenerateMediaArgumentsForShell() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCLIArchive::itDoesNotGenerateMediaArgumentsForShellIfUnauthorized() 
     */
    public function generateMediaArchivingArgumentsForShell($encrypt_media = false, $media_source_path = '', $zippath = '', $exclusion_rules = [], 
        $blogid_to_export = [], $error_log_path = '', $delete_source = false, $mode = '', $action = 'export')
    {
        $aux_data = [];
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $aux_data;
        }
        $encrypt_files = 'plain';
        if ($encrypt_media) {
            $encrypt_files = 'encrypt';
        }
        $export_id = $this->getSystemInitialization()->getExportId();
        $import_id = $this->getSystemInitialization()->getImportId();
        $aux_data['enable_encryption'] = $encrypt_files;
        $aux_data['source'] = $media_source_path;
        $aux_data['zip_path'] = $zippath;
        $aux_data['exclusion_rules'] = $exclusion_rules;
        $aux_data['blog_id'] = $blogid_to_export;
        $aux_data['export_id'] = $export_id;
        $aux_data['import_id'] = $import_id;
        $aux_data['ip'] = $this->getSystemInitialization()->getUserIp();
        $aux_data['browser'] = $this->getSystemInitialization()->getUserAgent();
        $aux_data['error_log_path'] = $error_log_path;
        $aux_data['action'] = $action;
        
        if ($delete_source) {
            $this->getSystemFunctions()->primeMoverDoDelete($zippath);
        }        
        
        $aux_data = apply_filters('prime_mover_filter_shell_aux_data', $aux_data, $mode);
        return $aux_data;
    }

    /**
     * Execute shell archiving tasks
     * @param array $ret
     * @param array $copymediabyshell
     * @param array $aux_data
     * @param string $media_tmp_file
     * @param string $shell_progress_key
     * @param string $mode
     * @return array
     */
    public function executeCopyMediaShellCommand($ret = [], $copymediabyshell = [], $aux_data = [], $media_tmp_file = '', $shell_progress_key = '', $mode = 'archivemedia')
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        if ( ! is_array($ret) || empty($ret) ) {
            return $ret;
        }
        if ( ! is_array($copymediabyshell) || empty($copymediabyshell) ) {
            return $ret;
        }
        if ( ! is_array($aux_data) || empty($aux_data) ) {
            return $ret;
        }
        if (empty($copymediabyshell ['executable']) || empty($copymediabyshell ['core_load_path'])) {
            return $ret;
        }
        $php_executable = $copymediabyshell ['executable'];
        $core_load_path = $copymediabyshell ['core_load_path'];
        $error_log_encoded = base64_encode($aux_data['error_log_path']);
        $error_log = escapeshellarg($error_log_encoded);

        $ret['shell_progress_key'] = $shell_progress_key;
        if ( ! empty($aux_data['action']) && ! empty($aux_data[$aux_data['action'] . '_id'])) {
            $ret['process_id'] = $aux_data[$aux_data['action'] . '_id'];
        }
        $ret['cli_start_time'] = microtime(true);
        $ret_tmp = $ret;
        $aux_data['media_tmp_file'] = $media_tmp_file;
        $aux_data['shell_progress_key'] = $shell_progress_key;
        
        $data = [];
        $data['ret'] = $ret_tmp;
        $data['aux'] = $aux_data;
        $data['mode'] = $mode;
        
        $data_encoded = base64_encode(json_encode($data));
        $raw_data = escapeshellarg($data_encoded);
        
        $loader = [];
        $loader['loader'] = $core_load_path;
        $loader['user_id'] = get_current_user_id();
        
        $loader['ip'] = $aux_data['ip'];      
        $loader['user_agent'] = $aux_data['browser'];        
        $loader['http_host'] = $this->getSystemInitialization()->getHttpHost();
        $loader['request_method'] = $this->getSystemInitialization()->getRequestMethod();
        $loader['shell_tmp_file'] = $media_tmp_file;
        $loader['shell_process_mode'] = $mode;
        
        $loader_encoded = base64_encode(json_encode($loader));
        $loader_data = escapeshellarg($loader_encoded);
        
        $script = PRIME_MOVER_COPYMEDIA_SCRIPT;        
        $hash_file = $this->getSystemFunctions()->sha256File($media_tmp_file);
        
        $message = $loader_encoded . $data_encoded . $hash_file . $error_log_encoded;   
        
        $auth_key = $this->getSystemInitialization()->getAuthKey();
        $exporter_auth = escapeshellarg(base64_encode(hash_hmac('sha256', $message, $auth_key)));
        $file_auth = escapeshellarg(base64_encode($media_tmp_file));
        $blog_id = $aux_data['blog_id'];  
        
        if ( ! $this->validateExecCall($php_executable, $script, $blog_id)) {
            return $ret;
        }        
              
        $this->logShellEvents($shell_progress_key, $blog_id, $data, $loader, $php_executable, $script, $error_log, $loader_data, $raw_data, $exporter_auth, $file_auth);
        $cli_opcache = $this->enableOpCacheForCli();
        $opcache = '';
        if ($cli_opcache) {
            $opcache = '-d opcache.enable_cli=1';
        }
        $process_id = exec("$php_executable $opcache $script $error_log $loader_data $raw_data $exporter_auth $file_auth > /dev/null & echo $!");
        
        $ret[$shell_progress_key] = true;
        $ret['shell_process_id'] = $process_id;
        $ret['media_tmp_file'] = $media_tmp_file;        
        
        return $ret;
    }    
    
    /**
     * Enable opcache for CLI processes if supported
     * @return boolean
     */
    private function enableOpCacheForCli()
    {
        $result = [];
        if (function_exists('opcache_get_status')) {
            $result = opcache_get_status();
        }
        return (isset($result['opcache_enabled']) && true === $result['opcache_enabled']);
    }
    
    /**
     * Log shell events
     */
    private function logShellEvents($shell_progress_key, $blog_id, $data, $loader, $php_executable, $script, $error_log, $loader_data, $raw_data, $exporter_auth, $file_auth)
    {
        do_action('prime_mover_log_processed_events', "Processing $shell_progress_key action in SHELL with the following parameters: ", $blog_id, 'export', 'executeCopyMediaShellCommand', $this);
        do_action('prime_mover_log_processed_events', "Data array: ", $blog_id, 'export', 'executeCopyMediaShellCommand', $this);
        do_action('prime_mover_log_processed_events', $data, $blog_id, 'export', 'executeCopyMediaShellCommand', $this);
        do_action('prime_mover_log_processed_events', "Loader array: ", $blog_id, 'export', 'executeCopyMediaShellCommand', $this);
        do_action('prime_mover_log_processed_events', $loader, $blog_id, 'export', 'executeCopyMediaShellCommand', $this);
        do_action('prime_mover_log_processed_events', "Command: ", $blog_id, 'export', 'executeCopyMediaShellCommand', $this);
        do_action('prime_mover_log_processed_events', "$php_executable $script $error_log $loader_data $raw_data $exporter_auth $file_auth > /dev/null & echo $!", $blog_id, 'export', 'executeCopyMediaShellCommand', $this);        
    }
    
    /**
     * Validate exec caller
     * @param string $given_executable
     * @param string $given_script
     * @param number $blog_id
     * @return boolean
     */
    private function validateExecCall($given_executable = '', $given_script = '', $blog_id = 0)
    {
        if ( ! $given_executable || ! $given_script ) {
            return false;
        }

        $script = realpath($given_script);            
        if ( ! $this->getSystemFunctions()->fileIsInsideGivenDirectory($given_script, PRIME_MOVER_PLUGIN_CORE_PATH)) {
            do_action('prime_mover_log_processed_events', "ERROR: Exec Files inside given directory fails.", $blog_id, 'export', 'validateExecCall', $this);
            return false;
        }
        if (basename($script) !== PRIME_MOVER_SHELL_ARCHIVER_FILENAME) {
            do_action('prime_mover_log_processed_events', "ERROR: Exec Shell archiver filename fails.", $blog_id, 'export', 'validateExecCall', $this);
            return false;
        }
        $php_executable = $this->getPhpExecutable();
        if ($given_executable !== $php_executable) {
            do_action('prime_mover_log_processed_events', "ERROR: Exec PHP executable fails.", $blog_id, 'export', 'validateExecCall', $this);
            return false;
        }
        
        return true;
    }
    
    /**
     * Checks if shell process is running
     * Linux only
     * @param number $process_id
     * @return boolean
     */
    private function processIsRunning($process_id = 0)
    {
        if ( ! $process_id ) {
            return false;
        }
        $process_id = escapeshellarg($process_id);
        $command = 'ps -p '. $process_id;
        
        /**
         * @var Type $op output variable
         */
        
        exec($command,$op);
        if (!isset($op[1])) {
            return false;
        } else {
            return true;
        }
    }
    
    /**
     * Returns TRUE if shell archiver is running during export
     * @param array $ret
     * @param array $copymediabyshell
     * @param string $media_tmp_file
     * @param string $shell_progress_key
     * @param number $blogid_to_export
     * @return boolean
     */
    public function shellMediaArchivingProcessRunning($ret = [], $copymediabyshell = [], $media_tmp_file = '', $shell_progress_key = '', $blogid_to_export = 0)
    {
        if ( ! is_array($copymediabyshell) || ! isset( $ret[$shell_progress_key] ) || ! $media_tmp_file ||  ! $shell_progress_key) {  
            $this->logProcessingErrorParameters($ret = [], $blogid_to_export = 0, $copymediabyshell = [], $shell_progress_key = '', $media_tmp_file = '');
            return false;
        }    
        
        $process_id = '';
        if ( ! empty($ret['shell_process_id']) ) {
            $process_id = $ret['shell_process_id'];
        }
        
        $running_process = false;
        if ($process_id) {
            $running_process = $this->processIsRunning($process_id);            
        } 
        
        do_action('prime_mover_log_processed_events', "Process ID: $process_id to be checked whether its running or stopped.", $blogid_to_export, 'export', 'shellMediaArchivingProcessRunning', $this);
        if ($running_process) {
            do_action('prime_mover_log_processed_events', "Shell process confirmed to be still running. ", $blogid_to_export, 'export', 'shellMediaArchivingProcessRunning', $this);
        } else {
            do_action('prime_mover_log_processed_events', "Shell process NOT anymore running.", $blogid_to_export, 'export', 'shellMediaArchivingProcessRunning', $this);
        }
        
        $tmp_file_exist = false;
        if ($this->getSystemFunctions()->nonCachedFileExists($media_tmp_file)) {
            do_action('prime_mover_log_processed_events', "Tmp file STILL EXIST, so its running.", $blogid_to_export, 'export', 'shellMediaArchivingProcessRunning', $this);
            $tmp_file_exist = true;
        } else {
            do_action('prime_mover_log_processed_events', "Tmp file does NOT anymore exist, looks deleted or stopped..", $blogid_to_export, 'export', 'shellMediaArchivingProcessRunning', $this);
        }
        
        return $tmp_file_exist && $running_process;
    }
    
    private function logProcessingErrorParameters($ret = [], $blogid_to_export = 0, $copymediabyshell = [], $shell_progress_key = '', $media_tmp_file = '')
    {
        do_action('prime_mover_log_processed_events', "shellMediaArchivingProcessRunning invalid parameters, bail out: ", $blogid_to_export, 'export', 'shellMediaArchivingProcessRunning', $this);
        do_action('prime_mover_log_processed_events', "Ret variable: ", $blogid_to_export, 'export', 'shellMediaArchivingProcessRunning', $this);
        do_action('prime_mover_log_processed_events', $ret, $blogid_to_export, 'export', 'shellMediaArchivingProcessRunning', $this);
        do_action('prime_mover_log_processed_events', "Copy media by shell: ", $blogid_to_export, 'export', 'shellMediaArchivingProcessRunning', $this);
        do_action('prime_mover_log_processed_events', $copymediabyshell, $blogid_to_export, 'export', 'shellMediaArchivingProcessRunning', $this);
        do_action('prime_mover_log_processed_events', "Shell progress key: ", $blogid_to_export, 'export', 'shellMediaArchivingProcessRunning', $this);
        do_action('prime_mover_log_processed_events', $shell_progress_key, $blogid_to_export, 'export', 'shellMediaArchivingProcessRunning', $this);
        do_action('prime_mover_log_processed_events', "Media tmp file: ", $blogid_to_export, 'export', 'shellMediaArchivingProcessRunning', $this);
        do_action('prime_mover_log_processed_events', $media_tmp_file, $blogid_to_export, 'export', 'shellMediaArchivingProcessRunning', $this);
    }
    /**
     * Returns TRUE if shell media is STOPPED
     * @param array $ret
     * @param array $copymediabyshell
     * @param string $media_tmp_file
     * @param string $shell_progress_key
     * @param number $blogid_to_export
     * @return boolean
     */
    public function shellMediaArchivingStopped($ret = [], $copymediabyshell = [], $media_tmp_file = '', $shell_progress_key = '', $blogid_to_export = 0)
    {
        $running = $this->shellMediaArchivingProcessRunning($ret, $copymediabyshell, $media_tmp_file, $shell_progress_key, $blogid_to_export );
        if ( ! $running ) {
            do_action('prime_mover_log_processed_events', "Shell media archiving STOPPED", $blogid_to_export, 'export', 'shellMediaArchivingStopped', $this);
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Returns TRUE if shell media archiving is COMPLETED
     * Otherwise false
     * @param array $copymediabyshell
     * @param array $ret
     * @param string $shell_progress_key
     * @param string $media_tmp_file
     * @return boolean
     */
    public function shellMediaArchivingCompleted($copymediabyshell = [], $ret = [], $shell_progress_key = '', $media_tmp_file = '')
    {
        return is_array($copymediabyshell) && isset($ret[$shell_progress_key]) && ! $this->getSystemFunctions()->nonCachedFileExists($media_tmp_file);
    }
    
    /**
     * Returns TRUE if shell media archving has not yet started
     * Otherwise FALSE
     * @param array $copymediabyshell
     * @param array $ret
     * @param string $shell_progress_key
     * @return boolean
     */
    public function shellMediaArchivingHasNotStarted($copymediabyshell = [], $ret = [], $shell_progress_key = '')
    {
        return (is_array($copymediabyshell) && ! isset($ret[$shell_progress_key]));
    }
    
    /**
     * Checks if we need to process using shell functions
     * @return boolean|string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCLIArchive::itMaybeArchiveMediaByShell()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCLIArchive::itDoesNotArchiveMediaByShellIfUnauthorized()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCLIArchive::itDoesNotArchiveMediaShellIfFileSystemNotSet()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCLIArchive::itDoesNotArchiveMediaShellIfSlowWebHost()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCLIArchive::itDoesNotArchiveMediaShellIfWindows()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCLIArchive::itDoesNotArchiveMediaShellIfNoExecFunctions()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCLIArchive::itDoesNotArchiveMediaShellIfNoPHPExecutables() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCLIArchive::itDoesNotArchiveMediaShellIfNoCoreLoadFile()
     */
    public function maybeArchiveMediaByShell()
    {
        global $wp_filesystem;
        
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return false;
        }        
        if ( ! $this->getSystemFunctions()->isWpFileSystemUsable($wp_filesystem) ) {
            return false;
        }
        if (defined('PRIME_MOVER_SLOW_WEB_HOST') && true === PRIME_MOVER_SLOW_WEB_HOST) {
            return false;
        }
        $parameters = [];
        if ( $this->getSystemChecks()->isWindows()) {
            return false;
        }
        
        if ( ! $this->getSystemChecks()->hasSystem()) {
            return false;
        }
        $php_executable = $this->getPhpExecutable();        
        if ( ! $php_executable ) {
            return false;
        }        
        
        $core_load_path = ABSPATH . 'wp-load.php';                
        if ( ! $wp_filesystem->exists($core_load_path)) {
            return false;
        }
        
        $parameters['executable'] = $php_executable;
        $parameters['core_load_path'] = $core_load_path;
        
        return $parameters;
    }

    /**
     * Get PHP executable
     * @return boolean|string
     */
    protected function getPhpExecutable()
    {    
        $phpinfo = $this->getShutDownUtilities()->phpinfo2array();
        if (empty($phpinfo['phpinfo']['PHP API']) || empty($phpinfo['phpinfo']['PHP Extension'])) {
            return false;
        }
        $phpversion_web = phpversion();
        $php_api_web_version = $phpinfo['phpinfo']['PHP API'];
        $php_extension_web_version = $phpinfo['phpinfo']['PHP Extension'];
        
        $probable_executables = [];    
        $which_php = `which php`;
        if ($which_php) {
            $probable_executables[] = trim(`which php`);
        }        
        if ( defined('PHP_BINDIR') && PHP_BINDIR) {
            $probable_executables[] = trailingslashit(trim(PHP_BINDIR)) . 'php';
        }
        if ( defined('PHP_BINARY') && PHP_BINARY) {
            $probable_executables[] = trailingslashit(trim(PHP_BINARY)) . 'php';    
        }               
        foreach ($probable_executables as $executable) {
            if ( ! $this->getSystemChecks()->isExecutable($executable) ) {
                continue;
            } 
            $php_shell_info = shell_exec(escapeshellcmd("$executable -i"));
            if ( ! $php_shell_info ) {
                continue;
            }
            if (false !== strpos($php_shell_info, $phpversion_web) && false !== strpos($php_shell_info, $php_api_web_version) && 
                false !== strpos($php_shell_info, $php_extension_web_version)) {
                return $executable;
            }
        }        
        return false;        
    }
    
    /**
     * Do Shell archiving related tasks
     * @param array $copymediabyshell
     * @param array $ret
     * @param string $shell_progress_key
     * @param number $blogid
     * @param boolean $encrypt_media
     * @param string $source
     * @param string $mode
     * @param string $zippath
     * @param string $error_log_path
     * @param string $media_tmp_file
     * @param array $process_methods
     * @param boolean $delete_source
     * @param array $exclusion_rules
     * @param string $task
     * @param string $action
     * @return array
     */
    public function doShellArchivingTasks($copymediabyshell = [], $ret = [], $shell_progress_key = '', $blogid = 0, $encrypt_media = false, $source = '', $mode = 'media', $zippath = '', 
        $error_log_path = '', $media_tmp_file = '', $process_methods = [], $delete_source = false, $exclusion_rules = [], $task = '', $action = 'export')
    {
        $current = $process_methods['current'];
        $previous = $process_methods['previous'];
        $next = $process_methods['next'];
        
        if ( ! $task ) {
            $task = esc_html__('Archiving', 'prime-mover');
        }
        
        if ($this->shellMediaArchivingHasRestarted($copymediabyshell, $ret, $shell_progress_key, $action) || $this->shellMediaArchivingHasNotStarted($copymediabyshell, $ret, $shell_progress_key)) {
            
            $ret = $this->processShellRunTimeErrors($ret, $media_tmp_file, true, $blogid, $action, $task, $shell_progress_key);
            if (isset($ret['error'])) {
                return $ret;
            }

            do_action('prime_mover_log_processed_events', "$task $mode files in shell environment.", $blogid, $action, 'doShellArchivingTasks', $this);
            $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__("$task %s via CLI", 'prime-mover'), $mode), $action);
            $aux_data = $this->generateMediaArchivingArgumentsForShell($encrypt_media, $source, $zippath, $exclusion_rules, $blogid, $error_log_path, $delete_source, $mode, $action);
            
            $ret = $this->executeCopyMediaShellCommand($ret, $copymediabyshell, $aux_data, $media_tmp_file, $shell_progress_key, $mode);
            return apply_filters("prime_mover_save_return_{$action}_progress", $ret, $blogid, $current, $previous);
            
        } elseif ($this->shellMediaArchivingProcessRunning($ret, $copymediabyshell, $media_tmp_file, $shell_progress_key, $blogid)) {
            
            do_action('prime_mover_log_processed_events', "Copying and archiving is ONGOING in shell environment.", $blogid, $action, 'doShellArchivingTasks', $this);
            return apply_filters("prime_mover_save_return_{$action}_progress", $ret, $blogid, $current, $previous);
            
        } elseif ($this->shellMediaArchivingCompleted($copymediabyshell, $ret, $shell_progress_key, $media_tmp_file)) {
            
            do_action('prime_mover_log_processed_events', "{$task} is now COMPLETED in shell environment.", $blogid, $action, 'doShellArchivingTasks', $this); 
            $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $shell_progress_key, 'import', $action);  
            
            return apply_filters("prime_mover_save_return_{$action}_progress", $ret, $blogid, $next, $current);
            
        } elseif ($this->shellMediaArchivingStopped($ret, $copymediabyshell, $media_tmp_file, $shell_progress_key, $blogid)) {            
            
            return $this->processShellRunTimeErrors($ret, $media_tmp_file, false, $blogid, $action, $task);
        }
    } 
    
     /**
     * Checks if CLI processing is restarted
     * @param array $copymediabyshell
     * @param array $ret
     * @param string $shell_progress_key
     * @param string $action
     * @return boolean
     */
    public function shellMediaArchivingHasRestarted($copymediabyshell = [], $ret = [], $shell_progress_key = '', $action = '')
    {
        $restarted = false;
        $cli_tmpname = '';
        
        if ( ! $action || ! $shell_progress_key) {
            return $restarted;
        }
        if (is_array($copymediabyshell) && $this->getSystemInitialization()->getProcessIdBasedOnGivenAction($action)) {
            $process_id = $this->getSystemInitialization()->getProcessIdBasedOnGivenAction($action);
            $cli_tmpname = $this->getSystemInitialization()->generateCliReprocessingTmpName($ret, $process_id, $shell_progress_key);
        }
        if ($cli_tmpname && $this->getSystemFunctions()->nonCachedFileExists($cli_tmpname)) {            
            $restarted = true;            
        }
        
        return $restarted;
    }
    
    /**
     * Process runtime shell errors
     * @param array $ret
     * @param string $media_tmp_file
     * @param boolean $strict_check
     * @param number $blogid
     * @param string $action
     * @param string $task
     * @param string $shell_progress_key
     * @return string
     */
    protected function processShellRunTimeErrors($ret = [], $media_tmp_file = '', $strict_check = false, $blogid = 0, $action = '', $task = '', $shell_progress_key = '')
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        do_action('prime_mover_log_processed_events', "{$task} is now STOPPED in shell environment.", $blogid, $action, 'doShellArchivingTasks', $this);
        global $wp_filesystem;
        $shell_runtime_error = trim($wp_filesystem->get_contents($media_tmp_file));
        $note = esc_html__('ERROR: Shell process terminated =', 'prime-mover');
        
        if ($shell_runtime_error && false !== strpos($shell_runtime_error, 'prime-mover-shell-shutdown-error')) {
            $ret['error'] = $note . ' ' . $shell_runtime_error;
        } elseif ( ! $strict_check ) {
            $ret['error'] = $note . ' ' . sprintf(esc_html__('by your web host due to insufficient resources. Try adding this in wp-config.php to see if it helps: %s ', 'prime-mover'), 
                'define("PRIME_MOVER_SLOW_WEB_HOST", true);');
        }       
        if ( ! empty($ret['error']) ) {
            $this->maybeDeleteCliRestartTmpFile($action, $ret, $shell_progress_key);
            $wp_filesystem->delete($media_tmp_file);
        }
        
        return $ret;
    } 
    
    /**
     * Maybe delete CLI restart tmp file
     * @param string $action
     * @param array $ret
     * @param string $shell_progress_key
     */
    protected function maybeDeleteCliRestartTmpFile($action = '', $ret = [], $shell_progress_key = '')
    {
        global $wp_filesystem;
        $process_id = $this->getSystemInitialization()->getProcessIdBasedOnGivenAction($action);
        $cli_tmpname = $this->getSystemInitialization()->generateCliReprocessingTmpName($ret, $process_id, $shell_progress_key);
        
        if ($cli_tmpname && $this->getSystemFunctions()->nonCachedFileExists($cli_tmpname)) {
            $wp_filesystem->delete($cli_tmpname);
        }
    }
    
    /**
     * Initialize master tmp files
     * @param array $ret
     * @return array
     */
    public function initializeMasterTmpFiles($ret = [])
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized() || empty($ret['original_blogid'])) {
            return $ret;
        }
        $copymediabyshell = $this->maybeArchiveMediaByShell();
        if ( ! $copymediabyshell ) {
            return $ret;
        }
        $ret['master_tmp_shell_files'] = wp_normalize_path($this->getSystemInitialization()->wpTempNam());
        $ret['master_tmp_shell_dirs'] = wp_normalize_path($this->getSystemInitialization()->wpTempNam());
        
        $blog_id = $ret['original_blogid'];
        $values = [$ret['master_tmp_shell_files'], $ret['master_tmp_shell_dirs']];
        
        $option = $this->getSystemInitialization()->getCliMasterTmpFilesOptions() . "_" . $blog_id;
        $this->getSystemFunctions()->updateSiteOption($option, $values, true);
        
        return $ret;
    }
    
    /**
     * Open master tmp file resource
     * @param array $ret
     * @param string $entity
     * @param string $mode
     * @return NULL|resource[]|resource
     */
    public function openMasterTmpFileResource($ret = [], $entity = '', $mode = 'wb')
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return null;
        }
        $is_cli = $this->getSystemInitialization()->isCliEnvironment();
        $copymediabyshell = $this->maybeArchiveMediaByShell();
        if ( ! $copymediabyshell && ! $is_cli) {
            return null;
        }
        if (empty($ret['master_tmp_shell_files']) || empty($ret['master_tmp_shell_dirs'])) {
            return null;
        }
        if (! $entity) {
            return [fopen($ret['master_tmp_shell_files'], $mode), fopen($ret['master_tmp_shell_dirs'], $mode)];
        }
        if ('file' === $entity) {
            return fopen($ret['master_tmp_shell_files'], $mode);
        }
        if ('dir' === $entity) {
            return fopen($ret['master_tmp_shell_dirs'], $mode);
        }
        return null;
    }
    
    /**
     * Write master tmp log
     * @param string $data
     * @param resource $resource
     * @param array $ret
     * @param string $mode
     * @param boolean $close
     * @return void|NULL
     */
    public function writeMasterTmpLog($data = '', $resource = null, $ret = [], $mode = 'wb', $close = false)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }    
        $is_cli = $this->getSystemInitialization()->isCliEnvironment();
        $copymediabyshell = $this->maybeArchiveMediaByShell();
        if ( ! $copymediabyshell && ! $is_cli) {
            return null;
        }
        if (! $data) {
            return;
        }
        $data = wp_normalize_path($data);
        if (! is_resource($resource) && is_file($data)) {
            $resource = $this->openMasterTmpFileResource($ret, 'file', $mode);
        }
        if ( ! is_resource($resource) && is_dir($data)) {
            $resource = $this->openMasterTmpFileResource($ret, 'dir', $mode);
        }
        if (is_resource($resource)) {
            fwrite($resource, $data . PHP_EOL);
            if ($close) {
                $this->closeMasterTmpLog($resource);
            }
        }       
    }
    
    /**
     * Close master tmp log resource
     * @param mixed $resources
     */
    public function closeMasterTmpLog($resources)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $file_handle = null;
        $dir_handle = null;
        if (is_array($resources) && !empty($resources)) {
            list($file_handle, $dir_handle) = $resources;
        }
        if (is_resource($file_handle)) {
            fclose($file_handle);
        }
        if (is_resource($dir_handle)) {
            fclose($dir_handle);
        }
        if (is_resource($resources)) {
            fclose($resources);
        }
    }
}
