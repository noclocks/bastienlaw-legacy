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

/**
 * COMMAND LINE CLASS INTERFACE FOR SHELL ARCHIVER
 * @since 1.0.7
 */

use Codexonics\PrimeMoverFramework\classes\PrimeMover;

if (! defined('ABSPATH')) {
    exit;
}

class PrimeMoverCLIShellArchiver
{
 
    private $prime_mover;
    private $utilities;
    private $parameters;
    private $importer_exporter_array;
    private $aux_array;
    private $zip_path;
    private $source_media;
    private $exclusion_rules;
    private $encryption_status;
    private $blog_id;
    private $export_id; 
    private $media_tmp_file;
    private $shell_progress_key;
    private $import_id;
    private $action = 'export';
    private $mode = '';
    
    /**
     * Construct
     * @param PrimeMover $prime_mover
     * @param array $utilities
     * @param array $parameters
     */
    public function __construct(PrimeMover $prime_mover, $utilities = [], $parameters = [])
    {
        $this->prime_mover = $prime_mover;
        $this->utilities = $utilities;
        $this->parameters = $parameters;
    }    
  
    /**
     * Get action
     * @return string|array
     */
    public function getAction()
    {
        return $this->action;
    }
    
    /**
     * 
     * @return \Codexonics\PrimeMoverFramework\cli\PrimeMoverCLIArchive
     */
    public function getCliArchiver()
    {
        return $this->getExporter()->getCliArchiver();
    }
    
    /**
     * Get importer
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverImporter
     */
    public function getImporter()
    {
        return $this->getPrimeMover()->getImporter();
    }
    
    /**
     * 
     * Get tmp file for this shell archiver
     */
    public function getMediaTmpFile()
    {
        return $this->media_tmp_file;
    }
    
    /**
     * Get shell progress key
     */
    public function getShellProgressKey()
    {
        return $this->shell_progress_key;
    }
    
    /**
     * Get Prime Mover instance
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMover
     */
    public function getPrimeMover()
    {
        return $this->prime_mover;
    }  
 
    /**
     * Get system initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     */
    public function getSystemInitialization()
    {
        return $this->getPrimeMover()->getSystemInitialization();
    }
    
    /**
     * Get exporter
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverExporter
     */
    public function getExporter()
    {
        return $this->getPrimeMover()->getExporter();
    }
 
    /**
     * Get system checks
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemChecks
     */
    public function getSystemChecks()
    {
        return $this->getPrimeMover()->getSystemChecks();
    }
    
    /**
     * Get system authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     */
    public function getSystemAuthorization()
    {
        return $this->getPrimeMover()->getSystemAuthorization();
    }
    
    /**
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     */
    public function getSystemFunctions()
    {
        return $this->getPrimeMover()->getSystemFunctions();
    }
        
    /**
     * Get export ID
     * @return array
     */
    public function getExportId()
    {
        return $this->export_id;
    }
    
    /**
     * Get import ID
     * @return array
     */
    public function getImportId()
    {
        return $this->import_id;
    }
    
    /**
     * Get progress handlers
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverProgressHandlers
     */
    public function getProgressHandlers()
    {
        return $this->getPrimeMover()->getExporter()->getProgressHandlers();
    }  
    
    /**
     * Get system check utilities
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverSystemCheckUtilities
     */
    public function getSystemCheckUtilities()
    {
        return $this->getSystemChecks()->getSystemCheckUtilities();
    }
    
    /**
     * Get blog ID
     */
    public function getBlogId()
    {
        return $this->blog_id;
    }
    
    /**
     * Get encryption status
     */
    public function getEncryptionStatus()
    {
        return $this->encryption_status;
    }
    
    /**
     * Get exclusion rules
     */
    public function getExclusionRules()
    {
        return $this->exclusion_rules;
    }
    
    /**
     * Get source media
     */
    public function getSourceMedia()
    {
        return $this->source_media;
    }
    
