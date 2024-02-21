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

use Codexonics\PrimeMoverFramework\interfaces\PrimeMoverExport;
use Codexonics\PrimeMoverFramework\build\Ifsnop\Mysqldump as IMysqldump;
use Codexonics\PrimeMoverFramework\streams\PrimeMoverStreamFilters;
use Exception;
use Codexonics\PrimeMoverFramework\streams\PrimeMoverIterators;
use Codexonics\PrimeMoverFramework\cli\PrimeMoverCLIArchive;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Export Class
 *
 * The Prime Mover Export Class aims to provide the export facility of this plugin.
 *
 */

class PrimeMoverExporter implements PrimeMoverExport
{
    private $stream_filters;
    private $can_export_plugins_themes = ['complete_export_mode', 'development_package'];
    private $iterators;
    private $cli_archiver;
    private $users;
    
    /**
     * Constructor
     * @param PrimeMoverStreamFilters $stream_entity
     * @param PrimeMoverIterators $iterators
     * @param PrimeMoverCLIArchive $cli_archiver
     * @param PrimeMoverUsers $users
     */
    public function __construct(
        PrimeMoverStreamFilters $stream_entity,
        PrimeMoverIterators $iterators,
        PrimeMoverCLIArchive $cli_archiver,
        PrimeMoverUsers $users
    ) 
    {
        $this->stream_filters = $stream_entity;
        $this->iterators = $iterators;
        $this->cli_archiver = $cli_archiver;
        $this->users = $users;
    }
    
    /**
     * Get users object
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverUsers
     */
    public function getUsersObject()
    {
        return $this->users;
    }
   
    /**
     * Maybe get third party callbacks
     * @param array $ret
     * @param number $blogid_to_export
     * @param number $start_time
     */
    public function maybeGetThirdPartyCallBacks($ret = [], $blogid_to_export = 0, $start_time = 0)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_export_progress', $ret, $blogid_to_export);
        
