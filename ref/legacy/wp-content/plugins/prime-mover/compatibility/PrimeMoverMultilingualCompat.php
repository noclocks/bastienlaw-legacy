<?php
namespace Codexonics\PrimeMoverFramework\compatibility;

/*
 * This file is part of the Codexonics.PrimeMoverFramework package.
 *
 * (c) Codexonics Ltd
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Codexonics\PrimeMoverFramework\classes\PrimeMover;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Multilingual Compatibility Class
 * Internationalization migraton/backup support.
 *
 */
class PrimeMoverMultilingualCompat
{     
    private $prime_mover;
    private $ml_plugin;
    private $callbacks;
    private $system_utilities;
    private $source_collate;
    private $target_charset;
    private $charset_same;
    private $sourcecollate_equivalence;
    private $sourcecharset_masterlists;
    
    /**
     * Construct
     * @param PrimeMover $prime_mover
     * @param array $utilities
     */
    public function __construct(PrimeMover $prime_mover, $utilities = [])
    {
        $this->prime_mover = $prime_mover;
        $this->ml_plugin = 'sitepress-multilingual-cms/sitepress.php';
        $this->callbacks = [
            'maybeAdjustStringTranslationTable' => 16,
            'maybeAdjustTranslationStatusTable' => 17,
            'maybeAdjustTranslatorIdJobsTable' => 18,
            'maybeAdjustManagerIdJobsTable' => 19,
            ];       
        $this->system_utilities = $utilities['sys_utilities'];
        
        $this->target_charset = '';
        $this->source_collate = '';
        $this->charset_same = false;
        $this->sourcecollate_equivalence = [];
        $this->sourcecharset_masterlists = [];
    }
    
    /**
     * Get source charset masterlists
     * @return array
     */
    public function getSourceCharsetMasterlists()
    {
        return $this->sourcecharset_masterlists;
    }
    
    /**
     * Get source collate charset equivalence
     * @return array
     */
    public function getSourceCollateCharset()
    {
        return $this->sourcecollate_equivalence;
    }
    
    /**
     * Get progress handlers
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverProgressHandlers
     */
    public function getProgressHandlers()
    {
        return $this->getPrimeMover()->getImporter()->getProgressHandlers();   
    }
    
    /**
     * Get charset same
     * @return boolean
     */
    public function getCharSetSame()
    {
        return $this->charset_same;
    }
    
    /**
     * Get source collate
     * @return string
     */
    public function getSourceCollate()
    {
        return $this->source_collate;
    }
    
    /**
     * Get target charset
     * @return string
     */
    public function getTargetCharSet()
    {
        return $this->target_charset;
    }
    
    /**
     * 
     * Get system utilities
     */
    public function getSystemUtilities()
    {
        return $this->system_utilities;
    }
    
    /**
     * Get Prime Mover
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMover
     */
    public function getPrimeMover()
    {
        return $this->prime_mover;
    }
    
    /**
     * Get callbacks
     * @return number[]
     */
    public function getCallBacks()
    {
        return $this->callbacks;
    }
    
    /**
     * Get multilingual plugin
     * @return string
     */
    public function getMultilingualPlugin()
    {
        return $this->ml_plugin;
    }
                         
    /**
     * Initialize hooks
     */
    public function initHooks()
    {
        foreach ($this->getCallBacks() as $callback => $priority) {
            add_filter('prime_mover_do_process_thirdparty_data', [$this, $callback], $priority, 3);
        }
        
        add_action('prime_mover_before_thirdparty_data_processing', [$this, 'removeProcessorHooksWhenDependencyNotMeet'], 10, 2); 
        add_filter('prime_mover_default_db_charset', [$this, 'dBCharset'], 10, 1);        
        add_filter('prime_mover_define_other_package_configuration', [$this, 'maybeAddLangFolderNameToPackageConfig'], 10, 1);
        
        add_filter('prime_mover_define_other_package_configuration', [$this, 'maybeAddCharSetAndCollateToPackageConfig'], 10, 2);
        add_filter('prime_mover_after_user_diff_confirmation', [$this, 'maybeSkipLanguagesFolder'], 10, 1);
        add_action('prime_mover_before_looping_restore_queries', [$this, 'maybeSetCharSetForRestoreQueries'], 0, 2);
        
        add_filter('prime_mover_filter_export_db_data', [$this, 'maybeFilterDefaultCharset'], 9, 1);        
        add_filter('prime_mover_before_mysqldump_php', [$this, 'initializeCharSetParameters'], 40, 1);
        add_filter('prime_mover_inject_db_parameters', [$this, 'maybeDetectMismatchCharset'], 10, 2);
        
        add_filter('prime_mover_filter_site_locales', [$this, 'maybeInjectActiveLanguagesLocale'], 10, 3);
    } 
 
