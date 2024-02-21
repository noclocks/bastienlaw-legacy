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

use Codexonics\PrimeMoverFramework\interfaces\PrimeMoverImport;
use Codexonics\PrimeMoverFramework\utilities\PrimeMoverSearchReplace;
use Codexonics\PrimeMoverFramework\cli\PrimeMoverCLIArchive;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The Prime Mover Import Class
 *
 * The Prime Mover Import Class aims to provide the import facility of this plugin.
 *
 */
class PrimeMoverImporter implements PrimeMoverImport
{
    private $cli_archiver;
    private $users;
    
    /**
     * @param PrimeMoverCLIArchive $cli_archiver
     * @param PrimeMoverUsers $users
     */
    public function __construct(
        PrimeMoverCLIArchive $cli_archiver,
        PrimeMoverUsers $users
    ) 
    { 
        $this->cli_archiver = $cli_archiver;
        $this->users = $users;
    }

    /**
     * Get Cli archiver
     * @return \Codexonics\PrimeMoverFramework\cli\PrimeMoverCLIArchive
     */
    public function getCliArchiver()
    {
        return $this->cli_archiver;
    }
    
    /**
     * Get progress handlers
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverProgressHandlers
     * @compatible 5.6
     * @audited
     */
    public function getProgressHandlers() 
    {
        return $this->getCliArchiver()->getProgressHandlers();
    }
    
    /**
     * Get System checks
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemChecks
     * @compatible 5.6
     * @audited
     */
    public function getSystemChecks()
    {
        return $this->getCliArchiver()->getSystemChecks();
    }
    
    /**
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     * @compatible 5.6
     * @audited
     */
    public function getSystemFunctions()
    {
        return $this->getCliArchiver()->getSystemChecks()->getSystemFunctions();
    }
 
    /**
     * Get System Initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     * @compatible 5.6
     * @audited
     */
    public function getSystemInitialization()
    {
        return $this->getCliArchiver()->getSystemChecks()->getSystemInitialization();
    }
    
    /**
     * Gets System authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     * @compatible 5.6
     * @audited
     */
    public function getSystemAuthorization()
    {
        return $this->getCliArchiver()->getSystemChecks()->getSystemAuthorization();
    }
    
    /**
     * Importer hooks
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itAddsImporterHooks()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itChecksIfHooksAreOutdated()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itDoesNotAddImporterHooksIfNotAuthorized() 
     * @audited
     */
    public function importerHooks()
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        /**
         * Pre-processing filters
         */
        add_filter('prime_mover_filter_pre_comparison_package_array', [$this, 'filterPreComparisonPackageArrayFunc'], 10, 3);
        add_filter('prime_mover_filter_pre_comparison_target_array', [$this, 'filterPreComparisonTargetArrayFunc'], 10, 3);
        
        add_filter('prime_mover_filter_pre_comparison_target_array', [$this, 'removeSchemeInPrefilterComparison'], 10, 3);
        add_filter('prime_mover_filter_pre_comparison_package_array', [$this, 'removeSchemeInPrefilterComparison'], 10, 3);
        
        add_filter('prime_mover_filter_pre_comparison_target_array', [$this, 'removeUploadinfoInPrefilterComparison'], 10, 3);
        add_filter('prime_mover_filter_pre_comparison_package_array', [$this, 'removeUploadinfoInPrefilterComparison'], 10, 3);      
        
        foreach ($this->getSystemInitialization()->getPrimeMoverImportMethods() as $import_method) {
            add_filter("prime_mover_do_import_{$import_method}", [$this, $import_method], 10, 4);
        }  
        