    /**
     * Get zip path
     */
    public function getZipPath()
    {
        return $this->zip_path;
    }
    
    /**
     * Get exporter array on export and importer array on import
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCLIShellArchiver::itRunsCliArchiver()
     */
    public function getImporterExporterArray()
    {
        return $this->importer_exporter_array;
    }
    
    /**
     * Get aux array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCLIShellArchiver::itRunsCliArchiver()
     */
    public function getAuxArray()
    {
        return $this->aux_array;
    }    
    
    /**
     * Get parameters
     */
    public function getParameters()
    {
        return $this->parameters;
    }
    
    /**
     * Get mode
     * @return string|mixed
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCLIShellArchiver::itRunsCliArchiver()
     */
    public function getMode()
    {
        return $this->mode;
    }
    
    /**
     * Initialize hooks
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCLIShellArchiver::itChecksIfHooksAreOutdated()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCLIShellArchiver::itAddsInitHooks() 
     */
    public function initHooks()
    {
        add_action('wp_loaded', [$this, 'maybeRunCLIArchiver']);
    }    
    
    /**
     * Hooked in `wp_loaded`
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCLIShellArchiver::itRunsCliArchiver()
     */
    public function maybeRunCLIArchiver()
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if ( ! $this->getSystemInitialization()->isCliEnvironment()) {
            return;
        }
        $valid_parameters = $this->isValidShellParameters();        
        if (false === $valid_parameters) {
            do_action('prime_mover_log_processed_events', "Validating parameters for CLI archiving failed:", 0, 'common', __FUNCTION__, $this);
            do_action('prime_mover_log_processed_events', $valid_parameters, 0, 'common', __FUNCTION__, $this);
            return;
        }
        
        /**
         * @var Type $script_path CLI-script
         * @var Type $error_log Error log path
         * @var Type $loader_data Loader data
         * @var Type $raw_data Raw data
         * @var Type $exporter_auth Exporter auth
         * @var Type $file_auth File auth
         */
        do_action('prime_mover_log_processed_events', "Successfully validated parameters for CLI archiving", 0, 'common', __FUNCTION__, $this);
        list($script_path, $error_log, $loader_data, $raw_data, $exporter_auth, $file_auth) = $valid_parameters;        
        
        /**
         * Main data array
         */
        $data = json_decode(base64_decode($raw_data), true);        
        $this->importer_exporter_array = $data['ret'];
        $this->aux_array = $data['aux'];
        $mode = $data['mode'];
        $this->mode = $mode;
        
        /**
         * Set aux data
         */
        $this->setAuxData($this->aux_array);  
        
        /**
         * Execute the mode of the task
         */
        if ('media' === $mode) {
            $this->processMediaForArchiving();  
        }
        
        if ('SQL' === $mode) {
            $this->processSQLForArchiving();  
        }
        
        if ('footprint' === $mode) {
            $this->processFootPrintForArchiving();
        }  
        
        if ('plugins' === $mode) {
            $this->processPluginsForArchiving();
        } 
        
        if ('themes' === $mode) {
            $this->processThemesForArchiving();
        }
        
        if ('main directory' === $mode) {
            $this->processMainDirectoryForArchiving();
        }
        
        if ('extraction' === $mode) {
            $this->doExtractionWork();
        }
        
        if ('plugins import' === $mode) {
            $this->processPluginsImport();
        }
        