    /**
     * Inject active languages locale to make sure MO Files are exported correctly
     * @param array $locales
     * @param array $ret
     * @param number $blog_id
     */
    public function maybeInjectActiveLanguagesLocale($locales = [], $ret = [], $blog_id = 0)
    {
        $this->getSystemFunctions()->switchToBlog($blog_id);
        if (!$this->getSystemFunctions()->isPluginActive($this->getMultilingualPlugin())) {
            $this->getSystemFunctions()->restoreCurrentBlog(); 
            return $locales;
        } 
        
        if (!function_exists('primeMoverLanguageToLocale')) {
            $this->getSystemFunctions()->restoreCurrentBlog(); 
            return $locales;
        }
        
        $locales_masterlist = primeMoverLanguageToLocale();
        if (!is_array($locales_masterlist)) {
            $this->getSystemFunctions()->restoreCurrentBlog(); 
            return $locales;
        }       
        
        $settings = $this->getSystemFunctions()->getOption('icl_sitepress_settings', false);
        $active_lang = [];
        if (is_array($settings) && isset($settings['active_languages']) && is_array($settings['active_languages'])) {
            $active_lang = $settings['active_languages'];
        }       
        
        if (!empty($active_lang)) {
            foreach ($active_lang as $lang) {
                $locale = '';
                if (isset($locales_masterlist[$lang])) {
                    $locale = $locales_masterlist[$lang];
                }
                
                if ($locale && !in_array($locale, $locales)) {
                    $locales[] = $locale;
                }
            }
        }
        
        $this->getSystemFunctions()->restoreCurrentBlog();        
        
        return $locales;
    }
    /**
     * Maybe set charset for restore queries
     * @param array $ret
     * @param number $blogid_to_import
     */
    public function maybeSetCharSetForRestoreQueries($ret = [], $blogid_to_import = 0)
    {        
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
       
        if (empty($ret['wprime_tar_config_set']['prime_mover_target_db_charset'])) {
            return;
        }
        $package_target_charset = $ret['wprime_tar_config_set']['prime_mover_target_db_charset'];
        
        global $wpdb;
        if (!is_object($wpdb) || !isset($wpdb->dbh)) {
            return;
        }
        
        if (!isset($wpdb->charset)) {
            return;
        }
        
        $wpdb_connection_charset = $wpdb->charset;
        if (!is_string($wpdb_connection_charset)) {
            return;
        }
        if (!$wpdb_connection_charset) {
            return;
        }
        
        if ($wpdb_connection_charset !== $package_target_charset) {  
            do_action('prime_mover_log_processed_events', "Forcing charset to use $package_target_charset before restoring dB. Original charset is $wpdb_connection_charset", $blogid_to_import, 'import', __FUNCTION__, $this);
            $wpdb->set_charset($wpdb->dbh, $package_target_charset);
        }
    }
    
    /**
     * Detect mismatch charset
     * @param array $ret
     * @param string $mode
     * @return array
     */
    public function maybeDetectMismatchCharset($ret = [], $mode = 'import')
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        
        if ('import' !== $mode) {
            return $ret;
        }        
       
        if (empty($ret['wprime_tar_config_set']['prime_mover_target_db_charset'])) {
            return $ret;
        }
        
        $current_charset = $this->getSystemInitialization()->getDbCharSetUsedBySite();
        if (!$current_charset) {
            return $ret;
        }
       
        $source_charset = $ret['wprime_tar_config_set']['prime_mover_target_db_charset'];           
        if (false === $this->getSystemUtilities()->maybeSourceAndTargetCharsetsSame($source_charset, $current_charset)) {
            $ret['error'] = sprintf(esc_html__('Mismatch source and target database charset error. Source site charset %s cannot be restored to this database charset using %s. Please read: https://codexonics.com/prime_mover/prime-mover/runtime-error-mismatch-source-and-target-database-charset-error/'), $source_charset, $current_charset);            
            
            $this->reactivatePlugins();
            return $ret;
        }
        