        add_filter('prime_mover_save_return_import_progress', [$this, 'saveImportProgressData'], 10, 4);
        add_filter('prime_mover_get_import_progress', [$this, 'getImportProgressData'], 10, 2);
    }

    /**
     * Maybe update user taxonomy
     * @param array $ret
     * @param number $blogid_to_import
     * @param array $files_array
     * @param number $start_time
     * @return void|mixed|NULL|array
     * @mainsite_compatible
     */
    public function maybeUpdateUserTaxonomy($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');
        
        if ( ! isset($ret['user_equivalence'] ) ) {
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }
        
        $ret = $this->getUsersObject()->getUserUtilities()->getUserFunctions()->updateUserTaxonomy($ret, $blogid_to_import, $start_time);
        
        if ( ! isset($ret['users_tax_object_offset']) ) {
            
            do_action('prime_mover_log_processed_events', $ret, $blogid_to_import, 'import', $current_func, $this, true);
            $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'import', $blogid_to_import);
            
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
            
        } else {            
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $current_func, $previous_func);
        }  
    }
    
    /**
     * Update post authors
     * @param array $ret
     * @param number $blogid_to_import
     * @param array $files_array
     * @param number $start_time
     * @return void|mixed|NULL|array
     * @mainsite_compatible
     */
    public function updatePostAuthors($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');
        
        if ( ! isset($ret['user_equivalence'] ) ) {
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }
        $mismatch_count = 0;
        if (isset($ret['user_mismatch_count'])) {
            $mismatch_count = $ret['user_mismatch_count'];
        }
        if ( ! $mismatch_count ) {
            do_action('prime_mover_log_processed_events', "Post mismatch count is zero, skipping update authors", $blogid_to_import, 'import', __FUNCTION__, $this);
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }
        
        $ret = $this->getUsersObject()->updatePostAuthors($ret, $blogid_to_import, $start_time);         
        if (!isset($ret['post_authors_leftoff'])) {
            
            do_action('prime_mover_log_processed_events', $ret, $blogid_to_import, 'import', $current_func, $this);
            $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'import', $blogid_to_import);
            
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
            
        } else {            
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $current_func, $previous_func);
        }  
    }
    
    /**
     * Specialized data processing for third party data that could be migration-affected.
     * @param array $ret
     * @param number $blogid_to_import
     * @param array $files_array
     * @param number $start_time
     * @return void|mixed|NULL|array
     */
    public function maybeProcessThirdPartyData($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');           
        
        do_action('prime_mover_before_thirdparty_data_processing', $ret, $blogid_to_import);
        $ret = $this->getSystemUtilities()->maybeComputeThirdPartyLastProcessor($ret);
               
        $ret = apply_filters('prime_mover_do_process_thirdparty_data', $ret, $blogid_to_import, $start_time);
        
        if (!isset($ret['prime_mover_thirdparty_processing_retry']) ) {                        
            do_action('prime_mover_log_processed_events', $ret, $blogid_to_import, 'import', $current_func, $this, true);            
            $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'import', $blogid_to_import);
            
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
            
        } else {             
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $current_func, $previous_func);
        }         
    }

    /**
     * Get System utilities
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverSystemUtilities
     */
    public function getSystemUtilities()
    {
        return $this->getSystemChecks()->getSystemCheckUtilities()->getSystemUtilities();
    }
    
    /**
     * Generate user equivalence
     * @param array $ret
     * @param number $blogid_to_import
     * @param array $files_array
     * @param number $start_time
     * @return void|mixed|NULL|array
     * @mainsite_compatible
     */
    public function generateUserEquivalence($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');
        
        if ( ! isset($ret['user_import_tmp_log'] ) ) {
            do_action('prime_mover_log_processed_events', "User import tmp log is not set, skipping user equivalence.", $blogid_to_import, 'import', __FUNCTION__, $this);
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }
        
        $tmp = $ret['user_import_tmp_log'];
        if ( ! $this->getSystemFunctions()->nonCachedFileExists($tmp) ) {
            do_action('prime_mover_log_processed_events', "User import tmp log does not exist, skipping user equivalence.", $blogid_to_import, 'import', __FUNCTION__, $this);
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }
        
        $ret = $this->getUsersObject()->generateUserEquivalence($ret, $blogid_to_import, $start_time);        
 
        if ( ! isset($ret['users_equivalence_offset']) ) {            
            do_action('prime_mover_log_processed_events', $ret, $blogid_to_import, 'import', $current_func, $this, true);
            $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'import', $blogid_to_import);
            
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
            
        } else {            
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $current_func, $previous_func);
        }          
    }
    
    /**
     * Get uesrs object
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverUsers
     */
    public function getUsersObject()
    {
        return $this->users;
    }
    
    /**
     * @mainsite_compatible
     * Generate user meta keys to adjust on import
     * @param array $ret
     * @param number $blogid_to_import
     * @param array $files_array
     * @param number $start_time
     * @return void|mixed|NULL|array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itGenerateUserMetaKeysToAdjust()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itDoesNotGenerateUserMetaKeysToAdjustIfUnauthorized()
     */
    public function generateUserMetaKeysToAdjust($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);
        
        /** @var Type $previous_func Previous function*/
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');       
        
        $ret = $this->getUsersObject()->getUserUtilities()->getUserFunctions()->getSpecialUserMetaKeysFromJsonFile($ret, $blogid_to_import);        
        return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
    }
    
    /**
     * Import users
     * @mainsite_compatible
     * @param array $ret
     * @param number $blogid_to_import
     * @param array $files_array
     * @param number $start_time
     * @return void|mixed|NULL|array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itDoesNotImportUsersIfUnauthorized()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itImportUsers()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itRetryImportUsersIfTimeOut()
     */
    public function importUsers($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');
        
        $ret = $this->getUsersObject()->importSiteUsers($ret, $blogid_to_import, $start_time);                
        if ( ! isset($ret['users_import_offset']) ) {

            do_action('prime_mover_log_processed_events', $ret, $blogid_to_import, 'import', $current_func, $this, true);
            $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'import', $blogid_to_import);
            
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
            
        } else {            
            
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $current_func, $previous_func);
        }     
    }
    
    /**
     * Maybe restore theme
     * @param array $ret
     * @param number $blogid_to_import
     * @param array $files_array
     * @param number $start_time
     * @return void|mixed|NULL|array
     * @mainsite_compatible
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itReturnsNullIfUnauthorizedToRestoreTheme()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itReturnsErrorDuringThemeRestore()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itMovesToNextProcessIfPluginsSet()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itMovesToNextProcessIfThemeRestoreCompleted()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itRetriesThemeRestoreIfEncounterTimeout()
     */
    public function maybeRestoreTheme($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');
        
        if ( ! empty($ret['error']) ) {            
            return $ret;
        }            
        
        if (!empty($ret['plugins_to_copy'])) {
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);             
        }
        
        $this->getSystemChecks()->deactivateThemeOnSpecificSite($blogid_to_import);
        $ret = apply_filters('prime_mover_before_theme_restore', $ret, $blogid_to_import);
        $ret = apply_filters('prime_mover_import_themes', $ret, $blogid_to_import, $start_time); 
        
        if (!empty($ret['copy_themes_done'])) {
            
            $ret = $this->cleanUpCopyDirParametersAfterCopy($ret);
            do_action('prime_mover_log_processed_events', $ret, $blogid_to_import, 'import', $current_func, $this, true);
            
            $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'import', $blogid_to_import);  
            $ret = apply_filters('prime_mover_after_theme_restore', $ret, $blogid_to_import);
            
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
            
        } else {
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $current_func, $previous_func);
        } 
    }
    
    /**
     * Optionally import plugins and themes
     * {@inheritDoc}
     * @see \Codexonics\PrimeMoverFramework\interfaces\PrimeMoverImport::multisiteOptionallyImportPluginsThemes()
     * @compatible 5.6
     * @audited
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itImportsPlugins()
     * @mainsite_compatible
     */
    public function multisiteOptionallyImportPluginsThemes($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);
        
        $process_methods = [];
        list($process_methods['current'], $process_methods['previous'], $process_methods['next']) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');
        
        $this->getSystemInitialization()->setSlowProcess();
        if ( ! empty($ret['error']) ) {
            $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Errors detected, alert user.', 'prime-mover'));
            return $ret;
        }       

        $copying_plugins_started = false;
        if (isset($ret['plugins_to_copy'])) {
            $copying_plugins_started = true;
        }
        $ret = apply_filters('prime_mover_import_plugins', $ret, $blogid_to_import, $copying_plugins_started, $start_time);            
        if (empty($ret['plugins_to_copy'])) {                
            $ret = $this->cleanUpCopyDirParametersAfterCopy($ret);
            $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $process_methods['current'], 'import', $blogid_to_import);               
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $process_methods['next'], $process_methods['current']);
            
        } else {             
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $process_methods['current'], $process_methods['previous']);
        }        
    }

    /**
     * Remove upload info in prefilter_comparison
     * @param array $system_footprint_site
     * @param array $ret
     * @param int $blogid_to_import
     * @return array
     * @compatible 5.6
     * @audited
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itRemovesUploadInfoInPreFilterComparison()
     */
    public function removeUploadinfoInPrefilterComparison(array $system_footprint_site, array $ret, $blogid_to_import = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        if (isset($system_footprint_site['upload_information_path'])) {
            unset($system_footprint_site['upload_information_path']);
        }
        
        if (isset($system_footprint_site['upload_information_url'])) {
            unset($system_footprint_site['upload_information_url']);
        }
        
        return $system_footprint_site;
    }
    
    /**
     * Remove scheme in prefilter_comparison
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itRemovesSchemeInPreFilterComparison()
     * @param array $system_footprint_site
     * @param array $ret
     * @param int $blogid_to_import
     * @return array
     * @compatible 5.6
     * @audited
     */
    public function removeSchemeInPrefilterComparison(array $system_footprint_site, array $ret, $blogid_to_import = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        if (isset($system_footprint_site['scheme'])) {
            unset($system_footprint_site['scheme']);
        }
        
        return $system_footprint_site;
    }
    
    /**
     * Move uploaded files to temporary uploads directory
     * {@inheritDoc}
     * @see PrimeMoverImport::moveImportedFilesToUploads()
     * @compatible 5.6
     * @mainsite_compatible
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itMovesImportedFilesToUploads()
     */
    public function moveImportedFilesToUploads($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {
        $ret['import_start_time'] = $start_time;        
        $this->getSystemInitialization()->setSlowProcess();
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }        
        
        /**
         * @var Type $previous_func Previous function
         */
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');
        if (isset($files_array['file'])) {
            if ( ! empty( $files_array['file'] ) ) {
                $this->getSystemInitialization()->setImportZipPath( $files_array['file'] );
            }
            
            $ret['blog_id']	= $blogid_to_import;
            $this->getSystemInitialization()->setImportBlogID($blogid_to_import);
            
            $ret['file'] = $files_array['file'];
            $file_path = $ret['file'];
                        
            if ($this->getSystemFunctions()->isExtractingPackageAsTar($file_path)) {
                $ret['wprime_tar_config_set'] = apply_filters('prime_mover_get_tar_package_config_from_file', [], $file_path, false, true);   
            }
 
            $ret = apply_filters('prime_mover_inject_db_parameters', $ret, 'import');              
            $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Processing files..', 'prime-mover'));
            
            do_action('prime_mover_log_processed_events', $ret, $blogid_to_import, 'import', $current_func, $this, true);            
            $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'import', $blogid_to_import);

            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }
    }
  
    /**
     * Create temporary monitor file
     * @param boolean $extracting_tar
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itCreatesTmpShellFile() 
     */
    protected function createTmpShellFile($extracting_tar = false)
    {
        if ($this->getCliArchiver()->maybeArchiveMediaByShell() && !$extracting_tar) {
            return $this->getSystemInitialization()->wpTempNam();
        }
    }
    
    /**
     * Unzip main Prime Mover Package
     * {@inheritDoc}
     * @see PrimeMoverImport::unzipImportedZipPackageMigration()
     * @compatible 5.6
     * @mainsite_compatible
     */
    public function unzipImportedZipPackageMigration($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {        
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);
        $process_methods = [];
        
        list($process_methods['current'], $process_methods['previous'], $process_methods['next']) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');        
        $this->getSystemInitialization()->setSlowProcess();
        
        if (isset($ret['error'])) {
            return $ret;
        }
        
        global $wp_filesystem;
        do_action('prime_mover_log_processed_events', "Extract main package..start", $blogid_to_import, 'import', $process_methods['current'], $this);         
        
        $copymediabyshell = $this->getCliArchiver()->maybeArchiveMediaByShell(); 
        $extracting_tar = false;
        if (!empty($ret['file']) && $wp_filesystem->exists($ret['file']) && $this->getSystemFunctions()->hasTarExtension($ret['file'])) {
            $extracting_tar = true;
        }
        $ret['is_extracting_tar'] = $extracting_tar;
        $shell_progress_key = 'unzipping_main_directory_started';        
        $error_log_path = $this->getSystemInitialization()->getTroubleShootingLogPath('migration');
        
        if ( ! isset($ret[$shell_progress_key])) {
            $media_tmp_file = $this->createTmpShellFile($extracting_tar);
        } else {
            $media_tmp_file = $ret['media_tmp_file'];
        }
              
        $task = esc_html__('Processing', 'prime-mover');        
        if ( ! isset($ret['file'] ) || ! $wp_filesystem->exists($ret['file'])) {
            if (! isset($ret[$shell_progress_key]) && !$extracting_tar) {
                $ret['error'] = esc_html__('Import file does not seem to exist.', 'prime-mover');
                return $ret;
            }
        }      
        $extraction_variables = [];
        if (!empty($ret['extraction_variables'])) {
            $extraction_variables = $ret['extraction_variables'];
            list($file_path, $destination, $unzipped_directory, $mode) = $extraction_variables;
        }
        if (! isset($ret[$shell_progress_key]) && empty($extraction_variables)) {
            list($file_path, $destination, $unzipped_directory, $mode, $ret) = $this->computeExtractVariables($ret);
        } else {             
            $unzipped_directory = $ret['unzipped_directory'];
        }
        if (isset($ret['error'])) {
            return $ret;
        }
        $this->getSystemInitialization()->setTemporaryImportPackagePath($unzipped_directory);        
        $this->getSystemFunctions()->temporarilyIncreaseMemoryLimits();        
        
        if ( ! isset($ret[$shell_progress_key])) {
            $ret['unzipped_directory'] = $unzipped_directory;
        }
        
        $this->getSystemInitialization()->setTemporaryImportPackagePath($unzipped_directory);
        
        if (is_array($copymediabyshell) && !$extracting_tar) {            
            
            return $this->getCliArchiver()->doShellArchivingTasks($copymediabyshell, $ret, $shell_progress_key, $blogid_to_import, false, '', 'extraction', '',
                $error_log_path, $media_tmp_file, $process_methods, false, [], $task, 'import');
            
        } elseif (!$extracting_tar) {            
            if (empty($ret['media_zip_last_index'])) {
                $ret['media_zip_last_index'] = 0;
            }
            if (empty($ret['media_zip_extraction_done'])) {
                $ret['media_zip_extraction_done'] = false;
            }
            
            $package_extraction_done = $ret['media_zip_extraction_done'];
            if ($package_extraction_done) {
                do_action('prime_mover_log_processed_events', "Done main package extraction, move on to next process..", $blogid_to_import, 'import', $process_methods['current'], $this);
                
                return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $process_methods['next'], $process_methods['current']);
            }            
                       
            $extraction_status = $this->doExtractionWork($ret, $blogid_to_import, $start_time, false, $file_path, $destination, $mode);
            
            if (empty($extraction_status['media_zip_extraction_done'])) {
                $ret['media_zip_last_index'] = $extraction_status['media_zip_last_index'];
            } else {
                $ret['media_zip_extraction_done'] = $extraction_status['media_zip_extraction_done'];
            }
            if ( ! empty($extraction_status['zip_bytes_offset']) ) {
                $ret['zip_bytes_offset'] = $extraction_status['zip_bytes_offset'];
            }
            if ( ! empty($extraction_status['error'] ) ) {
                $ret['error'] = $extraction_status['error'];
                return $ret;
            }
            
            $ret['total_numfiles_zip'] = (int)$extraction_status['total_numfiles_zip'];
            $remaining = $ret['total_numfiles_zip'] - $ret['media_zip_last_index'];
            $percent = floor(($ret['media_zip_last_index'] / $ret['total_numfiles_zip']) * 100);
            
            $percent_string = esc_html__('Starting..', 'prime-mover');
            if ($percent) {
                $percent_string = $percent . '%' . ' ' . esc_html__('done', 'prime-mover');
            }
            $text_files = esc_html__('file', 'prime-mover');
            if (isset($remaining) && $remaining > 1) {
                $text_files = esc_html__('files', 'prime-mover');
            }
            $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Unzip package %d remaining %s. %s', 'prime-mover'), $remaining, $text_files, $percent_string), 'import');
            
            if ( ! empty($extraction_status['media_zip_extraction_done'])) {
                $ret = $this->doAfterExtraction($ret, $blogid_to_import, $unzipped_directory, $file_path, false);
                $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $process_methods['current'], 'import', $blogid_to_import);
                
                return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $process_methods['next'], $process_methods['current']);
                
            } else {                
                do_action('prime_mover_log_processed_events', "Extraction not over, repeat this process.", $blogid_to_import, 'import', $process_methods['current'], $this);
                return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $process_methods['current'], $process_methods['previous']);
            }            
        } else {            
            $retrying = false;
            $base_read_offset = 0;
            if (isset($ret['prime_mover_tar_extract_base_offset'])) {                
                $base_read_offset = (int)$ret['prime_mover_tar_extract_base_offset'];
            }
            $offset = 0;
            if (isset($ret['prime_mover_tar_extract_offset'])) {
                $retrying = true;
                $offset = (int)$ret['prime_mover_tar_extract_offset'];
            }
            $index = 0;
            if (isset($ret['prime_mover_tar_extract_index'])) {
                $index = $ret['prime_mover_tar_extract_index'];
            }
            $iv = '';
            if (isset($ret['prime_mover_tar_extract_iv'])) {
                $iv = $ret['prime_mover_tar_extract_iv'];
            }
            
            if ($retrying) {
                $readable = $this->getSystemFunctions()->humanFileSize($offset, 1);
                $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Package extraction..%s bytes read.', 'prime-mover'), $readable), 'import');
            } else {
                $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Package extraction..starting.', 'prime-mover'), 'import');
            }            
            
            $key = $this->getSystemInitialization()->getDbEncryptionKey();
            $ret = apply_filters('prime_mover_tar_extractor', $ret, $blogid_to_import, $file_path, $destination, $base_read_offset, $start_time, $offset, $index, $key, $iv);
            if (!isset($ret['prime_mover_tar_extract_offset'])) {
                $ret = $this->doAfterExtraction($ret, $blogid_to_import, $unzipped_directory, $file_path, false);
                $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $process_methods['current'], 'import', $blogid_to_import);
                
                return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $process_methods['next'], $process_methods['current']);
                
            } else {
                
                do_action('prime_mover_log_processed_events', "Extraction not over, repeat this process.", $blogid_to_import, 'import', $process_methods['current'], $this);
                return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $process_methods['current'], $process_methods['previous']);
            }  
        }
    }
    
    /**
     * Do After extraction
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itPerformTaskAfterExtraction()
     * @param number $blogid_to_import
     * @param string $unzipped_directory
     * @param string $file_path
     * @param boolean $shell
     * @return string|array
     */
    public function doAfterExtraction($ret = [], $blogid_to_import = 0, $unzipped_directory = '', $file_path = '', $shell = false)
    {       
        $delete_zip = apply_filters('prime_mover_delete_zip_after_unzip', true, $file_path, $ret);
        if ($delete_zip) {
            $this->getSystemFunctions()->primeMoverDoDelete($file_path);
        }
        
        do_action('prime_mover_log_processed_events', $ret, $blogid_to_import, 'import', __FUNCTION__, $this, true);
        
        if (false === $shell) {
            $ret = $this->cleanUpMediaIndexes($ret);
        }        
        
        return $ret;
    }
    
    /**
     * Compute extraction variables
     * @param array $ret
     * @return array
     */
    public function computeExtractVariables($ret = [])
    {
        $file_path = $ret['file'];
        $extracting_tar = $this->getSystemFunctions()->isExtractingPackageAsTar($file_path);
        
        $destination = trailingslashit(dirname($file_path));
        $tar_config = [];
        if (!empty($ret['wprime_tar_config_set'])) { 
            $tar_config = $ret['wprime_tar_config_set'];            
        }         
          
        if ($extracting_tar && empty($tar_config)) {
            $tar_config = apply_filters('prime_mover_get_tar_package_config_from_file', [], $file_path);
        }        
        
        if ($extracting_tar) {            
            $correct_folder_name = $tar_config['tar_root_folder'];
        } else {
            $correct_folder_name = $this->getSystemFunctions()->getCorrectFolderNameFromZip($file_path);
        }       
        if (!$correct_folder_name) {
            $ret['error'] = esc_html__('Unzip failed. The package is not a Prime Mover site package.', 'prime-mover');
            return $ret;
        }

        $unzipped_directory	= trailingslashit($destination . $correct_folder_name);                
        $mode = esc_html__('Main', 'prime-mover');
        
        $extraction_variables = [$file_path, $destination, $unzipped_directory, $mode];        
        $ret['extraction_variables'] = $extraction_variables;       
        
        return [$file_path, $destination, $unzipped_directory, $mode, $ret];       
    }
    
    /**
     * Do extraction work
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itDoExtractionWork() 
     */
    public function doExtractionWork($ret = [], $blogid_to_import = 0, $start_time = 0, $shell = false, $file_path = '', $destination = '', $mode = '')
    {               
        $this->getSystemFunctions()->temporarilyIncreaseMemoryLimits();
        return $this->getSystemChecks()->getSystemCheckUtilities()->primeMoverExtractZipByParts($file_path, $ret, $destination, $blogid_to_import, $start_time, $mode, $shell);
    }
    
    /**
     * Clean up media indexes
     * @param array $ret
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itCleanUpMediaIndexes()
     */
    protected function cleanUpMediaIndexes($ret = [])
    {
        if (isset($ret['media_zip_last_index'])) {
            unset($ret['media_zip_last_index']);
        }
        
        if (isset($ret['media_zip_extraction_done'])) {
            unset($ret['media_zip_extraction_done']);
        }
        
        if (isset($ret['total_numfiles_zip'])) {
            unset($ret['total_numfiles_zip']);
        }
        
        if (isset($ret['zip_bytes_offset'])) {
            unset($ret['zip_bytes_offset']);
        }
        return $ret;        
    }
    
    /**
     * Validate imported site vs the package to make sure there is no import mismatch.
     * {@inheritDoc}
     * @see PrimeMoverImport::validateImportedSiteVsPackage()
     * @compatible 5.6
     * @mainsite_compatible
     */
    public function validateImportedSiteVsPackage($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }        
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);
        
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');        
        $this->getSystemInitialization()->setSlowProcess();
        $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Checking import package', 'prime-mover'));
                
        if (isset($ret['error'])) {
            return $ret;
        }
        if (isset($ret['unzipped_directory'])) {
            $unzipped_directory	= $ret['unzipped_directory'];
            $directory_name = basename($unzipped_directory);
            $exploded = explode("_", $directory_name);
            $reversed = array_reverse($exploded);
            if (isset($reversed[0])) {
                $associated_site_id_of_package	= intval($reversed[0]);
                if ($associated_site_id_of_package > 0) {
                    $blogid_to_import	= intval($blogid_to_import);
                    if ($blogid_to_import !==  $associated_site_id_of_package) {
                        $ret['error'] = esc_html__('Mismatch import!', 'prime-mover');
                    }
                } else {
                    $ret['error'] = esc_html__('Corrupt package', 'prime-mover');
                }
            } else {
                $ret['error'] = esc_html__('Corrupt package', 'prime-mover');
            }
        } else {
            $ret['error'] = esc_html__('Unzipped directory does not exist.', 'prime-mover');
        }
        
        if (isset($ret['error'])) {
            $this->getSystemFunctions()->primeMoverDoDelete($unzipped_directory);
            return $ret;
        }  
        
        $ret = apply_filters('prime_mover_validate_imported_package', $ret, $blogid_to_import);   
        do_action('prime_mover_log_processed_events', $ret, $blogid_to_import, 'import', $current_func, $this, true);        
        $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'import', $blogid_to_import);
        
        return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
    }
    
    /**
     * Compare system footprint
     * {@inheritDoc}
     * @see PrimeMoverImport::compareSystemFootprintImport()
     * @compatible 5.6
     * @mainsite_compatible
     */
    public function compareSystemFootprintImport($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);        
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');
        
        $this->getSystemInitialization()->setSlowProcess();
        $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Comparing system footprint', 'prime-mover'));
        
        if (isset($ret['error'])) {
            return $ret;
        }        
        global $wp_filesystem;
        if (isset($ret['unzipped_directory'])) {
            $unzipped_directory	= $ret['unzipped_directory'];
            $footprint_package	= $unzipped_directory . 'footprint.json';
            if ($wp_filesystem->exists($footprint_package)) {
                $json_string = file_get_contents($footprint_package);
                $system_footprint_package_array	= json_decode($json_string, true);
                $ret['imported_package_footprint'] = apply_filters('prime_mover_input_footprint_package_array', $system_footprint_package_array, $ret, $blogid_to_import);
                $system_footprint_target_site = $this->getSystemChecks()->primeMoverGenerateSystemFootprint($blogid_to_import);
                if (!isset($system_footprint_package_array['site_url'])) {
                    $ret['error'] = esc_html__('Site URL is not defined in this imported package, please re-generate the package at the source site.', 'prime-mover');
                    return $ret;
                }
                if (!isset($system_footprint_package_array['wp_root'])) {
                    $ret['error'] = esc_html__('WordPress root is not defined in this imported package, please re-generate the package at the source site.', 'prime-mover');
                    return $ret;
                }
                if (!isset($system_footprint_target_site['site_url'])) {
                    $ret['error'] = esc_html__('Site URL is not defined in this site. Please check your multisite settings.', 'prime-mover');
                    return $ret;
                }
                if (!isset($system_footprint_target_site['wp_root'])) {
                    $ret['error'] = esc_html__('WordPress root is not defined in this site. Please check your multisite settings.', 'prime-mover');
                    return $ret;
                }
                $ret['origin_site_url'] = $system_footprint_package_array['site_url'];
                $ret['origin_wp_root'] = $system_footprint_package_array['wp_root'];
                $ret['origin_db_prefix'] = $system_footprint_package_array['db_prefix'];
                
                $ret['target_site_url'] = $system_footprint_target_site['site_url'];
                $ret['target_wp_root'] = $system_footprint_target_site['wp_root'];
                $ret['target_db_prefix'] = $system_footprint_target_site['db_prefix'];

                $system_footprint_package_array	= apply_filters('prime_mover_filter_pre_comparison_package_array', $system_footprint_package_array, $ret, $blogid_to_import);
                $system_footprint_target_site = apply_filters('prime_mover_filter_pre_comparison_target_array', $system_footprint_target_site, $ret, $blogid_to_import);

                if ($system_footprint_target_site != $system_footprint_package_array) {                    
                    $diff = $this->getSystemFunctions()->getDetailedDifferencesBetweenFootprints($system_footprint_package_array, $system_footprint_target_site);
                    $ret['diff'] = $diff;
                    $ret = apply_filters('prime_mover_filter_config_after_diff_check', $ret, $blogid_to_import);
                }
            } else {                
                $ret['error'] = esc_html__('System footprint of package is not found', 'prime-mover');
                return $ret;
            }
        }
        
        do_action('prime_mover_log_processed_events', $ret, $blogid_to_import, 'import', $current_func, $this, true);
        $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'import', $blogid_to_import);
        
        return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
    }
    
    /**
     * Remove site URL and site paths before comparison
     * @param array $system_footprint_package_array
     * @param array $ret
     * @param number $blogid_to_import
     * @return array
     * @compatible 5.6
     * @audited
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itFiltersPreComparisonPackageArray()
     */
    public function filterPreComparisonPackageArrayFunc($system_footprint_package_array = [], $ret = [], $blogid_to_import = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if (! empty($system_footprint_package_array['site_url'])
                && ! empty($system_footprint_package_array['wp_root']) &&
                ! empty($system_footprint_package_array['db_prefix'])) {
            unset($system_footprint_package_array['site_url']);
            unset($system_footprint_package_array['wp_root']);
            unset($system_footprint_package_array['db_prefix']);
        }
        return $system_footprint_package_array;
    }
    
    /**
     * Remove site URL and site paths before comparison
     * @param array $system_footprint_target_site
     * @param array $ret
     * @param number $blogid_to_import
     * @return array
     * @compatible 5.6
     * @audited
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itFiltersPreComparisonTargetArray() 
     */
    public function filterPreComparisonTargetArrayFunc($system_footprint_target_site = [], $ret = [], $blogid_to_import = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if (! empty($system_footprint_target_site['site_url'])
                && ! empty($system_footprint_target_site['wp_root'])
                && ! empty($system_footprint_target_site['db_prefix'])) {
            unset($system_footprint_target_site['site_url']);
            unset($system_footprint_target_site['wp_root']);
            unset($system_footprint_target_site['db_prefix']);
        }
        return $system_footprint_target_site;
    }

    /**
     * Extract zip package
     * @param array $ret
     * @param number $blogid_to_import
     * @param array $files_array
     * @param number $start_time
     * @return array
     * @mainsite_compatible
     */
    public function extractZipPackage($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        
        if ( ! isset($ret['diff_confirmation'])) {
            $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);
        }
        
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');
        $is_extracting_tar = $ret['is_extracting_tar'];
        
        global $wp_filesystem;
        $unzipped_directory	= '';
        $is_extracting_tar = $ret['is_extracting_tar'];
        if (isset($ret['error'])) {
            return $ret;
        } else {
            if ($ret['unzipped_directory']) {
                $unzipped_directory	= $ret['unzipped_directory'];
            }
        }   
        $ret = apply_filters('prime_mover_after_user_diff_confirmation', $ret, $blogid_to_import);
        if ($is_extracting_tar) {
            $media_path = $unzipped_directory . 'media.wprime';
        } else {
            $media_path = $unzipped_directory . 'media.zip';
        }        
        if (false === $wp_filesystem->exists($media_path) && !$is_extracting_tar) {
            $ret['skipped_media'] = true;           
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }
        
        $source	= trailingslashit($unzipped_directory . $blogid_to_import);
        if (!is_dir($source) && $is_extracting_tar) {
            $ret['skipped_media'] = true;             
        }
        if ($is_extracting_tar) {
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }
        do_action('prime_mover_log_processed_events', "Extract zip package...start", $blogid_to_import, 'import', $current_func, $this);
        
        if (empty($ret['media_zip_last_index'])) {
            $ret['media_zip_last_index'] = 0;
        }
        if (empty($ret['media_zip_extraction_done'])) {
            $ret['media_zip_extraction_done'] = false;
        }
        
        $media_extraction_done = $ret['media_zip_extraction_done'];        
        if ($media_extraction_done) {
            
            do_action('prime_mover_log_processed_events', "Done extraction, move on to next process..", $blogid_to_import, 'import', $current_func, $this);
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }

        $this->getSystemFunctions()->temporarilyIncreaseMemoryLimits();        
        if ($is_extracting_tar) {
            $retrying = false;
            $base_read_offset = 0;
            if (isset($ret['prime_mover_tar_extract_base_offset'])) {
                $base_read_offset = (int)$ret['prime_mover_tar_extract_base_offset'];
            }
            $offset = 0;
            if (isset($ret['prime_mover_tar_extract_offset'])) {
                $retrying = true;
                $offset = (int)$ret['prime_mover_tar_extract_offset'];
            }
            
            $index = 0;
            if (isset($ret['prime_mover_tar_extract_index'])) {
                $index = $ret['prime_mover_tar_extract_index'];
            }  
            $iv = '';
            if (isset($ret['prime_mover_tar_extract_iv'])) {
                $iv = $ret['prime_mover_tar_extract_iv'];
            }
            
            if ($retrying) {
                $readable = $this->getSystemFunctions()->humanFileSize($offset, 1);
                $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Media extraction..%s bytes read.', 'prime-mover'), $readable), 'import');
            } else {
                $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Media extraction..starting.', 'prime-mover'), 'import');
            }
            $key = $this->getSystemInitialization()->getDbEncryptionKey();            
            $ret = apply_filters('prime_mover_tar_extractor', $ret, $blogid_to_import, $media_path, $unzipped_directory, $base_read_offset, $start_time, $offset, $index, $key, $iv);
            if (!isset($ret['prime_mover_tar_extract_offset'])) {
                $ret = $this->doAfterExtraction($ret, $blogid_to_import, $unzipped_directory, $media_path, false);
                $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'import', $blogid_to_import);
                
                return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
                
            } else {
                
                do_action('prime_mover_log_processed_events', "Extraction not over, repeat this process.", $blogid_to_import, 'import', $current_func, $this);
                return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $current_func, $previous_func);
            }             
        } else {
            $extraction_status = $this->getSystemChecks()->getSystemCheckUtilities()->primeMoverExtractZipByParts($media_path, $ret, $unzipped_directory, $blogid_to_import, $start_time);
            if (empty($extraction_status['media_zip_extraction_done'])) {
                
                $ret['media_zip_last_index'] = $extraction_status['media_zip_last_index'];
            } else {
                
                $ret['media_zip_extraction_done'] = $extraction_status['media_zip_extraction_done'];
            }
            
            if ( ! empty($extraction_status['error'] ) ) {
                
                $ret['error'] = $extraction_status['error'];
                return $ret;
            }
            
            $ret['total_numfiles_zip'] = (int)$extraction_status['total_numfiles_zip'];
            $remaining = $ret['total_numfiles_zip'] - $ret['media_zip_last_index'];
            $percent = floor(($ret['media_zip_last_index'] / $ret['total_numfiles_zip']) * 100);
            
            $percent_string = esc_html__('Starting..', 'prime-mover');
            if ($percent) {
                $percent_string = $percent . '%' . ' ' . esc_html__('done', 'prime-mover');
            }
            
            $text_files = esc_html__('file', 'prime-mover');
            if (isset($remaining) && $remaining > 1) {
                $text_files = esc_html__('files', 'prime-mover');
            }
            
            $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Extracting %d remaining media %s. %s', 'prime-mover'), $remaining, $text_files, $percent_string), 'import');
            
            if ( ! empty($extraction_status['media_zip_extraction_done'])) {
                do_action('prime_mover_log_processed_events', "Done extraction, move on to next process..", $blogid_to_import, 'import', $current_func, $this);
                $ret = $this->getSystemFunctions()->doMemoryLogs($ret, 'extractZipPackage', 'import', $blogid_to_import);
                
                return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
            } else {
                do_action('prime_mover_log_processed_events', "Extraction not over, repeat this process.", $blogid_to_import, 'import', $current_func, $this);
                
                return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $current_func, $previous_func);
            } 
        }        
    }

    /**
     * Update WordPress language folder directory files with the newly ones being imported
     * @param array $ret
     * @param number $blogid_to_import
     * @param array $files_array
     * @param number $start_time
     * @return string
     * @compatible 5.6
     * @audited
     * @mainsitesupport_affected
     * @mainsite_compatible
     */
    public function maybeCopyLanguageFolderToTarget($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');
        global $wp_filesystem;
        
        $unzipped_directory	= '';
        if (isset($ret['error'])) {
            return $ret;
        } else {
            if ($ret['unzipped_directory']) {
                $unzipped_directory	= $ret['unzipped_directory'];
            }
        }
        
        $this->getSystemFunctions()->temporarilyIncreaseMemoryLimits();
        $skipped_languages_folder = true;
        if (isset($ret['skipped_languages_folder'])) {
            $skipped_languages_folder = $ret['skipped_languages_folder'];            
        }
        
        if ($skipped_languages_folder) {            
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }
        
        $copying_done = false;
        if (!empty($ret['lang_import_done'] ) ) {
            $copying_done = $ret['lang_import_done'];
        }
        
        if ($copying_done) {
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }
        
        $copied = 0;
        if (!empty($ret['copydir_processed'])) {
            $copied = $ret['copydir_processed'];
        }
        
        if (empty($ret['source_lang_dir_path'])) {
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }
        
        $source = $ret['source_lang_dir_path'];
        if (empty($unzipped_directory)) {
            $ret['error'] = esc_html__('Unzipped directory is empty.', 'prime-mover');
            return $ret;
        }
        
        if (false === $wp_filesystem->exists($unzipped_directory)) {
            $ret['error'] = esc_html__('Unzipped directory does not exist.', 'prime-mover');
            return $ret;
        }
        
        $target_lang_path = $this->getSystemFunctions()->getLanguageFolder();        
        $target	= '';
        if (wp_mkdir_p($target_lang_path)) {
            $target	= $target_lang_path;
        }
        
        if (!$wp_filesystem->exists($source)) {
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }
        
        if  (!$wp_filesystem->exists($target) ) {
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }
        
        do_action('prime_mover_log_processed_events', "MIGRATING LANGUAGE FILES: $source TO: $target", $blogid_to_import, 'import', $current_func, $this);        
        $this->getSystemFunctions()->temporarilyIncreaseMemoryLimits();
        
        $original_count = 0;
        if ( ! empty($ret['media_import_original_count'] ) ) {
            $original_count = $ret['media_import_original_count'];
        } else {
            $original_count = $this->getSystemChecks()->getSystemCheckUtilities()->getTotalMediaFilesCountOnImport($ret, $source);
            $ret['media_import_original_count'] = $original_count;
        }
        
        $percent_string = esc_html__('Starting..', 'prime-mover');
        if ( ! $copying_done ) {
            $remaining = $original_count - $copied;
            $percent = floor(($copied / $original_count) * 100);
            if ($percent) {
                $percent_string = $percent . '%' . ' ' . esc_html__('done', 'prime-mover');
            }
        }
       
        $text_files = esc_html__('file', 'prime-mover');
        if (isset($remaining) && $remaining > 1) {
            $text_files = esc_html__('files', 'prime-mover');
        }
       
        $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Copying %d remaining language %s. %s', 'prime-mover'), $remaining, $text_files, $percent_string), 'import');
        $copy_directory_result	= $this->getSystemChecks()->getSystemCheckUtilities()->copyDir($source, $target, [], [], true, true, $start_time, $blogid_to_import,
            $copied, true, 'langimport', [], $ret);
                
        if (is_wp_error($copy_directory_result)) {             
            $ret['error']= $copy_directory_result->get_error_message();
            return $ret;
            
        } elseif (isset($copy_directory_result['copychunked_offset'], $copy_directory_result['copychunked_under_copy'])) {
            $ret = $copy_directory_result;
            $ret['lang_import_done'] = false;
            
        } elseif (true === $copy_directory_result) {
            $ret['lang_import_done'] = $copy_directory_result;
            $ret = $this->cleanUpCopyDirParametersAfterCopy($ret);
            $copying_done = true;
        }
        
        if ($copying_done) {
            do_action('prime_mover_log_processed_events', $ret, $blogid_to_import, 'import', $current_func, $this, true);
            $this->getSystemInitialization()->testRequestTerminateTimeout();
            
            $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'import', $blogid_to_import);
            
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
            
        } else {
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $current_func, $previous_func);
        }
    }
    
    /**
     * Update uploads directory files with the newly ones being imported
     * {@inheritDoc}
     * @see PrimeMoverImport::updateTargetMediaFilesWithNew()
     * @compatible 5.6
     * @audited
     * @mainsitesupport_affected
     * @mainsite_compatible
     */
    public function updateTargetMediaFilesWithNew($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import); 

        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');        
        global $wp_filesystem;
        
        $unzipped_directory	= '';        
        if (isset($ret['error'])) {            
            return $ret;
        } else {
            if ($ret['unzipped_directory']) {
                $unzipped_directory	= $ret['unzipped_directory'];
            }
        }        
        $this->getSystemFunctions()->temporarilyIncreaseMemoryLimits();              
        if (!empty($ret['skipped_media'])) { 
            
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }        
        $copying_done = false;
        if ( ! empty($ret['media_import_done'] ) ) {
            $copying_done = $ret['media_import_done'];
        } 
        
        if ($copying_done) {            
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }        
        $copied = 0;        
        if (!empty($ret['copydir_processed'])) {            
            $copied = $ret['copydir_processed'];
        }        
      
        $processed_blog	= 0;
        if ($blogid_to_import > 0) {
            $processed_blog	= $blogid_to_import;
        } 
        
        if ($processed_blog < 0) {
            $ret['error'] = esc_html__('Blog id does not exist.', 'prime-mover');
            return $ret;
        }
        
        $source	= $unzipped_directory . $processed_blog . DIRECTORY_SEPARATOR;
        if (empty($unzipped_directory)) {
            $ret['error'] = esc_html__('Unzipped directory is empty.', 'prime-mover');
            return $ret;
        }
        if (false === $wp_filesystem->exists($unzipped_directory)) {           
            $ret['error'] = esc_html__('Unzipped directory does not exist.', 'prime-mover');
            return $ret;
        }
        $media_path	= $this->getSystemFunctions()->primeMoverGetBlogsDirPath($processed_blog);
        
        if (false === $wp_filesystem->exists($media_path)) {
            $ret['error'] = esc_html__('Media path directory does not exist.', 'prime-mover');
            return $ret;
        }
        
        do_action('prime_mover_before_actual_import', $ret, $blogid_to_import, $files_array); 
        
        $target	= '';
        if (wp_mkdir_p($media_path)) {
            $target	= $media_path;
        }
        $exportdir_slug = $this->getSystemInitialization()->getMultisiteExportFolderSlug();
        $skip = [$exportdir_slug];
        
        if (is_multisite() && !$this->getSystemFunctions()->isMultisiteMainSite($blogid_to_import)) {
            $skip = [];
        }
        if ( ! $wp_filesystem->exists($source) ) {
            $ret['error'] = esc_html__('Source media import directory does not exist', 'prime-mover');
            return $ret;
        }
        if  ( ! $wp_filesystem->exists($target) ) {        
            $ret['error'] = esc_html__('Target media import directory does not exist', 'prime-mover');
            return $ret;
        }

        do_action('prime_mover_log_processed_events', "MIGRATING MEDIA FILES: $source TO: $target", $processed_blog, 'import', $current_func, $this);        
               
        $this->getSystemFunctions()->temporarilyIncreaseMemoryLimits();   
        $original_count = 0;
        if ( ! empty($ret['media_import_original_count'] ) ) {
            $original_count = $ret['media_import_original_count'];            
        } else {           
            $original_count = $this->getSystemChecks()->getSystemCheckUtilities()->getTotalMediaFilesCountOnImport($ret, $source);
            $ret['media_import_original_count'] = $original_count;
        }
        
        $percent_string = esc_html__('Starting..', 'prime-mover');
        if ( ! $copying_done ) {
            $remaining = $original_count - $copied;
            $percent = floor(($copied / $original_count) * 100);
            if ($percent) {
                $percent_string = $percent . '%' . ' ' . esc_html__('done', 'prime-mover');
            }            
        }
        $text_files = esc_html__('file', 'prime-mover');
        if (isset($remaining) && $remaining > 1) {
            $text_files = esc_html__('files', 'prime-mover');
        }
        $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Importing %d remaining media %s. %s', 'prime-mover'), $remaining, $text_files, $percent_string), 'import');            
        $copy_directory_result	= $this->getSystemChecks()->getSystemCheckUtilities()->copyDir(
            $source, 
            $target, 
            $skip, 
            [], 
            true, 
            true, 
            $start_time, 
            $processed_blog, 
            $copied,
            true,
            'default',
            [],
            $ret
        );
        
        if (is_wp_error($copy_directory_result)) {            
            do_action('prime_mover_log_processed_events', "After copying, there is an error." , $blogid_to_import, 'import', $current_func, $this);
            $ret['error']= $copy_directory_result->get_error_message();
            return $ret;
            
        } elseif (isset($copy_directory_result['copychunked_offset'], $copy_directory_result['copychunked_under_copy'])) {  
            
            do_action('prime_mover_log_processed_events', "After copying, it is not DONE we need to retry" , $blogid_to_import, 'import', $current_func, $this);
            do_action('prime_mover_log_processed_events', "File offset: " . $copy_directory_result['copychunked_offset'] , $blogid_to_import, 'import', $current_func, $this);
            
            do_action('prime_mover_log_processed_events', "File under copy: " . $copy_directory_result['copychunked_under_copy'] , $blogid_to_import, 'import', $current_func, $this);
            if (isset($copy_directory_result['copydir_processed'])) {
                do_action('prime_mover_log_processed_events', "Processed files: " . $copy_directory_result['copydir_processed'] , $blogid_to_import, 'import', $current_func, $this);
            }
            $ret = $copy_directory_result;
            $ret['media_import_done'] = false;
            
        } elseif (true === $copy_directory_result) {
            do_action('prime_mover_log_processed_events', "After copying, it is DONE." , $blogid_to_import, 'import', $current_func, $this);
            $ret['media_import_done'] = $copy_directory_result;
            $ret = $this->cleanUpCopyDirParametersAfterCopy($ret);
            $copying_done = true;                
        }
        
        if ($copying_done) {           
            do_action('prime_mover_log_processed_events', $ret, $blogid_to_import, 'import', $current_func, $this, true);
            $this->getSystemInitialization()->testRequestTerminateTimeout();

            $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'import', $blogid_to_import);   
           
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);            
        } else {
           
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $current_func, $previous_func);
        }
    }
    
    /**
     * Clean up ret array
     * @param array $ret
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itImportsPlugins()
     */
    protected function cleanUpCopyDirParametersAfterCopy($ret = [])
    {
        if (isset($ret['copychunked_offset'])) {
            unset($ret['copychunked_offset']);
        }
        
        if (isset($ret['copychunked_under_copy'])) {
            unset($ret['copychunked_under_copy']);
        }
        
        if (isset($ret['copydir_processed'])) {
            unset($ret['copydir_processed']);
        }       
        
        return $ret;
    }
    
    /**
     * Drop custom tables before database restore.
     * @param array $ret
     * @param number $blogid_to_import
     * @param array $files_array
     * @param number $start_time
     * @return array
     * @mainsite_compatible
     */
    public function dropCustomTables($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);        
        $process_methods = [];
        list($process_methods['current'], $process_methods['previous'], $process_methods['next']) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');
        
        if (! empty($ret['error']) ) {
            $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Errors detected, alert user.', 'prime-mover'));
            return $ret;
        }   
        
        global $wpdb;        
        if (isset($ret['core_tables'])) {
            $coretables = $ret['core_tables'];            
        } else {
            $coretables = $this->getSystemInitialization()->getCoreWpTables($blogid_to_import);
        }                
       
        if (isset($ret['tables_to_drop'])) {
            $alltables = $ret['tables_to_drop'];
            
        } else {
            $this->getSystemFunctions()->switchToBlog($blogid_to_import);
            $alltables = $this->getSystemFunctions()->getTablesforReplacement($blogid_to_import, $ret);
            $this->getSystemFunctions()->restoreCurrentBlog();
        }

        if (isset($ret['tables_orig_count_dropping'])) {
            $orig_count = $ret['tables_orig_count_dropping'];
        } else {
            $orig_count = count($alltables);
        }
        
        $latest_count = count($alltables);
        $processed = $orig_count - $latest_count;        
        $percent = floor(($processed / $orig_count) * 100);
        
        $percent_string = esc_html__('Starting..', 'prime-mover');
        if ($percent) {
            $percent_string = $percent . '%' . ' ' . esc_html__('done', 'prime-mover');
        }
        $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Dropping custom tables, %s', 'prime-mover'), $percent_string), 'import');
        $this->killBlockingProcesses($ret);        
        foreach ($alltables as $k => $t) {
            if (!in_array($t, $coretables)) {
                $drop_query = sprintf(
                    "DROP TABLE IF EXISTS `%s`;",
                    $t
                    );   
                $this->getSystemFunctions()->dropTable($drop_query, true, $wpdb, false);
            } 

            unset($alltables[$k]);
            $retry_timeout = apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, __FUNCTION__);
            if ((microtime(true) - $start_time) > $retry_timeout) {
                $ret['tables_to_drop'] = $alltables;
                $ret['core_tables'] = $coretables;
                $ret['tables_orig_count_dropping'] = $orig_count;
               
                return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $process_methods['current'], $process_methods['previous']);
            }            
        }
        
        if (isset($ret['tables_to_drop'])) {
            unset($ret['tables_to_drop']);
        }
        if (isset($ret['core_tables'])) {
            unset($ret['core_tables']);
        }
        if (isset($ret['tables_orig_count_dropping'])) {
            unset($ret['tables_orig_count_dropping']);
        }
        
        $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $process_methods['current'], 'import', $blogid_to_import);
        
        return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $process_methods['next'], $process_methods['current']);        
    }
    
    /**
     * {@inheritDoc}
     * @see PrimeMoverImport::importDb()
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverImporter::itImportsUnencryptedDbWhenRequested()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverImporter::itImportsEncryptedDbIfAllSet()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverImporter::itDoesNotImportWhenNotAuthorized()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverImporter::itDoesNotImportWhenItHasDiffs()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverImporter::itDoesNotImportDbWhenItHasErrors()
     * @mainsite_compatible
     */
    public function importDb($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {        
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);    
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');
        
        if (!empty($ret['import_db_done']) ) {            
            $this->getSystemInitialization()->testRequestTerminateTimeout();
            do_action('prime_mover_log_processed_events', $ret, $blogid_to_import, 'import', $current_func, $this, true);
           
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }        
        
        $this->getSystemInitialization()->setSlowProcess();
        if (!empty($ret['error'] ) ) {
            $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Errors detected, alert user.', 'prime-mover'));
            return $ret;
        }
        if (!isset($ret['unzipped_directory'])) {
            $ret['error'] = esc_html__('Unzipped directory is missing. It is deleted while the import is ongoing. Please repeat again.', 'prime-mover');
            return $ret;
        }        

        $unzipped_path = $ret['unzipped_directory'];
        $import_path_sql = $this->handleEncrypteddB($unzipped_path, $blogid_to_import);        
        if (!$import_path_sql ) {
            $ret['error'] = esc_html__('Unable to find the correct SQL path.', 'prime-mover');
            return $ret;
        }

        $import_command	= $this->mysqlRestoreCommand($import_path_sql); 
        do_action('prime_mover_before_db_processing', $ret, $blogid_to_import);
        
        $percent = "0%";
        if (!empty( $ret['percent_db_imported'] ) ) {
            $percent = $ret['percent_db_imported'] . "%";
        }
        $progress_phrase = sprintf(esc_html__('%s completed', 'prime-mover'), $percent);
        if ("0%" === $percent) {            
            $progress_phrase = esc_html__('Starting...', 'prime-mover');
        }
        $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Importing database tables. %s', 'prime-mover'), $progress_phrase));        
        $import_result = $this->restoredB($import_command, $ret, $blogid_to_import, $start_time);        
        
        do_action('prime_mover_log_processed_events', "Database import result available:" , $blogid_to_import, 'import', $current_func, $this);
        do_action('prime_mover_log_processed_events', $import_result , $blogid_to_import, 'import', $current_func, $this);
        
        if (!empty($import_result['error'] ) ) {            
            $ret['error'] = $import_result['error'];
            return $ret;
        }
        
        if (!empty($import_result['import_db_done'])) {          
            do_action('prime_mover_log_processed_events', "Import db done, proceed to next process" , $blogid_to_import, 'import', $current_func, $this);
            $this->getSystemInitialization()->testRequestTerminateTimeout();
           
            do_action('prime_mover_log_processed_events', $ret, $blogid_to_import, 'import', $current_func, $this, true);
            $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'import', $blogid_to_import);
           
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
            
        } else {           
            do_action('prime_mover_log_processed_events', "Import db not yet done, saving parameters and retry again." , $blogid_to_import, 'import', $current_func, $this);
            
            $ret['percent_db_imported'] = $import_result['percent_db_imported'];
            $ret['database_read_offset'] = $import_result['database_read_offset'];
            $ret['database_read_size'] = $import_result['database_read_size'];
            
            if (!empty($import_result['max_allowed_packet_original'])) {
                $ret['max_allowed_packet_original'] = $import_result['max_allowed_packet_original'];
            }
            
            if (!empty($import_result['max_allowed_packet_target'])) {
                $ret['max_allowed_packet_target'] = $import_result['max_allowed_packet_target'];
            }
            
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $current_func, $previous_func);
        }
    }

    /**
     * Execute database restore
     * @param array $restore_commands
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return boolean|string|NULL[]|number[]
     */
    protected function restoredB($restore_commands = [], $ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        if ( empty($restore_commands) || empty($restore_commands['target_path']) || !isset($restore_commands['decrypt']) ) {
            return false;
        }
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return false;
        }
        $max_allowed_packet_error = false;
        $max_allowed_package_target = 0;
        $target_path = $restore_commands['target_path'];
        $decrypt = $restore_commands['decrypt'];
        
        $max_allowed_packet = $this->getSystemChecks()->getSystemCheckUtilities()->getMaxAllowedPacket();        
        do_action('prime_mover_log_processed_events', "Database restoration max allowed packet value is $max_allowed_packet" , $blogid_to_import, 'import', __FUNCTION__, $this);
        
        $handle = @fopen($target_path, 'r');
        if (!$handle) {
            $return['error'] = esc_html__('Unable to open database file.', 'prime-mover');
            return $return;
        }        
        $is_retry = false;
        if (!empty($ret['database_read_offset']) ) {            
            fseek($handle, $ret['database_read_offset']);
            $is_retry = true;
        }        
        
        global $wpdb;        
        do_action('prime_mover_before_looping_restore_queries', $ret, $blogid_to_import);
        $ret['mysql_version'] = $wpdb->db_version();
        
        $templine = '';
        $return = [];
        
        $size = 0;        
        if (!empty($ret['database_read_size'] ) ) {            
            $size = $ret['database_read_size'];            
        } 
        
        if (!empty($ret['database_final_size'] ) ) {            
            $filesize = $ret['database_final_size'];            
        } else {            
            $filesize = $this->getSystemFunctions()->fileSize64($target_path);
        }

        $return['database_final_size'] = $filesize;       
        $percent = 0;
        if (!empty($ret['percent_db_imported'] ) ) {
            $percent = $ret['percent_db_imported'];
        }  
        $db_super_user = true;
        if (isset($ret['prime_mover_is_super_db_user']) && false === $ret['prime_mover_is_super_db_user']) {
            $db_super_user = false;
        }
        
        $max_allowed_packet_fix_rejected = false;
        $q = 0;
        while(!feof($handle)){            
            $line = fgets($handle);            
            if (false === $line) {
                break;
            }
            
            $size += mb_strlen($line, '8bit');
            $percent = round(($size / $filesize) * 100, 0);                       
            
            if ($decrypt) {
                $line = apply_filters('prime_mover_decrypt_data', $line);
            }
            $string_byte = 0;
            $line = apply_filters('prime_mover_filter_sql_data', $line, $ret);
            if (substr($line, 0, 2) == '--' || $line == '') {
                continue;
            }
            
            $templine .= $line;
            $executed = false;
            if (substr(trim($line), -1, 1) == ';') {
                $string_byte = mb_strlen($templine, '8bit');
                $string_byte = (int)$string_byte;
                if ($max_allowed_packet && $string_byte > $max_allowed_packet) {
                    $max_allowed_packet_error = true;  
                    if (!empty($ret['max_allowed_packet_original']) && !empty($ret['max_allowed_packet_target']) && $max_allowed_packet === $ret['max_allowed_packet_original']) {
                        $max_allowed_packet_fix_rejected = true;
                        $max_allowed_package_target = $ret['max_allowed_packet_target'];
                        break;
                    }
                    
                    $max_allowed_package_target = $this->getSystemUtilities()->maxAllowedPacketAdjustOnRunTime($wpdb, $db_super_user, $string_byte, $max_allowed_packet); 
                    if ($db_super_user) {
                        fclose($handle);                        
                        do_action('prime_mover_log_processed_events', "A retry is needed after MAX_ALLOWED_PACKET dynamic adjustment, $percent% done" , $blogid_to_import, 'import', __FUNCTION__, $this);
                        
                        $return['max_allowed_packet_original'] = $max_allowed_packet;
                        $return['max_allowed_packet_target'] = $max_allowed_package_target;
                        
                        return $return;                          
                    } else {
                        break;
                    }
                }
                
                $wpdb->query(apply_filters('prime_mover_filter_sql_query', $templine, $ret, $q, $wpdb, $is_retry));
                $templine = '';
                $q++;
                $executed = true;                
            }            
            
            if ($executed) {
                $return = $this->computeLastDbReadPositions($percent, $handle, $size);
            }
            
            $retry_timeout = apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, 'restoredB');
            if ($executed && ((microtime(true) - $start_time) > $retry_timeout) ) {                
                fclose($handle);                
                do_action('prime_mover_log_processed_events', "$retry_timeout seconds time out on database restore, $percent% done" , $blogid_to_import, 'import', __FUNCTION__, $this);
                
                return $return;                
            }
        }
        
        fclose($handle); 
        
        if ($max_allowed_packet_error && $max_allowed_packet_fix_rejected) {
            $return['error'] = sprintf(esc_html__('Error: Prime Mover is unable to increase max_allowed_packet due to server restrictions. Please consider increasing this above %d bytes.'), $max_allowed_package_target);
        } elseif ($max_allowed_packet_error && !$db_super_user) {
            $return['error'] = sprintf(esc_html__('Error: Your MySQL server max_allowed_packet size is insufficient. Please consider increasing this above %d bytes.'), $max_allowed_package_target);
        } else {
            $return['import_db_done'] = true;
        }     
        
        return $return;
    }
    
    /**
     * Compute last dB read positions
     * @param mixed $percent
     * @param resource $handle
     * @param mixed $size
     * @return array
     */
    protected function computeLastDbReadPositions($percent, $handle, $size)
    {
        $return['percent_db_imported'] = $percent;
        $return['database_read_offset'] = ftell($handle);
        $return['database_read_size'] = $size;
        
        return $return;
    }
    
    /**
     * Get correct import SQL depending whether a raw SQL is available or encrypted format
     * @param string $unzipped_path
     * @param number $blogid_to_import
     * @return string
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverImporter::itImportsUnencryptedDbWhenRequested()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverImporter::itImportsEncryptedDbIfAllSet()
     * @audited
     */
    private function handleEncrypteddB($unzipped_path = '', $blogid_to_import = 0)
    {
        global $wp_filesystem;
        $import_path_sql = '';
        if ( ! $unzipped_path || ! $blogid_to_import ) {
            return $import_path_sql;
        }
        $db_import_options = ['.sql', '.sql.enc'];
        foreach ($db_import_options as $extension ) {
            $sql_file_name = $blogid_to_import . $extension;
            $temp_path_sql = $unzipped_path . $sql_file_name;            
            
            if ($wp_filesystem->exists($temp_path_sql)) {
                $import_path_sql = $temp_path_sql;
            }            
            if ($import_path_sql && '.sql.enc' === $extension) {
                $this->getSystemInitialization()->setEncryptedDb(true);
            }
            if ($import_path_sql) {
                return $import_path_sql;
            }
        }
        return $import_path_sql;
    }

    /**
     * Formulate MySQL restore command with encryption support.
     * @param string $sql_file_name
     * @return string[]|boolean[]
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverImporter::itImportsUnencryptedDbWhenRequested()
     * @audited
     */
    protected function mysqlRestoreCommand($sql_file_name = '')
    {   
        $commands = [];
        $decrypt = false;
        if ($this->getSystemInitialization()->getEncryptedDb()) {
            $decrypt = true;
        }      
        
        $commands['target_path'] = $sql_file_name;
        $commands['decrypt'] = $decrypt;
        
        return $commands;        
    }
    
    /**
     * Rename db prefix helper
     * @param array $converted_table_names
     * @param string $target_prefix
     * @param number $blog_id
     * @param array $ret
     */
    private function renameDbPrefixHelper($converted_table_names = [], $target_prefix = '', $blog_id = 0, $ret = [])
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if (is_array($converted_table_names) && ! empty($converted_table_names)) {
            global $wpdb;
            $options_table = $target_prefix . 'options';
            $deactivated_plugins = false;
            
            do_action('prime_mover_log_processed_events', 'Actual renaming of tables: ', $blog_id, 'import', __FUNCTION__ , $this);
            do_action('prime_mover_log_processed_events', $converted_table_names, $blog_id, 'import', __FUNCTION__, $this);
            
            $this->killBlockingProcesses($ret);
            
            foreach ($converted_table_names as $original_table_name => $new_table_name) {                
                $drop_query = sprintf(
                    "DROP TABLE IF EXISTS `%s`;",
                    $new_table_name
                    );
                
                do_action('prime_mover_log_processed_events', "Renaming $original_table_name TO $new_table_name", $blog_id, 'import', __FUNCTION__, $this);                
                $drop_result = $this->getSystemFunctions()->dropTable($drop_query, false, $wpdb, true);
                
                if (true === $drop_result) {
                    do_action('prime_mover_log_processed_events', "Old $new_table_name successfully dropped.", $blog_id, 'import', __FUNCTION__, $this); 
                    
                    $rename_query = sprintf(
                        "RENAME TABLE `%s` TO `%s`;",
                        $original_table_name,
                        $new_table_name
                        );

                    $wpdb->query($rename_query);
                }
                
                if ($options_table === $new_table_name) {                      
                    $this->renameSomeOptions($ret, $blog_id);
                    $this->maybeRestoreDefaultUserRole($blog_id);
                    $deactivated_plugins = $this->getSystemFunctions()->activatePrimeMoverPluginOnly($blog_id);
                    
                    do_action('prime_mover_log_processed_events', 'Successfully renamed new options table.', $blog_id, 'import', __FUNCTION__, $this);
                    do_action('prime_mover_log_processed_events', "Re-deactivated plugins status is : $deactivated_plugins", $blog_id, 'import', __FUNCTION__, $this);
                }                
            }
        }
    }
    
    /**
     * Restore default user role if imported is not setting it.
     * @param number $blog_id
     */
    protected function maybeRestoreDefaultUserRole($blog_id = 0)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized() || is_multisite()) {
            return;
        }
        $current_user_id = get_current_user_id();
        $db_prefix = $this->getSystemFunctions()->getDbPrefixOfSite(1);
        $current_role_option = $db_prefix . 'user_roles';
        wp_cache_delete('alloptions', 'options');
        if ($this->maybeRestoreEmergencyRoleOptions($current_role_option)) {
            $this->getSystemFunctions()->updateOption($current_role_option, get_user_meta($current_user_id, $this->getSystemInitialization()->getDefaultUserRole(), true)); 
            delete_user_meta($current_user_id, $this->getSystemInitialization()->getDefaultUserRole());            
        }
    }
    
    /**
     * Check if we need to restore emergency user role options
     * @param array $current_role_option
     * @param number $blog_id
     * @return boolean
     */
    protected function maybeRestoreEmergencyRoleOptions($current_role_option = [], $blog_id = 0)
    {
        $source_role_option = $this->getSystemFunctions()->getOption($current_role_option);        
        if (!$source_role_option) {            
            do_action('prime_mover_log_processed_events', 'Imported options does not have WordPress user role set. Emergency restoration of default user role to avoid breaking restore process.', $blog_id, 'import', __FUNCTION__, $this);
            return true;
        }
        
        if (!isset($source_role_option['administrator']['capabilities']['manage_options']) && !isset($source_role_option['administrator']['capabilities']['activate_plugins'])) {
            do_action('prime_mover_log_processed_events', 'Imported options although set does not have administrator capabilities. Emergency restoration of default user role to avoid breaking restore process.', $blog_id, 'import', __FUNCTION__, $this);
            return true;
        }       
        
        return false;   
    }
    
    /**
     * Kill blocking processes to Drop and Rename statements
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itKillsBlockingProcesses()
     */
    protected function killBlockingProcesses($ret = [])
    {       
        global $wpdb;
        if (isset($ret['prime_mover_is_super_db_user']) && false === $ret['prime_mover_is_super_db_user']) {
            return;
        }
        $result = $wpdb->get_results("SHOW PROCESSLIST", ARRAY_A); 
        if (!is_array($result)) {
            return;
        }
        
        $filter_to_use = ['db' => DB_NAME, 'Command' => 'Sleep'];
        $filtered = wp_list_filter($result, $filter_to_use);
        $ids = wp_list_pluck($filtered, 'Id');
        
        foreach ($ids as $process_id) {
            $prepared = $wpdb->prepare("KILL %d", $process_id);
            $wpdb->query($prepared);
        }          
    }
        
    /**
     * Activate plugins if not still activated
     * {@inheritDoc}
     * @see \Codexonics\PrimeMoverFramework\interfaces\PrimeMoverImport::activatePluginsIfNotActivated()
     * @compatible 5.6
     * @mainsite_compatible
     */
    public function activatePluginsIfNotActivated($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');
        
        $this->getSystemInitialization()->setSlowProcess();
        if ( ! empty($ret['error']) ) {            
            $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Errors detected, alert user.', 'prime-mover'));
            return $ret;
        }        
        $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Activating plugins', 'prime-mover'));
        if (! isset($ret['imported_package_footprint']['plugins'])) {
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }
        
        $source_site_active_plugins = $ret['imported_package_footprint']['plugins'];
        if (empty($source_site_active_plugins) || ! is_array($source_site_active_plugins)) {
          
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }

        if (is_multisite()) {
            $this->getSystemFunctions()->maybeForceDeleteOptionCaches('active_plugins', true, true);
        }       
        
        do_action('prime_mover_before_activating_plugins', $ret);
        $this->getSystemFunctions()->switchToBlog($blogid_to_import);
        $this->getSystemFunctions()->maybeForceDeleteOptionCaches('active_plugins', true, false);
        
        $active_plugins = $this->getSystemFunctions()->getOption('active_plugins', []);
        global $wp_filesystem;
        $active_plugins_keys = array_keys($source_site_active_plugins);
        
        foreach ($active_plugins_keys as $plugin) {
            if (empty($plugin)) {
                continue;
            }
            if (! $wp_filesystem->exists(PRIME_MOVER_PLUGIN_CORE_PATH . $plugin)) {
                continue;
            }            
           
            do_action('prime_mover_log_processed_events', "Activating plugin : $plugin", $blogid_to_import, 'import', $current_func, $this);               
            if (is_plugin_inactive($plugin)) {   
                $active_plugins[] = $plugin;
              
                do_action('prime_mover_log_processed_events', "$plugin IS INACTIVE", $blogid_to_import, 'import', $current_func, $this);
            } else {
              
                do_action('prime_mover_log_processed_events', "$plugin IS ALREADY ACTIVE", $blogid_to_import, 'import', $current_func, $this);
            }
        }
        
        $this->getSystemFunctions()->updateOption('active_plugins', $active_plugins);
        $this->getSystemFunctions()->restoreCurrentBlog();
        
        do_action('prime_mover_after_db_processing', $ret, $blogid_to_import);
        do_action('prime_mover_log_processed_events', $ret, $blogid_to_import, 'import', $current_func, $this, true);
       
        $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'import', $blogid_to_import);       
        return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
    }

    /**
     * Success import
     * {@inheritDoc}
     * @see \Codexonics\PrimeMoverFramework\interfaces\PrimeMoverImport::markImportSuccess()
     * @compatible 5.6
     * @audited
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itMarkImportSuccess() 
     */
    public function markImportSuccess($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);
       
        /**
         * @var Type $next_func Next function
         */
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');
        
        $this->getSystemInitialization()->setSlowProcess();
        if ( ! empty($ret['error']) ) {            
            $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Errors detected, alert user.', 'prime-mover'));
            return $ret;
        }        
        do_action('prime_mover_after_actual_import', $ret, $blogid_to_import, $files_array);
        do_action('prime_mover_log_processed_events', 'Marked import success', $blogid_to_import, 'import', $current_func, $this);

        $ret['import_success']	= true;
        do_action('prime_mover_log_processed_events', $ret, $blogid_to_import, 'import', $current_func, $this, true);
       
        $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'import', $blogid_to_import);  
        $this->getSystemChecks()->computePerformanceStats($blogid_to_import, $ret, 'import');
        
        return $this->cleanUpProgressForFinalReturn($ret);
    }
    
    /**
     * Rename some options in target dB after renaming dB prefix
     * @param array $ret
     * @param number $blogid_to_import
     * @return void
     * @compatible 5.6
     * @audited
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itRenameSomeOptions()
     */
    protected function renameSomeOptions($ret = [], $blogid_to_import = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if (empty($ret) || ! $blogid_to_import) {
            return $ret;
        }
        
        $affected_options = $this->getAffectedOptionsAfterRenamePrefix($ret, $blogid_to_import);
        if (empty($affected_options)) {
            return $ret;
        }
        $this->executeRenameOptionsQuery($affected_options, $blogid_to_import, $ret);
    }
    
    /**
     * Remove prefix from table name
     * @param string $prefix
     * @param string $table
     * @return string
     * @audited
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itRemovesDbPrefixFromTable() 
     */
    protected function removePrefixFromTable($prefix = '', $table = '')
    {        
        if (substr($table, 0, strlen($prefix)) == $prefix) {
            $table = substr($table, strlen($prefix));
        }        
        return $table;
    }
    
    /**
     * Execute rename options query
     * @param array $affected_options
     * @param number $blogid_to_import
     * @param array $ret
     * @compatible 5.6
     * @audited
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itExecutesRenameOptionsQuery() 
     */
    protected function executeRenameOptionsQuery($affected_options = [], $blogid_to_import = 0, $ret = [])
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if (empty($affected_options) || ! is_array($affected_options) || ! $blogid_to_import) {
            return;
        }
        global $wpdb;
        $this->getSystemFunctions()->switchToBlog($blogid_to_import);
        
        $origin_prefix = $ret['origin_db_prefix'];        
        foreach ($affected_options as $v) {            
            $name_without_prefix = $this->removePrefixFromTable($origin_prefix, $v);
            $new_option_name = $wpdb->prefix . $name_without_prefix;
            delete_option($new_option_name);
            
            $update_query = "UPDATE {$wpdb->prefix}options SET option_name = '{$new_option_name}' WHERE option_name = '%s'";
            $wpdb->query($wpdb->prepare($update_query, $v));
        }
        $this->getSystemFunctions()->restoreCurrentBlog();
    }
    
    /**
     * @param array $ret
     * @param number $blogid_to_import
     * @return void|array|mixed[]
     * @compatible 5.6
     * @audited
     */
    protected function getAffectedOptionsAfterRenamePrefix($ret = [], $blogid_to_import = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $affected_options = array();
        if (empty($ret) || ! $blogid_to_import) {
            return $affected_options;
        }

        $this->getSystemFunctions()->switchToBlog($blogid_to_import); 
        global $wpdb;
        
        $options_query = "SELECT option_name FROM {$wpdb->prefix}options WHERE option_name LIKE %s";
        $prefix_search = $wpdb->esc_like($ret['origin_db_prefix']) . '%';
        $option_query_prepared = $wpdb->prepare($options_query, $prefix_search);
        $option_query_results = $wpdb->get_results($option_query_prepared, ARRAY_N);
        
        if (! is_array($option_query_results) || empty($option_query_results)) {
            $this->getSystemFunctions()->restoreCurrentBlog(); 
            return $affected_options;
        }
        foreach ($option_query_results as $v) {
            if (! is_array($v)) {
                continue;
            }
            $affected_options[] = reset($v);
        }
        $this->getSystemFunctions()->restoreCurrentBlog();        
        return $affected_options;
    }
    
    /**
     * Convert old table names to new
     * @param array $ret
     * @param array $clean_tables
     * @return mixed[]
     * @compatible 5.6
     * @audited
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itConvertsOldTableNamesToNew() 
     */
    protected function convertOldTableNamesToNew($ret = [], $clean_tables = [])
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $converted = [];
        if (! empty($clean_tables)) {            
            foreach ($clean_tables as $original_table_name) {
                $new_table_name = str_replace($ret['origin_db_prefix'], $ret['target_db_prefix'], $original_table_name);
                $converted[ $original_table_name ] = $new_table_name;
            }
        }
        return apply_filters('prime_mover_filter_converted_db_tables_before_rename', $converted, $clean_tables, $ret);
    }
  
    /**
     * Rename table names to have a compatible dB prefix if possible
     * {@inheritDoc}
     * @see PrimeMoverImport::renameDbPrefix()
     * @compatible 5.6
     * @mainsite_compatible
     */
    public function renameDbPrefix($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);
       
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');
        $this->getSystemInitialization()->setSlowProcess();
        
        if ( ! empty($ret['error'] ) ) {
            $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Errors detected, alert user.', 'prime-mover'));
            return $ret;
        }
        if ($ret['origin_db_prefix'] === $ret['target_db_prefix']) {
           
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }
        $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Renaming dB prefix with target site.', 'prime-mover'));
        
        global $wpdb;
        $db_search = "SHOW TABLES LIKE %s";
        
        $clean_tables = [];
        $package_exported_tables = $ret['imported_package_footprint']['exported_db_tables'];
        if ( ! is_array($package_exported_tables) ) {
           
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }
        foreach ($package_exported_tables as $table_to_validate) {
            $table_to_validate = filter_var($table_to_validate, $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter());
            $sql = $wpdb->prepare($db_search ,  $table_to_validate);
            if ($wpdb->get_var($sql) === $table_to_validate) {
                $clean_tables[] = $table_to_validate;
            }
        }
        
        if (empty($clean_tables)) {
            $ret['error'] = esc_html__('Unable to rename tables, please check that your database is not empty or these tables exists.', 'prime-mover');
            return $ret;
        }
        
        $converted_table_names = $this->convertOldTableNamesToNew($ret, $clean_tables);
        
        $this->getSystemInitialization()->setSlowProcess();        
        $this->renameDbPrefixHelper($converted_table_names, $ret['target_db_prefix'], $blogid_to_import, $ret);
        
        do_action('prime_mover_log_processed_events', $ret, $blogid_to_import, 'import', $current_func, $this, true);
        do_action('prime_mover_after_renamedb_prefix', $blogid_to_import, $ret);
        
        $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'import', $blogid_to_import);        
        return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
    }
    
    /**
     * Count affected table rows prior to search and replace process
     * @param array $ret
     * @param number $blogid_to_import
     * @param array $files_array
     * @param number $start_time
     * @mainsite_compatible
     */
    public function countTableRows($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {        
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);  
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');
        
        if ( ! empty($ret['error'] ) ) {
            do_action('prime_mover_log_processed_events',"Receiving error on count table rows function, skipping", $blogid_to_import, 'import', $current_func, $this);
            $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Errors detected, alert user.', 'prime-mover'));
            return $ret;
        }        
        
        $doing_count_table_rows_retry = false;
        if (isset($ret['count_tablerows_ongoing_count']) && isset($ret['count_tablerows_resume_tables'])) {
            $doing_count_table_rows_retry = true;
        }        
        
        if ( isset($ret['main_search_replace_replaceables']) ) {
            $replaceables = $ret['main_search_replace_replaceables'];
        } else {
            $replaceables = $this->getSystemFunctions()->generateImportReplaceables($ret);
            $ret['main_search_replace_replaceables'] = $replaceables;
        }        
        
        if ( ! $doing_count_table_rows_retry && apply_filters('prime_mover_skip_search_replace', false, $ret, $blogid_to_import, $replaceables)) {
            
            do_action('prime_mover_skipped_search_replace', $ret, $blogid_to_import, $replaceables);
            do_action('prime_mover_log_processed_events',"Skipping search replace on main process method...", $blogid_to_import, 'import', $current_func, $this);
            do_action('prime_mover_log_processed_events',"Replaceables: ", $blogid_to_import, 'import', $current_func, $this);
            do_action('prime_mover_log_processed_events', $replaceables, $blogid_to_import, 'import', $current_func, $this);
            
            $ret['main_search_replace_skip'] = true;
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }       
        
        if (!isset($ret['force_site_url_update_later']) && true === $this->getSystemInitialization()->getForceAdjustSiteUrl()) {
            $ret['force_site_url_update_later'] = true;
        }        
        
        $percent_string = esc_html__('Starting..', 'prime-mover');
        if ( ! empty($ret['count_tablerows_progress'] ) ) {
            $percent_string = $ret['count_tablerows_progress'];
        }        
        
        $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Table rows count. %s', 'prime-mover'), $percent_string), 'import');        
        $retry_timeout = apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, $current_func);
        
        $this->getSystemFunctions()->switchToBlog($blogid_to_import);        
        
        $this->getSystemFunctions()->temporarilyIncreaseMemoryLimits(); 
        if ($doing_count_table_rows_retry) {            
            $all_tables = $ret['count_tablerows_resume_tables'];
            $table_rows_count = $ret['count_tablerows_ongoing_count'];
            
        } else {            
            $all_tables = $this->getSystemFunctions()->getTablesforReplacement($blogid_to_import, $ret);
            $ret['main_search_replace_tables'] = $all_tables; 
            $table_rows_count = [];
        }        
        
        global $wpdb;     
        foreach ($all_tables as $k => $table) {   
            
            $table_rows_count[$table] = $wpdb->get_var("SELECT count(*) FROM {$table}");
            unset($all_tables[$k]);           
            
            if ((microtime(true) - $start_time) > $retry_timeout) { 
                do_action('prime_mover_log_processed_events', "$retry_timeout hits while counting table rows.", $blogid_to_import, 'import', $current_func, $this);
                
                $ret['count_tablerows_resume_tables'] = $all_tables;
                $ret['count_tablerows_ongoing_count'] = $table_rows_count;
                
                $completed = count($ret['main_search_replace_tables']) - count($all_tables);
                $percent = floor(($completed / count($ret['main_search_replace_tables'])) * 100);                
                
                if ($percent) {
                    $percent_string = $percent . '%' . ' ' . esc_html__('done', 'prime-mover');
                }  
                $ret['count_tablerows_progress'] = $percent_string;
                $this->getSystemFunctions()->restoreCurrentBlog();
                
                return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $current_func, $previous_func);
            }
        }
        
        $this->getSystemFunctions()->restoreCurrentBlog();        
        do_action('prime_mover_log_processed_events', "Table rows count done.", $blogid_to_import, 'import', $current_func, $this);        
        
        $ret['main_search_replace_total_rows_count'] = array_sum($table_rows_count);
        $ret['main_search_replace_tables_rows_count'] = $table_rows_count;
        
        $ret = $this->cleanUpTableRowsMeta($ret);
       
        $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'import', $blogid_to_import);  
        return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);  
    }
    
    /**
     * Clean up table rows meta
     * @param array $ret
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itCleanUpTablesRowsMeta()
     */
    protected function cleanUpTableRowsMeta($ret = [])
    {
        if (isset($ret['count_tablerows_resume_tables'])) {
            unset($ret['count_tablerows_resume_tables']);
        }
        if (isset($ret['count_tablerows_ongoing_count'])) {
            unset($ret['count_tablerows_ongoing_count']);
        }
        if (isset($ret['count_tablerows_progress'])) {
            unset($ret['count_tablerows_progress']);
        }
        return $ret;
    }
    
    /**
     * {@inheritDoc}
     * @see PrimeMoverImport::searchAndReplace()
     * @compatible 5.6
     * @mainsite_compatible
     */
    public function searchAndReplace($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {        
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);  
        $ret['completion_text'] = esc_html__('Your site has been restored successfully', 'prime-mover');
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');
        
        if ( ! empty($ret['error'] ) ) {                        
            
            do_action('prime_mover_log_processed_events',"Receiving error on search replace function, skipping", $blogid_to_import, 'import', $current_func, $this);            
            $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Errors detected, alert user.', 'prime-mover'));
            return $ret;
        }        
        
        if ( ! empty($ret['main_search_replace_skip'] ) ) {            
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }        
        
        if (empty($ret['main_search_replace_replaceables'] ) || empty($ret['main_search_replace_tables']) || empty($ret['main_search_replace_total_rows_count']) ) {
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }        
        
        if ( ! empty($ret['srch_rplc_completed']) ) {
            do_action('prime_mover_log_processed_events',"Search replace COMPLETED check #1, moving to next process.", $blogid_to_import, 'import', $current_func, $this);
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }
        
        $replaceables = $ret['main_search_replace_replaceables'];          
        $retries = false;
        $percent_string = esc_html__('Starting...', 'prime-mover');
        
        if ( ! empty($ret['ongoing_srch_rplc_percent']) ) {
            $percent_string = $ret['ongoing_srch_rplc_percent'];
            $retries = true;
        }
        
        $replaceables = apply_filters('prime_mover_filter_final_replaceables', $replaceables, $ret, $retries, $blogid_to_import); 
        if (!isset($ret['final_replaceables'])) {
            $ret['prime_mover_final_replaceables'] = $replaceables;
        }
        
        $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Search and replace. %s', 'prime-mover'), $percent_string), 'import');        
        $this->logSearchReplaceParameters($ret, $blogid_to_import, $replaceables);
        
        if (is_array($replaceables) && empty($replaceables)) {
            do_action('prime_mover_log_processed_events',"Unable to generate replaceables", $blogid_to_import, 'import', $current_func, $this);
            $ret['error'] = esc_html__('Unable to generate replaceables, could that your site package is corrupted. Please check.', 'prime-mover');
            return $ret;
        }
        $this->getSystemFunctions()->switchToBlog($blogid_to_import);        
        $this->getSystemFunctions()->temporarilyIncreaseMemoryLimits();
        
        $connection = $this->getSystemInitialization()->getConnectionInstance();
        
        if ( ! empty( $ret['ongoing_srch_rplc_remaining_tables'] ) ) {
            $all_tables = $ret['ongoing_srch_rplc_remaining_tables'];
        } else {
            $all_tables = $ret['main_search_replace_tables'];
        }     
        
        global $wpdb;
        $posts_table = $wpdb->posts;
        
        $excluded_columns = [$posts_table => 'guid']; 
        $ret = PrimeMoverSearchReplace::load($connection, $replaceables, $all_tables, 1, $this, $excluded_columns, $start_time, $ret);        
        $this->getSystemFunctions()->restoreCurrentBlog();        
        
        if ( ! empty($ret['srch_rplc_completed'] ) ) {
            $ret['completion_text'] = esc_html__('Your site has been migrated successfully', 'prime-mover');
            do_action('prime_mover_log_processed_events',"Search replace COMPLETED check #2, moving to next process.", $blogid_to_import, 'import', $current_func, $this);
            $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'import', $blogid_to_import); 
           
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);            
        } else {
            do_action('prime_mover_log_processed_events',"Search replace NOT over, repeating again..", $blogid_to_import, 'import', $current_func, $this);
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $current_func, $previous_func);
        }
    }
    
    /**
     * Log search replace parameters
     * @param array $ret
     * @param number $blogid_to_import
     * @param array $replaceables
     * @codeCoverageIgnore
     */
    private function logSearchReplaceParameters($ret = [], $blogid_to_import = 0, $replaceables = [])
    {        
        do_action('prime_mover_log_processed_events',"Starting search and replace with these following parameters", $blogid_to_import, 'import', __FUNCTION__, $this);
        do_action('prime_mover_log_processed_events',$replaceables, $blogid_to_import, 'import', __FUNCTION__, $this);
        do_action('prime_mover_log_processed_events',"Footprint parameters: ", $blogid_to_import, 'import', __FUNCTION__, $this, true);
        do_action('prime_mover_log_processed_events', $ret, $blogid_to_import, 'import', __FUNCTION__, $this, true);
    }
    
    /**
     * {@inheritDoc}
     * @see \Codexonics\PrimeMoverFramework\interfaces\PrimeMoverImport::markTargetSiteUploadsInformation()
     * @compatible 5.6
     * @mainsite_compatible
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itDoesNotMarkTargetSiteUploadsInformationWhenUnAuthorized()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itReturnsErrorMarkingSiteUploadsInformation()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itMarkTargetSiteUploadsInformation() 
     */
    public function markTargetSiteUploadsInformation($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');
        
        $this->getSystemInitialization()->setSlowProcess();
        if ( ! empty($ret['error'] ) ) {
            $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Errors detected, alert user.', 'prime-mover'));
            return $ret;
        }
        
        $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Saving uploads info..', 'prime-mover'));
        $upload_path_information = '';
        $upload_url_path_information = '';
        $uploads_path = $this->getSystemFunctions()->getBlogOption($blogid_to_import, 'upload_path');
        $upload_url_path = $this->getSystemFunctions()->getBlogOption($blogid_to_import, 'upload_url_path');
        
        if ($uploads_path) {            
            $upload_path_information = $uploads_path;
        }
        
        if ($upload_url_path) {
            $upload_url_path_information = $upload_url_path;
        }

        $ret['upload_path_information'] = $upload_path_information;
        $ret['upload_url_path_information'] = $upload_url_path_information;
        
        $this->getSystemFunctions()->switchToBlog($blogid_to_import);        
        $ret['canonical_uploads_information'] = $this->getSystemInitialization()->getWpUploadsDir(true, true);
        $this->getSystemFunctions()->restoreCurrentBlog($blogid_to_import);
        
        $ret = apply_filters('prime_mover_filter_other_information', $ret, $blogid_to_import);
       
        do_action('prime_mover_log_processed_events', $ret, $blogid_to_import, 'import', $current_func, $this, true); 
        $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'import', $blogid_to_import); 
       
        return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
    }

    /**
     * {@inheritDoc}
     * @see \Codexonics\PrimeMoverFramework\interfaces\PrimeMoverImport::restoreCurrentUploadsInformation()
     * @compatible 5.6
     * @audited
     * @mainsite_compatible
     */
    public function restoreCurrentUploadsInformation($ret = [], $blogid_to_import = 0, $files_array = [], $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_import_progress', $ret, $blogid_to_import);
       
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__, 'import');
        
        $this->getSystemInitialization()->setSlowProcess();
        if ( ! empty($ret['error']) ) {
            $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Errors detected, alert user.', 'prime-mover'));
            return $ret;
        }        
        $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Restoring uploads info..', 'prime-mover'));
        
        if (! isset($ret['upload_path_information']) || ! isset($ret['upload_url_path_information'])) {
            return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
        }

        $this->getSystemFunctions()->switchToBlog($blogid_to_import);
        wp_cache_delete('alloptions', 'options');
        $this->getSystemFunctions()->restoreCurrentBlog();
        
        $upload_structure = $ret['upload_path_information'];
        $upload_url = $ret['upload_url_path_information'];
        
        $this->getSystemFunctions()->updateBlogOption($blogid_to_import, 'upload_path', $upload_structure);
        $this->getSystemFunctions()->updateBlogOption($blogid_to_import, 'upload_url_path', $upload_url);
 
        $canonical_home = '';
        if (isset($ret['dev_home_url'])) {
            $canonical_home = $ret['dev_home_url'];
        }
        $after_restore_home = get_home_url($blogid_to_import);
        if ($canonical_home && $after_restore_home && $after_restore_home !== $canonical_home) {
            do_action('prime_mover_log_processed_events', "Restoring to original home URL: $canonical_home", $blogid_to_import, 'import', $current_func, $this);
            $this->getSystemFunctions()->updateBlogOption($blogid_to_import, 'home', $canonical_home);
        }
        
        $canonical_siteurl = '';
        if (isset($ret['dev_site_url'])) {
            $canonical_siteurl = $ret['dev_site_url'];
        }
        
        if (isset($ret['force_site_url_update_later']) && true === $ret['force_site_url_update_later'] && $canonical_siteurl) {
            do_action('prime_mover_log_processed_events', "Restoring to original site URL: $canonical_siteurl", $blogid_to_import, 'import', $current_func, $this);
            $this->getSystemFunctions()->updateBlogOption($blogid_to_import, 'siteurl', $canonical_siteurl);
        }
        
        do_action('prime_mover_log_processed_events', $ret, $blogid_to_import, 'import', $current_func, $this, true);
        $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'import', $blogid_to_import);
       
        return apply_filters('prime_mover_save_return_import_progress', $ret, $blogid_to_import, $next_func, $current_func);
    }
    
    /**
     * Save and return import progress data
     * @param array $ret
     * @param number $blogid_to_import
     * @param string $next_method
     * @param string $current_method
     * @return array
     * @audited
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itSavesImportProgress()
     */
    public function saveImportProgressData($ret = [], $blogid_to_import = 0, $next_method = '', $current_method = '')
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized() ) {
            return $ret;
        }
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return $ret;
        }
        if ( ! empty($ret['error']) ) {
            return $ret;
        }
        if ( ! $blogid_to_import || ! $next_method || ! $current_method ) {
            return $ret;
        }
        $ret['ongoing_import'] = true;
        $ret['next_method'] = $next_method;
        $ret['current_method'] = $current_method;
        
        $meta_key = $this->getProgressHandlers()->generateTrackerId($blogid_to_import, 'import');       
        wp_cache_delete($user_id, 'user_meta' );
        
        global $wpdb;
        $wpdb->query("UNLOCK TABLES;");      
          
        $umeta_id = 0;
        if (!isset($ret['prime_mover_tracker_umeta_id']) && !is_multisite()) {
            $usermeta_table = $this->getSystemFunctions()->getUserMetaTableName();
            $umeta_id = $wpdb->get_var($wpdb->prepare("SELECT umeta_id FROM {$usermeta_table} WHERE meta_key = %s", $meta_key));
        }
        
        if ($umeta_id) {
            $ret['prime_mover_tracker_umeta_id'] = (int)$umeta_id;
        }
        
        do_action('prime_mover_update_user_meta', $user_id, $meta_key, $ret);         
        return $ret;
    }
    
    /**
     * Return import progress data to continue processing
     * @param array $ret
     * @param number $blogid_to_export
     * @return array
     * @audited
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itGetsImportProgressData()
     */
    public function getImportProgressData($ret = [], $blogid_to_import = 0)
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized() || ! $blogid_to_import) {
            return $ret;
        }
        $user_id = get_current_user_id();
        $meta_key = $this->getProgressHandlers()->generateTrackerId($blogid_to_import, 'import');
        wp_cache_delete($user_id, 'user_meta' );
        
        return get_user_meta($user_id, $meta_key, true);
    }
    
    /**
     * Clean up import progress
     * @param array $ret
     * @return array
     * @audited
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImporter::itCleanUpProgressForFinalReturn() 
     */
    protected function cleanUpProgressForFinalReturn($ret = [])
    {
        if (isset($ret['ongoing_import'])) {
            unset($ret['ongoing_import']);
        }
        if (isset($ret['next_method'])) {
            unset($ret['next_method']);
        }
        if (isset($ret['current_method'])) {
            unset($ret['current_method']);
        }
        return $ret;
    }
}
