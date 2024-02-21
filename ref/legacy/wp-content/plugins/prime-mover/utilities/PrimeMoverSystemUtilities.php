<?php
namespace Codexonics\PrimeMoverFramework\utilities;

/*
 * This file is part of the Codexonics.PrimeMoverFramework package.
 *
 * (c) Codexonics Ltd
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions;
use WP_Hook;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionException;
use wpdb;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover System Utilities
 * Helper functionality for System functions
 *
 */
class PrimeMoverSystemUtilities
{
    private $system_functions;

    const HOOK_NAME = 'prime_mover_do_process_thirdparty_data';
    const THIRDPARTY_HOOKS = 'prime_mover_thirdparty_data_hooks';
    const THIRDPARTY_NETWORKWIDE = 'prime_mover_thirdparty_networkwide_signature';
    
    /**
     *
     * @param PrimeMoverSystemFunctions $system_functions
     */
    public function __construct(PrimeMoverSystemFunctions $system_functions)
    {
        $this->system_functions = $system_functions;
    }
    
    /**
     * Given a blog id, delete all backup files created and clean up the options table.
     * @param number $blog_id
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemUtilities::itCleansBackupDirectoryUponRequests()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemUtilities::itCleansAllBackupDirectoryFilesUponRequests()
     */
    public function cleanBackupDirectoryUponRequest($blog_id = 0, $clean_all = false) 
    {
        if ( ! $this->getSystemFunctions()->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $backups = $this->getSystemFunctions()->getFilesToRestore($blog_id);
        foreach ($backups as $backup) {
            if (empty($backup['filepath'])) {
                continue;
            }
            $filepath = $backup['filepath'];
            $delete_result = $this->getSystemFunctions()->primeMoverDoDelete($filepath);
            if ($delete_result) {
                $this->cleanOptionsRelatedToThisBackup($filepath, $blog_id);
            }
        }
        
        $errorlog_option = $this->getSystemFunctions()->getSystemInitialization()->getErrorLogOptionOfBlog($blog_id);
        delete_option($errorlog_option);
        
        if ($clean_all) {
            $dir_path = $this->getSystemInitialization()->getExportDirectoryPath($blog_id);
            $this->getSystemFunctions()->primeMoverDoDelete($dir_path); 
        }
        do_action('prime_mover_after_allzipfiles_delete', $blog_id);
    }
    
    /**
     * Clean options when a backup is deleted
     * @param string $filepath
     * @param number $blog_id
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemUtilities::itCleansBackupDirectoryUponRequests()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemUtilities::itCleansAllBackupDirectoryFilesUponRequests() 
     */
    public function cleanOptionsRelatedToThisBackup($filepath = '', $blog_id = 0)
    {
        if ( ! $filepath || ! $blog_id ) {
            return;
        }
        if ( ! $this->getSystemFunctions()->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $option_name = $this->getSystemFunctions()->getSystemInitialization()->generateZipDownloadOptionName(sanitize_html_class(basename($filepath)), $blog_id);
        $option_value = $this->getSystemFunctions()->getSiteOption($option_name, false, true, true);
        if ( ! $option_value || ! isset($option_value['hash'])) {
            return;
        }
        
        $hash = $option_value['hash'];
        $this->getSystemFunctions()->deleteSiteOption($hash, true);
        $this->getSystemFunctions()->deleteSiteOption($option_name, true);
        $this->getSystemFunctions()->deleteSiteOption($hash . '_filename', true);
    }
    
    /**
     *
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     * @compatible 5.6
     */
    public function getSystemFunctions()
    {
        return $this->system_functions;
    }
    
    /**
     * Initialized hooks
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemUtilities::itAddsInitHooks()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemUtilities::itChecksIfHooksAreOutdated()
     */
    public function initHooks()
    {
        add_filter('multsite_migration_targetplugin_status', [ $this, 'computeTargetPluginStatus'], 10, 3);
        add_filter('prime_mover_filter_theme_diff', [ $this, 'analyzeThemeDiff' ], 10, 1);
        add_filter('prime_mover_excluded_media_folders', [$this, 'excludeCorePrimeMoverDirectoriesInMediaExportLists'], 10, 2); 
        
        add_filter('prime_mover_filter_target_childtheme_msg', [ $this, 'ifNotUsingChildDoesItExist'], 10, 2);
        add_action('prime_mover_after_generating_download_url', [$this, 'deleteAllTmpFiles'], 100, 5);
        add_filter('prime_mover_is_thirdparty_lastprocessor', [$this, 'maybeTheLastProcessor'], 10, 5);
        
        add_action('wp_loaded', [$this, 'getThirdPartyCallBacks']);
        add_action('activated_plugin', [$this, 'getThirdPartyCallBacks'], 10, 1);
        add_action('deactivated_plugin', [$this, 'getThirdPartyCallBacks'], 10, 1);
    }
    
    /**
     * Exclude core Prime Mover directories in export media file lists
     * @param array $excluded_dir
     * @param number $blogid_to_export
     * @return array
     */
    public function excludeCorePrimeMoverDirectoriesInMediaExportLists($excluded_dir = [], $blogid_to_export = 0)
    {       
        if (!is_array($excluded_dir)) {
            return $excluded_dir;
        }
        
        if (!$blogid_to_export) {
            return $excluded_dir;
        }
        
        $core_directories = $this->getSystemFunctions()->getSystemInitialization()->getPrimeMoverCoreDirectories();
        foreach ($core_directories as $core_directory) {
            if (!in_array($core_directory, $excluded_dir)) {                                
                $excluded_dir[] = untrailingslashit(wp_normalize_path($core_directory));
            }
        }
        
        do_action('prime_mover_log_processed_events', "prime_mover_excluded_media_folders filter OUTPUT:", $blogid_to_export, 'export', __FUNCTION__, $this);
        do_action('prime_mover_log_processed_events', $excluded_dir, $blogid_to_export, 'export', __FUNCTION__, $this);
        
        return $excluded_dir;
    }
    
    /**
     * Checks if last processor
     * @param boolean $last_processor
     * @param mixed $class
     * @param string $function
     * @param array $ret
     * @param number $blogid_to_import
     * @return string|boolean
     */
    public function maybeTheLastProcessor($last_processor = false, $class = null, $function = '', $ret = [], $blogid_to_import = 0)
    {
        if (empty($ret['thirdparty_lastprocessor_signature'])) {
            return $last_processor;
        }
        
        $class_name = 'Non-object oriented';
        if (is_object($class)) {
            $class_name = get_class($class);
            $func_data = [];
            $func_data[] = $class;
            $func_data[] = $function;
            
        } else {
            
            $func_data = $function;
        }
        if ($ret['thirdparty_lastprocessor_signature'] === $this->hashLastProcessorFunc($func_data)) {
            $last_processor = true;
            do_action('prime_mover_log_processed_events', "Last third party processor: $function function, class name: " . $class_name   , $blogid_to_import, 'import', __FUNCTION__, $this);
        }
        
        return $last_processor;
    }
    
    /**
     * Delete tmp files in tmp directory created by PrimeMoverSystemInitialization::wpTempNam()
     * @param string $results
     * @param string $hash
     * @param number $blogid_to_export
     * @param boolean $export_directory_on
     * @param array $ret
     */
    public function deleteAllTmpFiles($results = '', $hash = '', $blogid_to_export = 0, $export_directory_on = false, $ret = [])
    {
        if ( ! empty($ret['copymedia_shell_tmp_list'] ) && $this->getSystemFunctions()->nonCachedFileExists($ret['copymedia_shell_tmp_list']) ) {
            $this->getSystemFunctions()->primeMoverDoDelete($ret['copymedia_shell_tmp_list'], true);
        }      
    }
    
    /**
     * Check if target site is not using child theme, double check that its installed
     * @param string $msg
     * @param array $diff
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemUtilities::itReturnsNoErrorMsgIfChildThemeExist() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemUtilities::itReturnsErrorIfChildThemeDoesNotExist()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemUtilities::itReturnsEmptylIfNotUsingChildTheme() 
     */
    public function ifNotUsingChildDoesItExist($msg = '', array $diff = [])
    {
        if (empty($diff['themes']['child']['source_child'])) {
            return $msg;
        }
        $source_child_theme = $diff['themes']['child']['source_child'];
        $theme_object = wp_get_theme($source_child_theme);
        if (! $theme_object->exists()) {
            $msg = esc_html__('Not installed.', 'prime-mover');
        }
        
        return $msg;
    }
    
    /**
     * Checks if theme exist
     * @param string $source_parent_theme
     * @param string $source_child_theme
     * @param string $source_isUsingChildTheme
     * @return boolean[]
     * @compatible 5.6
     */
    protected function isThemeExist($source_parent_theme = '', $source_child_theme = '', $source_isUsingChildTheme = '')
    {
        $ret = [];
        $exist = false;
        $parent_exist = false;
        $child_exist = false;
        
        $parenttheme_object_target = wp_get_theme($source_parent_theme);
        if ($parenttheme_object_target->exists()) {
            $parent_exist = true;
        }
        
        if ('yes' === $source_isUsingChildTheme) {
            $childtheme_object_target = wp_get_theme($source_child_theme);
            if ($childtheme_object_target->exists()) {
                $child_exist = true;
            }
        }
        
        //Checks
        if ('no' === $source_isUsingChildTheme && $parent_exist) {
            $exist = true;
        }
        
        if ('yes' === $source_isUsingChildTheme && $child_exist && $parent_exist) {
            $exist = true;
        }
        
        $using_child_theme = false;
        if ('yes' === $source_isUsingChildTheme) {
            $using_child_theme = true;
        }
        $ret['exist'] = $exist;
        $ret['parent_exist'] = $parent_exist;
        $ret['child_exist'] = $child_exist;
        $ret['using_child_theme'] = $using_child_theme;
        
        return $ret;
    }
    
    /**
     * analyze Theme Diff
     * @param array $theme_diff
     * @compatible 5.6
     */
    public function analyzeThemeDiff(array $theme_diff)
    {
        $version_diff = [];
        if (! $this->areThemeSetforSourceDiffCheck($theme_diff)) {
            return $theme_diff;
        }
        
        $source_isUsingChildTheme = $theme_diff['using_child_theme']['source'];
        if (! $source_isUsingChildTheme) {
            return $theme_diff;
        }
        
        $source_parent_theme = $theme_diff['parent']['source_parent'];
        $source_parent_version = $theme_diff['parent']['source_version'];
        
        $source_child_theme = $theme_diff['child']['source_child'];
        $source_child_version = $theme_diff['child']['source_version'];
        
        /**
         * Check #1: If source theme does not exist or installed in target site
         */
        $theme_exist_check = $this->isThemeExist($source_parent_theme, $source_child_theme, $source_isUsingChildTheme);
        $theme_diff = $this->updateThemeDiff('theme_exist_check', $theme_diff, $theme_exist_check);
        
        /**
         * Check #2: If source theme exist in target site but using different version.
         */
        $version_diff = $this->isThemeVersionTheSame($source_parent_theme, $source_parent_version, $source_child_theme, $source_child_version, $theme_exist_check, $source_isUsingChildTheme);
        $theme_diff = $this->updateThemeDiff('theme_version_check', $theme_diff, $version_diff);
        
        /**
         * Only show the exact differences,
         * Remove entries that are already the same
         */
        $theme_diff = $this->outputTrueDiffs($theme_diff, $version_diff);
        return $theme_diff;
    }
    
    /**
     * output True diffs
     * @param array $theme_diff
     * @param array $version_diff
     * @return array[]
     * @compatible 5.6
     */
    protected function outputTrueDiffs(array $theme_diff, array $version_diff)
    {
        if (empty($version_diff)) {
            return $theme_diff;
        }
        if (empty($theme_diff)) {
            return $theme_diff;
        }
   
        if (! empty($version_diff['same_parent']) && true === $version_diff['same_parent']) {
            //Parent elements the same, remove from diff
            $theme_diff['parent'] = [];
        }
        
        if (isset($version_diff['using_child_theme']) && isset($version_diff['same_child']) &&
            'yes' === $version_diff['using_child_theme'] && true === $version_diff['same_child']) {
            $theme_diff['child'] = [];
        }
        
        return $theme_diff;
    }

    /**
     * Checks if all theme diff requisites are checked for source
     * @param array $theme_diff
     * @return boolean
     * @compatible 5.6
     */
    protected function areThemeSetforSourceDiffCheck(array $theme_diff)
    {
        if (empty($theme_diff)) {
            return false;
        }
        if (empty($theme_diff['using_child_theme']['source'])) {
            return false;
        }
        
        if (empty($theme_diff['parent']['source_parent'])) {
            return false;
        }
        
        if (empty($theme_diff['parent']['source_version'])) {
            return false;
        }
        
        if (empty($theme_diff['child']['source_child'])) {
            return false;
        }
        
        if (empty($theme_diff['child']['source_version'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Checks if all theme diff requisites are checked for target
     * @param array $theme_diff
     * @return boolean
     * @compatible 5.6
     */
    protected function areThemeSetforTargetDiffCheck(array $theme_diff)
    {
        if (empty($theme_diff)) {
            return false;
        }
        if (empty($theme_diff['using_child_theme']['target'])) {
            return false;
        }
        
        if (empty($theme_diff['parent']['target_parent'])) {
            return false;
        }
        
        if (empty($theme_diff['parent']['target_version'])) {
            return false;
        }
        
        if (empty($theme_diff['child']['target_child'])) {
            return false;
        }
        
        if (empty($theme_diff['child']['target_version'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Theme diff version check
     * @param string $source_parent_theme
     * @param string $source_parent_version
     * @param string $source_child_theme
     * @param string $source_child_version
     * @param array $theme_exist_check
     * @param string $source_isUsingChildTheme
     * @return boolean[]
     * @compatible 5.6
     */
    protected function isThemeVersionTheSame(
        $source_parent_theme = '',
        $source_parent_version = '',
        $source_child_theme = '',
        $source_child_version = '',
        array $theme_exist_check = [],
        $source_isUsingChildTheme = ''
    ) 
    {
        $ret = [];
        $same = false;
        $same_parent_version = false;
        $same_child_version = false;
        $installed_version = '';
        $child_installed_version = '';
        
        $parent_exist = false;
        if (isset($theme_exist_check['parent_exist']) && $theme_exist_check['parent_exist']) {
            $parent_exist = true;
        }
        
        //Target parent version check, target parent SHOULD exist at this point.
        if ($parent_exist) {
            $parenttheme_object_target = wp_get_theme($source_parent_theme);
            $installed_version = $parenttheme_object_target->get('Version');
            if (0 === version_compare($installed_version, $source_parent_version)) {
                $same_parent_version = true;
            }
        }
        
        //Target child version check, check if child exist
        $child_exist = false;
        if (isset($theme_exist_check['child_exist']) && $theme_exist_check['child_exist']) {
            $child_exist = true;
        }
        $child_installed_version = '';
        if ($child_exist) {
            $childtheme_object_target = wp_get_theme($source_child_theme);
            $child_installed_version = $childtheme_object_target->get('Version');
            if (0 === version_compare($child_installed_version, $source_child_version)) {
                $same_child_version = true;
            }
        }
        
        //Checks
        if ('no' === $source_isUsingChildTheme && $same_parent_version) {
            $same = true;
        }
        
        if ('yes' === $source_isUsingChildTheme && $child_exist && $same_child_version && $same_parent_version) {
            $same = true;
        }
        
        $ret['same'] = $same;
        $ret['same_parent'] = $same_parent_version;
        $ret['same_child'] = $same_child_version;
        $ret['target_installed_parent_version'] = $installed_version;
        $ret['target_installed_child_version'] = $child_installed_version;
        $ret['using_child_theme'] = $source_isUsingChildTheme;
        
        return $ret;
    }
    
    /**
     * Update theme diff
     * @param string $mode
     * @param array $theme_diff
     * @param array $input_data
     * @return string
     * @compatible 5.6
     */
    protected function updateThemeDiff($mode = '', array $theme_diff = [], array $input_data = [])
    {
        if ('theme_exist_check' === $mode) {
            if (isset($input_data['parent_exist']) && ! $input_data['parent_exist']) {
                //Source parent theme does not exist in target site, update diff
                $theme_diff['parent']['target_parent'] = esc_html__('Not installed.', 'prime-mover');
                $theme_diff['child']['target_child'] = esc_html__('Not installed.', 'prime-mover');
                
                //Remove version because its not needed
                unset($theme_diff['parent']['target_version']);
                unset($theme_diff['child']['target_version']);
            }
            
            if (isset($input_data['child_exist']) && ! $input_data['child_exist']) {
                
                //Source child theme does not exist in target site, update diff
                $theme_diff['child']['target_child'] = esc_html__('Not installed.', 'prime-mover');
                unset($theme_diff['child']['target_version']);
            }
        }
        
        if ('theme_version_check' === $mode) {
            if (isset($input_data['same']) && true === $input_data['same']) {
                $theme_diff = [];
                return $theme_diff;
            }
            
            if (isset($input_data['same_parent']) && false === $input_data['same_parent'] && ! empty($input_data['target_installed_parent_version'])) {
                //Target parent theme differs from source parent theme, update diff
                $theme_diff['parent']['target_parent'] = $theme_diff['parent']['source_parent'];
                $theme_diff['parent']['target_version'] = $input_data['target_installed_parent_version'];
            }
            if (isset($input_data['using_child_theme']) && isset($input_data['same_child']) &&
                'yes' === $input_data['using_child_theme'] && ! $input_data['same_child'] && ! empty($input_data['target_installed_child_version'])) {
                //Using child theme and it differs in source version, update diff
                $theme_diff['child']['target_child'] = $theme_diff['child']['source_child'];
                $theme_diff['child']['target_version'] = $input_data['target_installed_child_version'];
            }
            if (isset($input_data['using_child_theme']) && 'no' === $input_data['using_child_theme'] &&
                 isset($theme_diff['child']) && isset($theme_diff['using_child_theme']['target'])) {
                //Source is not using child theme but target is. Remove all child diffs.
                unset($theme_diff['child']);
                unset($theme_diff['using_child_theme']['target']);
            }
        }
        
        return $theme_diff;
    }
    
    /**
     * Get System initialization
     * @compatible 5.6
     */
    public function getSystemInitialization()
    {
        return $this->getSystemFunctions()->getSystemInitialization();
    }
        
    /**
     *
     * @param string $source_plugin_name
     * @return string
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemUtilities::itGetsPluginAbsolutePath()
     */
    public function getPluginAbsolutePath($source_plugin_name = '')
    {
        return PRIME_MOVER_PLUGIN_CORE_PATH . $source_plugin_name;
    }
    
    /**
     *
     * @param string $source_plugin_name
     * @return string
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemUtilities::itGetsAbsoluteThemePath() 
     */
    public function getThemeAbsolutePath($source_theme = '')
    {
        return PRIME_MOVER_THEME_CORE_PATH . $source_theme;
    }
    
    /**
     * Compute target plugin status based on different case scenarios
     * Handling case #4, case #5 and case #6
     * @param string $status
     * @param string $source_plugin_name
     * @param string $source_version
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemUtilities::itComputesTargetPluginStatus()
     */
    public function computeTargetPluginStatus($status = '', $source_plugin_name = '', $source_version = '')
    {
        global $wp_filesystem;
        if (! $source_plugin_name || ! $source_version) {
            return $status;
        }
        if (! $wp_filesystem->exists($this->getPluginAbsolutePath($source_plugin_name))) {
            //Case #6, update diff status
            $status = esc_html__('Not installed.', 'prime-mover');
        } else {
            //At this point, plugin is installed on target site but we are not sure of its version
            $version_check = $this->isVersionDifferent($source_plugin_name, $source_version);
            if ($version_check) {
                //Case #5, different version, update diff status
                $status = sprintf(esc_html__('%s', 'prime-mover'), $version_check);
            }
        }
        
        return $status;
    }
    
    /**
     * Returns TRUE if version is different
     * @param string $source_plugin_name
     * @param string $source_version
     * @compatible 5.6
     */
    protected function isVersionDifferent($source_plugin_name = '', $source_version = '')
    {
        $target_plugin_data = get_plugin_data($this->getPluginAbsolutePath($source_plugin_name));
        $target_plugin_version = esc_html__('unknown', 'prime-mover');
        
        if (! empty($target_plugin_data['Version'])) {
            $target_plugin_version = $target_plugin_data['Version'];
        }
        
        if (0 !== version_compare($target_plugin_version, $source_version)) {
            //Differing versions
            return $target_plugin_version;
        } else {
            //Version the same
            $target_plugin_version = '';
        }
        
        return $target_plugin_version;
    }
    
    /**
     * Checks if clean real path
     * @param string $input_data
     * @return boolean
     */
    private function isCleanRealPathHelper($input_data = '')
    {
        $realpath = realpath($input_data);
        $userpath = rtrim($input_data, DIRECTORY_SEPARATOR);
        if ( false === $realpath || $realpath !== $userpath) {
            return false;
        }
        return true;
    }

    /**
     * Check if provided path is clean
     * @param string $input_data
     * @param boolean $make_dir
     * @return boolean
     */
    public function isCleanRealPath($input_data = '', $make_dir = true)
    {
        if ( ! $input_data ) {
            return false;
        }        
        if ($make_dir) {
            /**
             * Make directory if not exist
             */
            if (wp_mkdir_p($input_data)) {
                return $this->isCleanRealPathHelper($input_data);
            }
        } else {
            return $this->isCleanRealPathHelper($input_data);            
        }        
        return false;
    }
    
    /**
     * Get caller plugin using the hooks
     * Inspired by Query Monitor plugin: 
     * https://wordpress.org/plugins/query-monitor/
     * @param array $callback
     * @param number $blogid_to_export
     * @param array $activated_plugins_list
     * @return string
     */
    protected function getCallerPlugin(array $callback, $blogid_to_export = 0, $activated_plugins_list = []) 
    {        
        $plugin_file = '';        
        try {            
            if (is_array($callback['function'])) {
                if (is_object( $callback['function'][0])) {
                    $class = get_class($callback['function'][0]);
                } else {
                    $class = $callback['function'][0];
                }                
                $ref = new ReflectionMethod($class, $callback['function'][1]);
            } else {
                $ref = new ReflectionFunction($callback['function']);
            }           
            $plugin_file = $ref->getFileName();  
            
        } catch (ReflectionException $e) {
            
            do_action('prime_mover_log_processed_events', "Reflection error detected, details:", $blogid_to_export, 'export', __FUNCTION__, $this);
            do_action('prime_mover_log_processed_events', $e->getMessage(), $blogid_to_export, 'export', __FUNCTION__, $this);
            
            return '';            
        }
        
        return $this->computePluginBaseNameFromReflectionFile($plugin_file, $activated_plugins_list);               
    }
    
    /**
     * Compute plugin basename from reflection file
     * @param string $plugin_file
     * @param array $activated_plugins_list
     * @return string
     */
    protected function computePluginBaseNameFromReflectionFile($plugin_file = '', $activated_plugins_list = [])
    {
        if (!$plugin_file) {
            return '';
        }
        
        $plugin_file = wp_normalize_path($plugin_file);
        $plugin_core = wp_normalize_path(trailingslashit(PRIME_MOVER_PLUGIN_CORE_PATH));
        $relative_full = str_replace($plugin_core, '', $plugin_file);
        
        $basename = basename($relative_full);
        $exploded = [];
        if ($basename === $relative_full) {
            $relative_dir = $relative_full;
        } else {
            $relative_dir = dirname($relative_full);
            $exploded = explode("/", dirname($relative_full));
        }
        
        if (!empty($exploded[0])) {
            $relative_dir = untrailingslashit($exploded[0]);
        }
       
        $relative_plugin = wp_normalize_path(trailingslashit(PRIME_MOVER_PLUGIN_CORE_PATH) . $relative_dir);        
        if (isset($activated_plugins_list[$relative_plugin])) {
            return $activated_plugins_list[$relative_plugin];
        }
        
        return '';        
    }
    
    /**
     * Get activated plugins for callback processing
     * @param number $blogid_to_export
     * @param string $processed_plugin
     * @param string $current_action
     * @return @return array
     */
    protected function getActivatedPluginsForCallBackProcessing($blogid_to_export = 0, $processed_plugin = '', $current_action = '')
    {
        $plugin_processor = [];
        $activated_plugins = $this->getSystemFunctions()->getActivatedPlugins($blogid_to_export, []);
        $activated_plugins = $this->maybeHandleBulkDeactivate($activated_plugins);
        
        foreach ($activated_plugins as $plugin) {
            if ($processed_plugin === $plugin && $current_action && 'deactivated_plugin' === $current_action) { 
                continue;
            }
            $basename = basename($plugin);
            if ($basename === $plugin) {
                $plugindir = $plugin;
            } else {
                $plugindir = dirname($plugin);
            }
            $plugindir = wp_normalize_path(trailingslashit(PRIME_MOVER_PLUGIN_CORE_PATH) . $plugindir); 
            $plugin_processor[$plugindir] = $plugin;
        }
        
        return $plugin_processor;
    }
    
    /**
     * Handle bulk deactivate
     * @param array $activated_plugins
     * @return array
     */
    protected function maybeHandleBulkDeactivate($activated_plugins = [])
    {        
        $deactivate_check = $this->getSystemInitialization()->getUserInput('post', $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(), '', '', 0, true, true);
        if (empty($deactivate_check['action']) || empty($deactivate_check['checked'])) {
            return $activated_plugins;
        }
         
        if (!is_array($deactivate_check['checked']) || !is_array($activated_plugins) || 'deactivate-selected' !== $deactivate_check['action']) {
            return $activated_plugins;
        }
        
        if (empty($activated_plugins)) {
            return $activated_plugins;
        }
        
        return array_diff($activated_plugins, $deactivate_check['checked']);
    }
    
    /**
     * Maybe skip if requisites not meet
     * @return boolean
     */
    protected function maybeSkipRequisitesNotMeet()
    {
        if (wp_doing_ajax() || wp_doing_cron()) {
            return true;
        }
        
        if (is_multisite() && is_network_admin()) {
            return true;
        }
        
        if (!$this->getSystemInitialization()->isAdministrator(false)) {
            return true;
        }  
        
        return false;
    }
    
    /**
     * Get third party callbacks on export process
     * @param array $ret
     * @param number $blogid_to_export
     * @return mixed|boolean|NULL|array
     */
    public function getThirdPartyCallBacksOnExport($ret = [], $blogid_to_export = 0)
    {
        $this->getSystemFunctions()->switchToBlog($blogid_to_export);        
        $thirdparty_plugins = $this->getSystemFunctions()->getBlogOption($blogid_to_export, self::THIRDPARTY_HOOKS);
        if (!empty($thirdparty_plugins)) {
            $ret['thirdparty_callback_plugins'] = $thirdparty_plugins;
        }
        
        $this->getSystemFunctions()->restoreCurrentBlog();
        return $ret;
    }
    
    /**
     * Get third party callback processors to be used on main exporter process
     * @param array $ret
     * @param number $blogid_to_export
     * @return array
     */
    public function getThirdPartyCallBacks($plugin = '')
    {
        if ($this->maybeSkipRequisitesNotMeet()) {
            return;
        }
        
        $blogid_to_export = 0;
        if (is_multisite()) {
            $blogid_to_export = get_current_blog_id();
        }        
      
        $this->getSystemFunctions()->switchToBlog($blogid_to_export);
        $current_action = current_action(); 
        $thirdparty_hooks_opt_value = $this->getSystemFunctions()->getBlogOption($blogid_to_export, self::THIRDPARTY_HOOKS);
        if ($this->maybeSkipThirdPartyHooks($thirdparty_hooks_opt_value, $current_action, $plugin, $blogid_to_export)) {
            $this->getSystemFunctions()->restoreCurrentBlog();
            return;
        }
        
        $action = $this->getWpHookObjectCallBack(true); 
        $activated_plugins_list = $this->getActivatedPluginsForCallBackProcessing($blogid_to_export, $plugin, $current_action);          
        $thirdparty_plugins = [];        
        if (false === $action) {
            $this->getSystemFunctions()->restoreCurrentBlog();
            return;
        }
        
        foreach ($action as $callbacks) {            
            foreach ( $callbacks as $callback ) {
                $thirdparty = $this->getCallerPlugin($callback, $blogid_to_export, $activated_plugins_list);
                if (!$thirdparty) {
                    continue;
                }
                
                if (in_array($thirdparty, [PRIME_MOVER_DEFAULT_FREE_BASENAME, PRIME_MOVER_DEFAULT_PRO_BASENAME])) {
                    continue;
                }
               
                if (!$this->pluginFileExists($thirdparty)) {
                    continue;
                }
                
                $thirdparty_plugins[] = $thirdparty;
            }
        }
       
        $this->saveThirdPartyPluginSignatures($thirdparty_plugins, $blogid_to_export);        
        $this->getSystemFunctions()->restoreCurrentBlog();
    }
    
    /**
     * Save third party plugin signatures
     * @param array $thirdparty_plugins
     * @param number $blogid_to_export
     */
    protected function saveThirdPartyPluginSignatures($thirdparty_plugins = [], $blogid_to_export = 0)
    {        
        $this->getSystemFunctions()->updateBlogOption($blogid_to_export, self::THIRDPARTY_HOOKS, $thirdparty_plugins, false);        
        if (is_multisite()) {
            $this->getSystemFunctions()->updateBlogOption($blogid_to_export, self::THIRDPARTY_NETWORKWIDE, $this->hashNetworkPlugins(), false);
        }
    }
    
    /**
     * Hash network activated plugins (multisite only)
     * @return string
     */
    protected function hashNetworkPlugins()
    {
        $active_sitewide_plugins = $this->getSystemFunctions()->getSiteOption('active_sitewide_plugins');
        return sha1(maybe_serialize($active_sitewide_plugins));
    }
    
    /**
     * Returns TRUE if skip third party hooks plugin analysis
     * @param string $thirdparty_hooks_opt_value
     * @param string $current_action
     * @param string $plugin
     * @param number $blogid_to_export
     */
    protected function maybeSkipThirdPartyHooks($thirdparty_hooks_opt_value = '', $current_action = '', $plugin = '', $blogid_to_export = 0)
    {
        $skip = false;       
        if ('wp_loaded' !== $current_action && !$plugin) {
            return true;
        }
        
        if (false !== $thirdparty_hooks_opt_value && 'wp_loaded' === $current_action) {
            $skip = true;
        }
        
        if ($skip && is_multisite() && $this->hashNetworkPlugins() !== $this->getSystemFunctions()->getBlogOption($blogid_to_export, self::THIRDPARTY_NETWORKWIDE)) {
            return false;
        }
        
        $invalid = [];
        if ($skip && is_array($thirdparty_hooks_opt_value) && !empty($thirdparty_hooks_opt_value)) {
            foreach ($thirdparty_hooks_opt_value as $third_party_plugin) {                
                if (false === $this->pluginFileExists($third_party_plugin)) {
                    $invalid[] = $third_party_plugin;                    
                }
            }
        }
        
        if (!empty($invalid)) {
            $this->getSystemFunctions()->deactivatePlugins($invalid);
            return false;
        }
        
        return $skip;
    }
    
    /**
     * Checks if plugin file exists
     * @param string $plugin
     * @return boolean
     */
    protected function pluginFileExists($plugin = '')
    {
        if (validate_file($plugin) ) {
            return false;
        }
        
        if (!$this->getSystemFunctions()->nonCachedFileExists(WP_PLUGIN_DIR . '/' . $plugin)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get WP_Hook object or it's callback
     * @param boolean $return_wphook
     * @return boolean|WP_Hook|array
     */
    protected function getWpHookObjectCallBack($return_wphook = false)
    {
        global $wp_filter;
        if (!isset($wp_filter[self::HOOK_NAME])) {
            return false;
        }
        
        $wphook_object = $wp_filter[self::HOOK_NAME];
        if (!is_object($wphook_object)) {
            return false;
        }
        
        if (!isset($wphook_object->callbacks)) {
            return false;
        }
        
        $callbacks = $wphook_object->callbacks;
        if (!is_array($callbacks)) {
            return false;
        }
        
        if ($return_wphook) {
            return $wphook_object;
        }
        
        return $callbacks;
    }
    
    /**
     * Compute third party last processor callback priority
     * @param array $ret
     * @return array
     */
    public function maybeComputeThirdPartyLastProcessor($ret = [])
    {
        if (!$this->getSystemFunctions()->getSystemAuthorization()->isUserAuthorized() || !is_array($ret)) {
            return $ret;
        }
        
        if (isset($ret['thirdparty_lastprocessor_signature'])) {
            return $ret;
        }
        
        $callbacks = $this->getWpHookObjectCallBack();
        if (false === $callbacks) {
            return $ret;
        }       
        
        $priorities = array_keys($callbacks);
        $last_priority = end($priorities);
        
        $last_callback = end($callbacks[$last_priority]);    
        if (empty($last_callback['function'])) {
            return $ret;
        }
        
        $func_signature = $this->hashLastProcessorFunc($last_callback['function']);
        $ret['thirdparty_lastprocessor_signature'] = $func_signature;
        
        return $ret;
    }
    
    /**
     * Hash last processor func
     * @param mixed $func
     * @return string
     */
    protected function hashLastProcessorFunc($func = null)
    {
        $func_compat = [];
        if (is_array($func) && isset($func[0]) && is_object($func[0]) && isset($func[1])) {
            $func_compat[0] = get_class($func[0]);
            $func_compat[1] = $func[1];
        }
        if (!empty($func_compat)) {
            $func = $func_compat;
        }
        return sha1(maybe_serialize($func));
    }
    
    /**
     * Compute host domain
     * @return mixed
     */
    public function computeHostDomain()
    {
        $network_url = network_site_url();
        return parse_url($network_url, PHP_URL_HOST);
    }
    
    /**
     * Max allowed package adjustment on runtime for privileged users
     * @param wpdb $wpdb
     * @param boolean $db_super_user
     * @param number $string_byte
     * @param number $max_allowed_packet
     * @return number
     */
    public function maxAllowedPacketAdjustOnRunTime(wpdb $wpdb, $db_super_user = false, $string_byte = 0, $max_allowed_packet = 0)
    {
        if (!$this->getSystemFunctions()->getSystemAuthorization()->isUserAuthorized() || !$string_byte || !is_object($wpdb)) {
            return $max_allowed_packet;
        }
        
        $max_allowed_package_target = (0.10) * ($string_byte) + $string_byte;
        $max_allowed_package_target = ceil($max_allowed_package_target);
        if (!$db_super_user) {
            return $max_allowed_package_target;
        }
       
        $wpdb->query(
            $wpdb->prepare(
                "SET GLOBAL max_allowed_packet= %d",
                $max_allowed_package_target
                )
        );
        
        return $max_allowed_package_target;
    }
    
    /**
     * Checks if package is themeless
     * @param array $ret
     * @return boolean
     */
    public function isRestoringThemeLessPackage($ret = [])
    {
        $themeless = 'no';
        if (!empty($ret['imported_package_footprint']['themeless'])) {
            $themeless = $ret['imported_package_footprint']['themeless'];
        }
        
        return ('yes' === $themeless);
    }
    
    /**
     * Generate URL to permalinks page
     * @param number $blog_id
     * @param boolean $query_version
     * @return string
     */
    public function generateUrlToPermalinksPage($blog_id = 0, $query_version = true)
    {
        $this->getSystemFunctions()->switchToBlog($blog_id);
        $permalinks_page = admin_url('options-permalink.php');
        $this->getSystemFunctions()->restoreCurrentBlog($blog_id);        
     
        if ($query_version) {
            return add_query_arg(
                [
                    'prime_mover_force_redirect_to_permalinks' => 'yes',
                    'prime_mover_force_redirect_nonce' => $this->getSystemFunctions()->primeMoverCreateNonce('prime_mover_force_redirect_to_permalinks'),
                    'prime_mover_target_blogid' => $blog_id
                ], $permalinks_page
                );
        } else {
            return $permalinks_page;
        }         
    }
    
    /**
     * Check if source and target site charset is the same
     * Returns TRUE for exact string charset match
     * And if source is utf8/utf8mb3 AND target is utf8mb4 (since it's 100% backward compatible)
     * 
     * @param string $source_charset
     * @param string $target_charset
     * @return boolean
     */
    public function maybeSourceAndTargetCharsetsSame($source_charset = '', $target_charset = '')
    {
        if ($source_charset === $target_charset) {
            return true;
        }
        
        if (in_array($source_charset, $this->getSystemInitialization()->getUtfEncoding()) && in_array($target_charset, $this->getSystemInitialization()->getUtfEncoding())) {
            return true;
        }
        
        return false;        
    }    
    
    /**
     * Checks if enc key is valid
     * @return boolean
     */
    public function isEncKeyValid()
    {
        return (defined('PRIME_MOVER_DB_ENCRYPTION_KEY') && PRIME_MOVER_DB_ENCRYPTION_KEY);
    }
   
    /**
     * Output suggested enc key constant value
     * @param string $key
     * @return string
     */
    public function outputSuggestedEncConstant($key = '')
    {
        if (!$key) {
            $key = wp_generate_password(64, false, false);
        }        
        
        $out = '';
        $out .= PHP_EOL;
        $out .= "if (!defined('PRIME_MOVER_DB_ENCRYPTION_KEY')) {";
        $out .= PHP_EOL;
        $out .= "    define('PRIME_MOVER_DB_ENCRYPTION_KEY', '" . $key . "' );";
        $out .= PHP_EOL;
        $out .= "}";
        $out .= PHP_EOL;
        
        return $out;
    }  
    
    /**
     * Checks if home URL valid
     * @return boolean
     */
    public function isWpHomeUrlValid()
    {
        if (is_multisite()) {
            return true;
        }
        
        if (defined('WP_HOME') && WP_HOME) {
            return true;
        }
        return false;
    }
    
    
    /**
     * Checks if site URL valid
     * @return boolean
     */
    public function isWpSiteUrlValid()
    {
        if (is_multisite()) {
            return true;
        }
        
        if (defined('WP_SITEURL') && WP_SITEURL) {
            return true;
        }
        return false;
    }    
    
    /**
     * Output site URL parameter
     * @return string
     */
    public function outputSiteUrlParameter()
    {
        $site_url = get_site_url();
        
        $out = '';
        $out .= PHP_EOL;
        $out .= "if (!defined('WP_SITEURL')) {";
        $out .= PHP_EOL;
        $out .= "    define( 'WP_SITEURL', '" . $site_url . "' );";
        $out .= PHP_EOL;
        $out .= "}";
        $out .= PHP_EOL;
        
        return $out;
    }
    
    /**
     * Output home URL parameter
     * @return string
     */
    public function outputHomeUrlParameter()
    {
        $home_url = get_home_url();
        
        $out = '';
        $out .= PHP_EOL;
        $out .= "if (!defined('WP_HOME')) {";
        $out .= PHP_EOL;
        $out .= "    define( 'WP_HOME', '" . $home_url . "' );";
        $out .= PHP_EOL;
        $out .= "}";
        $out .= PHP_EOL;
        
        return $out;
    }    
}