        /** @var Type $previous_func Previous function*/
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__);
        
        if (isset($ret['error'])) {
            return $ret;
        }
        
        if (!$this->getUsersObject()->maybeExportUsers($ret)) {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $next_func, $current_func);;
        }
        
        $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Getting third party hooks..', 'prime-mover'), 'export' );
       
        $ret = $this->getSystemChecks()->getSystemCheckUtilities()->getSystemUtilities()->getThirdPartyCallBacksOnExport($ret, $blogid_to_export);  
        do_action('prime_mover_after_getting_third_party_callback', $ret, $blogid_to_export);
        
        return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $next_func, $current_func);
    }
        
    /**
     * Generate meta keys to adjust
     * @param array $ret
     * @param number $blogid_to_export
     * @param number $start_time
     * @return void|mixed|NULL|array
     * @audited
     * @mainsite_compatible
     */
    public function generateUserMetaKeysToAdjust($ret = [], $blogid_to_export = 0, $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_export_progress', $ret, $blogid_to_export);
        
        /** @var Type $previous_func Previous function*/
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__);
        
        if (isset($ret['error'])) {
            return $ret;
        }
        if ( ! $this->getUsersObject()->maybeExportUsers($ret)) {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $next_func, $current_func);
        }
        $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Exporting user meta keys..', 'prime-mover'), 'export' );
        $ret['usermeta_keys_export_adjust'] = $this->getUsersObject()->getUserUtilities()->getUserFunctions()->generateUserMetaKeysToAdjustOnExport($ret, $blogid_to_export);   
        do_action('prime_mover_log_processed_events', $ret, $blogid_to_export, 'export', $current_func, $this);
        
        return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $next_func, $current_func);
    }
    
    /**
     * In non-shell mode, add users export files to archive
     * @param array $ret
     * @param number $blogid_to_export
     * @param number $start_time
     * @return void|mixed|NULL|array
     * @mainsite_compatible
     */
    public function maybeAddUsersExportFileToArchive($ret = [], $blogid_to_export = 0, $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_export_progress', $ret, $blogid_to_export);
        
        /** @var Type $previous_func previous function */
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__);
        
        if (isset($ret['error'])) {
            return $ret;
        }
        if ( ! $this->getUsersObject()->maybeExportUsers($ret)) {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $next_func, $current_func);
        }        
       
        $ret = $this->getUsersObject()->addUserJsonToArchiveNonShellMode($ret, $start_time, $blogid_to_export);
        do_action('prime_mover_log_processed_events', $ret, $blogid_to_export, 'export', $current_func, $this);        
        
        if (!isset($ret['tar_add_file_offset'])) {            
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $next_func, $current_func);            
        } else {            
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $current_func, $previous_func);
        }  
    }
    
    /**
     * Export users
     * @param array $ret
     * @param number $blogid_to_export
     * @param number $start_time
     * @return void|mixed|NULL|array
     * @mainsite_compatible
     */
    public function exportUsers($ret = [], $blogid_to_export = 0, $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_export_progress', $ret, $blogid_to_export);
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__);
        
        if (isset($ret['error'])) {
            return $ret;
        }
        if ( ! $this->getUsersObject()->maybeExportUsers($ret)) {
            $ret['include_users'] = false;
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $next_func, $current_func);
        }
        $ret['include_users'] = true;
        $offset = 0;
        if (isset($ret['users_export_query_offset'])) {
            $offset = (int)$ret['users_export_query_offset'];
        }
        
        $ret = $this->getUsersObject()->exportSiteUsers($offset, $blogid_to_export, $ret, $start_time);        
        if ( ! isset($ret['users_exported'])) {
                     
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $next_func, $current_func);
            
        } else {
          
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $current_func, $previous_func);
        }       
    }
    
    /**
     * Get CLI archiver object
     * @return \Codexonics\PrimeMoverFramework\cli\PrimeMoverCLIArchive
     */
    public function getCliArchiver()
    {
        return $this->cli_archiver;
    }

    /**
     * Get iterators
     * @return \Codexonics\PrimeMoverFramework\streams\PrimeMoverIterators
     */
    public function getIterators()
    {
        return $this->iterators;
    }
    
    /**
     * Get export modes that includes plugins/themes
     * @return string[]
     * 
     */
    public function getCanExportPluginsThemes()
    {
        return $this->can_export_plugins_themes;
    }
    
    /**
     * Get stream filters
     * @return \Codexonics\PrimeMoverFramework\streams\PrimeMoverStreamFilters
     * 
     */
    public function getStreamFilters()
    {
        return $this->stream_filters;
    }
    
    /**
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     * @compatible 5.6
     * 
     */
    public function getSystemFunctions()
    {
        return $this->getCliArchiver()->getSystemChecks()->getSystemFunctions();
    }
    
    /**
     * Get System Initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     * @compatible 5.6
     * 
     */
    public function getSystemInitialization()
    {
        return $this->getCliArchiver()->getSystemChecks()->getSystemInitialization();
    }
 
    /**
     * Get System checks
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemChecks
     * @compatible 5.6
     * 
     */
    public function getSystemChecks()
    {
        return $this->getCliArchiver()->getSystemChecks();
    }
    
    /**
     * Exporter hooks
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itAddsExporterHooks()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itChecksIfHooksAreOutdated()
     * 
     */
    public function exporterHooks()
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }        
        foreach ($this->getSystemInitialization()->getPrimeMoverExportMethods() as $export_method) {            
            add_filter("prime_mover_export_{$export_method}", [$this, $export_method], 10, 3);
        }  
        
        add_filter('prime_mover_save_return_export_progress', [$this, 'saveExportProgressData'], 10, 4);
        add_filter('prime_mover_get_export_progress', [$this, 'getExportProgressData'], 10, 2);
    }
    
    /**
     * Maybe skip plugins and themes export
     * Returns TRUE if we need to skip plugins/themes otherwise FALSE
     * @param array $ret
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itExportsPluginsAndThemes()
     */
    public function maybeSkipPluginsThemesExport($ret = [])
    {
        $export_mode = $ret['multisite_export_options'];
        $allowed = $this->getCanExportPluginsThemes();
        
        if ( ! in_array($export_mode, $allowed, true)) {
            return true;            
        }
        if (! isset($ret['export_system_footprint'])) {
            return true;
        }       
        return false;
    }
    
    /**
     * @audited
     * @param array $ret
     * @param number $blogid_to_export
     * @param number $start_time
     * @return []
     * @mainsite_compatible
     */
    public function generateThemesFilesList($ret = [], $blogid_to_export = 0, $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_export_progress', $ret, $blogid_to_export);
        if (isset($ret['error'])) {
            return $ret;
        }
        $process_methods = [];
        list($process_methods['current'], $process_methods['previous'],  $process_methods['next']) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__);
        
        if ($this->maybeSkipPluginsThemesExport($ret)) {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['next'], $process_methods['current']);
        }
        
        $original_count = 0;
        if (isset($ret['original_themes_count'])) {
            $original_count = $ret['original_themes_count'];
        }
        if (isset($ret['themes_to_list'])) {
            $themes_to_export = $ret['themes_to_list'];
        } else {
            $themes_to_export = $this->getThemesToExport($ret);
            $original_count = count($themes_to_export);
            $ret['original_themes_count'] = $original_count;
        }
        $processed = 0;
        if (empty($themes_to_export)) {
            do_action('prime_mover_log_processed_events', 'Active themes not found in theme directory - proceed to support themeless export.', $blogid_to_export, 'export', $process_methods['current'], $this);
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['next'], $process_methods['current']);
        }
        foreach ($themes_to_export as $k => $path_to_copy) {
            if (1 === $processed) {
                break;
            }            
            $ongoing = count($themes_to_export);
            $done = $original_count - $ongoing;
            $percent = floor(($done/$original_count) * 100) . '%';
            
            $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('List theme files: %s done', 'prime-mover'), $percent), 'export');
            $ret = $this->getIterators()->generateFilesListGivenDir($path_to_copy, $ret);
            if (!empty($ret['copymedia_shell_tmp_list'] )) {
                $processed++;
                unset($themes_to_export[$k]);
            }
        }
        $ret['themes_to_list'] = $themes_to_export;
        $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $process_methods['current'], 'export', $blogid_to_export);
        if (empty($ret['themes_to_list'])) {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['next'], $process_methods['current']);
        } else {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['current'], $process_methods['previous']);
        }        
    }
    
    /**
     * Get themes to export
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itGetsThemesToExport()
     * @param array $ret
     * @param boolean $ret_format
     * @return array|string[]
     */
    public function getThemesToExport($ret = [], $ret_format = true)
    {
        $themes = [];
        $footprint = $ret;
        if ($ret_format && !empty($ret['export_system_footprint'])) {
            $footprint = $ret['export_system_footprint'];
        } 
        
        if (empty($footprint['stylesheet']) || empty($footprint['template'])) {
            return $themes;
        }
        if (!is_array($footprint['stylesheet'])) {
            return $themes;
        }
        if (!is_array($footprint['template'])) {
            return $themes;
        }
        
        $stylesheet = $footprint['stylesheet'];          
        $stylesheet_name = key($stylesheet);
        
        $stylesheet_path = $this->getSystemFunctions()->getThemeFullPath($stylesheet_name);
        if (! $stylesheet_path) {
            return $themes;
        }
        $themes[] = wp_normalize_path($stylesheet_path);       
        $using_child_theme = 'no';
        if (isset($footprint['using_child_theme'])) {
            $using_child_theme = $footprint['using_child_theme'];
        }
        $template_path = '';
        if ('yes' === $using_child_theme) {
            $template = $footprint['template'];
            $template_name = key($template);
            $template_path = $this->getSystemFunctions()->getThemeFullPath($template_name);            
        }
        if ($template_path) {
            $themes[] = wp_normalize_path($template_path); 
        }
        return $themes;        
    }
    
    /**
     * Maybe export themes
     * @param array $ret
     * @param number $blogid_to_export
     * @param number $start_time
     * @return void|mixed|NULL|array|array|string|mixed|NULL
     * @mainsite_compatible
     */
    public function maybeExportThemes($ret = [], $blogid_to_export = 0, $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_export_progress', $ret, $blogid_to_export);
        if (isset($ret['error'])) {
            return $ret;
        }        
        $process_methods = [];        
        list($process_methods['current'], $process_methods['previous'],  $process_methods['next']) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__);
        
        if ($this->maybeSkipPluginsThemesExport($ret)) {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['next'], $process_methods['current']);
        }        
               
        $zippath = $ret['target_zip_path'];        
        $copying_media_started = false;
        $counted = 0;
        if (isset($ret['total_media_files'])) {
            $counted = $ret['total_media_files'];
        }
        if (isset($ret['processed_file_count'])) {
            $copying_media_started = true;
            $counted = $ret['processed_file_count'];
        }
        if (empty($ret['copymedia_shell_tmp_list'])) {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['next'], $process_methods['current']);            
        }
        
        $mode = 'exporting_theme';
        $archive_alias = apply_filters('prime_mover_filter_basezip_folder', basename(PRIME_MOVER_THEME_CORE_PATH), $ret, $mode, PRIME_MOVER_THEME_CORE_PATH, false);
        $tar_archiving_params = [];
        $tar_archiving_params[] = $archive_alias;
        $tar_archiving_params[] = [];
        $tar_archiving_params[] = $mode;
        
        $resume_positions = [];
        $counted = 0;
        $bytes_written = 0;
        
        if (!empty($ret['tar_add_dir_offsets'])) {
            $copying_media_started = true;
            $resume_positions = $ret['tar_add_dir_offsets'];
            unset($ret['tar_add_dir_offsets']);
        }
        
        if (!empty($resume_positions['files_archived'])) {
            $counted = $resume_positions['files_archived'];
        }
        
        if (!empty($resume_positions['bytes_written'])) {
            $bytes_written = $resume_positions['bytes_written'];
        }
        
        $encryption_key = '';
        if ($ret['enable_db_encryption']) {
            $encryption_key = $this->getSystemInitialization()->getDbEncryptionKey();
        }
        
        $this->doArchivingFilesProgress($ret, $counted, $copying_media_started, 'theme', $bytes_written);
        $ret = apply_filters('prime_mover_add_directory_to_tar_archive', $ret, $zippath, $ret['copymedia_shell_tmp_list'], PRIME_MOVER_THEME_CORE_PATH, $start_time, 
            $resume_positions, false, $blogid_to_export, $tar_archiving_params, 'ab', $encryption_key);
        if (empty($ret['tar_add_dir_offsets'])) {
            $ret = $this->cleanUpMediaTmpList($ret);
            $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $process_methods['current'], 'export', $blogid_to_export);
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['next'], $process_methods['current']);
            
        } else {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['current'], $process_methods['previous']);
        }                
    }
    
    /**
     * Generate plugin file list
     * @param array $ret
     * @param number $blogid_to_export
     * @param number $start_time
     * @return void|mixed|NULL|array
     * @mainsite_compatible
     */
    public function generatePluginFilesList($ret = [], $blogid_to_export = 0, $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_export_progress', $ret, $blogid_to_export);
        if (isset($ret['error'])) {
            return $ret;
        }
        $process_methods = [];        
        list($process_methods['current'], $process_methods['previous'],  $process_methods['next']) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__);
        
        if ($this->maybeSkipPluginsThemesExport($ret)) {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['next'], $process_methods['current']);
        }
        
        $original_count = 0;
        if (isset($ret['original_plugins_count'])) {
            $original_count = $ret['original_plugins_count'];
        }
        if (isset($ret['plugins_to_list'])) {
            $plugins_to_export = $ret['plugins_to_list'];
        } else {            
            $plugins_to_export = $this->getPluginsToExport($ret);
            $original_count = count($plugins_to_export);
            $ret['original_plugins_count'] = $original_count;
        }      
        $processed = 0;
        foreach ($plugins_to_export as $k => $plugin) {             
            if (1 === $processed) {
                break;
            }
            $path_to_copy = $this->getSystemFunctions()->getPluginFullPath($plugin, true);
            if (!$path_to_copy) {
                continue;
            }            
            $ongoing = count($plugins_to_export);
            $done = $original_count - $ongoing;
            $percent = floor(($done/$original_count) * 100) . '%';            
            
            $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('List plugin files: %s done', 'prime-mover'), $percent), 'export');
            $ret = $this->getIterators()->generateFilesListGivenDir($path_to_copy, $ret); 
            if (!empty($ret['copymedia_shell_tmp_list'] )) {
                $processed++;
                unset($plugins_to_export[$k]);
            }            
        }        
        $ret['plugins_to_list'] = $plugins_to_export;
        $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $process_methods['current'], 'export', $blogid_to_export);       
        if (empty($ret['plugins_to_list'])) {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['next'], $process_methods['current']);            
        } else {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['current'], $process_methods['previous']);
        }         
    }
    
    /**
     * Optionally export plugins and themes of this site
     * @param array $ret
     * @param number $blogid_to_export
     * @return void|
     * @compatible 5.6
     * @@mainsite_compatible
     */
    public function optionallyExportPluginsThemes($ret = [], $blogid_to_export = 0, $start_time = 0)
    {       
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }       
        $ret = apply_filters('prime_mover_get_export_progress', $ret, $blogid_to_export);
        if (isset($ret['error'])) {
            return $ret;
        }
        $this->getSystemInitialization()->setSlowProcess();    
        $process_methods = [];
        
        list($process_methods['current'], $process_methods['previous'],  $process_methods['next']) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__);
        
        if ($this->maybeSkipPluginsThemesExport($ret)) {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['next'], $process_methods['current']);
        } 
        
        $zippath = $ret['target_zip_path'];          
        $copying_media_started = false;
        $counted = 0;
        if (isset($ret['total_media_files'])) {
            $counted = $ret['total_media_files'];
        }
        if (isset($ret['processed_file_count'])) {
            $copying_media_started = true;
            $counted = $ret['processed_file_count'];
        }      
        
        if (!empty($ret['copymedia_shell_tmp_list'])) {
            $mode = 'exporting_plugins';
            $archive_alias = apply_filters('prime_mover_filter_basezip_folder', basename(PRIME_MOVER_PLUGIN_CORE_PATH), $ret, $mode, PRIME_MOVER_PLUGIN_CORE_PATH, false);
            $tar_archiving_params = [];
            $tar_archiving_params[] = $archive_alias;
            $tar_archiving_params[] = [];
            $tar_archiving_params[] = $mode;
            
            $resume_positions = [];
            $counted = 0;
            $bytes_written = 0;
            if (!empty($ret['tar_add_dir_offsets'])) {
                $copying_media_started = true;
                $resume_positions = $ret['tar_add_dir_offsets'];
                unset($ret['tar_add_dir_offsets']);
            }
            if (!empty($resume_positions['files_archived'])) {
                $counted = $resume_positions['files_archived'];
            }
            if (!empty($resume_positions['bytes_written'])) {
                $bytes_written = $resume_positions['bytes_written'];
            }
            $this->doArchivingFilesProgress($ret, $counted, $copying_media_started, 'plugin', $bytes_written);            
            $encryption_key = '';
            if ($ret['enable_db_encryption']) {
                $encryption_key = $this->getSystemInitialization()->getDbEncryptionKey();
            }
            
            $ret = apply_filters('prime_mover_add_directory_to_tar_archive', $ret, $zippath, $ret['copymedia_shell_tmp_list'], PRIME_MOVER_PLUGIN_CORE_PATH, 
                $start_time, $resume_positions, false, $blogid_to_export, $tar_archiving_params, 'ab', $encryption_key);
            if (empty($ret['tar_add_dir_offsets'])) {
                $ret = $this->cleanUpMediaTmpList($ret);
                $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $process_methods['current'], 'export', $blogid_to_export);                
                return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['next'], $process_methods['current']);
                
            } else {
                return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['current'], $process_methods['previous']);
            }           
        } else {
            $localname = trailingslashit(basename($ret['temp_folder_path'])) . basename(PRIME_MOVER_PLUGIN_CORE_PATH);
            $ret = apply_filters('prime_mover_add_file_to_tar_archive', $ret, $zippath, 'ab', PRIME_MOVER_PLUGIN_CORE_PATH, $localname, 0, 0, $blogid_to_export, false, false); 
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['next'], $process_methods['current']);
        }
    }
 
    /**
     * Do archiving files export progress
     * @param array $ret
     * @param number $counted
     * @param boolean $copying_media_started
     * @param string $mode
     * @param number $bytes_written
     */
    protected function doArchivingFilesProgress($ret = [], $counted = 0, $copying_media_started = false, $mode = 'media', $bytes_written = 0)
    {
        $percent = "0%";
        if ($copying_media_started && isset($ret['total_media_files'])) {
            $percent = floor(($counted/ $ret['total_media_files']) * 100) . '%';
        }
        $readable = $this->getSystemFunctions()->humanFileSize($bytes_written, 1);
        $text_files = sprintf(esc_html__('%s file', 'prime-mover'), $mode);
        if (isset($counted) && $counted > 1) {
            $text_files = sprintf(esc_html__('%s files', 'prime-mover'), $mode);
        }
        if ($copying_media_started) {
            $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('%s bytes %s archived, %s done.', 'prime-mover'), $readable, $text_files, $percent), 'export' );
        } else {
            $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Archiving %s, starting.', 'prime-mover'), $mode), 'export' );
        }
    }
    /**
     * Get plugins to export
     * @param array $ret
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itGetsPluginsToExport() 
     */
    public function getPluginsToExport($ret = [])
    {
        return apply_filters('prime_mover_plugins_to_export', $ret);
    }
    
    /**
     * Get System Authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     * @compatible 5.6
     * 
     */
    public function getSystemAuthorization()
    {
        return $this->getCliArchiver()->getSystemChecks()->getSystemAuthorization();
    }
 
    /**
     * Get progress handlers
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverProgressHandlers
     * @compatible 5.6
     * 
     */
    public function getProgressHandlers() 
    {
        return $this->getCliArchiver()->getProgressHandlers();
    }
    
    /**
     * Create tempfolder for this site export
     * @param number $blogid_to_export
     * {@inheritDoc}
     * @see PrimeMoverExport::createTempfolderForThisSiteExport()
     * @compatible 5.6
     * @mainsite_compatible
     * 
     */
    public function createTempfolderForThisSiteExport($ret = [], $blogid_to_export = 0, $start_time = 0)
    {        
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret['export_start_time'] = $start_time;
        $ret = apply_filters('prime_mover_inject_db_parameters', $ret, 'export');
        $this->getSystemInitialization()->setSlowProcess();        
        global $wp_filesystem;
        $blogid_to_export = (int) $blogid_to_export;
        if ($blogid_to_export < 1) {
            $ret['error'] = esc_html__('Blog ID is not defined', 'prime-mover');
            return $ret;
        }
        $this->getSystemInitialization()->setExportBlogID( $blogid_to_export );
        $ret['original_blogid'] = $blogid_to_export;
        $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Creating temp folder', 'prime-mover'), 'export' );
        
        $main_site_export_folder = $this->getSystemInitialization()->getMultisiteExportFolderPath() . $blogid_to_export . DIRECTORY_SEPARATOR;
        if (false === $wp_filesystem->exists($main_site_export_folder)) {
            wp_mkdir_p($main_site_export_folder);
        }
        
        if (false === $wp_filesystem->exists($main_site_export_folder)) {
            $ret['error'] = esc_html__('Unable to create site export folders. Check permission.', 'prime-mover');
            return $ret;
        }
        
        $encrypt = $this->maybeEncryptDatabaseDump($ret);
        $ret['enable_db_encryption'] = $encrypt;
        
        $blogid_to_process = apply_filters('prime_mover_filter_blogid_to_export', $blogid_to_export, $ret);
        $folder_name = $this->multisiteCreateFoldername($blogid_to_process, $blogid_to_export);        
        
        $folder_path_export = $main_site_export_folder. $folder_name;
        if (wp_mkdir_p($folder_path_export)) {
            $ret['temp_folder_path'] = $folder_path_export.DIRECTORY_SEPARATOR;
            $this->getSystemInitialization()->setTemporaryExportPackagePath($ret['temp_folder_path']);
        }
        
        $tarfile = "$folder_name.wprime";              
        $tarpath = $main_site_export_folder . $tarfile;        
        $ret = apply_filters('prime_mover_add_file_to_tar_archive', $ret, $tarpath, 'wb', $ret['temp_folder_path'], basename($ret['temp_folder_path']), 0, 0, $blogid_to_export, false, false);            

        if (empty($ret['error'])) {
            $ret['target_zip_path'] = $tarpath;            
        }
        
        if (empty($ret['target_zip_path'])) {
            $ret['error'] = esc_html__('Unable to create temporary WPRIME archive. Check permission.', 'prime-mover');
            return $ret;
        }
        
        if (false === $wp_filesystem->exists($folder_path_export)) {
            $ret['error'] = esc_html__('Unable to create export folder for WPRIME file. Check permission.', 'prime-mover');
            return $ret;
        }
        
        /**
         * @var Type $previous_func previous func
         */        
        $ret = apply_filters('prime_mover_after_creating_tar_archive', $ret, $blogid_to_export);
        
        list($current_func, $previous_func,  $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__);        
        do_action('prime_mover_log_processed_events', $ret, $blogid_to_export, 'export', $current_func, $this, true);  
        $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'export', $blogid_to_export);        
        
        return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $next_func, $current_func);
    }
        
    /**
     * Create unique folder name
     * {@inheritDoc}
     * @see \Codexonics\PrimeMoverFramework\interfaces\PrimeMoverExport::multisiteCreateFoldername()
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itCreatesFolderName() 
     * 
     */
    public function multisiteCreateFoldername($blogid_to_export = 0, $original_id = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $blog_name = $this->getSystemFunctions()->getBlogOption($original_id, 'blogname' );
        $sanitized_site_name = mb_strimwidth( sanitize_key( $blog_name ), 0, 8, '');        
        $file_name = $sanitized_site_name . wp_generate_password( 15, false, false ) . 'blogid_' . $blogid_to_export;

        return sanitize_file_name($file_name);
    }
    
    /**
     * Handle random db prefix change
     * @param array $ret
     * @return boolean
     * @mainsitesupport_affected
     */
    private function handleRandomdBPrefix($ret = [], $blogid_to_export = 0)
    {
        $randomize_db_prefix = false;
        $export_target_id = 1;
        $is_target_set = false;
        if ( ! empty($ret['prime_mover_export_targetid']) ) {
            $export_target_id = (int)$ret['prime_mover_export_targetid'];
            $is_target_set = true;
        }
        if ($export_target_id > 0 && $is_target_set) {
            $randomize_db_prefix = true;
        }
        
        $this->getSystemInitialization()->setRandomizeDbPrefix($randomize_db_prefix);
        $random_string = wp_generate_password( 7, false, false );
        $updated_prefix = $random_string . '_';
        
        $original_prefix = $updated_prefix;
        $updated_prefix = apply_filters('prime_mover_computed_updated_prefix', $updated_prefix, $blogid_to_export, $original_prefix);        
        $updated_prefix = strtolower($updated_prefix);
        
        $this->getSystemInitialization()->setRandomDbPrefix($updated_prefix);        
        $ret['mayberandomizedbprefix'] = $randomize_db_prefix;
        $ret['randomizedbprefixstring'] = $updated_prefix;
        
        return apply_filters('prime_mover_inject_thirdparty_app_prefix', $ret, $blogid_to_export, $original_prefix , $updated_prefix, $randomize_db_prefix);
    }
    
    /**
     * Dump database for export
     * {@inheritDoc}
     * @see PrimeMoverExport::dumpDbForExport()
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itDoesNotDumpWhenNotAuthorized()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itReturnsErrorWhenExporterDetectsError()
     * @audited
     * @mainsite_compatible
     * 
     */
    public function dumpDbForExport($ret = [], $blogid_to_export = 0, $start_time = 0)
    {        
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }        
        $ret = apply_filters('prime_mover_get_export_progress', $ret, $blogid_to_export);
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__);
        if (isset($ret['error'])) {
            return $ret;
        }
        
        $dump_in_progress = $this->maybeDumpInProgress($ret);        
        $this->reportDbDumpProgress($ret);           
        $use_mysqldump_php = true;  
        
        if ( ! $dump_in_progress ) {
            $ret = $this->handleRandomdBPrefix($ret, $blogid_to_export);
        }
        
        $randomize_db_prefix = $ret['mayberandomizedbprefix'];
        $encrypt = $ret['enable_db_encryption'];
        
        $target_path = $this->getTargetDumpPath($ret, apply_filters('prime_mover_filter_blogid_to_export', $blogid_to_export, $ret));
        $clean_tables = apply_filters('prime_mover_tables_to_export', $this->getTablesToExport($blogid_to_export), $blogid_to_export, $ret);
        
        if (empty($clean_tables)) {
            $ret['error'] = esc_html__('Unable to dump database, please check that your database is not empty or these tables exists.', 'prime-mover');
            return $ret;
        }        
        if ($encrypt) {
            $this->getSystemInitialization()->setEncryptExportData(true);
            do_action('prime_mover_encrypted_db_command_generated', $ret, $blogid_to_export);
        }
        $filter_export_data = $this->maybeFilterExportDbData($encrypt, $randomize_db_prefix);        
        if ( ! $dump_in_progress ) {
            do_action('prime_mover_before_db_dump_export', $blogid_to_export, $ret);
        }        
        
        $dump_result = $this->getDbDumpResult($use_mysqldump_php, $blogid_to_export, $current_func, $clean_tables, $filter_export_data, $target_path, $ret, $start_time);   
        if (!empty($dump_result['error'])) {
            $ret['error'] = $dump_result['error'];
            return $ret; 
        }
        if ( ! empty($dump_result['php_db_dump_index_to_resume']) || ! empty($dump_result['shell_db_dump_index_to_resume']) ) {
            $ret = $dump_result;
            $ret['db_dump_in_progress'] = true;            
           
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $current_func, $previous_func);            
        }     
        $dump_exist = false;
        if ($this->getSystemFunctions()->nonCachedFileExists($target_path, true)) {
            $dump_exist = true;
        }
        if ( ! $dump_exist && ! $use_mysqldump_php) {
            $ret['force_dbdump_in_php'] = true;
            if (isset($ret['db_dump_in_progress'])) {
                unset($ret['db_dump_in_progress']);
            }
            do_action('prime_mover_log_processed_events', "Dump failed on first try, retrying on MySQLDump PHP method", $blogid_to_export, 'export', $current_func, $this);
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $current_func, $previous_func); 
        }
        if (! $dump_exist && $use_mysqldump_php) {
            $ret['error'] = esc_html__('Runtime error, no MySQL database file being dumped.', 'prime-mover');
            return $ret;            
        }        
        do_action('prime_mover_after_db_dump_export', $blogid_to_export, $ret);
        if ( ! empty($dump_result['error']) ) {
            $ret['error'] = $dump_result['error'];
            return $ret;
        }        
        $ret = $this->doAfterDbProcessingRet($ret, $clean_tables, $blogid_to_export, $current_func, $target_path);        
        return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $next_func, $current_func);
    }
    
    /**
     * Finalize returng array after db processing
     * @param array $ret
     * @param array $clean_tables
     * @param number $blogid_to_export
     * @param string $current_func
     * @param string $target_path
     * @return array|number
     * @mainsitesupport_affected
     */
    protected function doAfterDbProcessingRet($ret = [], $clean_tables = [], $blogid_to_export = 0, $current_func = '', $target_path = '')
    {
        $ret['completed_target_dump_path'] = $target_path; 
        $ret = apply_filters('prime_mover_filter_ret_after_db_dump', $ret, $clean_tables, $blogid_to_export);
        
        do_action('prime_mover_log_processed_events', $ret, $blogid_to_export, 'export', $current_func, $this, true);
        $this->getSystemInitialization()->testRequestTerminateTimeout();
        
        $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'export', $blogid_to_export);
        $this->getCliArchiver()->writeMasterTmpLog($target_path, null, $ret, 'ab', true);
        
        return $ret;
    }
    
    /**
     * Get db dump result
     * @param boolean $use_mysqldump_php
     * @param number $blogid_to_export
     * @param string $current_func
     * @param array $clean_tables
     * @param boolean $filter_export_data
     * @param string $target_path
     * @param array $ret
     * @param number $start_time
     * @return string[]|number|NULL[]|boolean[]|boolean
     */
    protected function getDbDumpResult($use_mysqldump_php = true, $blogid_to_export = 0, $current_func = '', 
        $clean_tables = [], $filter_export_data = false, $target_path = '', $ret = [], $start_time = 0)
    {
        if ($use_mysqldump_php) {            
            $ret = apply_filters('prime_mover_before_mysqldump_php', $ret, $clean_tables);
            do_action('prime_mover_log_processed_events', 'Starting MySQLdump using PHP', $blogid_to_export, 'export', $current_func, $this);
            
            $this->getSystemFunctions()->temporarilyIncreaseMemoryLimits();
            $dump_result = $this->executeDumpUsingPHP($filter_export_data, $target_path, $clean_tables, $ret, $start_time);
            
        } else {
            do_action('prime_mover_log_processed_events', 'Starting MySQLdump using native shell command.', $blogid_to_export, 'export', $current_func, $this);
            $this->getSystemFunctions()->temporarilyIncreaseMemoryLimits();
            $dump_result = $this->doMySqlDumpUsingShell($clean_tables, $filter_export_data, $target_path, $blogid_to_export, $ret, $start_time);
        }
        
        return $dump_result;
    }
    
    /**
     * Maybe filter export db data
     * @param boolean $encrypt
     * @param boolean $randomize_db_prefix
     * @return boolean
     */
    protected function maybeFilterExportDbData($encrypt = false, $randomize_db_prefix= false)
    {
        $filter_export_data = false;
        if ($encrypt || $randomize_db_prefix) {
            $filter_export_data = true;
        }
        
        return $filter_export_data;
    }
    
    /**
     * Report db dump progress
     * @param array $ret
     */
    protected function reportDbDumpProgress($ret = [])
    {
        if ( ! empty($ret['dump_percent_progress']) ) {
            $this->getProgressHandlers()->updateTrackerProgress( sprintf( esc_html__('Dumping database %s done.', 'prime-mover'), $ret['dump_percent_progress']), 'export' );
        } else {
            $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Dumping database', 'prime-mover'), 'export' );
        }
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itChecksWhetherToUseMySQLDumpPHP() 
     * Maybe use MySQL Dump PHP if shell isn't supported
     * @param array $ret
     * @return boolean
     * @deprecated
     */
    protected function maybeUseMySQLDumpPhp($ret = [])
    {
        $use_mysqldump_php = true;
        if ( ! empty($ret['force_dbdump_in_php'] ) ) {
            return $use_mysqldump_php;
        }
        
        $shell_support = $this->getCliArchiver()->maybeArchiveMediaByShell();       
        $mysqldump_path = $this->getSystemChecks()->getMySqlDumpPath();
       
        if ($mysqldump_path && is_array($shell_support)) {
            $use_mysqldump_php = false;
        }
        
        return $use_mysqldump_php;
    }
    
    /**
     * Checks if dump is in progress
     * @param array $ret
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itChecksIfDumpIsInProgress()
     */
    protected function maybeDumpInProgress($ret = [])
    {
        $dump_in_progress = false;
        if ( ! empty($ret['db_dump_in_progress']) ) {
            $dump_in_progress = $ret['db_dump_in_progress'];
        }
        $this->getSystemInitialization()->setSlowProcess();
        return $dump_in_progress;
    }
    
    /**
     * MySQL dump using shell
     * @param array $tables_to_dumped_shell
     * @param boolean $filter_export_data
     * @param string $target_path
     * @param number $blogid_to_export
     * @param array $ret
     * @param number $db_dump_start
     * @return boolean
     */
    private function doMySqlDumpUsingShell($tables_to_dumped_shell = [], $filter_export_data = false, $target_path = '', $blogid_to_export = 0, $ret = [], $db_dump_start = 0)
    {        
        if ( ! empty($ret['shell_db_dump_clean_tables']) ) {
            
            $tables_to_dumped_shell = $ret['shell_db_dump_clean_tables'];            
            unset($ret['shell_db_dump_clean_tables']);
        }        
        
        $original_tables_count = count($tables_to_dumped_shell);
        if ( ! empty($ret['shell_db_dump_original_table_counts']) ) {
            
            $original_tables_count = $ret['shell_db_dump_original_table_counts'];
            unset($ret['shell_db_dump_original_table_counts']);
        }        
                
        foreach ($tables_to_dumped_shell as $key => $table_shell_dumped) {            
            $limit = 500000;     
            
            $offset = 0;
            if ( ! empty($ret['shell_db_dump_index_to_resume']) ) {
                $offset = $ret['shell_db_dump_index_to_resume'];
                unset($ret['shell_db_dump_index_to_resume']);
            }
            
            $create_table = true;
            if (isset($ret['shell_db_dump_create_tables'])) {
                $create_table = $ret['shell_db_dump_create_tables'];
                unset($ret['shell_db_dump_create_tables']);
            }
            
            while ($shell_dump_res = $this->executeDumpUsingShellFunctions($filter_export_data, $target_path, $blogid_to_export, $table_shell_dumped, $offset, $ret, $limit,
                $original_tables_count, $tables_to_dumped_shell, $create_table)) {
                    if ( ! empty($shell_dump_res['error']) ) {
                        $ret['error'] = $shell_dump_res['error'];
                        break;
                    }                    
                    $offset = $limit + $offset;
                    $create_table = false;
                    $retry_timeout = apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, 'doMySqlDumpUsingShell');
                    if (microtime(true) - $db_dump_start > $retry_timeout) {
                    
                        do_action('prime_mover_log_processed_events', "$retry_timeout seconds time out hits while dumping database. Offset to resume: $offset, Table to resume: $table_shell_dumped", $blogid_to_export, 'export', __FUNCTION__, $this);
                    
                        $count_ongoing_tables_for_dumping = count($tables_to_dumped_shell);
                        $completed_dumped = $original_tables_count - $count_ongoing_tables_for_dumping;
                        $percent = round(($completed_dumped / $original_tables_count) * 100, 0) . '%';
                    
                        $ret['dump_percent_progress'] = $percent;                    
                        $ret['shell_db_dump_clean_tables'] = $tables_to_dumped_shell;
                        $ret['shell_db_dump_index_to_resume'] = $offset;
                        $ret['shell_db_dump_original_table_counts'] = $original_tables_count;
                        $ret['shell_db_dump_create_tables'] = $create_table;
                    
                        return $ret;
                }
            }
            if ( ! empty($ret['error']) ) {
                return $ret;
            }
            unset($tables_to_dumped_shell[$key]);
        }
        
        if (isset($ret['shell_db_dump_index_to_resume']))  {
            unset($ret['shell_db_dump_index_to_resume']);
        }
        return $ret;
    }
    
    /**
     * Execute dump in shell if supported
     * @param string $command
     * @param boolean $filter_export_data
     * @param string $target_path
     * @return array
     * 
     */
    private function executeDumpUsingShellFunctions($filter_export_data = false, $target_path = '', $blogid_to_export = 0, $table_shell_dumped = [], $offset = 0,
        $ret = [], $limit = 0, $original_tables_count = 0, $clean_tables = [], $create_table = true)
    {
        $delete_sql_if_exist = false;
        $clean_tables_count = count($clean_tables);
        if ($clean_tables_count === $original_tables_count && 0 === $offset) {
            $delete_sql_if_exist = true;
        }
        $command = $this->generateMySQLDumpShellCommand($ret, $blogid_to_export, [$table_shell_dumped], $limit, $offset, $create_table);
        $dump_ret = [];
        if ( ! $command || ! $target_path ) {
            $dump_ret['error'] = esc_html__('Undefined execute dump in shell parameters.', 'prime-mover');
            return $dump_ret;
        }
        if (is_array($command) && isset($command['error'])) {
            $dump_ret['error'] = $command['error'];
            return $dump_ret;
        }
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $dump_ret['error'] = esc_html__('Unauthorized dump.', 'prime-mover');
            return $dump_ret;
        }
        $handle = popen("($command)","r");
        if (false === $handle) {
            $dump_ret['error'] = esc_html__('Error in MySQLdump shell using popen.', 'prime-mover');
            return $dump_ret;
        }
        
        if ($delete_sql_if_exist) {
            $this->getSystemFunctions()->primeMoverDoDelete($target_path);
        }
        
        $bytes_written = 0;
        while(!feof($handle)){            
            $line = fgets($handle);
            
            if (false === $line) {
                break;
            }            
            if ($filter_export_data) {
                $line = apply_filters('prime_mover_filter_export_db_data', $line);
                $line = $line . PHP_EOL;
            }
            $bytes_written += file_put_contents($target_path, $line, FILE_APPEND);
        }
        
        pclose($handle);               
        return $bytes_written;
    }

    /**
     * Execute MySQLdump in PHP
     * @param boolean $filter_export_data
     * @param string $target_path
     * @param array $clean_tables
     * @param array $ret
     * @param number $db_dump_start
     * @return string[]|array|string[]|NULL[]|boolean[]|string[]|NULL[]
     */
    private function executeDumpUsingPHP($filter_export_data = false, $target_path = '', $clean_tables = [], $ret = [], $db_dump_start = 0)
    {
        $dump_ret = [];
        if ( ! $target_path ) {
            $dump_ret['error'] = esc_html__('Undefined execute dump in PHP parameters.', 'prime-mover');
            return $dump_ret;
        }
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $dump_ret['error'] = esc_html__('Unauthorized dump.', 'prime-mover');
            return $dump_ret;
        }
        $register_stream = false;
        if ($filter_export_data) {
            $register_stream = $this->getStreamFilters()->register();
        }
        
        if ($filter_export_data && ! $register_stream) {
            $dump_ret['error'] = esc_html__('Unable to register encrypted stream filter.', 'prime-mover');
            return $dump_ret;
        }
        $dumped_log = [];
        $previous_rows_dumped = 0;
        if ( ! empty($ret['php_db_dump_ongoing_rows_dumped']) ) {
            $previous_rows_dumped = $ret['php_db_dump_ongoing_rows_dumped'];
        }
        try {
            if ( ! defined('DB_HOST') ) {
                $dump_ret['error'] = esc_html__('ERROR: DB_HOST constant is undefined in wp-config.php.', 'prime-mover');
                return $dump_ret;
            }
            $db_host = $this->getSystemFunctions()->parsedBHostForPDO();
            if ( ! $db_host ) {
                $dump_ret['error'] = esc_html__('Unable to parse DB_HOST for PDO connection.', 'prime-mover');
                return $dump_ret;
            }
            if (empty($db_host['host'])) {
                $dump_ret['error'] = esc_html__('Error, no known DB host to connect.', 'prime-mover');
                return $dump_ret;
            }
            $hostname = $db_host['host'];
            $port = '';
            $native_port_set = false;
            if ( ! empty($db_host['port'] ) &&  $db_host['port'] > 0) {
                $port = $db_host['port'];
            }
            
            if ($port) {
                $native_port_set = true;
            }
            
            if (!$native_port_set && defined('PRIME_MOVER_FORCE_DB_PORT') && PRIME_MOVER_FORCE_DB_PORT) {
                $native_port_set = true;
            }
            
            $port = apply_filters('prime_mover_filter_db_port', $port, $ret);
            if (!$port) {
                $port = PRIME_MOVER_DEFAULT_MYSQL_PORT;
                $port = (int)$port;
            }
            
            $socket = '';
            if ( ! empty($db_host['socket'] )) {
                $socket = $db_host['socket'];
            }
            
            $mysql_server = 'mysql:host=' . $hostname . ';dbname=' . DB_NAME;
            $mysql_server_ported = '';
            
            if ($port) {
                $ret['db_port'] = $port;
                $mysql_server_ported = 'mysql:host=' . $hostname . ';port=' . $port . ';dbname=' . DB_NAME;
            }
            
            $connection_mode = '';
            if ($socket) {
                $mysql_server = 'mysql:unix_socket=' . $socket . ';dbname=' . DB_NAME;
            } else {
                list($mysql_server, $connection_mode) = apply_filters('prime_mover_filter_pdo_dsn', [$mysql_server, 'no_port_conn'], [$mysql_server_ported, 'ported_conn'], $port, $ret, $native_port_set);
            }
            if ($connection_mode) {
                $ret['pdo_connection_mode'] = $connection_mode;
            }
            do_action('prime_mover_log_processed_events', $mysql_server, 0, 'export', __FUNCTION__, $this);
            
            if ( ! empty($ret['php_db_dump_clean_tables'] ) ) {
                $clean_tables = $ret['php_db_dump_clean_tables'];
                unset($ret['php_db_dump_clean_tables']);
            }            
            if (!empty($ret['prime_mover_db_dump_size'])) {
                $batch_size = $ret['prime_mover_db_dump_size'];
            } else {
                $batch_size = $this->getSystemInitialization()->getMySqlDumpPHPBatchSize();
            }            
            do_action('prime_mover_log_processed_events', "PHPDump Batch Size: $batch_size", 0, 'export', __FUNCTION__, $this);            
            $table_count = count($clean_tables);
            $pdoSettings = apply_filters('prime_mover_get_dump_pdo_settings', [], $ret); 
            if ( ! empty($ret['php_db_dump_original_table_row_counts']) ) {
                $original_table_rows_count = $ret['php_db_dump_original_table_row_counts'];
            } else {
                $original_table_rows_count = $this->countAllTableRowsDb($clean_tables);
            }
            $table_processed = 0;
            foreach ($clean_tables as $key => $table) {
                list($primary_keys, $orderbykeys) = apply_filters('prime_mover_db_primary_keys_dump', [], $ret, $table);
                $i = 0;
                $left_off = [];
                
                if (!empty($ret['php_db_dump_index_to_resume'] ) ) {
                    $i = $ret['php_db_dump_index_to_resume'];
                    unset($ret['php_db_dump_index_to_resume']);
                }                
                
                if (!empty($ret['php_db_dump_left_off'])) {
                    $left_off = $ret['php_db_dump_left_off'];
                    unset($ret['php_db_dump_left_off']);
                }
                while ($dumped_rows = $this->triggerMySQLDumpPHP($mysql_server, $table, $filter_export_data, $target_path, $i, $batch_size, $clean_tables, $table_count, $primary_keys, $orderbykeys, $left_off, $table_processed, $pdoSettings)) {
                   
                    $dumped_count = $dumped_rows['count'];
                    $left_off = $dumped_rows['left_off'];
                   
                    $i = $i + $batch_size;                    
                    $dumped_log[] = $dumped_count;
                    $retry_timeout = apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, __FUNCTION__);
                    
                    if (microtime(true) - $db_dump_start > $retry_timeout) {
                        
                        do_action('prime_mover_log_processed_events', "$retry_timeout seconds time out hits while dumping database. Index to resume: $i, Table to resume: $table, Batch size used: $batch_size", 0, 'export', __FUNCTION__, $this);
                        
                        $count_ongoing_rows_dumped = (array_sum($dumped_log)) + ($previous_rows_dumped);
                        if ($original_table_rows_count && ($count_ongoing_rows_dumped < $original_table_rows_count)) {
                            $percent = round(($count_ongoing_rows_dumped / $original_table_rows_count) * 100, 0) . '%';
                            $ret['dump_percent_progress'] = $percent;
                        }
                        if ($original_table_rows_count && ($count_ongoing_rows_dumped > $original_table_rows_count)) {
                            $ret['dump_percent_progress'] = "99%";
                        }
                        
                        $ret['php_db_dump_clean_tables'] = $clean_tables;
                        $ret['php_db_dump_index_to_resume'] = $i;
                        $ret['php_db_dump_original_table_row_counts'] = $original_table_rows_count;
                        $ret['php_db_dump_ongoing_rows_dumped'] = $count_ongoing_rows_dumped;
                        $ret['php_db_dump_left_off'] = $left_off;
                        
                        return $ret;
                    }
                }
                $table_processed++;
                unset($clean_tables[$key]);
            }
        } catch (Exception $e) {
            $dump_ret['error'] = $e->getMessage();
            return $dump_ret;
        }
        
        $dump_ret['result'] = true;
        return $dump_ret;
    }
    
    /**
     * Generate MySQL dump shell command
     * @param array $ret
     * @param number $blogid_to_export
     * @param array $clean_tables
     * @return mixed
     * 
     */
    private function generateMySQLDumpShellCommand($ret = [], $blogid_to_export = 0, $clean_tables = [], $limit = 100, $offset = 0, $create_table = true)
    {
        $no_password = false;
        if (empty(DB_PASSWORD)) {
            $no_password = true;
        }
        
        $db_username = escapeshellarg(DB_USER);
        $db_password = escapeshellarg(DB_PASSWORD);
        $db_name = escapeshellarg(DB_NAME);
        
        if (empty($clean_tables)) {
            $ret['error'] = esc_html__('Unable to dump database, please check that your database is not empty or these tables exists.', 'prime-mover');
            return $ret;
        }
        $tables	= implode(" ", $clean_tables);
        $mysqldump_path = $this->getSystemChecks()->getMySqlDumpPath();
        if (! $mysqldump_path) {
            $ret['error'] = esc_html__('Unable to get correct MySQLdump command.', 'prime-mover');
            return $ret;
        }
        $mysqldump_path = escapeshellarg($mysqldump_path);
        $password_phrase = ' ';
        if (false === $no_password) {
            $password_phrase = ' -p'. $db_password;
            $password_phrase = apply_filters('prime_mover_passwordless_dump', $password_phrase);
        }
        
        $db_host = $this->getSystemFunctions()->parsedBHostForPDO();
        if ( ! $db_host ) {            
            $ret['error'] = esc_html__('Unable to parse DB_HOST for MySQL command execution.', 'prime-mover');
            return $ret;
        }
        if (empty($db_host['host'])) {            
            $ret['error'] = esc_html__('Error, no known DB host to connect.', 'prime-mover');
            return $ret;
        }
        
        $db_hostname = $db_host['host'];
        $db_hostname = escapeshellarg($db_hostname);
        
        $port = '';
        if ( ! empty($db_host['port'] ) &&  $db_host['port'] > 0) {
            $port = (int)$db_host['port'];
            $port = escapeshellarg($port);
        }
        
        $socket = '';
        if ( ! empty($db_host['socket'] )) {
            $socket = $db_host['socket'];
        }
        
        $port_phrase = ' ';
        if ($port) {
            $port_phrase = ' -P '. $port;
        }
        
        $socket_phrase = ' ';
        if ($socket) {
            $socket_phrase = ' -S '. $socket;
        }
        
        $limit = (int)$limit;
        $offset = (int)$offset;
        
        $limit_phrase = "--where=1 limit $offset, $limit";
        $limit_phrase = escapeshellarg($limit_phrase);
        
        $create_info_phrase = '';
        
        $compact_phrase = '';
        if ( ! $create_table) {
            $create_info_phrase = "--no-create-info";
            $compact_phrase = "--compact";
        }       
        
        $dump_command = $mysqldump_path . ' '.
            $password_phrase .
            ' -u' . $db_username .
            ' -h'. $db_hostname . ' ' .
            $port_phrase . ' ' .
            $socket_phrase . ' ' .
            $limit_phrase . ' ' .
            $create_info_phrase . ' ' .
            $compact_phrase . ' ' .
            $db_name . ' ' .
            $tables;
            
        return $dump_command;
    }
    
    /**
     * Count all table rows
     * @param array $tables
     * @return number
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itCountsAllTableRows() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itReturnsZeroCountWhenNoTables() 
     */
    protected function countAllTableRowsDb($tables = [])
    {
        global $wpdb;
        $rows = 0;
        $tables_count = [];
        if (empty($tables)) {
            return $rows;
        }
        foreach ($tables as $table) {            
            $tables_count[] = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");            
        }       
        return array_sum($tables_count);       
    }
    
    /**
     * Checks if dump should be encrypted
     * @param array $ret
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itGetsTargetDbDump()
     */
    private function maybeEncryptDatabaseDump($ret = [])
    {
        $db_encryption_key = $this->getSystemInitialization()->getDbEncryptionKey();
        $encrypt = false;

        if ($this->maybeEncryptDbIfRequested($db_encryption_key, $ret)) {
            $encrypt = true;
        }
        return $encrypt;
    }
    
    /**
     * Get target dump path
     * @param array $ret
     * @param number $blogid_to_export
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itGetsTargetDbDump() 
     */
    protected function getTargetDumpPath($ret = [], $blogid_to_export = 0)
    {
        $enc = '';        
        $tmp_folderpath = $ret['temp_folder_path'];
        $target_path = $tmp_folderpath . $blogid_to_export . '.sql';
        
        if ($this->maybeEncryptDatabaseDump($ret)) {            
            $enc = '.enc';
            $target_path = $target_path . $enc;
        }

        return $target_path;
    }
    
    /**
     * Clean Tables to export
     * @param number $blogid_to_export
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itGetTablesForExport() 
     */
    protected function getTablesToExport($blogid_to_export = 0)
    {
        $this->getSystemFunctions()->switchToBlog($blogid_to_export);
        
        global $wpdb;
        $target_prefix = $wpdb->prefix;
        $escaped_like = $wpdb->esc_like($target_prefix);
        $target_prefix = $escaped_like . '%';        
        
        if ($this->getSystemFunctions()->isMultisiteMainSite($blogid_to_export, true)) {              
            $regex = $escaped_like . '[0-9]+';
            $table_name = DB_NAME;            
            $db_search = $this->getSystemFunctions()->getMultisiteMainSiteTableQuery($table_name);
            
            $prepared = $wpdb->prepare($db_search, $target_prefix, $regex);            
            $tables_to_export = $wpdb->get_results($prepared, ARRAY_N);           
            
        } else {                        
            $db_search = "SHOW TABLES LIKE %s";
            $tables_to_export = $wpdb->get_results($wpdb->prepare($db_search, $target_prefix), ARRAY_N);            
        }        
        
        $this->getSystemFunctions()->restoreCurrentBlog();
        return $this->getSystemFunctions()->cleanDbTablesForExporting($tables_to_export, $blogid_to_export, $wpdb, 'export');     
    }
    
    /**
     * Encrypt dB on export if requested or applicable
     * @param string $db_encryption_key
     * @param array $ret
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itMaybeEncryptDbIfRequested() 
     * 
     */
    protected function maybeEncryptDbIfRequested($db_encryption_key = '', $ret = [])
    {    
        if (defined('PRIME_MOVER_GEARBOX_VERSION') && isset($ret['prime_mover_encrypt_db'])
            && 'true' === $ret['prime_mover_encrypt_db'] && $db_encryption_key) {
            return true;
        }

        return false;
    }
    
    /**
     * Trigger MySQL dump
     * @param string $mysql_server
     * @param string $table
     * @param boolean $filter_export_data
     * @param string $target_path
     * @param number $i
     * @param number $batch_size
     * @param array $clean_tables
     * @param number $table_count
     * @param array $primary_keys
     * @param array $orderbykeys
     * @param array $left_off
     * @param number $table_processed
     * @param array $pdoSettings
     * @return void|array
     */
    private function triggerMySQLDumpPHP($mysql_server = '', $table = '', $filter_export_data = false, $target_path = '', 
        $i = 0, $batch_size = 10, $clean_tables = [], $table_count = 0, $primary_keys = [], $orderbykeys = [], $left_off = [], $table_processed = 0, $pdoSettings = [])
    {
        $dumpSettings = $this->getMySQLDumpPHPParameters([$table]);
        if (0 === $table_processed) {
            do_action('prime_mover_log_processed_events', "MySQLdump settings: ", $this->getSystemInitialization()->getExportBlogID(), 'export', __FUNCTION__, $this);
            do_action('prime_mover_log_processed_events', $dumpSettings, $this->getSystemInitialization()->getExportBlogID(), 'export', __FUNCTION__, $this);
            
            do_action('prime_mover_log_processed_events', "PDO settings: ", $this->getSystemInitialization()->getExportBlogID(), 'export', __FUNCTION__, $this);
            do_action('prime_mover_log_processed_events', $pdoSettings, $this->getSystemInitialization()->getExportBlogID(), 'export', __FUNCTION__, $this);
        }
        $dump = new IMysqldump\Mysqldump($mysql_server, DB_USER, DB_PASSWORD, $dumpSettings, $pdoSettings);  
        
        if ($filter_export_data) {
            $encryted_streamer = $this->getStreamFilters()->getStreamFilter($target_path);
            return $dump->start($encryted_streamer, true, $i, $batch_size, $table, $clean_tables, $table_count, $primary_keys, $orderbykeys, $left_off);
        } else {
            return $dump->start($target_path, true, $i, $batch_size, $table, $clean_tables, $table_count, $primary_keys, $orderbykeys, $left_off);
        } 
    }
    
    /**
     * Get MySQL dump - PHP parameters
     * @param array $clean_tables
     * @return array[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itGetsMySQLDumpPHPParameters() 
     */
    protected function getMySQLDumpPHPParameters($clean_tables = [])
    {
        /**
         * Optimal MySQL dump settings
         * References:
         * https://dba.stackexchange.com/questions/87100/what-are-the-optimal-mysqldump-settings
         * https://serversforhackers.com/c/mysqldump-with-modern-mysql
         */
        return [
            'include-tables' => $clean_tables,
            'exclude-tables' => [],
            'compress' => IMysqldump\Mysqldump::NONE,
            'init_commands' => [],
            'no-data' => [],
            'reset-auto-increment' => false,
            'add-drop-database' => false,
            'add-drop-table' => true,
            'add-drop-trigger' => true,
            'add-locks' => true,
            'complete-insert' => false,
            'databases' => false,       
            'default-character-set' => apply_filters('prime_mover_default_db_charset', $this->getSystemInitialization()->getDefaultDbCharSet()),
            'disable-keys' => true,
            'extended-insert' => true,
            'events' => false,
            'hex-blob' => false,
            'insert-ignore' => false,
            'no-autocommit' => false,
            'no-create-info' => false,
            'lock-tables' => true,
            'routines' => false,
            'single-transaction' => true,
            'skip-triggers' => false,
            'skip-tz-utc' => false,
            'skip-comments' => false,
            'skip-dump-date' => false,
            'skip-definer' => false,
            'where' => ''
        ];
    }

    /**
     * Create temporary monitor file
     * @return string
     */
    protected function createTmpShellFile()
    {
        if ($this->getCliArchiver()->maybeArchiveMediaByShell()) {
            return $this->getSystemInitialization()->wpTempNam();
        }
    }
    
    /**
     * Zip dB dump
     * @param array $ret
     * @param number $blogid_to_export
     * @param number $start_time
     * @return void|mixed|NULL|array|boolean
     * @audited
     * @mainsite_compatible
     */
    public function zipDbDump($ret = [], $blogid_to_export = 0, $start_time = 0)
    {        
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        list($current_func, $previous_func,  $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__); 
        $ret = apply_filters('prime_mover_get_export_progress', $ret, $blogid_to_export);               
        
        if (isset($ret['error'])) {
            return $ret;
        }
        
        if ( ! isset($ret['completed_target_dump_path']) ) {
            $ret['error'] = esc_html__('Completed target dump zip path is not located for archiving.', 'prime-mover');
            return $ret;
        }        
        $target_path = $ret['completed_target_dump_path'];        
       
        $sqlfile = basename($target_path);
        $localname = basename($ret['temp_folder_path']) . '/' . $sqlfile;
        $file_position = 0;
        if (!empty($ret['tar_add_file_offset'])) {
            $file_position = $ret['tar_add_file_offset'];
            unset($ret['tar_add_file_offset']);
        }
        $readable = $this->getSystemFunctions()->humanFileSize($file_position, 0);
        $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Archiving database..%s bytes done.', 'prime-mover'), $readable), 'export' );
        
        $ret = apply_filters('prime_mover_add_file_to_tar_archive', $ret, $ret['target_zip_path'], 'ab', $target_path, $localname, $start_time, $file_position, $blogid_to_export, true, false);
        if (!empty($ret['tar_add_file_offset'])) {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $current_func, $previous_func);
        }           

        $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'export', $blogid_to_export);       
        return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $next_func, $current_func);           
    }
    
    /**
     * Zip db dump helper
     * @param array $ret
     * @param string $target_path
     * @param boolean $encrypt
     */
    public function zipDbDumpHelper($ret = [], $target_path = '', $encrypt = false)
    {
        $this->getSystemFunctions()->addToPrimeMoverZipPackage($ret['target_zip_path'], false, $target_path, $ret['temp_folder_path'], true, false, true);
        if ($encrypt) {
            $signature_path = $ret['temp_folder_path'] . $this->getSystemInitialization()->getSignatureFile();
            $this->getSystemFunctions()->addToPrimeMoverZipPackage($ret['target_zip_path'], false, $signature_path, $ret['temp_folder_path'], true, false, true);
        }
    }

    /**
     * Copy language files to the temporary export folder
     * {@inheritDoc}
     * @see PrimeMoverExport::copyMediaFiles()
     * @compatible 5.6
     * @audited
     * @mainsitesupport_affected
     * @mainsite_compatible
     *
     */
    public function copyLanguageFiles($ret = [], $blogid_to_export = 0, $start_time = 0)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        
        $ret = apply_filters('prime_mover_get_export_progress', $ret, $blogid_to_export);
        if (isset($ret['error'])) {
            return $ret;
        }        
        
        $process_methods = [];        
        list($process_methods['current'], $process_methods['previous'],  $process_methods['next']) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__); 
        
        if (defined('PRIME_MOVER_BYPASS_LANGUAGE_FOLDER_EXPORT') && true === PRIME_MOVER_BYPASS_LANGUAGE_FOLDER_EXPORT) {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['next'], $process_methods['current']);
        }
        
        if (!isset($ret['lang_folder_exported'])) {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['next'], $process_methods['current']);
        }
               
             
        $zippath = $ret['target_zip_path'];        
        $copying_media_started = false;
        $counted = 0;
        if (isset($ret['total_media_files'])) {
            $counted = $ret['total_media_files'];
        }
        if (isset($ret['processed_file_count'])) {
            $copying_media_started = true;
            $counted = $ret['processed_file_count'];
        }
        
        if (empty($ret['copymedia_shell_tmp_list']) || empty($ret['languages_folder_path'])) {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['next'], $process_methods['current']);
        }
       
        $languages_folder_path = $ret['languages_folder_path'];
        $mode = 'exporting_languages_folders';        
        $archive_alias = apply_filters('prime_mover_filter_basezip_folder', basename($languages_folder_path), $ret, $mode, $languages_folder_path, false);
       
        $tar_archiving_params = [];
        $tar_archiving_params[] = $archive_alias;
        $tar_archiving_params[] = [];
        $tar_archiving_params[] = $mode;
        
        $resume_positions = [];
        $counted = 0;
        $bytes_written = 0;
        
        do_action('prime_mover_log_processed_events', "Exporting language directory.", $this->getSystemInitialization()->getExportBlogID(), 'export', __FUNCTION__, $this);
        
        if (!empty($ret['tar_add_dir_offsets'])) {
            $copying_media_started = true;
            $resume_positions = $ret['tar_add_dir_offsets'];
            unset($ret['tar_add_dir_offsets']);
        }
        
        if (!empty($resume_positions['files_archived'])) {
            $counted = $resume_positions['files_archived'];
        }
        
        if (!empty($resume_positions['bytes_written'])) {
            $bytes_written = $resume_positions['bytes_written'];
        }
       
        $this->doArchivingFilesProgress($ret, $counted, $copying_media_started, 'languages', $bytes_written);
        $ret = apply_filters('prime_mover_add_directory_to_tar_archive', $ret, $zippath, $ret['copymedia_shell_tmp_list'], $languages_folder_path, $start_time, 
            $resume_positions, false, $blogid_to_export, $tar_archiving_params, 'ab', '');
        
        if (empty($ret['tar_add_dir_offsets'])) {
            $ret = $this->cleanUpMediaTmpList($ret);
            $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $process_methods['current'], 'export', $blogid_to_export);
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['next'], $process_methods['current']);
            
        } else {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['current'], $process_methods['previous']);
        }       
    }    
    
    /**
     * Generate language folder files list
     * @param array $ret
     * @param number $blogid_to_export
     * @param number $start_time
     * @return array|array
     * @audited
     * @mainsitesupport_affected
     * @mainsite_compatible
     */
    public function generateLanguageFilesList($ret = [], $blogid_to_export = 0, $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        list($current_func, $previous_func,  $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__);
        
        $ret = apply_filters('prime_mover_get_export_progress', $ret, $blogid_to_export);
        if (isset($ret['error'])) {
            return $ret;
        }
        
        if (defined('PRIME_MOVER_BYPASS_LANGUAGE_FOLDER_EXPORT') && true === PRIME_MOVER_BYPASS_LANGUAGE_FOLDER_EXPORT) {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $next_func, $current_func);
        }
        
        $locale = apply_filters('prime_mover_filter_site_locales', $this->getSystemInitialization()->getLocaleOfExportedSite(), $ret, $blogid_to_export);            
        $language_folder = $this->getSystemFunctions()->getLanguageFolder();
        if (!is_string($language_folder) || !is_dir($language_folder)) {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $next_func, $current_func);
        }
        
        $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Generating languages files list..', 'prime-mover'), 'export' );
        do_action('prime_mover_log_processed_events', 'Generating language files for exporting belonging to locale:' . implode(',', $locale), $this->getSystemInitialization()->getExportBlogID(), 'export', __FUNCTION__, $this);
        $ret = $this->getIterators()->generateFilesListGivenDir($language_folder, $ret, [], $locale);
       
        $ret['languages_folder_path'] = $language_folder;
        $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'export', $blogid_to_export);        
        return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $next_func, $current_func);
    }
    
    /**
     * Generate media files list
     * @param array $ret
     * @param number $blogid_to_export
     * @param number $start_time
     * @return array|array
     * @audited
     * @mainsitesupport_affected
     * @mainsite_compatible
     */
    public function generateMediaFilesList($ret = [], $blogid_to_export = 0, $start_time = 0)
    {          
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        list($current_func, $previous_func,  $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__); 
        $ret = apply_filters('prime_mover_get_export_progress', $ret, $blogid_to_export);
        if (isset($ret['error'])) {
            return $ret;
        }
        
        $this->getSystemInitialization()->setSlowProcess();
        
        if ( ! isset($ret['multisite_export_options']) ) {
            $ret['error'] = esc_html__('Error ! Export options not set.', 'prime-mover');
            return $ret;
        }
        
        if ('dbonly_export' === $ret['multisite_export_options']) {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $next_func, $current_func);
        }
        
        if ('development_package' === $ret['multisite_export_options']) {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $next_func, $current_func);
        }        
        $blogsdir_exported_blog = $this->getSystemFunctions()->primeMoverGetBlogsDirPath($blogid_to_export);        
        
        if ( ! is_dir($blogsdir_exported_blog)) {
            $ret['error'] = esc_html__('Uploads directory of this blog does not seem to exist.', 'prime-mover');
            return $ret;
        }
        
        $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Generating export files list..', 'prime-mover'), 'export' );        
        $ret = $this->getIterators()->generateFilesListGivenDir($blogsdir_exported_blog, $ret, apply_filters('prime_mover_excluded_media_folders', [], $blogid_to_export, $blogsdir_exported_blog, $ret));         
        $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'export', $blogid_to_export);  
        
        return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $next_func, $current_func);
    }
     
    /**
     * Copy media files of this sub-site to the temporary export folder
     * {@inheritDoc}
     * @see PrimeMoverExport::copyMediaFiles()
     * @compatible 5.6
     * @audited
     * @mainsitesupport_affected
     * @mainsite_compatible
     * 
     */
    public function copyMediaFiles($ret = [], $blogid_to_export = 0, $start_time = 0)
    {        
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_export_progress', $ret, $blogid_to_export);
        
        $process_methods = [];
        list($process_methods['current'], $process_methods['previous'], $process_methods['next']) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__); 
        
        if (isset($ret['error'])) {            
            return $ret;
        }
        
        if ( ! isset($ret['multisite_export_options']) ) {
            $ret['error'] = esc_html__('Error ! Export options not set.', 'prime-mover');
            return $ret;
        }

        if ('dbonly_export' === $ret['multisite_export_options']) {     
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['next'], $process_methods['current']);
        }

        if ('development_package' === $ret['multisite_export_options']) {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['next'], $process_methods['current']);
        }
        
        $media_tmp_file = '';
        if ( ! empty($ret['copymedia_shell_tmp_list']) ) {
            $media_tmp_file = $ret['copymedia_shell_tmp_list'];
        }    
               
        $blogsdir_exported_blog = $this->getSystemFunctions()->primeMoverGetBlogsDirPath($blogid_to_export); 
        $exportdir_slug = $this->getSystemInitialization()->getMultisiteExportFolderSlug();        
        $importdir_slug = $this->getSystemInitialization()->getUploadTmpPathSlug();
        $download_tmp_slug = $this->getSystemInitialization()->getTmpDownloadsFolderSlug();
        $lock_files_slug = $this->getSystemInitialization()->getLockFilesFolderSlug();
        
        $skip = [$exportdir_slug, $importdir_slug, $download_tmp_slug, $lock_files_slug];
        if (is_multisite() && !$this->getSystemFunctions()->isMultisiteMainSite($blogid_to_export)) {
            $skip = [];
        }        
        $skip = apply_filters('prime_mover_excluded_filesfolders_export', $skip, $ret, $blogid_to_export);          
        $exclusion_rules = [
            'skip_files_directories' => $skip, 
            'skip_by_extensions' => apply_filters('prime_mover_excluded_filetypes_export', [], $ret, $blogid_to_export)
        ];
        
        $ret['media_export'] = true;  
        $zippath = $ret['target_zip_path'];
        
        $encrypt_media = apply_filters('prime_mover_enable_media_encryption', false, $ret);
        $ret['encrypted_media'] = $encrypt_media;           
        
        $copying_media_started = false;    
        $counted = 0;
        if (isset($ret['total_media_files'])) {            
            $counted = $ret['total_media_files'];
        }
        if ($counted && !isset($ret['wprime_media_files_count'])) {
            $ret['wprime_media_files_count'] = (int)$ret['total_media_files'];
        }
        if (isset($ret['processed_file_count'])) {   
            $copying_media_started = true;
            $counted = $ret['processed_file_count'];            
        }        
                  
        $archive_alias = apply_filters('prime_mover_filter_basezip_folder', basename($blogsdir_exported_blog), $ret, 'exporting_media', $blogsdir_exported_blog, false);            
        $tar_archiving_params = [];
        $tar_archiving_params[] = $archive_alias;
        $tar_archiving_params[] = $exclusion_rules;
        $tar_archiving_params[] = 'exporting_media';
        
        $resume_positions = [];
        $counted = 0;
        $bytes_written = 0;
        if (!empty($ret['tar_add_dir_offsets'])) {
            $copying_media_started = true;
            $resume_positions = $ret['tar_add_dir_offsets'];
            unset($ret['tar_add_dir_offsets']);
        }
        if (!empty($resume_positions['files_archived'])) {
            $counted = $resume_positions['files_archived'];
        }
        if (!empty($resume_positions['bytes_written'])) {
            $bytes_written = $resume_positions['bytes_written'];
        }
        $this->doArchivingFilesProgress($ret, $counted, $copying_media_started, 'media', $bytes_written);
        $encryption_key = '';
        if ($encrypt_media) {
            $encryption_key = $this->getSystemInitialization()->getDbEncryptionKey();
        }        
        
        $ret = apply_filters('prime_mover_add_directory_to_tar_archive', $ret, $zippath, $media_tmp_file, $blogsdir_exported_blog, $start_time, 
            $resume_positions, false, $blogid_to_export, $tar_archiving_params, 'ab', $encryption_key);
        if (empty($ret['tar_add_dir_offsets'])) {
            $ret = $this->cleanUpMediaTmpList($ret);
            $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $process_methods['current'], 'export', $blogid_to_export);
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['next'], $process_methods['current']);
            
        } else {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['current'], $process_methods['previous']);
        }        
    }    
    
    /**
     * Clean up ret tmp list
     * @param array $ret
     * @return array
     */
    protected function cleanUpMediaTmpList($ret = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        
        $tmp = '';
        if (isset($ret['copymedia_shell_tmp_list'])) {
            $tmp = $ret['copymedia_shell_tmp_list'];
            unset($ret['copymedia_shell_tmp_list']);
        }
        if ($this->getSystemFunctions()->nonCachedFileExists($tmp)) {
            $this->getSystemFunctions()->primeMoverDoDelete($tmp);
        }
        return $ret;
    }
    
    /**
     * Get error log path
     * @param number $blog_id
     * @return string
     */
    private function getErrorLogPath($blog_id = 0)
    {
        $error_log = $this->getSystemInitialization()->getErrorLogFile($blog_id);
        return $this->getProgressHandlers()->getShutDownUtilities()->getPrimeMoverErrorPath($blog_id, $error_log);
    }
    
    /**
     * Generate footprint data of activated plugins and themes of this sub-site export
     * {@inheritDoc}
     * @see PrimeMoverExport::generateExportFootprintConfig()
     * @compatible 5.6
     * @audited
     * @mainsite_compatible
     */
    public function generateExportFootprintConfig($ret = [], $blogid_to_export = 0, $start_time = 0)
    {        
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_export_progress', $ret, $blogid_to_export);
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__);
        
        if (isset($ret['error'])) {
            return $ret;
        }
        $this->getSystemInitialization()->setSlowProcess();
        $tmp_folderpath = $ret['temp_folder_path'];        
        
        $export_system_footprint = $this->getSystemChecks()->primeMoverGenerateSystemFootprint($blogid_to_export, $tmp_folderpath, $ret);
        
        $footprint_analysis = $this->getSystemFunctions()->primeMoverValidateFootprintData($export_system_footprint);
        $valid_system_footprint = $footprint_analysis['overall_valid'];
        $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Generating config', 'prime-mover'), 'export' );
        
        if ( false === $valid_system_footprint ) {
            $ret['error'] = $footprint_analysis['errors'];
            return $ret;
        }
        
        $export_system_footprint['exported_db_tables'] = $ret['exported_db_tables'];
        unset($ret['exported_db_tables']);
        
        $export_system_footprint = apply_filters('prime_mover_filter_export_footprint', $export_system_footprint, $ret, $blogid_to_export);
        $construct_message = json_encode($export_system_footprint);
        $ret['export_system_footprint'] = $export_system_footprint;
        
        global $wp_filesystem;
        $source = $tmp_folderpath . 'footprint.json'; 
        $put_results = $wp_filesystem->put_contents($source, $construct_message);
        if (false === $put_results) {
            $ret['error'] = esc_html__('Error putting footprint files.', 'prime-mover');
            return $ret;
        }           
                       
        do_action('prime_mover_log_processed_events', $ret, $blogid_to_export, 'export', $current_func, $this, true);
        $this->writeOtherFiles($ret, $blogid_to_export);        
 
        $this->generateExportFootPrintHelper($ret, $source, $blogid_to_export);
        return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $next_func, $current_func);
                   
    }
    
    /**
     * Write other files on footprint config
     * @param array $ret
     * @param number $blogid_to_export
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itWritesOtherFiles()
     */
    public function writeOtherFiles($ret = [], $blogid_to_export = 0)
    {
        $blog_id_otherfiles = $blogid_to_export;
        $export_system_footprint = $ret['export_system_footprint'];
        $tmp_folderpath = $ret['temp_folder_path'];
        if ( ! empty($ret['prime_mover_export_targetid']) ) {
            $blog_id_otherfiles = $ret['prime_mover_export_targetid'];
        }
        do_action( 'prime_mover_write_otherfiles', $tmp_folderpath, $blog_id_otherfiles, $export_system_footprint, $ret );
    }
  
    /**
     * Footprint archiving helper
     * @param array $ret
     * @param string $tmp_folderpath
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itGeneratesExportFootPrintHelper()
     */
    public function generateExportFootPrintHelper($ret = [], $source = '', $blogid_to_export = 0)
    {
        $tmp_folderpath = $ret['temp_folder_path'];                  
        $basename = basename($source);
        $local_name = trailingslashit(basename($tmp_folderpath)) . $basename;
        apply_filters('prime_mover_add_file_to_tar_archive', $ret, $ret['target_zip_path'], 'ab', $source, $local_name , 0, 0, $blogid_to_export, false, false);             
    }
    
    /**
     * Finalizing media archive
     * This is only used to formally close WPRIME archive
     * @param array $ret
     * @param number $blogid_to_export
     * @return array
     * @mainsite_compatible
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itFinalizesMediaArchive()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itDoesNotFinalizeMediaIfUnauthorized()
     */
    public function finalizingMediaArchive($ret = [], $blogid_to_export = 0, $start_time = 0)
    {        
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_export_progress', $ret, $blogid_to_export);
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__);
            
        $wprime_path = $ret['target_zip_path'];
        $ret['wprime_closed'] = apply_filters('prime_mover_close_wprime_archive', false, $wprime_path, $ret, $blogid_to_export);
        
        return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $next_func, $current_func);
    }
    
    /**
     * Zipped export directory
     * {@inheritDoc}
     * @see PrimeMoverExport::zippedFolder()
     * @compatible 5.6
     * @mainsite_compatible
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itZipsFolder() 
     */
    public function zippedFolder($ret = [], $blogid_to_export = 0, $start_time = 0)
    {        
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_export_progress', $ret, $blogid_to_export);    
        $process_methods = [];
        list($process_methods['current'], $process_methods['previous'], $process_methods['next']) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__);    
        
        if (isset($ret['error'])) {            
            return $ret;
        }      
        
        $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $process_methods['current'], 'export', $blogid_to_export);
        return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['next'], $process_methods['current']);               
    }
    
    /**
     * Delete temporary export folder
     * {@inheritDoc}
     * @see PrimeMoverExport::deleteTemporaryFolder()
     * @compatible 5.6
     * @mainsite_compatible
     */
    public function deleteTemporaryFolder($ret = [], $blogid_to_export = 0, $start_time = 0)
    {        
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        
        $ret = apply_filters('prime_mover_get_export_progress', $ret, $blogid_to_export);
        $process_methods = [];
        
        list($process_methods['current'], $process_methods['previous'], $process_methods['next']) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__); 
        
        if (isset($ret['error'])) {            
            return $ret;
        }
 
        $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Deleting temp folder', 'prime-mover'), 'export' );
        $tmp_folderpath = $ret['temp_folder_path'];
              
        $delete_result = $this->getSystemFunctions()->primeMoverDoDelete($tmp_folderpath, true, $start_time);
        if (false === $delete_result) {
            $ret['error'] = esc_html__('Unable to delete temporary directory', 'prime-mover');
            return $ret;
        }
        if (is_array($delete_result) && isset($delete_result['retry'])) {
            do_action('prime_mover_log_processed_events', "Time out hits WHILE DELETING TEMP directory, retry again", $blogid_to_export, 'export', __FUNCTION__, $this, true);
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['current'], $process_methods['previous']);
        }
        
        do_action('prime_mover_log_processed_events', $ret, $blogid_to_export, 'export', $process_methods['current'], $this, true);            
        $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $process_methods['current'], 'export', $blogid_to_export);
        
        return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $process_methods['next'], $process_methods['current']);        
    }
    
    /**
     * Generate download URL
     * {@inheritDoc}
     * @see PrimeMoverExport::generateDownloadUrl()
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverExporter::itGeneratesDownloadURLWhenAllSet()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverExporter::itDoesNotGenerateDownloadURLNotAuthorized()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverExporter::itGeneratesDownloadURLWhenExportDirectoryOn()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverExporter::itGeneratesDownloadUrlWhenExportDirectoryOff()
     * @mainsite_compatible
     */
    public function generateDownloadUrl($ret = [], $blogid_to_export = 0, $start_time = 0)
    {        
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        $ret = apply_filters('prime_mover_get_export_progress', $ret, $blogid_to_export);
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__);
        if (isset($ret['error'])) {           
            return $ret;
        }
        $this->getSystemInitialization()->setSlowProcess();
        $export_directory_on = false;
        $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Generate download URL', 'prime-mover'), 'export' );
        $results = $ret['target_zip_path'];

        $download_nonce = $this->getSystemFunctions()->primeMoverCreateNonce('prime_mover_download_package_' . $blogid_to_export);
        $export_directory_on = $this->isExportDirectoryOn($ret);
        
        if (isset($ret['computed_export_hash'])) {
            $hash = $ret['computed_export_hash'];
        } else {
            $hash = $this->getSystemFunctions()->hashString($ret['temp_folder_path']);
        }        
       
        $args = [
            'prime_mover_download_nonce' => $download_nonce,
            'prime_mover_export_hash' => $hash,
            'prime_mover_blogid' => $blogid_to_export
        ];
        $admin_mode = true;
        if ($export_directory_on) {
            unset($args['prime_mover_download_nonce']);   
        }
             
        $download_url = $this->getSystemInitialization()->getDownloadURLGivenParameters($args, $admin_mode);
        $ret['download_url'] = $download_url;
        
        $results = str_replace(wp_normalize_path($this->getSystemInitialization()->getMultisiteExportFolderPath()), '', $results);
        $is_wprime = false;
        if (isset($ret['wprime_closed']) && $ret['wprime_closed']) {
            $is_wprime = true;
        }
        $generatedFilename = $this->getSystemFunctions()->createFriendlyName($blogid_to_export, $results, $is_wprime, $ret);
        
        $ret['generated_filename'] = $generatedFilename;
        $this->getSystemFunctions()->updateSiteOption($hash, $results, true);
        $this->getSystemFunctions()->updateSiteOption($hash . "_filename", $generatedFilename, true);        
        
        do_action('prime_mover_after_generating_download_url', $results, $hash, $blogid_to_export, $export_directory_on, $ret);
        do_action('prime_mover_log_processed_events', $ret, $blogid_to_export, 'export', $current_func, $this, true);
  
        $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'export', $blogid_to_export); 
    
        return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $next_func, $current_func);
    }
    
    /**
     * Check if export directory mode is on.
     * @param array $ret
     * @return boolean
     * 
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverExporter::itDoPostExportProcessingWhenUsingCustomExportDir()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverExporter::itDoPostExportProcessingWhenUsingDefaultDir(
     */
    private function isExportDirectoryOn($ret = [])
    {
        if (isset($ret['multisite_export_location']) && 'export_directory' === $ret['multisite_export_location'] ) {
            return true;
        } 
        return false;
    }
    
    /**
     * Post export processing filter
     * @param array $ret
     * 
     * @param number $blogid_to_export
     * @return mixed|NULL|array
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverExporter::itDoPostExportProcessingWhenUsingCustomExportDir()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverExporter::itDoPostExportProcessingWhenUsingDefaultDir()
     * @audited
     */
    public function doPostExportProcessing($ret = [], $blogid_to_export = 0, $start_time = 0)
    {        
        $ret = apply_filters('prime_mover_get_export_progress', $ret, $blogid_to_export);
        /**
         *  @var Type $next_func Next function
         */
        list($current_func, $previous_func, $next_func) = $this->getSystemInitialization()->getProcessMethods(__FUNCTION__);
        
        $export_directory_on = $this->isExportDirectoryOn($ret);
        $this->getSystemInitialization()->setSlowProcess();
        
        $ret = apply_filters('prime_mover_post_export_processing', $ret, $blogid_to_export, $export_directory_on, $start_time);
        do_action('prime_mover_log_processed_events', $ret, $blogid_to_export, 'export', $current_func, $this, true);
        
        if ( ! empty($ret['dropbox_resume_upload']) || ! empty($ret['gdrive_resume_url'])) {
            return apply_filters('prime_mover_save_return_export_progress', $ret, $blogid_to_export, $current_func, $previous_func);
            
        } else {
            $ret = $this->getSystemFunctions()->doMemoryLogs($ret, $current_func, 'export', $blogid_to_export); 
            $this->getSystemChecks()->computePerformanceStats($blogid_to_export, $ret, 'export');
            
            return $this->cleanUpProgressForFinalReturn($ret);
        }       
    }   
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itSavesExportProgressData() 
     * Save and return export progress data
     * 
     * @param array $ret
     * @param number $blogid_to_export
     * @param string $next_method
     * @param string $current_method
     * @return array
     */
    public function saveExportProgressData($ret = [], $blogid_to_export = 0, $next_method = '', $current_method = '')
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
        if ( ! $blogid_to_export || ! $next_method || ! $current_method ) {
            return $ret;
        }
        $ret['ongoing_export'] = true;
        $ret['next_method'] = $next_method;
        $ret['current_method'] = $current_method;

        $meta_key = $this->getProgressHandlers()->generateTrackerId($blogid_to_export, 'export');
        wp_cache_delete($user_id, 'user_meta' );
        
        do_action('prime_mover_update_user_meta', $user_id, $meta_key, $ret);         
        return $ret;
    }
    
    /**
     * Return export progress data to continue processing
     * @param array $ret
     * @param number $blogid_to_export
     * 
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itGetsExportProgressData()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itDoesNotGetProgressExportDataBlogIdNotSet()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itDoesNotGetProgressExportDataNotAuthorized()
     */
    public function getExportProgressData($ret = [], $blogid_to_export = 0)
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized() || ! $blogid_to_export) {
            return $ret;
        }
        $user_id = get_current_user_id();
        $meta_key = $this->getProgressHandlers()->generateTrackerId($blogid_to_export, 'export');
        wp_cache_delete($user_id, 'user_meta' );
        
        return get_user_meta($user_id, $meta_key, true);        
    }
    
    /**
     * Clean up export progress
     * 
     * @param array $ret
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExporter::itCleansUpProgressForFinalReturn()
     */
    protected function cleanUpProgressForFinalReturn($ret = [])
    {        
        if (isset($ret['ongoing_export'])) {
            unset($ret['ongoing_export']);
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