        return $ret;
    }
    
    /**
     * Reactivate plugins in single site in case of error
     * @param array $ret
     */
    protected function reactivatePlugins()
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if (is_multisite()) {
            return;
        }
        $this->getProgressHandlers()->reactivatePlugins(); 
    }
    
    /**
     * Initialize charset parameters
     * Hooked to `prime_mover_before_mysqldump_php` ACTION executed before PHP-MySQL dump process to set reusable object properties
     * This belongs to export process.
     * @param array $ret
     * @return array
     */
    public function initializeCharSetParameters($ret = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        $initialized_params = [];
        $source_charset = $this->getSystemInitialization()->getDbCharSetUsedBySite();
        
        $target_charset = $this->getSystemInitialization()->getTargetCharset($ret);        
        if (!$source_charset || !$target_charset) {
            return $ret;
        }
       
        $this->target_charset = $target_charset;       
        $this->charset_same = $this->getSystemUtilities()->maybeSourceAndTargetCharsetsSame($source_charset, $target_charset);
        $this->source_collate = $this->getSystemInitialization()->getDbCollateUsedBySite();        
        $ret = $this->maybeComputeTableCharSetsCollate($ret);
        
        if (isset($ret['collate_charset_equivalence'])) {
            $this->sourcecollate_equivalence = $ret['collate_charset_equivalence'];
        }
        
        if (isset($ret['source_charset_masterlists'])) {
            $this->sourcecharset_masterlists = $ret['source_charset_masterlists'];
        }        
        
        do_action('prime_mover_log_processed_events', "Logging initialized charsets and collate before MySQLdump: ", $this->getSystemInitialization()->getExportBlogID(), 'export', __FUNCTION__, $this);        
        
        $initialized_params['target_charset'] = $this->target_charset;
        $initialized_params['charset_same'] = $this->charset_same;
        $initialized_params['source_collate'] = $this->source_collate;
        $initialized_params['sourcecollate_equivalence'] = $this->sourcecollate_equivalence;
        $initialized_params['sourcecharset_masterlists'] = $this->sourcecharset_masterlists;
        
        do_action('prime_mover_log_processed_events', $initialized_params, $this->getSystemInitialization()->getExportBlogID(), 'export', __FUNCTION__, $this);
        
        return $ret;
    }
    
    /**
     * Maybe compute table charset collate
     * Only used when target charset is different
     * @param array $ret
     * @return array
     */
    protected function maybeComputeTableCharSetsCollate($ret = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        
        if ($this->getCharSetSame() || empty($ret['tbl_primary_keys'])) {
            return $ret;
        }     
        
        if (!is_array($ret['tbl_primary_keys']) || isset($ret['collate_charset_equivalence'])) {
            return $ret;
        }   
       
        $tables = array_keys($ret['tbl_primary_keys']);        
        
        global $wpdb;
        $collations = [];
        foreach ($tables as $table) {
            $results = $wpdb->get_results("SHOW FULL COLUMNS FROM $table");
            if (!$results ) {
                continue;
            }
            $pluck = wp_list_pluck($results, 'Collation');
            if (!is_array($pluck)) {
            }
            $collations[] = array_unique(array_filter($pluck));          
        }
        
        $unique_collations = array_unique(array_reduce($collations, 'array_merge', []));
        $source_collate = '';
        if ($this->getSourceCollate()) {
            $source_collate = $this->getSourceCollate();
        }
        
        if ($source_collate && !in_array($source_collate, $unique_collations)) {
            $unique_collations[] = $source_collate;
        }
       
        $unique_collations = array_unique($unique_collations);        
        $collations_charset = [];
        foreach ($unique_collations as $collation) {
            $charset = strstr($collation, '_', true); 
            if ($charset === $this->getTargetCharSet()) {
                continue;
            }
            $collations_charset[$collation] = $charset;            
        }
        
        return $this->generateCollateCharSetMasterLists($collations_charset, $ret);
    }
    
    /**
     * Generate collate charset masterlists
     * @param array $collations_charset
     * @param array $ret
     * @return array
     */
    protected function generateCollateCharSetMasterLists($collations_charset = [], $ret = [])
    {
        $unique_charsets = array_unique(array_values($collations_charset));
        $other_charsets = [];
        $utf8_native = [];
        foreach ($unique_charsets as $value) {
            if (PRIME_MOVER_UNICODE_CHARSET === $value) {
                $utf8_native[] = $value;
            } else {
                $other_charsets[] = $value;
            }
        }
        
        $unique_charsets = array_merge($other_charsets, $utf8_native);
        if (!empty($collations_charset)) {
            $ret['collate_charset_equivalence'] = $collations_charset;
        }
        
        if (!empty($unique_charsets)) {
            $ret['source_charset_masterlists'] = $unique_charsets;
        }
        
        return $ret;
    }
    
    /**
     * Returns TRUE if we need to bailout and skip default charset filtering
     * Otherwise FALSE
     * @param string $string
     * @return boolean
     */
    protected function maybeBailoutFilterDefaultCharset($string = '')
    {
        if (false !== strpos($string, "DEFAULT CHARSET=") || false !== strpos($string, "CHARACTER SET ") || false !== strpos($string, "COLLATE ")) {
            return false;
        } else {
            return true;
        }
    }
    
    /**
     * Maybe filter default charset to target charset
     * @param string $data
     * @return string|mixed
     */
    public function maybeFilterDefaultCharset($data = '')
    {
        if (!$data || $this->getCharSetSame()) {
            return $data;
        }
       
        if ($this->maybeBailoutFilterDefaultCharset($data)) {
            return $data;
        }
        
        $target_charset = $this->getTargetCharSet();      
        foreach ($this->getSourceCollateCharset() as $collate_source => $charset_source) {
            $data = str_replace("DEFAULT CHARSET={$charset_source} COLLATE={$collate_source}", "DEFAULT CHARSET={$target_charset}", $data);
        }       
       
        foreach ($this->getSourceCollateCharset() as $collate_source => $charset_source) {
            $data = str_replace("CHARACTER SET {$charset_source} COLLATE {$collate_source}", "CHARACTER SET {$target_charset}", $data);
        } 
                
        foreach ($this->getSourceCharsetMasterlists() as $charset_source) {
            $data = str_replace("DEFAULT CHARSET={$charset_source}", "DEFAULT CHARSET={$target_charset}", $data); 
        }
        
        foreach ($this->getSourceCharsetMasterlists() as $charset_source) {
            $data = str_replace("CHARACTER SET {$charset_source}", "CHARACTER SET {$target_charset}", $data); 
        }
        
        $collate_only = array_keys($this->getSourceCollateCharset());
        foreach ($collate_only as $collate) {
            $data = str_replace("COLLATE {$collate}", "", $data);
        }             
        
        return $data;
    }   
    
    /**
     * Add lang folder name to package config
     * @param array $config
     * @return array
     */
    public function maybeAddLangFolderNameToPackageConfig($config = [])
    {
        $language_folder = $this->getSystemFunctions()->getLanguageFolder();
        if (!is_string($language_folder) || !is_dir($language_folder)) {
            return $config;
        }
       
        $config['prime_mover_source_site_lang_folder'] = basename($language_folder);        
        return $config;
    }
    
    /**
     * Check if we need to restore language folder during import
     * @param array $ret
     * @return array
     */
    public function maybeSkipLanguagesFolder($ret = [])
    {
        $ret['skipped_languages_folder'] = true;
        $tar_config = $this->getPackageConfig($ret);
        if (!is_array($tar_config) || !is_array($ret)) {
            return $ret;
        } 
        
        if (empty($tar_config['prime_mover_source_site_lang_folder'])) {
            return $ret;
        }
        
        if (empty($ret['unzipped_directory'])) {
            return $ret;
        }
        
        $unzipped_directory = $ret['unzipped_directory'];
        $lang_folder = $tar_config['prime_mover_source_site_lang_folder'];
        
        
        $language_directory	= wp_normalize_path(trailingslashit($unzipped_directory . $lang_folder));
        if (is_dir($language_directory)) {
            $ret['source_lang_dir_path'] = $language_directory;
            $ret['skipped_languages_folder'] = false;
            
        }
        
        return $ret;
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
     * Get system initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     */
    public function getSystemInitialization()
    {
        return $this->getPrimeMover()->getSystemInitialization();
    }

    /**
     * Maybe add charset and collate to package info for internationalization migration support.
     * This process only runs on export when the package is created.
     * @param array $config
     * @param array $ret
     * @param number $blogid_to_export
     * @return string
     */
    public function maybeAddCharSetAndCollateToPackageConfig($config = [], $ret = [], $blogid_to_export = 0)
    {
        $db_charset = $this->getSystemInitialization()->getDbCharSetUsedBySite();
        if (false !== $db_charset) {
            $config['prime_mover_source_site_db_charset'] = $db_charset;
        }
       
        $db_collate = $this->getSystemInitialization()->getDbCollateUsedBySite();
        if (false !== $db_collate) {
            $config['prime_mover_source_site_db_collate'] = $db_collate;
        }
       
        $target_charset = $this->getSystemInitialization()->getTargetCharset($ret);
        if ($target_charset) {
            $config['prime_mover_target_db_charset'] = $target_charset;
        }
        return $config;
    }
    
    /**
     * Get package config
     * @param array $ret
     * @return boolean
     */
    protected function getPackageConfig($ret = [])
    {
        if (!isset($ret['wprime_tar_config_set'])) {
            return false;
        }
   
        return $ret['wprime_tar_config_set'];
    }
              
    /**
     * Use correct database charset when dumping database
     * @param string $charset
     * @return string
     */
    public function dBCharset($charset = '')
    {        
        $dB_charset_used_by_site = $this->getSystemInitialization()->getDbCharSetUsedBySite();
        if ($dB_charset_used_by_site) {
            return $dB_charset_used_by_site;
        }
 
        return $charset;
    }
    
    /**
     * Remove processor hooks when multilingual plugin not activated
     * @param array $ret
     * @param number $blogid_to_import
     */
    public function removeProcessorHooksWhenDependencyNotMeet($ret = [], $blogid_to_import = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, $this->getMultilingualPlugin());
        if (is_array($validation_error)) {
            foreach ($this->getCallBacks() as $callback => $priority) {
                remove_filter('prime_mover_do_process_thirdparty_data', [$this, $callback], $priority, 3);
            }
        }
    }
    
    /**
     * Adjust manager ID in jobs table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter - priority 19
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustManagerIdJobsTable($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, $this->getMultilingualPlugin());
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'icl_translate_job';
        $leftoff_identifier = '3rdparty_manager_id_job_leftoff';
        
        $primary_index = 'job_id';
        $column_strings = 'job_id, manager_id';
        $update_variable = '3rdparty_manager_id_job_log_updated';
        
        $progress_identifier = 'manager ID jobs table';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        $handle_unique_constraint = '';
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, $blogid_to_import, $leftoff_identifier, $primary_index, $column_strings,
            $update_variable, $progress_identifier, $start_time, $last_processor, $handle_unique_constraint);
    }
    
    /**
     * Adjust translator ID in jobs table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter - priority 18
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustTranslatorIdJobsTable($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, $this->getMultilingualPlugin());
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'icl_translate_job';
        $leftoff_identifier = '3rdparty_translator_id_job_leftoff';
        
        $primary_index = 'job_id';
        $column_strings = 'job_id, translator_id';
        $update_variable = '3rdparty_translator_id_job_log_updated';
        
        $progress_identifier = 'translator ID jobs table';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        $handle_unique_constraint = '';
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, $blogid_to_import, $leftoff_identifier, $primary_index, $column_strings,
            $update_variable, $progress_identifier, $start_time, $last_processor, $handle_unique_constraint);
    }
    
    /**
     * Adjust translation status table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter - priority 17
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustTranslationStatusTable($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, $this->getMultilingualPlugin());
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'icl_translation_status';
        $leftoff_identifier = '3rdparty_st_status_leftoff';
        
        $primary_index = 'rid';
        $column_strings = 'rid, translator_id';
        $update_variable = '3rdparty_st_status_log_updated';
        
        $progress_identifier = 'translation status table';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        $handle_unique_constraint = '';
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, $blogid_to_import, $leftoff_identifier, $primary_index, $column_strings,
            $update_variable, $progress_identifier, $start_time, $last_processor, $handle_unique_constraint);
    }
    
    /**
     * Adjust string translation table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter - priority 16
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustStringTranslationTable($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, $this->getMultilingualPlugin());
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'icl_string_translations';
        $leftoff_identifier = '3rdparty_st_leftoff';
        
        $primary_index = 'id';
        $column_strings = 'id, translator_id';
        $update_variable = '3rdparty_st_log_updated';
        
        $progress_identifier = 'string translation table';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        $handle_unique_constraint = '';
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, $blogid_to_import, $leftoff_identifier, $primary_index, $column_strings,
            $update_variable, $progress_identifier, $start_time, $last_processor, $handle_unique_constraint);
    }   
}