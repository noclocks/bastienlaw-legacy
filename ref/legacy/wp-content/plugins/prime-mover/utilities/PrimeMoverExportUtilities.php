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

use Codexonics\PrimeMoverFramework\classes\PrimeMoverExporter;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Export Utilities
 * Helper functionality for Export.
 *
 */
class PrimeMoverExportUtilities
{
    private $exporter;
    private $valid_export_options = [ 'dbonly_export', 'db_and_media_export', 'development_package', 'complete_export_mode' ];    
    
    /**
     * Constructor
     * @param PrimeMoverExporter $exporter
     */
    public function __construct(PrimeMoverExporter $exporter)
    {
        $this->exporter = $exporter;
    }

    /**
     * Get export modes
     * @return array
     */
    public function getExportModes()
    {
        $export_texts = [
            esc_js(__('Database backup only', 'prime-mover')),
            esc_js(__('Database + Media files backup only', 'prime-mover')),
            esc_js(__('Debugging package', 'prime-mover')),
            esc_js(__('Complete full backup (Database + Plugins/Themes + Media files)', 'prime-mover'))
            ];
        
        $valid_export_options = $this->getValidExportOptions();
        return array_combine($valid_export_options, $export_texts);
    }
    
    /**
     * Get plugin full path helper
     * @param string $plugin
     * @param boolean $exist_check
     * @return string
     */
    public function getPluginFullPath($plugin = '', $exist_check = true)
    {
        return $this->getExporter()->getSystemFunctions()->getPluginFullPath($plugin, $exist_check);
    }
    
    /**
     * Gets valid Export options
     * @return string[]
     * @compatible 5.6
     */
    public function getValidExportOptions()
    {
        return $this->valid_export_options;
    }
    
    /**
     * Get system check utilities
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverSystemCheckUtilities
     */
    public function getSystemCheckUtilities()
    {
        return $this->getExporter()->getSystemChecks()->getSystemCheckUtilities();
    }
    
    /**
     * Get exporter
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverExporter
     * @compatible 5.6
     */
    public function getExporter()
    {
        return $this->exporter;
    }
    
    /**
     * Gets System authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     * @compatible 5.6
     */
    public function getSystemAuthorization()
    {
        return $this->getExporter()->getSystemAuthorization();
    }
        