        if ('temp directory' === $mode) {
            $this->deleteTmpDirectory();
        }
    }
 
    /**
     * Delete tmp directory
     */
    protected function deleteTmpDirectory()
    {
        $ret = $this->getImporterExporterArray();        
        $tmp_folderpath = $ret['temp_folder_path'];
        $blogid = $this->getBlogId();
        
        global $wp_filesystem;
        $delete_result = $wp_filesystem->rmdir($tmp_folderpath, true);        
        
        if (false === $delete_result) {
            do_action('prime_mover_log_processed_events', 'ERROR: Unable to delete temporary directory in shell mode.', $blogid, 'export', __FUNCTION__, $this);
        }        
        
        $this->deleteMediaTmpFile();
        
        $memory_used = $this->getSystemFunctions()->getPeakMemoryUsage();
        $this->getSystemFunctions()->logPeakMemoryUsage($blogid, __FUNCTION__, 'export', $memory_used);
    }
    
    /**
     * Import plugins import
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCLIShellArchiver::itProcessPluginsImport()
     */
    protected function processPluginsImport()
    {
        $ret = $this->getImporterExporterArray();
        $blogid_to_import = $this->getBlogId();
        $plugins_import = apply_filters('prime_mover_import_plugins_by_shell', $ret, $blogid_to_import, false, 0, true);        
        
        if (true === $plugins_import) {
            $this->deleteMediaTmpFile();
            
            $memory_used = $this->getSystemFunctions()->getPeakMemoryUsage();
            $this->getSystemFunctions()->logPeakMemoryUsage($blogid_to_import, __FUNCTION__, 'import', $memory_used);            
        } elseif (isset($plugins_import['error'])) {
            do_action( 'prime_mover_shutdown_actions', ['type' => 1, 'message' => $plugins_import['error']] );
        }
    }
 
    /**
     * Process plugins for export archiving
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCLIShellArchiver::itProcessPluginsForArchiving()
     */
    protected function processPluginsForArchiving()
    {
        $blog_id = $this->getBlogId();
        $ret = $this->getImporterExporterArray();
        
        $plugins_archiving = apply_filters('prime_mover_export_plugins_by_shell', $ret);
        if (true === $plugins_archiving) {
            $this->deleteMediaTmpFile();
            $memory_used = $this->getSystemFunctions()->getPeakMemoryUsage();
            $this->getSystemFunctions()->logPeakMemoryUsage($blog_id, __FUNCTION__, 'export', $memory_used);
        }
    } 
    
    /**
     * Process themes for export archiving
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCLIShellArchiver::itProcessThemesForArchiving() 
     */
    protected function processThemesForArchiving()
    {
        $blog_id = $this->getBlogId();
        $ret = $this->getImporterExporterArray();
        $zippath = $this->getZipPath();
 
        $themes_archiving = apply_filters('prime_mover_export_themes', $ret, $zippath, true);        
        if (true === $themes_archiving) {
            $this->deleteMediaTmpFile();
            $memory_used = $this->getSystemFunctions()->getPeakMemoryUsage();
            $this->getSystemFunctions()->logPeakMemoryUsage($blog_id, __FUNCTION__, 'export', $memory_used);
        }
    }     
    
    /**
     * Extract Prime Mover main package
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCLIShellArchiver::itDoExtractionWork()
     */
    protected function doExtractionWork()
    {
        $ret = $this->getImporterExporterArray();   
        
        /** @var Type $unzipped_directory Unzipped directory*/
        list($file_path, $destination, $unzipped_directory, $mode) = $this->getImporter()->computeExtractVariables($ret);
        $blogid_to_import = $this->getBlogId();
        
        $start_time = $ret['cli_start_time'];
        $extraction_result = $this->getImporter()->doExtractionWork($ret, $blogid_to_import, $start_time, true, $file_path, $destination, $mode);         
        $done = false;
        
        if (isset($extraction_result['media_zip_extraction_done'])) {            
            $done = true;
        } elseif(isset($extraction_result['error'])) {            
            do_action( 'prime_mover_shutdown_actions', ['type' => 1, 'message' => $extraction_result['error']] );            
        } 
        
        if ($done) {
            $this->getImporter()->doAfterExtraction($ret, $blogid_to_import, $unzipped_directory, $file_path, true);
            $this->deleteMediaTmpFile();
            
            $memory_used = $this->getSystemFunctions()->getPeakMemoryUsage();
            $this->getSystemFunctions()->logPeakMemoryUsage($blogid_to_import, __FUNCTION__, 'import', $memory_used);
        }        
    }
    
    /**
     * Process main directory for archiving
     */
    protected function processMainDirectoryForArchiving()
    {
        $blog_id = $this->getBlogId(); 
        $ret = $this->getImporterExporterArray();

        $directory_handle = fopen($ret['master_tmp_shell_dirs'], "ab");
        $file_handle = fopen($ret['master_tmp_shell_files'], "rb");
        
        stream_copy_to_stream($file_handle, $directory_handle);
        
        fclose($directory_handle);
        fclose($file_handle);
        
        $this->zipDirectory('', true);        
        $zippath = $this->getZipPath();
        
        $hash_path = $ret['temp_folder_path'] . 'hash.txt';
        
        if ($this->getSystemFunctions()->nonCachedFileExists($zippath)) {
            $computed_hash = $this->getSystemFunctions()->hashString($ret['temp_folder_path']);
            file_put_contents($hash_path, $computed_hash, FILE_APPEND | LOCK_EX);
        }       
        
        $this->deleteMediaTmpFile($ret);
        
        $memory_used = $this->getSystemFunctions()->getPeakMemoryUsage();
        $this->getSystemFunctions()->logPeakMemoryUsage($blog_id, __FUNCTION__, 'export', $memory_used);
    }
    
    /**
     * Process media for archiving
     */
    protected function processMediaForArchiving()
    {
        /**
         * Execute the archiving process
         */
        $blog_id = $this->getBlogId();        
        list($media_zip, $ret) = $this->zipDirectory();       
        $this->getCliArchiver()->writeMasterTmpLog($media_zip, null, $ret, 'ab', true);
        
        $this->addMediaEncryptedSignature();
        $this->deleteMediaTmpFile();
        $memory_used = $this->getSystemFunctions()->getPeakMemoryUsage();
        $this->getSystemFunctions()->logPeakMemoryUsage($blog_id, __FUNCTION__, 'export', $memory_used);
    }
 
    /**
     * Process SQL for archiving
     */
    protected function processSQLForArchiving()
    {
        $blog_id = $this->getBlogId(); 
        $ret = $this->getImporterExporterArray();
        $source = $this->getSourceMedia();
        $encrypt = false;
        if ('encrypt' === $this->getEncryptionStatus()) {
            $encrypt = true;
        }
        $this->getExporter()->zipDbDumpHelper($ret, $source, $encrypt);
        $this->deleteMediaTmpFile();
        
        $memory_used = $this->getSystemFunctions()->getPeakMemoryUsage();
        $this->getSystemFunctions()->logPeakMemoryUsage($blog_id, __FUNCTION__, 'export', $memory_used);
    }
 
    /**
     * Process footprint for archiving
     */
    protected function processFootPrintForArchiving()
    {
        $blog_id = $this->getBlogId(); 
        $ret = $this->getImporterExporterArray();
        $source = $this->getSourceMedia();
        $blog_id = $this->getBlogId();
        $hash_path = $ret['temp_folder_path'] . 'hash.txt';
        
        $this->getExporter()->writeOtherFiles($ret, $blog_id);
        $this->getExporter()->generateExportFootPrintHelper($ret, $source);
        
        if ($this->getExporter()->maybeSkipPluginsThemesExport($ret)) {
            $computed_hash = $this->getSystemFunctions()->hashString($ret['temp_folder_path']);
            file_put_contents($hash_path, $computed_hash, FILE_APPEND | LOCK_EX);
        }
        $this->deleteMediaTmpFile();
        
        $memory_used = $this->getSystemFunctions()->getPeakMemoryUsage();
        $this->getSystemFunctions()->logPeakMemoryUsage($blog_id, __FUNCTION__, 'export', $memory_used);
    }      
    
    /**
     * Set auxiliary data for archiving
     * @param array $aux_array
     */
    private function setAuxData($aux_array = [])
    {
        if ( ! empty($aux_array['zip_path'])) {
            $this->zip_path = $aux_array['zip_path'];
        }
        if ( ! empty($aux_array['source'])) {
            $this->source_media = $aux_array['source'];
        }
        if ( ! empty($aux_array['exclusion_rules'])) {
            $this->exclusion_rules = $aux_array['exclusion_rules'];
        }
        if ( ! empty($aux_array['enable_encryption'])) {
            $this->encryption_status = $aux_array['enable_encryption'];
        }
        if ( ! empty($aux_array['blog_id'])) {
            $this->blog_id = $aux_array['blog_id'];
            $this->getSystemInitialization()->setExportBlogID($this->blog_id);
        }
        if ( ! empty($aux_array['export_id'])) {
            $this->export_id = $aux_array['export_id'];
        } 
        if ( ! empty($aux_array['import_id'])) {
            $this->import_id = $aux_array['import_id'];
        } 
        if ( ! empty($aux_array['media_tmp_file'])) {
            $this->media_tmp_file = $aux_array['media_tmp_file'];
        }
        if ( ! empty($aux_array['shell_progress_key'])) {
            $this->shell_progress_key = $aux_array['shell_progress_key'];
        }
        if ( ! empty($aux_array['action'])) {
            $this->action = $aux_array['action'];
        }
    }

    /**
     * Checks for valid shell parameters for archiving
     * @return boolean|array
     */
    protected function isValidShellParameters()
    {
        if ( ! defined('PRIME_MOVER_DOING_SHELL_ARCHIVE') ) {
            return false;
        }
        if ( ! PRIME_MOVER_DOING_SHELL_ARCHIVE ) {
            return false;
        }
        
        $parameters = $this->getParameters();             
                
        /**
         * @var Type $script_path CLI-script
         * @var Type $error_log Error log path
         * @var Type $loader_data Loader data
         * @var Type $raw_data Raw data
         * @var Type $exporter_auth Exporter auth
         * @var Type $file_auth File auth
         */
        list($script_path, $error_log, $loader_data, $raw_data, $exporter_auth, $file_auth) = $parameters; 
        
        if ( ! $this->hmacAuthentication($error_log, $loader_data, $raw_data, $exporter_auth, $file_auth) || PRIME_MOVER_COPYMEDIA_SCRIPT !== $script_path) {
           return false;
        }
        
        return $parameters;
    }
 
    /**
     * Message authentication before we can use these data
     * @param string $error_log
     * @param string $loader_data
     * @param string $raw_data
     * @param string $exporter_auth
     * @param string $file_auth
     * @return boolean
     */
    private function hmacAuthentication($error_log = '', $loader_data = '', $raw_data = '', $exporter_auth = '', $file_auth = '')
    {
        $file_auth = base64_decode($file_auth);
        if (! file_exists($file_auth) ) {
            return false;
        }
        
        $hash_file = $this->getSystemFunctions()->sha256File($file_auth);
        $data = $loader_data . $raw_data . $hash_file . $error_log;         
        $exporter_auth = base64_decode($exporter_auth);
        
        $auth_key = $this->getSystemInitialization()->getAuthKey();
        $message_auth = hash_hmac('sha256', $data, $auth_key);
        
        if ( ! hash_equals($message_auth, $exporter_auth)) {
            $this->logHmacAuthenticationFailedEvent($message_auth, $exporter_auth);            
            return false;
        }
        
        return true;
    } 
    
    private function logHmacAuthenticationFailedEvent($message_auth, $exporter_auth)
    {
        do_action('prime_mover_log_processed_events', "HMAC authentication fails with following data:", 0, 'common', __FUNCTION__, $this);
        do_action('prime_mover_log_processed_events', "Computed HMAC :", 0, 'common', __FUNCTION__, $this);
        do_action('prime_mover_log_processed_events', $message_auth, 0, 'common', __FUNCTION__, $this);
        do_action('prime_mover_log_processed_events', "Given HMAC :", 0, 'common', __FUNCTION__, $this);
        do_action('prime_mover_log_processed_events', "$exporter_auth", 0, 'common', __FUNCTION__, $this);
    }
    
    /**
     * Zip directory (recursive)
     * @param string $source
     * @param boolean $use_defaults
     * USED
     */
    public function zipDirectory($source = '', $use_defaults = false)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        global $wp_filesystem;
        if ( ! $source ) {
            $exclusion_rules = $this->getExclusionRules();
            $source = $this->getSourceMedia();
        }
        
        $destination = $this->getZipPath();        
        $encryption_status = $this->getEncryptionStatus();
        $blog_id = $this->getBlogId();
        
        $encrypt = false;
        if ('encrypt' === $encryption_status) {
            $encrypt = true;
        }
        
        $ret = $this->getImporterExporterArray();
        $system_checks = $this->getSystemChecks();
        
        if ( ! $this->getSystemFunctions()->isWpFileSystemUsable($wp_filesystem)) {
            return;
        }
        $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Zipping %s in CLI starting..', 'prime-mover'), $this->getMode()), 'export', $this->getExportId());
        if ($use_defaults) {
            $result = $system_checks->primeMoverZipDirectory($source, $destination, false, [], false, false, 0, $ret, [], '', 0, $blog_id, true);  
            
        } else {
            $result = $system_checks->primeMoverZipDirectory($source, $destination , $encrypt,
                [], false, false, 0, $ret, $exclusion_rules, 'exporting_media', 0, $blog_id, true); 
        }        
        if (isset($result['close']) && true === $result['close']) {           
            $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Zipping %s in CLI completed..', 'prime-mover'), $this->getMode()), 'export', $this->getExportId());
            return [$destination, $ret];
        } else {
            $mode = $this->getMode();
            do_action('prime_mover_log_processed_events', "ERROR: Unable to zip {$mode} directory in shell process.", 0, 'common', 'zipDirectory', $this);           
            do_action( 'prime_mover_shutdown_actions', ['type' => 1, 'message' => sprintf(esc_html__('ERROR: Unable to zip %s directory in shell process.', 'prime-mover'), $mode)] );  
        }        
    }
    
    /**
     * Add media encrypted signature
     */
    public function addMediaEncryptedSignature()
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $ret = $this->getImporterExporterArray();
        $encrypt_media = $ret['encrypted_media'];
        
        $blogid_to_export = $this->getBlogId();        
        do_action('prime_mover_after_copying_media', $encrypt_media, $blogid_to_export, $ret);
    }
    
    /**
     * Get action ID
     * @return string
     */
    public function getActionId()
    {
        $action_id = $this->getExportId();
        if ('import' === $this->getAction()) {
            $action_id = $this->getImportId();
        }
        return $action_id;
    }
    
    /**
     * Delete tmp file
     * @param array $ret
     */
    public function deleteMediaTmpFile($ret = [])
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Deleting %s shell tmp file starting..', 'prime-mover'), $this->getMode()), $this->getAction(), $this->getActionId());        
        $shell_tmp_list_file = $this->getMediaTmpFile();
        
        $this->getSystemFunctions()->primeMoverDoDelete($shell_tmp_list_file);
        $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Deleting %s tmp file completed..', 'prime-mover'), $this->getMode()), $this->getAction(), $this->getActionId());
        
        $clear_option = false;
        if (!empty($ret['master_tmp_shell_dirs'])) {
            $this->getSystemFunctions()->primeMoverDoDelete($ret['master_tmp_shell_dirs']);
            $clear_option = true;
        }
        if (!empty($ret['master_tmp_shell_files'])) {
            $this->getSystemFunctions()->primeMoverDoDelete($ret['master_tmp_shell_files']);
            $clear_option = true;
        }
        if (!$clear_option || empty($ret['original_blogid'])) {
            return;
        }
        
        $blog_id = $ret['original_blogid'];       
        $option = $this->getSystemInitialization()->getCliMasterTmpFilesOptions() . "_" . $blog_id;
        delete_site_option($option);
    }
}