    /**
     * Init hooks
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExportUtilities::itAddsInitHooks()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExportUtilities::itChecksIfHooksAreOutdated()
     */
    public function initHooks()
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }       
        add_filter('prime_mover_exclude_plugins_in_export', [ $this, 'excludePrimeMoverPluginInExport' ], 10, 1);        
        add_action('prime_mover_do_after_export_button', [ $this, 'exportDialog'], 10, 1);
        
        add_filter('prime_mover_get_export_option', [ $this, 'getExportOption'], 10, 2);
        add_action('prime_mover_write_otherfiles', [ $this, 'writeBlogID'], 10, 4 );        
                    
        add_filter( 'prime_mover_ajax_rendered_js_object', [$this, 'setExportModeDialogTexts'], 110, 1 );        
        add_filter('prime_mover_filter_ret_after_db_dump', [$this, 'filterTablesForWriting'], 10, 3);
        add_filter('prime_mover_filter_site_footprint', [$this, 'filterFootprintRandomPrefix'], 10, 1);        
        
        add_filter('prime_mover_filter_export_footprint', [$this, 'addSiteTitleToFootPrint'], 10, 3);        
        add_filter('prime_mover_ajax_rendered_js_object', [$this, 'setExportProcessError'], 45, 1 ); 
        
        /**
         * @since 1.0.6
         */
        add_filter('prime_mover_filter_blogid_to_export', [$this, 'adjustIdForMultisiteExport'], 10, 2);
        add_filter('prime_mover_filter_basezip_folder', [$this, 'filterMediaFolderForMultisite'], 10, 3);
        add_filter('prime_mover_media_resource_is_excluded', [$this, 'maybeExcludeThisMediaResource'], 10, 5);
        
        add_filter('prime_mover_plugins_to_export', [$this, 'getPluginsToExport'], 10, 1);        
        add_filter('prime_mover_filter_basezip_folder', [$this, 'prependPluginsFolderInsideZipArchive'], 10, 5);
        
        add_filter('prime_mover_export_themes', [ $this, 'primeMoverExportThemesFunc' ], 10, 3);        
        add_filter('prime_mover_filter_basezip_folder', [$this, 'prependThemesFolderInsideZipArchive'], 10, 5);
        add_filter('prime_mover_filter_basezip_folder', [$this, 'prependLanguagesFolderInsideArchive'], 10, 5);
        
        add_filter('prime_mover_export_plugins_by_shell', [ $this, 'primeMoverExportPluginsFunc' ], 10, 1);
        add_filter( 'prime_mover_ajax_rendered_js_object', [$this, 'setExportMethodLists'], 110, 1 );
        
        /**
         * @since 1.1.3
         */
        add_action('prime_mover_do_after_export_button', [ $this, 'exportDoneDialog'], 20, 1);
    }
  
    /**
     * Shows import done dialog
     * @param int $blog_id
     * @compatibility 5.6
     */
    public function exportDoneDialog($blog_id = 0)
    {
        if ( ! $blog_id ) {
            return;
        }
        ?>
        <div style="display:none;" id="js-prime-mover-export-done-dialog-<?php echo esc_attr($blog_id); ?>" title="<?php esc_attr_e('Awesome!', 'prime-mover')?>"> 
            <h3 class="prime-mover-success-p-dialog"><?php esc_html_e('Your site has been exported successfully', 'prime-mover');?></h3>
			<ul class="prime-mover-ul-dialog">
                <li class="prime-mover-download-button-hero js-prime-move-download-button-hero-<?php echo esc_attr($blog_id); ?>"></li>
                <li class="prime-move-go-to-package-manager js-prime-move-go-to-package-manager-<?php echo esc_attr($blog_id); ?>"><?php esc_html_e('Or download it later in', 'prime-mover');?> 
                <a href="<?php echo esc_url($this->getExporter()->getSystemFunctions()->getBackupMenuUrl($blog_id)); ?>"><?php esc_html_e('Package Manager.', 'prime-mover');?></a></li>
            </ul>	  	
        </div>
    <?php
    }
    
    /**
     * Set export methods list
     * @param array $args
     * @return array
     */
    public function setExportMethodLists($args = [])
    {
        $args['prime_mover_export_method_lists'] = $this->getExporter()->getSystemInitialization()->getPrimeMoverExportMethods();
        
        return $args;
    }

    /**
     * Prepend plugins folder with correct paths inside zip
     * @param string $original_basename
     * @param array $ret
     * @param string $exporting_mode
     * @param string $source
     * @param boolean $shell_mode
     * @return string
     */
    public function prependPluginsFolderInsideZipArchive($original_basename = '', $ret = [], $exporting_mode = '', $source = '', $shell_mode = false)
    {
        if ('exporting_plugins' !== $exporting_mode) {
            return $original_basename;
        }
        if ( ! $source || ! $original_basename || empty($ret['temp_folder_path'])) {
            return $original_basename;
        }
        
        $plugin_foldername = $this->getPluginFoldername();
        $temp_folder_name = basename($ret['temp_folder_path']);
        if ($shell_mode) {
            return $temp_folder_name . '/' . $plugin_foldername . '/' . $original_basename;
        } else {
            return $temp_folder_name . '/' . $plugin_foldername;
        }
        
    }
    
    /**
     * Prepend languages folder inside archive
     * @param string $original_basename
     * @param array $ret
     * @param string $exporting_mode
     * @param string $source
     * @param boolean $shell_mode
     * @return string
     */
    public function prependLanguagesFolderInsideArchive($original_basename = '', $ret = [], $exporting_mode = '', $source = '', $shell_mode = false)
    {
        if ('exporting_languages_folders' !== $exporting_mode) {
            return $original_basename;
        }
        
        if (!$source || !$original_basename || empty($ret['temp_folder_path']) || empty($ret['languages_folder_path'])) {
            return $original_basename;
        }
        
        $languages_foldername = basename($ret['languages_folder_path']);
        $temp_folder_name = basename($ret['temp_folder_path']);
        
        if ($shell_mode) {
            return $temp_folder_name . '/' . $languages_foldername . '/' . $original_basename;
        } else {
            return $temp_folder_name . '/' . $languages_foldername;
        }        
    }
    
    /**
     * Prepend themes folder inside zip archive
     * @param string $original_basename
     * @param array $ret
     * @param string $exporting_mode
     * @param string $source
     * @param boolean $shell_mode
     * @return string
     */
    public function prependThemesFolderInsideZipArchive($original_basename = '', $ret = [], $exporting_mode = '', $source = '', $shell_mode = false)
    {
        if ('exporting_theme' !== $exporting_mode) {
            return $original_basename;
        }
        if ( ! $source || ! $original_basename || empty($ret['temp_folder_path'])) {
            return $original_basename;
        }
        
        $theme_foldername = $this->getThemeFoldername();
        $temp_folder_name = basename($ret['temp_folder_path']);
        
        if ($shell_mode) {
            return $temp_folder_name . '/' . $theme_foldername . '/' . $original_basename;  
        } else {
            return $temp_folder_name . '/' . $theme_foldername;
        }
              
    }
    
    /**
     * Export theme
     * @compatible 5.6
     * @param array $export_data
     * @param string $zippath
     * @param boolean $shell_mode
     */
    public function primeMoverExportThemesFunc($export_data = [], $zippath = '', $shell_mode = false)
    {
        if (! isset($export_data['export_system_footprint']['stylesheet']) || ! isset($export_data['export_system_footprint']['template'])) {
            return;
        }
        $stylesheet = $export_data['export_system_footprint']['stylesheet'];
        if (empty($stylesheet) || empty($export_data['temp_folder_path'])) {
            return;
        }        
  
        $tmp_folderpath = $export_data['temp_folder_path'];
        $theme_foldername = $this->getThemeFoldername();
        
        $themes_target_copy_path = $tmp_folderpath . $theme_foldername . DIRECTORY_SEPARATOR;        
        $make_directory_result = false;
        if (true === $shell_mode) {
            
            global $wp_filesystem;
            $make_directory_result = $wp_filesystem->mkdir($themes_target_copy_path);                       
        }
        
        if (false === $make_directory_result && true === $shell_mode) {
            return;
        } 
        
        $resource = $this->getExporter()->getCliArchiver()->openMasterTmpFileResource($export_data, '', 'ab');
        
        /**
         * @var resource $dir_resource
         * @var resource $file_resource
         */
        $file_resource = null;
        $dir_resource = null;
        if (is_array($resource) && !empty($resource)) {
            list($file_resource, $dir_resource) = $resource;
        }        
        if (is_resource($dir_resource) && $make_directory_result) {            
            $this->getExporter()->getCliArchiver()->writeMasterTmpLog($themes_target_copy_path, $dir_resource);
        }
        
        if (true === $shell_mode) {
            $stylesheet_copy = $this->handleThemeCopyShell($stylesheet, $themes_target_copy_path, $export_data, $resource);
        }
        
        if ( ! $stylesheet_copy ) {
            return;
        }
        
        $using_child_theme = 'no';
        if (isset($export_data['export_system_footprint']['using_child_theme'])) {
            $using_child_theme = $export_data['export_system_footprint']['using_child_theme'];
        }
        if ('yes' === $using_child_theme) {
            $template = $export_data['export_system_footprint']['template'];
            
            if (true === $shell_mode) {
                $this->handleThemeCopyShell($template, $themes_target_copy_path, $export_data, $resource);
            }          
        }        
        $this->getExporter()->getCliArchiver()->closeMasterTmpLog($resource);
        return true;
    }
     
    /**
     * Handle theme copying
     * @param array $stylesheet
     * @param string $themes_target_copy_path
     * @param array $export_data
     * @param array $resource
     * @return boolean|WP_Error|number|boolean
     */
    private function handleThemeCopyShell(array $stylesheet, $themes_target_copy_path = '', $export_data = [], $resource = [])
    {
        global $wp_filesystem;
        $ret = false;
        $stylesheet_name = key($stylesheet);
        
        $path_to_copy = $this->getThemeFullPath($stylesheet_name);
        if (! $path_to_copy) {
            return $ret;
        }
        $theme_target_copy_path = $themes_target_copy_path . $stylesheet_name . DIRECTORY_SEPARATOR;
        $theme_make_directory_result = $wp_filesystem->mkdir($theme_target_copy_path);
        if (false === $theme_make_directory_result) {
            return $ret;
        }
        
        return $this->getSystemCheckUtilities()->copyDir($path_to_copy, $theme_target_copy_path, [], [], false, false, 0, 0, 0, false, 'default', $resource);        
    }
    
    /**
     * Get plugins to export
     * @param array $export_data
     * @return array|array
     */
    public function getPluginsToExport($export_data = [])
    {
        if (! isset($export_data['export_system_footprint']['plugins'])) {
            return [];
        }
        $plugins = $export_data['export_system_footprint']['plugins'];
        if (empty($plugins) || empty($export_data['temp_folder_path'])) {
            return[];
        }
      
        $plugins = apply_filters('prime_mover_exclude_plugins_in_export', $plugins, $export_data);
        if ( ! is_array($plugins) ) {
            return [];
        }
        return array_keys($plugins);        
    }
 
    /**
     * Handle error copying plugins
     * @param mixed $copy_directory_result
     * @param string $error_message
     */
    protected function handleErrorCopyingPlugins($copy_directory_result, $error_message = '')
    {
        if (is_wp_error($copy_directory_result)) {
            $copying_error = $copy_directory_result->get_error_message();
            $copying_error_data = $copy_directory_result->get_error_data();
            if (is_array($copying_error_data)) {
                $copying_error_data = implode(',', $copying_error_data);
            }
            $error_message = $copying_error . ' ' . $copying_error_data;
        } 
        
        do_action( 'prime_mover_shutdown_actions', ['type' => 1, 'message' => $error_message] );
        wp_die();
    }
    
    /**
     * Get plugins list for re-processing
     * @param array $plugins_list
     * @param string $cli_tmpname
     * @return mixed
     */
    public function getPluginListForReprocessing($plugins_list = [], $cli_tmpname = '')
    {
        global $wp_filesystem;
        if ($this->getExporter()->getSystemFunctions()->nonCachedFileExists($cli_tmpname)) {            
            $json_string = $wp_filesystem->get_contents($cli_tmpname);
            $plugins_list = json_decode($json_string, true);
            $this->getExporter()->getSystemFunctions()->primeMoverDoDelete($cli_tmpname, true);
        }
        return $plugins_list;
    }
    
    /**
     * Get plugins for export in CLI
     * @param array $export_data
     * @param array $resources
     * @return string[]|mixed[]|array[]
     */
    public function getPluginsForExport($export_data = [], $resources = [])
    {
        global $wp_filesystem;
        $blog_id = $this->getBlogIdFromExporterArray($export_data);
        if (! isset($export_data['export_system_footprint']['plugins'])) {
            $this->handleErrorCopyingPlugins('', esc_html__('Plugins array does not exists', 'prime-mover'));
        }
        $plugins = $export_data['export_system_footprint']['plugins'];
        if (empty($plugins) || empty($export_data['temp_folder_path'])) {
            $this->handleErrorCopyingPlugins('', esc_html__('Tmp folder path does not exist', 'prime-mover'));
        }
        
        $tmp_folderpath = $export_data['temp_folder_path'];
        $plugin_foldername = $this->getPluginFoldername();        
        $plugins_target_copy_path = $tmp_folderpath . $plugin_foldername . DIRECTORY_SEPARATOR;
        
        global $wp_filesystem;
        $directory_created = false;
        if ( ! $this->getExporter()->getSystemFunctions()->nonCachedFileExists($plugins_target_copy_path)) {
            $make_directory_result = $wp_filesystem->mkdir($plugins_target_copy_path);
            if (false === $make_directory_result) {
                $this->handleErrorCopyingPlugins('', esc_html__('Cannot create target plugins directory.', 'prime-mover'));
            } else {
                $directory_created = true;
            }            
        }
        
        /** @var mixed $file_resource */
        $file_resource = null;
        $dir_resource = null;
        if (is_array($resources) && !empty($resources)) {          
            list($file_resource, $dir_resource) = $resources;
        }
            
        if ($directory_created && is_resource($dir_resource)) {
            fwrite($dir_resource, $plugins_target_copy_path . PHP_EOL);
        }        
        
        $plugins = apply_filters('prime_mover_exclude_plugins_in_export', $plugins, $export_data);
        $plugins_list = [];
        if ( ! is_array($plugins) ) {
            $this->handleErrorCopyingPlugins('', esc_html__('Invalid plugins data format - should be array.', 'prime-mover'));
        }
        $plugins_list = array_keys($plugins);
        $cli_tmpname = $this->getExporter()->getSystemInitialization()->generateCliReprocessingTmpName($export_data, $export_data['process_id'], $export_data['shell_progress_key']);
        $plugins_list = $this->getPluginListForReprocessing($plugins_list, $cli_tmpname);
        
        do_action('prime_mover_log_processed_events', "Archiving these list of plugins: ", $blog_id, 'export', 'primeMoverExportPluginsFunc', $this);
        do_action('prime_mover_log_processed_events', $plugins_list, $blog_id, 'export', 'primeMoverExportPluginsFunc', $this);
        
        return [$plugins_list, $plugins_target_copy_path, $cli_tmpname];
    }
    
    /**
     * Get blog ID from exporter array
     * @param array $ret
     * @return number
     */
    protected function getBlogIdFromExporterArray($ret = [])
    {
        $blog_id = 0;
        if ( ! empty($ret['original_blogid'] ) ) {
            $blog_id = $ret['original_blogid'];
        }
        return $blog_id;
    }
    
    /**
     * Export plugins by whole directory (used in shell mode)
     * @param array $export_data
     * @return string|void|WP_Error|boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExportUtilities::itExportsPluginsInShellMode()
     */
    public function primeMoverExportPluginsFunc($export_data = [])
    {
        global $wp_filesystem;
        $resource = $this->getExporter()->getCliArchiver()->openMasterTmpFileResource($export_data, '', 'ab');
        list($plugins_list, $plugins_target_copy_path, $cli_tmpname) = $this->getPluginsForExport($export_data, $resource);  
        $blog_id = 0;
        
        if (isset($export_data['original_blogid'])) {
            $blog_id = $export_data['original_blogid'];
        }  
        
        foreach ($plugins_list as $k => $plugin) {
            $path_to_copy = $this->getPluginFullPath($plugin);
            if (! $path_to_copy) {
                continue;
            }            
            $ds = DIRECTORY_SEPARATOR;
            if ($this->isPluginFile($path_to_copy)) {
                $ds = '';
            }
            
            $plugin_dirname = basename($path_to_copy);
            $plugin_target_copy_path = $plugins_target_copy_path . $plugin_dirname . $ds;
            
            if ($ds) {                
                $wp_filesystem->mkdir($plugin_target_copy_path);
            }
            $copy_directory_result = '';
            if ($ds) {
                $copy_directory_result = $this->getExporter()->getSystemFunctions()->recurseCopy($path_to_copy, $plugin_target_copy_path, $export_data, $plugins_list,
                    ['source' => __METHOD__, 'mode' => 'export', 'blog_id' => $blog_id], $resource);                 
            } else {
                $this->getExporter()->getCliArchiver()->writeMasterTmpLog($plugin_target_copy_path, $resource);
                $copy_directory_result = $wp_filesystem->copy($path_to_copy, $plugin_target_copy_path, true);
            }
            
            if (is_wp_error($copy_directory_result)) {                
                $this->handleErrorCopyingPlugins($copy_directory_result, '');
            } elseif ($this->getExporter()->getSystemFunctions()->nonCachedFileExists($cli_tmpname) ) {                
                return $copy_directory_result;
            } else {                
                unset($plugins_list[$k]);
            }
        }
        $this->getExporter()->getCliArchiver()->closeMasterTmpLog($resource);
        return true;
    } 
    
    /**
     * Exclude media resource implementation
     * @since 1.0.6
     * @param boolean $ret
     * @param string $file
     * @param array $exclusion_rules
     * @param string $source
     * @param boolean $exporting_media
     * @return string|boolean
     */
    public function maybeExcludeThisMediaResource($ret = false, $file = '', $exclusion_rules = [], $source = '', $exporting_media = false)
    {
        if ( ! $exporting_media ) {
            return $ret;
        }
        $file = wp_normalize_path($file);
        $source = wp_normalize_path($source);
        $skip_files_directories = [];
        if ( ! empty($exclusion_rules['skip_files_directories']) ) {
            $skip_files_directories = $exclusion_rules['skip_files_directories'];
        }
        $skip_by_extensions = [];
        if ( ! empty($exclusion_rules['skip_by_extensions']) ) {
            $skip_by_extensions = $exclusion_rules['skip_by_extensions'];
        }
        $check = str_replace(trailingslashit($source), '', $file);
        $dirpath = dirname($file);
        /**
         * First, we need to check if parent directory of a file is already excluded
         */
        if ($this->checkIfDirIsExcluded($dirpath , $skip_files_directories, $source, $file)) {
            return true;
        }
        /**
         * Second, we need to check if the given file itself is a directory which is also excluded
         */        
        if (is_dir($file) && $this->checkIfDirIsExcluded($file , $skip_files_directories, $source, $file)) {
            return true;
            
        } 
        /**
         * Lastly if its a file, we need to check if its excluded by extension and if the file is excluded by name itself
         */
        if (is_file($file) && $this->checkIfFileIsExcluded($file, $skip_files_directories, $skip_by_extensions, $check)) {
            return true;
        }

        return $ret;        
    }
    
    /**
     * Returns true if given file is excluded
     * @param string $given_file
     * @param array $skip_files_directories
     * @param array $skip_by_extensions
     * @param string $check
     * @return boolean
     */
    private function checkIfFileIsExcluded($given_file = '', $skip_files_directories = [], $skip_by_extensions = [], $check = '')
    {
        if ( ! $given_file || ! $check) {
            return false;
        }
        $ext = strtolower(pathinfo($given_file, PATHINFO_EXTENSION));
        if (in_array($ext, $skip_by_extensions, true)) {
            return true;
        }
        if (in_array($check, $skip_files_directories, true)) {
            return true;
        } 
        return false;
    }
    
    /**
     * Returns true if given directory is excluded
     * @param string $given_dir
     * @param array $skip_files_directories
     * @param string $source
     * @param string $file
     * @return boolean
     */
    private function checkIfDirIsExcluded($given_dir = '', $skip_files_directories = [], $source = '', $file = '')
    {
        if ( ! $given_dir || ! $source || ! $file) {
            return false;
        }
        foreach ($skip_files_directories as $relative) {
            $excluded = trailingslashit($source) . $relative;
            if (is_dir($excluded) && false !== strpos($file, $excluded)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Filter media root folder name should use the correct blog ID
     * @param string $original_basename
     * @param array $ret
     * @param string $exporting_mode
     * @return string|number
     * @since 1.0.6
     */
    public function filterMediaFolderForMultisite($original_basename = '', $ret = [], $exporting_mode = '')
    {
        if ( ! $original_basename || empty($ret) || empty($ret['original_blogid']) || empty($ret['prime_mover_export_targetid'])) {
            return $original_basename;
        }
        if ( 'exporting_media' !== $exporting_mode ) {
            return $original_basename;
        }
        $original_blogid = (int)$ret['original_blogid'];
        $export_blogid = (int)$ret['prime_mover_export_targetid'];
        $temp_folder_name = basename($ret['temp_folder_path']);
        
        if ($original_blogid === $export_blogid) {
            return $temp_folder_name . '/' . $original_blogid;
        } else {
            return $temp_folder_name . '/' . $export_blogid;  
        }   
    }

    /**
     * Checks if we need to adjust blog ID for export to multisite
     * @param number $blogid_to_export
     * @param array $ret
     * @return number
     * @since 1.0.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExportUtilities::itAdjustIdForMultisiteExport() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExportUtilities::itReturnsSameIdIfNotExportingToFromMultisite()
     */
    public function adjustIdForMultisiteExport($blogid_to_export = 0, $ret = [])
    {
        if ( empty($ret['prime_mover_export_targetid']) ) {
            /**
             * Export ID isn't adjust, return.
             */
            return $blogid_to_export;
        }
        $export_target_id = (int)$ret['prime_mover_export_targetid'];
        if ($export_target_id > 0) {
            return $export_target_id;
        }        
        return $blogid_to_export;
    }
    
    /**
     * Setup ongoing export process error
     * @param array $args
     * @return array
     */
    public function setExportProcessError( array $args )
    {        
        $args['prime_mover_exportprocess_error_message'] = esc_js(
            "<p>" . sprintf(__('Export process fails for site ID : {{BLOGID}}. Retry is attempted but still fails after %s seconds.', 'prime-mover'), '<strong>{{RETRYSECONDS}}</strong>') . "</p>" .
            "<p>" . '<strong>' . __('Server Error : {{PROGRESSSERVERERROR}}', 'prime-mover') . '</strong>' . "</p>" .
            "<p>" . __('Error occurs while processing', 'prime-mover') . ' ' . "<strong>{{EXPORTMETHODWITHERROR}}</strong>" . ' ' . __('method.', 'prime-mover') . "</p>" . 
            "<p><strong>" . sprintf(__('Can you try increasing the web server timeout beyond %s', 'prime-mover'), '<strong>{{FIXEDSECONDS}}</strong>') . ' ' . __('seconds', 'prime-mover') ."?</strong></p>" .
            "<p>" . __('This might help resolve this issue when exporting large sites.', 'prime-mover') . "</p>"
                );
        
        $args['prime_mover_unknown_export_process_error'] = esc_js(__('unknown', 'prime-mover')); 
        
        return $args;
    }
    
    /**
     * Add site title to footprint
     * @param array $export_system_footprint
     * @param array $ret
     * @param number $blogid_to_export
     * @return array
     */
    public function addSiteTitleToFootPrint($export_system_footprint = [], $ret = [], $blogid_to_export = 0)
    {
        $export_system_footprint['site_title'] = $this->getExporter()->getSystemFunctions()->getSiteTitleGivenBlogId($blogid_to_export);        
        return $export_system_footprint;
    }
    
    /**
     * Filter footprint dB prefix
     * @param array $footprint
     * @return array
     * @updated 1.0.6
     */
    public function filterFootprintRandomPrefix($footprint = [])
    {     
        if ( ! $this->getExporter()->getSystemInitialization()->getMaybeRandomizeDbPrefix()) {
            return $footprint;
        } 
        if (empty($footprint['db_prefix'])) {
            return $footprint;  
        }
        $footprint['db_prefix'] = $this->getExporter()->getSystemInitialization()->getRandomPrefix();
        return $footprint;
    }
    
    /**
     * Rename tables consistent with random db prefix
     * @param array $ret
     * @param array $clean_tables
     * @param number $blogid_to_export
     * @updated 1.0.6
     * @return array
     */
    public function filterTablesForWriting($ret = [], $clean_tables = [], $blogid_to_export = 0)
    {
        if ( ! $this->getExporter()->getSystemInitialization()->getMaybeRandomizeDbPrefix() || ! $blogid_to_export) {
            return $ret;
        } 
        
        if (isset($ret['bp_randomizedbprefixstring'])) {
            return $ret;
        }
        
        $current_prefix = $this->getExporter()->getSystemFunctions()->getDbPrefixOfSite($blogid_to_export);
        $renamed_tables = [];
        if ( ! empty($clean_tables) ) {
            $random_prefix = $this->getExporter()->getSystemInitialization()->getRandomPrefix();
            foreach ($clean_tables as $table) {
                $renamed_tables[] = str_replace($current_prefix, $random_prefix, $table);
            }            
        }
        
        if ( ! empty($renamed_tables) ) {
            $ret['exported_db_tables'] = $renamed_tables;
            return $ret;
        }        
        
        return $ret;
    }
    
    /**
     * Set export mode dialog texts
     * @param array $args
     * @return array
     */
    public function setExportModeDialogTexts( array $args )
    {
        $args['prime_mover_export_mode_texts'] = $this->getExportModes();
        return $args;
    }
     
    /**
     * Create a blog ID text file for user to easily know the blog ID associated with this package
     * @param string $folderpath
     * @param int $blog_id
     * @compatible 5.6
     */   
     public function writeBlogID($folderpath = '', $blog_id = 0, $export_system_footprint = [], $ret = []) 
    {
        if ( ! $folderpath || ! $blog_id ) {
            return;
        }
        global $wp_filesystem;
        $wp_filesystem->put_contents($folderpath . 'blogid.txt', $blog_id);      
        
        if (empty($ret['target_zip_path'])){
            return;
        } 
        $blogid_txt_path = trailingslashit($folderpath) . 'blogid.txt';
        $local_name = trailingslashit(basename($folderpath)) . 'blogid.txt';
        apply_filters('prime_mover_add_file_to_tar_archive', $ret, $ret['target_zip_path'], 'ab', $blogid_txt_path, $local_name , 0, 0, $blog_id, false, false);       
    }
    
    /**
     * Get export option
     * @param string $export_option
     * @param number $blog_id
     * @return string
     * @compatible 5.6
     */
    public function getExportOption($export_option = '', $blog_id = 0)
    {
        if (! $export_option) {
            return $export_option;
        }
        if (in_array($export_option, $this->getValidExportOptions())) {
            return $export_option;
        } else {
            return '';
        }
    }
    /**
     * Markup for export dialog
     * @param int $blog_id
     * @compatible 5.6
     * @mainsitesupport_affected
     */
    public function exportDialog($blog_id = 0)
    {
        ?>
        <div style="display:none;" id="js-prime-mover-export-dialog-confirm-<?php echo esc_attr($blog_id); ?>" 
        	title="<?php echo apply_filters('prime_mover_filter_export_dialog_title', esc_attr__('Export Options', 'prime-mover'), $blog_id) ?>" >            
            	    <?php do_action('prime_mover_before_export_options', $blog_id); ?>
            	<?php if (is_multisite()) {?>
            	<p>
            	<?php 
            	esc_html_e('Please select the export options for blog ID', 'prime-mover'); ?>: <strong><?php echo $blog_id; 
            	?>
            	</strong>
            	</p>
            	<?php } else {?>  
            	<p>
            	<?php 
            	esc_html_e('Please select the export options for this site', 'prime-mover'); ?> :
            	</p>  	
            	<?php }?>          		  	
    		  	<p class="prime-mover-migration-tools-p"><label><input autocomplete="off" type="radio" name="prime-mover-export-mode-<?php echo esc_attr($blog_id); ?>" value="dbonly_export"> 
    		  		<?php esc_html_e('Export database ONLY', 'prime-mover')?>
    		  		<span title="<?php esc_attr_e( 'Migrate only database. This is used if only database changes are needed in migration. For example, you want to test some new WordPress settings before pushing all these settings to the live site.', 'prime-mover' ); ?>" 
    		  		class="prime-mover-export-option-info">&#9432;</span></label>
    		  	</p>
				
				<p class="prime-mover-migration-tools-p"><label><input autocomplete="off" type="radio" name="prime-mover-export-mode-<?php echo esc_attr($blog_id); ?>" value="db_and_media_export"> 
					<?php esc_html_e('Export database and media files ONLY', 'prime-mover')?>
					<span title="<?php esc_attr_e( 'Migrate only database and media. For example, you are doing some website redesign work on your local site that involves adding new pages, posts and new images. You can migrate this to the live site using this option. It is recommended to clean up uploads directory before creating this package.', 'prime-mover' ); ?>" 
					class="prime-mover-export-option-info">&#9432;</span></label>
				</p>

				<p class="prime-mover-migration-tools-p"><label><input autocomplete="off" type="radio" name="prime-mover-export-mode-<?php echo esc_attr($blog_id); ?>" value="development_package"> 
					<?php esc_html_e('Export package for WordPress debugging', 'prime-mover')?>
					<span title="<?php 
					esc_attr_e( 'This package should only be used for WordPress debugging purposes. This package does not include any media files. There might be some missing images at the target site. Do not use this option if you need all images to fully work at the target site.', 'prime-mover' ); ?>" 
    		  		class="prime-mover-export-option-info">&#9432;</span></label>
				</p>
									
				<p class="prime-mover-migration-tools-p"><label><input autocomplete="off" checked="checked" type="radio" name="prime-mover-export-mode-<?php echo esc_attr($blog_id); ?>" value="complete_export_mode"> 
				<?php esc_html_e('Export database, media files, plugins and themes.', 'prime-mover')?>				
				<span title="<?php esc_attr_e( 'This is complete package. You need this option if you want to migrate everything including your plugins and themes. A example usage would be to perform a full-site migration from one server to another or if you want a full-site backup. This will create the largest package size.', 'prime-mover' ); ?>" 
    		  		class="prime-mover-export-option-info">&#9432;</span>
				</label>
				</p>
				
				<h3><?php esc_html_e('Export type (required)', 'prime-mover' )?></h3>
		            <p class="prime-mover-migration-tools-p">
		            <label class="js-prime-mover-export-as-singlesite-label-<?php echo esc_attr($blog_id); ?>">
		            <input autocomplete="off" id="js-prime-mover-export-as-single-site-<?php echo esc_attr($blog_id); ?>" data-blog-id="<?php echo esc_attr($blog_id); ?>"
		            class="js-prime-mover-export-type" type="radio" name="prime-mover-export-type-<?php echo esc_attr($blog_id); ?>" value="single-site-export"> 
		            <?php 
		            echo esc_html__('Export to single-site format', 'prime-mover');
		            ?>
		            </label>
		            </p>
		            <?php 
		            if (is_multisite()) {
		            ?>
		                <p class="prime-mover-migration-tools-p">
		                <label class="js-prime-mover-export-as-multisitebackup-label-<?php echo esc_attr($blog_id); ?>">
		                <input autocomplete="off" id="js-prime-mover-export-as-multisitebackup-<?php echo esc_attr($blog_id); ?>" data-blog-id="<?php echo esc_attr($blog_id); ?>"
		                class="js-prime-mover-export-type" type="radio" name="prime-mover-export-type-<?php echo esc_attr($blog_id); ?>" value="multisitebackup-export"> 
		                <?php 
		                echo esc_html__('Export as subsite backup', 'prime-mover');
		                ?>
		                </label>
		                </p>
		            <?php 
		            }
		            ?>							   		            
		            
		            <p class="prime-mover-migration-tools-p">
		            <label class="js-prime-mover-export-as-multisite-label-<?php echo esc_attr($blog_id); ?>">
		            <input autocomplete="off" id="js-prime-mover-export-as-multisite-<?php echo esc_attr($blog_id); ?>" data-blog-id="<?php echo esc_attr($blog_id); ?>"
		            class="js-prime-mover-export-type" type="radio" name="prime-mover-export-type-<?php echo esc_attr($blog_id); ?>" value="multisite-export"> 
		            <?php 
		            echo esc_html__('Export to multisite format', 'prime-mover');
		            ?>
		            </label>
		            </p>
    		        <div style="display:none" class="prime-mover-export-to-multisite-div" id="js-prime-mover-export-to-multisite-div-<?php echo esc_attr($blog_id); ?>">
    		            <p class="prime-mover-migration-tools-p">
        				    <input id="js-prime-mover-export-targetid-<?php echo esc_attr($blog_id); ?>" autocomplete="off" type="text" name="prime-mover-export-targetid" value="">
        	                <span title="<?php 
					        esc_attr_e( 'Multisite target blog ID should be an integer greater than 0', 'prime-mover' ); ?>" 
    		  		        class="prime-mover-export-option-info">&#9432;</span>    		  		        			    
    				    </p>
    				    <p class="prime-mover-enter-multisite-id prime-mover-migration-tools-p"><?php echo esc_html__('Enter multisite target blog ID (integers only)', 'prime-mover'); ?>.</p>
    				    <p class="prime-mover-get-blogid-link prime-mover-migration-tools-p"><a class="prime-mover-external-link" target="_blank" href="<?php echo esc_url(PRIME_MOVER_GET_BLOGID_TUTORIAL); ?>">
    				    <?php echo esc_html__('How to get multisite target blog ID ?', 'prime-mover'); ?></a></p>
				    </div>				    				
				
				<?php		
				do_action('prime_mover_dothings_export_dialog', $blog_id );								
				?>              	
        </div>
    <?php
    }
    
    /**
     * Exclude Prime mover plugin itself in the export
     * @param array $plugins
     * @return array
     * @compatible 5.6
     */
    public function excludePrimeMoverPluginInExport(array $plugins)
    {        
        $allowed  = apply_filters('prime_mover_standard_extensions', ['prime-mover.php'] );
        $plugins = array_filter(
            $plugins,
            function ($key) use ($allowed) {
                $ret = true;
                $filename = basename($key);
                if (in_array($filename, $allowed)) {
                    $ret = false;
                }
                return $ret;
            },
            ARRAY_FILTER_USE_KEY
            );
        
        return $plugins;
    }
    
    /**
     * Get plugin folder name
     * @return string
     * @compatible 5.6
     */
    public function getPluginFoldername()
    {
        return basename(PRIME_MOVER_PLUGIN_CORE_PATH);
    }
    
    /**
     * Get theme folder name
     * @return string
     * @compatible 5.6
     */
    public function getThemeFoldername()
    {
        return basename(PRIME_MOVER_THEME_CORE_PATH);
    }
    
    /**
     * Get theme full path
     * @param string $theme
     * @return string
     * @compatible 5.6
     */
    public function getThemeFullPath($theme = '', $exist_check = true)
    {
        return $this->getExporter()->getSystemFunctions()->getThemeFullPath($theme, $exist_check);
    }
    
    /**
     * Checks if valid plugin file
     * @param string $input
     * @return boolean
     */
    public function isPluginFile($input = '') {
        $ret = false;
        if ( ! $input ) {
            return $ret;    
        }
        if ('php' === pathinfo($input, PATHINFO_EXTENSION) ) {
            $ret = true;    
        }
        return $ret;
    }
    
    /**
     * Get export mode of a given backup
     * @param number $blog_id
     * @param string $sanitized_name
     * @param boolean $output_slug
     * @return string
     */
    public function getExportModeOfThisBackup($blog_id = 0, $sanitized_name = '', $output_slug = false)
    {
        $export_mode = '';
        if ( ! $blog_id || ! $sanitized_name ) {
            return $export_mode;
        }
        
        $option_name = $this->getExporter()->getSystemInitialization()->generateZipDownloadOptionName($sanitized_name, $blog_id);        
        $setting = $this->getExporter()->getSystemFunctions()->getSiteOption($option_name, false, true, true);        
        
        if ( ! $setting || empty($setting['export_option'])) {
            return $export_mode;
        }
        $export_mode_db = $setting['export_option'];
        $export_modes = $this->getExportModes();
        if ( ! isset($export_modes[$export_mode_db])) {
            return $export_mode;
        }
        if ($output_slug) {
            return $export_mode_db;
        }
        $export_mode = $export_modes[$export_mode_db];
        return $export_mode;
    }
}
