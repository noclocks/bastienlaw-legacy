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

use Codexonics\PrimeMoverFramework\interfaces\PrimeMoverSystemCheck;
use Codexonics\PrimeMoverFramework\utilities\PrimeMoverSystemCheckUtilities;
use Generator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The Prime Mover System Checks Class
 *
 * The Prime Mover System Check Class aims to provide the system checks for the facility of this plugin.
 *
 */
class PrimeMoverSystemChecks implements PrimeMoverSystemCheck
{       
    private $system_check_utilities;
    
    /**
     * Constructor
     * @param PrimeMoverSystemCheckUtilities $system_check_utilities
     */
    public function __construct(
        PrimeMoverSystemCheckUtilities $system_check_utilities
    ) 
    {
        $this->system_check_utilities = $system_check_utilities;
    }
    
    /**
     * @compatible 5.6
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverSystemCheckUtilities
     */
    public function getSystemCheckUtilities() 
    {
        return $this->system_check_utilities;
    }
    
    /**
     * Get system functions
     * @compatible 5.6
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     */
    public function getSystemFunctions()
    {
        return $this->getSystemCheckUtilities()->getSystemFunctions();
    }
    
    /**
     * Get System Initialization
     * @compatible 5.6
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     */
    public function getSystemInitialization()
    {
        return $this->getSystemCheckUtilities()->getSystemInitialization();
    }
    
    /**
     * Get System authorization
     * @compatible 5.6
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     */
    public function getSystemAuthorization()
    {
        return $this->getSystemCheckUtilities()->getSystemAuthorization();
    }
    
    /**
     * System hooks
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemChecks::itAddsInitHooks()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemChecks::itChecksIfHooksAreOutdated()
     */
    public function systemCheckHooks()
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        add_filter('prime_mover_filter_site_footprint', [$this, 'addSchemeToSystemFootprint'], 10, 2);
        add_filter('prime_mover_filter_site_footprint', [$this, 'addUploadInfoToSystemFootprint'], 10, 2);
        
        add_filter('prime_mover_can_decrypt_media', [$this, 'canDecryptMedia'], 10, 3);
        add_filter('prime_mover_save_return_import_progress', [$this, 'normalizePathForWindows'], 0, 1);
        add_filter('prime_mover_save_return_export_progress', [$this, 'normalizePathForWindows'], 0, 1);
    }
    
    /**
     * Ideally before path related settings are saved to database.
     * We should normalize the path under Windows
     * @param array $ret
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemChecks::itNormalizesPathForWindows()
     */
    public function normalizePathForWindows($ret = [])
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        if ( ! is_array($ret) ) {
            return $ret;
        }
        if ( ! $this->isWindows() ) {
            return $ret;
        }
        if ( ! empty( $ret['file'] ) ) {
            $ret['file'] = wp_normalize_path($ret['file']);
        }
        if ( ! empty($ret['unzipped_directory']) ) {
            $ret['unzipped_directory'] = wp_normalize_path($ret['unzipped_directory']);
        }
        if ( ! empty($ret['origin_wp_root']) ) {
            $ret['origin_wp_root'] = wp_normalize_path($ret['origin_wp_root']);
        }
        if ( ! empty($ret['target_wp_root']) ) {
            $ret['target_wp_root'] = wp_normalize_path($ret['target_wp_root']);
        }
        if ( ! empty($ret['upload_path_information']) ) {
            $ret['upload_path_information'] = wp_normalize_path($ret['upload_path_information']);
        }
        if ( ! empty($ret['upload_information_path']) ) {
            $ret['upload_information_path'] = wp_normalize_path($ret['upload_information_path']);
        }
        if ( ! empty($ret['temp_folder_path'] ) ) {
            $ret['temp_folder_path'] = wp_normalize_path($ret['temp_folder_path']);
        }
        if ( ! empty($ret['blogsdir_target_copy_path']) ) {
            $ret['blogsdir_target_copy_path'] = wp_normalize_path($ret['blogsdir_target_copy_path']);
        }
        if ( ! empty($ret['target_zip_path']) ) {
            $ret['target_zip_path'] = wp_normalize_path($ret['target_zip_path']);
        }
        if ( ! empty($ret['imported_package_footprint']['wp_root'])) {
            $ret['imported_package_footprint']['wp_root'] = wp_normalize_path($ret['imported_package_footprint']['wp_root']);
        }
        if ( ! empty($ret['imported_package_footprint']['upload_path_information'])) {
            $ret['imported_package_footprint']['upload_path_information'] = wp_normalize_path($ret['imported_package_footprint']['upload_path_information']);
        }
        if ( ! empty($ret['export_system_footprint']['wp_root'])) {
            $ret['export_system_footprint']['wp_root'] = wp_normalize_path($ret['export_system_footprint']['wp_root']);
        }
        if ( ! empty($ret['export_system_footprint']['upload_information_path'])) {
            $ret['export_system_footprint']['upload_information_path'] = wp_normalize_path($ret['export_system_footprint']['upload_information_path']);
        }        
        if ( ! empty($ret['canonical_uploads_information']['path'])) {
            $ret['canonical_uploads_information']['path'] = wp_normalize_path($ret['canonical_uploads_information']['path']);
        }        
        if ( ! empty($ret['canonical_uploads_information']['basedir'])) {
            $ret['canonical_uploads_information']['basedir'] = wp_normalize_path($ret['canonical_uploads_information']['basedir']);
        }        
        if ( ! empty($ret['root_uploads_information']['path'])) {
            $ret['root_uploads_information']['path'] = wp_normalize_path($ret['root_uploads_information']['path']);
        }
        if ( ! empty($ret['root_uploads_information']['basedir'])) {
            $ret['root_uploads_information']['basedir'] = wp_normalize_path($ret['root_uploads_information']['basedir']);
        }        
        if ( ! empty($ret['user_import_tmp_log'])) {
            $ret['user_import_tmp_log'] = wp_normalize_path($ret['user_import_tmp_log']);
        }        
        if ( ! empty($ret['imported_package_footprint']['upload_information_path'])) {
            $ret['imported_package_footprint']['upload_information_path'] = wp_normalize_path($ret['imported_package_footprint']['upload_information_path']);
        }
        if ( ! empty($ret['main_search_replace_replaceables']['wpupload_path']['search'])) {
            $ret['main_search_replace_replaceables']['wpupload_path']['search'] = wp_normalize_path($ret['main_search_replace_replaceables']['wpupload_path']['search']);
        }
        if ( ! empty($ret['copymedia_shell_tmp_list']) ) {
            $ret['copymedia_shell_tmp_list'] = wp_normalize_path($ret['copymedia_shell_tmp_list']);
        }
        return $ret;
    }
    
    /**
     * Add upload info to system footprint
     * @param array $footprint
     * @param int $blogid_to_export
     * @compatible 5.6
     */
    public function addUploadInfoToSystemFootprint(array $footprint, $blogid_to_export = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $footprint;
        }
        if (! $blogid_to_export || empty($footprint)) {
            return $footprint;
        }
        $upload_information_path = $this->getSystemFunctions()->primeMoverGetBlogsDirPath($blogid_to_export);
        $upload_information_url = $this->getSystemFunctions()->primeMoverGetBlogsDirUrl($blogid_to_export);
        
        if (! $upload_information_path || ! $upload_information_url) {
            return $footprint;
        }
        
        $upload_information_path = rtrim($upload_information_path, DIRECTORY_SEPARATOR);
        $footprint['upload_information_path'] = $upload_information_path;
        $footprint['upload_information_url'] = $upload_information_url;
        
        $legacy_base_url = $this->getSystemCheckUtilities()->getLegacyBaseURL($blogid_to_export);
        if ( $this->getSystemCheckUtilities()->isLegacyMultisiteBaseURL($blogid_to_export) && $legacy_base_url ) {
            $footprint['legacy_upload_information_url'] = $legacy_base_url;
        }
        
        return $footprint;
    }
    
    /**
     * Add site scheme to system footprint
     * @param array $footprint
     * @param int $blogid_to_export
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemChecks::itAddSchemeToSystemFootprintIfSsl() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemChecks::itAddSchemeToSystemFootprintIfNonSsl() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemChecks::itDoesNotAddSchemeToSystemFootprintIfUnauthorized()
     */
    public function addSchemeToSystemFootprint(array $footprint, $blogid_to_export = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $footprint;
        }
        if (! $blogid_to_export || empty($footprint)) {
            return $footprint;
        }      
        
        $footprint['scheme'] = $this->getSystemFunctions()->getUrlSchemeOfThisSite();        
        return $footprint;
    }

    /**
     * Check if essential plugin requirements are meet.
     * @return boolean
     * {@inheritDoc}
     * @see PrimeMoverSystemCheck::primeMoverEssentialRequisites()
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itReturnsTrueWhenAllRequirementsMeet()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itReturnsFalseWhenFolderNotCreated() 
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itReturnsFalseWhenFileSystemNotInitialized()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itReturnsFalseWhenItDoesNotHaveSystem()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itReturnsFalseWhenItDoesNotHaveOpenSSLSupport()
     * {@inheritDoc}
     * @see \Codexonics\PrimeMoverFramework\interfaces\PrimeMoverSystemCheck::primeMoverEssentialRequisites()
     */
    public function primeMoverEssentialRequisites($doing_import = false)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $all_requisites_meet = false;
        /**
         * Export folder check
         */
        if ((true === $this->getSystemInitialization()->getMultisiteExportFolderCreated($doing_import )) &&
            /**
             * WP filesystem check
             */
            (true === $this->getSystemInitialization()->getMultisiteWpFilesystemInitialized())
            ) {
            $all_requisites_meet = true;
        }
        return $all_requisites_meet;
    }
        
    /**
     * Check if mbstring extension is running
     * Publicly overridable no auth check
     * @return boolean
     * @compatible 5.6
     */
    public function primeMoverCheckIfMbstringEnabled()
    {
        $enabled = false;
        if (extension_loaded('mbstring')) {
            $enabled = true;
        }
        return $enabled;
    }
    
    /**
     * @compatible 5.6
     * Generate system footprint for checking system compatibility when doing export/import
     * {@inheritDoc}
     * @see PrimeMoverSystemCheck::primeMoverGenerateSystemFootprint()
     */
    public function primeMoverGenerateSystemFootprint($blogid_to_export = 0, $tmp_folderpath = '', $ret = [])
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        $activated_plugins_list = [];
        $footprint = [];
        
        $activated_plugins_list = $this->getSystemFunctions()->getActivatedPlugins($blogid_to_export, $activated_plugins_list);        
        global $wp_filesystem;
        
        foreach ($activated_plugins_list as $plugin_to_check) {
            $plugin_full_path = PRIME_MOVER_PLUGIN_CORE_PATH . $plugin_to_check;
            
            if (! $wp_filesystem->exists($plugin_full_path)) {
                continue;
            }
            
            $plugin_meta_data = get_plugin_data($plugin_full_path);
            if ((is_array($plugin_meta_data)) && (isset($plugin_meta_data['Version']))) {
                if (!empty($plugin_meta_data['Version'])) {
                    $footprint['plugins'][	$plugin_to_check ]	= $plugin_meta_data['Version'];
                }
            }
        }
       
        $stylesheet = $this->getSystemFunctions()->getBlogOption($blogid_to_export, 'stylesheet');
        $theme_template = $this->getSystemFunctions()->getBlogOption($blogid_to_export, 'template');
        $stylesheet_info = wp_get_theme($stylesheet, PRIME_MOVER_THEME_CORE_PATH);
        $theme_template_info = wp_get_theme($theme_template, PRIME_MOVER_THEME_CORE_PATH);        
        $using_child_theme = $this->getSystemFunctions()->isUsingChildTheme($blogid_to_export);         
        
        $stylesheet_version = $stylesheet_info->get('Version');
        if (empty($stylesheet_version) ) {
            $stylesheet_version = '1.0.0';
        }
        $theme_template_version = $theme_template_info->get('Version');
        if (empty($theme_template_version)) {
            $theme_template_version = '1.0.0';
        }
        $footprint['stylesheet'][ $stylesheet ] = $stylesheet_version;
        $footprint['template'][ $theme_template ] =	$theme_template_version;
        $footprint['using_child_theme'] = $using_child_theme;
        $footprint['footprint_blog_id'] = $blogid_to_export;
        
        $site_url =	$this->getSystemFunctions()->getBlogOption($blogid_to_export, 'siteurl');  
        $site_url_parsed = $this->getSystemFunctions()->removeSchemeFromUrl($site_url);
        if (!empty($site_url)) {
            $footprint['site_url'] = $site_url_parsed;
        }
        
        if (defined('ABSPATH')) {
            $footprint['wp_root'] = $this->getSystemFunctions()->getAbsPath();
        }

        $db_prefix = $this->getSystemFunctions()->getDbPrefixOfSite($blogid_to_export);
        if ($db_prefix) {
            $footprint['db_prefix'] = $this->getSystemFunctions()->getDbPrefixOfSite($blogid_to_export);
        }
        
        $footprint = apply_filters('prime_mover_filter_site_footprint', $footprint, $blogid_to_export, $ret);
        return $footprint;
    } 
        
    /**
     * Normalize path
     * @param string $file
     * @return string
     */
    protected function normalizePath($file = '')
    {
        $file = realpath($file);
        return wp_normalize_path($file);        
    }
    
    /**
     * Git or SVN repo check
     * @param string $file
     * @return boolean
     */
    public function isGitSvnRepo($file = '')
    {
        if (in_array(basename($file), ['.svn', '.git', '.', '..'])) {
            return true;
        }
        $file = $this->normalizePath($file);
        if ( false !== strpos($file, '/.svn')) {
            return true;
        }        
        if (false !== strpos($file, '/.git')) {
            return true;
        }
        return false;
    }
    
    /**
     * Is requisites meet for zipping
     * @param string $source
     * @param string $destination
     * @return boolean
     */
    protected function isRequisitesMeetForZipping($source = '', $destination = '')
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return false;
        }
        if ( ! $source || ! $destination ) {
            return false;
        }
        if ((false === $this->primeMoverEssentialRequisites()) || !$this->getSystemFunctions()->nonCachedFileExists($source) || !is_writeable(dirname($destination))) {
            return false;
        }
        return true;
    }
    
    /**
     * Close archive and return
     * @param string $file
     * @param number $blog_id
     * @param array $processed
     * @param ZipArchive $zip
     * @param number $start_time
     * @param number $total_sizes
     * @param number $count
     * @param boolean $large_file
     * @param number $filesize
     * @param number $batch_bytes (previous value)
     * @param number $position
     * @param array $ret
     * @return array
     */
    protected function closeArchiveAndReturn($file = '', $blog_id = 0, $processed = [], ZipArchive $zip = null, $start_time = 0, $total_sizes = 0, $count = 0, $large_file = false, $filesize = 0, $batch_bytes = 0, $position = 0, $ret = [])
    {
        if ( ! $file || ! $blog_id) {
            return;
        }
        if ($large_file) {
            do_action('prime_mover_log_processed_events', "Large file detected: $file with filesize $filesize. Returning the results immediately and retry request.", $blog_id, 'export', __FUNCTION__, $this);
        }        
        
        do_action('prime_mover_log_processed_events', "About to close archive with $count new files and $total_sizes bytes.", $blog_id, 'export', __FUNCTION__, $this);
        $close = $zip->close();        
        $processing_time = microtime(true) - $start_time;
        do_action('prime_mover_log_processed_events', "Archive successfully closed with $count new files and $total_sizes bytes.", $blog_id, 'export', __FUNCTION__, $this);
        
        if (true === $close && $this->isLargeFile($filesize) && 1 === $count) {
            do_action('prime_mover_log_processed_events', "Large file $file has been added to archive and successfully closed.", $blog_id, 'export', __FUNCTION__, $this);
        } else {
            $retry_timeout = apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, 'closeArchiveAndReturn');
            do_action('prime_mover_log_processed_events', "Computing batch bytes based on $retry_timeout seconds timeout.", $blog_id, 'export', __FUNCTION__, $this);            
            $batch_bytes = floor(($total_sizes / $processing_time) * $retry_timeout);
        }
        
        do_action('prime_mover_log_processed_events', "Time out reached, able to process $count items.", $blog_id, 'export', __FUNCTION__, $this);
        do_action('prime_mover_log_processed_events', "Processing time computed is $processing_time, batch bytes data is $batch_bytes and total sizes archived is $total_sizes.", $blog_id, 'export', __FUNCTION__, $this);
        if ($batch_bytes < 5242880) {
            $batch_bytes = 5242880;
        }
        $ret['batch_bytes'] = $batch_bytes;
        $ret['file_reading_position'] = $position;
        $ret['processed_file_count'] = $count;
        
        return ['close' => $close, 'returned_array' => $processed, 'ret' => $ret];        
    }
    
    /**
     * Encrypt archive entity
     * @param ZipArchive $zip
     * @param string $entity
     * @param boolean $encrypt
     */
    protected function doEncrypt(ZipArchive $zip, $entity = '', $encrypt = false, $filesize = 0, $blog_id = 0)
    {
        if ( ! $entity || ! $encrypt) {
            return;
        }
        if ($this->isLargeFile($filesize)) {
            do_action('prime_mover_log_processed_events', "Large file $entity, skipping zip encryption.", $blog_id, 'export', 'doEncrypt', $this);
            return;
        }
        $zip->setEncryptionName($entity, ZipArchive::EM_AES_256);
    }
    
    /**
     * Get batch bytes
     * @param array $ret
     * @return number
     */
    protected function getBatchBytes($ret = [])
    {
        if ( ! empty($ret['batch_bytes']) ) {
            $batch_bytes = (int)$ret['batch_bytes'];
            return $batch_bytes;
        }
        return 0;
    }
    
    /**
     * Add empty directory to zip archive
     * @param string $source
     * @param array $ret
     * @param string $exporting_mode
     * @param string $file
     * @param array $returned_array
     * @param number $count
     * @param string $key
     * @param boolean $encrypt
     * @param ZipArchive $zip
     * @param number $blog_id
     * @param boolean $shell_mode
     * @return array
     */
    protected function addEmptyDirectoryToArchive($source = '', $ret = [], $exporting_mode = '', $file = '', $returned_array = [], $count = 0, $key = '', $encrypt = false, ZipArchive $zip = null, $blog_id = 0, $shell_mode = false)
    {
        $add_result = false;
        $directory = apply_filters('prime_mover_filter_basezip_folder', basename($source), $ret, $exporting_mode, $source, $shell_mode) . '/' . str_replace($source . '/', '', $file . '/');        
        $directory = trailingslashit($directory);
        
        do_action('prime_mover_log_processed_events', "Before adding directory: $directory to archive.", $blog_id, 'export', __FUNCTION__, $this, true);
        if ($zip->addEmptyDir($directory)) {
            do_action('prime_mover_log_processed_events', "Successfully added directory: $directory to archive.", $blog_id, 'export', __FUNCTION__, $this, true);
            list($returned_array, $count) = $this->unsetAndCount($returned_array, $key, $count);
            $this->doEncrypt($zip, $directory, $encrypt);
            $add_result = true;
        } else {
            do_action('prime_mover_log_processed_events', "Error adding $file to archive, exporting mode: $exporting_mode", $blog_id, 'export', __FUNCTION__, $this);
            list($returned_array, $count) = $this->unsetAndCount($returned_array, $key, $count);
        }
        return [$returned_array, $count, $add_result];
    }
    
    /**
     * Unset and count
     * @param array $returned_array
     * @param string $key
     * @param number $count
     * @return array
     */
    protected function unsetAndCount($returned_array = [], $key = '', $count = 0)
    {
        $count++;        
        return [$returned_array, $count];
    }
    
    /**
     * Maybe optimize performance for large files
     * @param number $filesize
     * @param ZipArchive $zip
     * @param string $entity
     * @param number $blog_id
     */
    protected function maybeOptimizePerformanceForLargeFileInArchive($filesize = 0, ZipArchive $zip = null, $entity = '', $blog_id = 0)
    {
        if ( ! $entity ) {
            return;
        }
        if (method_exists($zip, 'setCompressionName')) {            
            $zip->setCompressionName($entity, ZipArchive::CM_STORE );
        } 
    }
    
    /**
     * Add file to archive (Windows compatible - long file names)
     * @param string $filename
     * @param string $localname
     * @param number $filesize
     * @param ZipArchive $zip
     * @return boolean
     */
    protected function addFileOnWindows($filename = '', $localname = '', $filesize = 0, ZipArchive $zip = null)
    {
        if (!$this->isWindows()) {
            return false;  
        }
        $string_length = strlen($filename);
        if ($string_length < 250) {
            return false;
        }
        if ($this->isLargeFile($filesize)) {
            return false;
        }
        $content = file_get_contents($filename);
        return $zip->addFromString($localname, $content);        
    }
    
    /**
     * Add file to zip archive
     * @param ZipArchive $zip
     * @param string $file
     * @param string $files
     * @param number $filesize
     * @param number $total_sizes
     * @param array $returned_array
     * @param string $key
     * @param number $count
     * @param boolean $encrypt
     * @param string $exporting_mode
     * @param number $blog_id
     * @return number[]|boolean[]|array[]|array[][]|number[][]
     */
    protected function addFileToArchive(ZipArchive $zip, $file = '', $files = '', $filesize = 0, $total_sizes = 0, $returned_array = [], $key = '', $count = 0, $encrypt = false, $exporting_mode = '', $blog_id = 0)
    {
        $add_result = false;
        do_action('prime_mover_log_processed_events', "Adding $file to archive", $blog_id, 'export', 'addFileToArchive', $this, true);
        if ($this->addFileOnWindows($file, $files, $filesize, $zip)) {
            do_action('prime_mover_log_processed_events', "Successfully added special file exemption on Windows $file to archive", $blog_id, 'export', 'addFileToArchive', $this, true);
            $total_sizes += $filesize;
            list($returned_array, $count) = $this->unsetAndCount($returned_array, $key, $count);
            $this->doEncrypt($zip, $files, $encrypt, $filesize, $blog_id);
            $add_result = true;
        } elseif ($zip->addFile($file, $files)) {            
            do_action('prime_mover_log_processed_events', "Successfully added $file to archive", $blog_id, 'export', 'addFileToArchive', $this, true);
            $total_sizes += $filesize;
            list($returned_array, $count) = $this->unsetAndCount($returned_array, $key, $count);            
            $this->doEncrypt($zip, $files, $encrypt, $filesize, $blog_id);
            $add_result = true;
        } else {            
            do_action('prime_mover_log_processed_events', "Error adding $file to archive, exporting mode: $exporting_mode", $blog_id, 'export', 'addFileToArchive', $this);
            list($returned_array, $count) = $this->unsetAndCount($returned_array, $key, $count);            
        }
        $this->maybeOptimizePerformanceForLargeFileInArchive($filesize, $zip, $files, $blog_id);        
        return [$returned_array, $count, $add_result, $total_sizes];
    }
    
    /**
     * Checks if large file
     * @param number $filesize
     * @return boolean
     */
    protected function isLargeFile($filesize = 0)
    {
        return ($filesize > $this->getSystemInitialization()->getPrimeMoverLargeFileSize());
    }
    
    /**
     * Maybe finally close archive
     * @param number $filesize
     * @param number $count
     * @param number $batch_bytes
     * @param number $total_sizes
     * @param array $returned_processed
     * @param number $batch_archive
     * @param string $exporting_mode
     * @param number $start_time
     * @return boolean
     */
    protected function maybeFinallyCloseArchive($filesize = 0, $count = 0, $batch_bytes = 0, $total_sizes = 0, $returned_processed = [], $batch_archive = 0, $exporting_mode = 'exporting_media', $start_time = 0)
    {
        $batch_threshold_bytes = PRIME_MOVER_THRESHOLD_BYTES_MEDIA;
        if ('exporting_plugins' === $exporting_mode) {
            $batch_threshold_bytes = PRIME_MOVER_THRESHOLD_BYTES_PLUGIN;
        }
        $elapsed = microtime(true) - $start_time;
        return (
            $elapsed > apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, 'closeArchiveAndReturn') ||
            ($this->isLargeFile($filesize) && 1 === $count && $returned_processed) || 
            ($batch_bytes > 0 && $total_sizes > $batch_bytes && $returned_processed) || 
            (0 === $batch_bytes && $total_sizes > $batch_threshold_bytes && $returned_processed)
            );
    }
    
    /**
     * Zip Directory
     * @compatible 5.6
     * {@inheritDoc}
     * @see \Codexonics\PrimeMoverFramework\interfaces\PrimeMoverSystemCheck::primeMoverZipDirectory()
     * $exporting_mode is either of these: 'exporting_plugins', 'exporting_media', 'exporting_theme'
     */
    public function primeMoverZipDirectory($source = '', $destination = '', $encrypt = false, $files = [], $resume_mode = false,
        $returned_processed = false, $batch_archive = 0, $ret = [], $exclusion_rules = [], $exporting_mode = '', $start_time = 0, $blog_id = 0, $shell_mode = false)
    {
        if ( ! $this->isRequisitesMeetForZipping($source, $destination)) {            
            $ret['error'] = esc_html__('Requisites not meet for zipping', 'prime-mover');
            return ['close' => false, 'ret' => $ret];
        }
        
        do_action('prime_mover_log_processed_events', "Zip directory request received", $blog_id, 'export', __FUNCTION__, $this);
        $blog_id = $ret['original_blogid'];               
        
        $total_sizes = 0;
        $batch_bytes = $this->getBatchBytes($ret);
        if ($batch_bytes) {
            unset($ret['batch_bytes']);
        }
        
        $source = $this->normalizePath($source);
        $destination = wp_normalize_path($destination);        
        $zip = $this->getSystemInitialization()->getZipArchiveInstance();
        $res = false;
        
        if ($this->getSystemFunctions()->nonCachedFileExists($destination)) {
            $res = $zip->open($destination);
        } else {
            $res = $zip->open($destination, ZipArchive::CREATE);
        }
        
        if (true !== $res) {            
            $ret['error'] = sprintf(esc_html__("ERROR: Unable to open zip archive: %d", 'prime-mover'), $destination);
            return ['close' => false, 'ret' => $ret];
        }
        
        if ( ! $start_time ) {
            $start_time = microtime(true);
        }
        
        if ($encrypt) {
            $zip = $this->getSystemInitialization()->setEncryptionPassword($zip);
        }
        
        if (true === is_dir($source)) {
            $dir_to_create = apply_filters('prime_mover_filter_basezip_folder', basename($source), $ret, $exporting_mode, $source, $shell_mode);
            do_action('prime_mover_log_processed_events', "Before adding directory: $dir_to_create to archive.", $blog_id, 'export', __FUNCTION__, $this, true);
            $zip->addEmptyDir($dir_to_create);
            if ($encrypt) {
                $zip->setEncryptionName(apply_filters('prime_mover_filter_basezip_folder', basename($source), $ret, $exporting_mode, $source, $shell_mode), ZipArchive::EM_AES_256);
            }
            $count = 0;
            if (isset($ret['processed_file_count'])) {
                $count = (int)$ret['processed_file_count'];
            }
            foreach ($this->getFileGenerator($ret, $exporting_mode, $files, $source, $shell_mode) as $key => $file) { 
                $this->maybeTestZipDelay();
                $file = trim(wp_normalize_path($file));
                $add_result = false;
                $filesize = 0;
                if ($this->isGitSvnRepo($file) || apply_filters('prime_mover_media_resource_is_excluded', false, $file, $exclusion_rules, $source, $exporting_mode)) {
                    list($files, $count) = $this->unsetAndCount($files, $key, $count);
                    do_action('prime_mover_log_processed_events', "File $file is excluded from the archive.", $blog_id, 'export', __FUNCTION__, $this, true);
                    continue;
                }
                if (true === is_dir($file)) {
                    list($files, $count, $add_result) = $this->addEmptyDirectoryToArchive($source, $ret, $exporting_mode, $file, $files, $count, $key, $encrypt, $zip, $blog_id, $shell_mode);
                } elseif (true === is_file($file)) {
                    $files_basename = apply_filters('prime_mover_filter_basezip_folder', basename($source), $ret, $exporting_mode, $source, $shell_mode) . '/' . str_replace($source . '/', '', $file);
                    $filesize = $this->getSystemFunctions()->fileSize64($file);
                    if ($this->isLargeFile($filesize) && $returned_processed && $count > 0) {
                        return $this->closeArchiveAndReturn($file, $blog_id, $files, $zip, $start_time, $total_sizes, $count, true, $filesize, $batch_bytes, $key, $ret);
                    }
                    list($files, $count, $add_result, $total_sizes) = $this->addFileToArchive($zip, $file, $files_basename, $filesize, $total_sizes, $files, $key, $count, $encrypt, $exporting_mode, $blog_id);
                }
                if ( ! $add_result ) {
                    do_action('prime_mover_log_processed_events', "ERROR: zip add result false $file, unsetting and moving to next file", $blog_id, 'export', __FUNCTION__, $this, true);
                    list($files, $count) = $this->unsetAndCount($files, $key, $count);
                    continue;
                }
                if ($this->maybeFinallyCloseArchive($filesize, $count, $batch_bytes, $total_sizes, $returned_processed, $batch_archive, $exporting_mode, $start_time)) {
                    return $this->closeArchiveAndReturn($file, $blog_id, $files, $zip, $start_time, $total_sizes, $count, false, $filesize, $batch_bytes, $key, $ret);
                }
            }
        } elseif (true === is_file($source)) {
            $files_basename = apply_filters('prime_mover_filter_basezip_folder', basename($source), $ret, $exporting_mode, $source, $shell_mode);
            do_action('prime_mover_log_processed_events', "Before adding source: $source to archive.", $blog_id, 'export', 'addFileToArchive', $this, true);
            $result = $zip->addFile($source, $files_basename);
            $this->maybeOptimizePerformanceForLargeFileInArchive(0, $zip, $files_basename, 0);            
            
            if ( ! $result ) {
                do_action('prime_mover_log_processed_events', "Error adding $file to archive, exporting mode: $exporting_mode", $blog_id, 'export', __FUNCTION__, $this);
            }
            $this->doEncrypt($zip, $source, $encrypt);
        }
        do_action('prime_mover_log_processed_events', "CLOSING THE MAIN ARCHIVE...", $blog_id, 'export', __FUNCTION__, $this);
        $close = $zip->close();
        
        $ret = $this->clearMediaList($ret);       
        if ( ! $close ) {
            do_action('prime_mover_log_processed_events', "Error closing $destination archive, exporting mode: $exporting_mode", $blog_id, 'export', __FUNCTION__, $this);
        }
        
        do_action('prime_mover_log_processed_events', "SUCCESSFULLY CLOSED THE MAIN ARCHIVE...", $blog_id, 'export', __FUNCTION__, $this);
        
        if ($returned_processed) {
            return ['close' => $close, 'returned_array' => $files, 'ret' => $ret];
        } else {
            return ['close' => $close, 'returned_array' => [], 'ret' => $ret];
        }        
    }
    
    /**
     * Maybe test zip delay
     */
    protected function maybeTestZipDelay()
    {
        if (defined('PRIME_MOVER_TEST_ZIP_ARCHIVING_DELAY') && true === PRIME_MOVER_TEST_ZIP_ARCHIVING_DELAY) {
            $this->getSystemInitialization()->setProcessingDelay(100000, true);
        }        
    }
    
    /**
     * Clear media list array
     * @param array $ret
     * @return $ret
     */
    protected function clearMediaList($ret = [])
    {
        if (isset($ret['copymedia_shell_tmp_list'])) {
            global $wp_filesystem;
            $wp_filesystem->put_contents($ret['copymedia_shell_tmp_list'], '');
            $this->getSystemFunctions()->primeMoverDoDelete($ret['copymedia_shell_tmp_list']);
            unset($ret['copymedia_shell_tmp_list']);
        }
        if (isset($ret['batch_bytes'])) {
            unset($ret['batch_bytes']);            
        }
        if (isset($ret['file_reading_position'])) {
            unset($ret['file_reading_position']);
        }
        if (isset($ret['processed_file_count'])) {
            unset($ret['processed_file_count']);
        }
        if (isset($ret['total_media_files'])) {
            unset($ret['total_media_files']);
        }
        if (isset($ret['processed_file_count'])) {
            unset($ret['processed_file_count']);
        }  
        return $ret;        
    }
    
    /**
     * Get file generator
     * @param array $ret
     * @param string $exporting_mode
     * @param array $files
     * @param string $source
     * @param boolean $shell_mode
     * @return Generator
     */
    protected function getFileGenerator($ret = [], $exporting_mode = '', $files = [], $source = '', $shell_mode = false)
    {               
        if ( ! empty($ret['copymedia_shell_tmp_list']) && file_exists($ret['copymedia_shell_tmp_list'])) {            
            $handle = fopen($ret['copymedia_shell_tmp_list'], "rb");
            $file_position = 0;
            if (isset($ret['file_reading_position'])) {
                $file_position = (int)$ret['file_reading_position'];
                unset($ret['file_reading_position']);
            }
            if ($file_position) {
                fseek($handle, $file_position);
            }
            while(!feof($handle)) {
                $line = trim(fgets($handle));
                if (!$line) {
                    continue;
                }
                $pos = ftell($handle);
                yield $pos => $line;
            }
            fclose($handle);
            
        } elseif (!empty($ret['master_tmp_shell_files']) && !empty($ret['master_tmp_shell_dirs'])) {            
            $handler = fopen($ret['master_tmp_shell_dirs'], "rb");
            while(!feof($handler)) {
                $line = trim(fgets($handler));
                if (!$line) {
                    continue;
                }
                yield $line;
            }
            fclose($handler);            
                     
        } elseif (empty($files)) {  
            $this->getSystemFunctions()->temporarilyIncreaseMemoryLimits();
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
            
            foreach ($files as $file) {
                yield $file->getPathname();
            }                       
        } 
    }
       
    /**
     * Get files list from iterator using Generator syntax
     * @param RecursiveIteratorIterator $files
     * @return Generator
     */
    protected function getFilesList(RecursiveIteratorIterator $files)
    {
        foreach ($files as $file) {
            yield $file->getPathname();
        }
    }
    
    /**
     * Add zip file type support by default
     * @param string $value
     * @param string $option
     * @param number $network_id
     * @return string
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemChecks::itAddsZipFileTypeSupportWhenSet()
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemChecks::itAddsZipFileTypeSupportWhenNotSet()
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemChecks::itDoesNotAnymoreAddsZipIfAlreadySupported()
     */
    public function addZipFileTypeSupport($value = '', $option = '', $network_id = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if (! $network_id || ! $option) {
            return $value;
        }
        if ('upload_filetypes' === $option) {
            $supported_filetypes = explode(" ", $value);
            $supported_filetypes = array_filter($supported_filetypes);
            if (!in_array('zip', $supported_filetypes)) {
                $supported_filetypes[] = 'zip';
                $value = implode(" ", $supported_filetypes);
            }
            if (!in_array('wprime', $supported_filetypes)) {
                $supported_filetypes[] = 'wprime';
                $value = implode(" ", $supported_filetypes);
            }
        }
        return $value;
    }
    
    /**
     * Add plugin action links
     * @param array $actions
     * @param string $plugin_file
     * @param array $plugin_data
     * @param string $context
     * @return array
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemChecks::itAddsPluginActionLinksWhenMatched() 
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemChecks::itDoesNotAddpluginactionlinksWhenNotMatched() 
     */
    public function addPluginActionLinks($actions = [], $plugin_file = '', $plugin_data = [], $context ='')
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if (! $plugin_file || ! isset($plugin_data['PluginURI'])) {
            return $actions;
        }
        $pluginfile = basename($plugin_file);
        $pluginuri	= $plugin_data['PluginURI'];
        if ($this->getSystemInitialization()->getPluginUri() === $pluginuri && $this->getSystemInitialization()->getPluginFile() === $pluginfile) {
            $actions[$this->getSystemInitialization()->getPrimeMoverActionLink()] = '<a class="edit" href="' . esc_url_raw(network_admin_url('sites.php')) . '">' . esc_html__('Migrate/Backup Sites', 'prime-mover') . '</a>';
        }
        return $actions;
    }
    
    /**
     * Analyze the most correct MySQL dump path based on the system this plugin is running.
     * Inspired by https://wordpress.org/plugins/duplicator/
     * @return boolean|string|
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemChecks::itGetsMySQLDumpPath() 
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemChecks::itReturnsFalseIfNoSystemForMySQLDump() 
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemChecks::itReturnsNullIfNotAuthorizedToDump()
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemChecks::itReturnsFalseWhenNoMySQLDumpHandler() 
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemChecks::itGetsMySQLDumpPathInWindows()
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemChecks::itReturnsFalseWhenNoMySQLDumpHandlerInWindows()
     */
    public function getMySqlDumpPath()
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        //Is system() possible
        if (! $this->hasSystem()) {
            return false;
        }
        $is_windows = false;
        
        if ($this->isWindows()) {
            $is_windows = true;            
            
        } 
        $paths = [];
        
        /**
         * Using MySQL environment variable(this wont go wrong)
         */
        $binary_executable = $this->getSystemCheckUtilities()->getMySQLBaseDirExecutablePath('mysqldump', $is_windows);
        if ($binary_executable && ! in_array($binary_executable, $paths)) {
            $paths[] = $binary_executable;
        }
        
        // Find the one which works
        foreach ($paths as $path) {
            if ($this->isExecutable($path)) {
                return $path;
            }
        }
        
        return false;
    }    
        
    /**
     * Checks if executable
     * @param string $path
     */
    public function isExecutable($path = '')
    {
        return @is_executable($path);
    }    
        
    /**
     * Can system() be called on this server
     * Modified from Snap Creek | https://wordpress.org/plugins/duplicator/
     * @return bool Returns true if system() can be called on server
     * @compatible 5.6
     *
     */
    public function hasSystem()
    {        
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $cmds = $this->getSystemInitialization()->getCoreSystemFunctions();
        if (array_intersect($cmds, array_map('trim', explode(',', @ini_get('disable_functions'))))) {
            return false;
        }
        
        if (extension_loaded('suhosin')) {
            $suhosin_ini = @ini_get("suhosin.executor.func.blacklist");
            if (array_intersect($cmds, array_map('trim', explode(',', $suhosin_ini)))) {
                return false;
            }
        }
        
        if (! @shell_exec('echo multisite migration')) {
            return false;
        }
        
        return true;
    }
  
    /**
     * Checks if WAMP server
     * @return boolean
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemChecks::itReturnsTrueIfWampServer
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemChecks::itReturnsFalseIfNotWampServer
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemChecks::itReturnsFalseIfNotOnWindows()
     */
    public function isWampServer()
    {
        return ($this->isWindows() && ! empty($_SERVER['DOCUMENT_ROOT']) && strpos(strtolower($_SERVER['DOCUMENT_ROOT']), 'wamp') !== false);
    }
    
    /**
     * Checks if using Microsoft IIS server
     * @return boolean
     */
    public function isIISServer()
    {
        global $is_IIS, $is_iis7;
        $ret = false;
        if ($is_IIS || $is_iis7) {
            $ret = true;
        }
        return $ret;
    }
    
    /**
     * Is the server running Windows operating system
     * CREDITS: Snap Creek | https://wordpress.org/plugins/duplicator/
     * @return bool Returns true if operating system is Windows
     * @compatible 5.6
     *
     */
    public function isWindows()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return true;
        }
        return false;
    }
    
    /**
     * Checks if can support encrypted Zip Archives
     * @return boolean
     */
    public function canSupportEncryptedArchive()
    {        
        $compatible_php_version = false;
        $has_encrypted_zip_support = false;
        if (version_compare(PHP_VERSION, '7.2.0') >= 0) {
            $compatible_php_version = true;
        }
        $ziparchive = $this->getSystemInitialization()->getZipArchiveInstance();
        $methods = get_class_methods($ziparchive);
        if (in_array('setEncryptionName', $methods)) {
            $has_encrypted_zip_support = true;
        }
        
        return ($compatible_php_version && $has_encrypted_zip_support);
    }
    
    /**
     * Can decrypt media
     * @param boolean $ret
     * @param array $verify_result
     * @param array $phpinfoarray
     * @return string|boolean
     */
    public function canDecryptMedia($ret = true, $verify_result = [], $phpinfoarray = [])
    {        
        if (empty($verify_result)) {
            return $ret;
        }
        if (empty($verify_result['encrypted_media'])) {
            return $ret;
        }
        $encrypted_media = $verify_result['encrypted_media'];
        if ('false' === $encrypted_media) {
            return $ret;
        } 
        return $this->isLibZipVersionCompatible($phpinfoarray);
    }
    
    /**
     * Checks if libzip version is compatible
     * @param array $phpinfoarray
     * @return boolean
     */
    public function isLibZipVersionCompatible($phpinfoarray = [])
    {
        $libzip_installed_version = $this->getSystemFunctions()->getLibZipversion($phpinfoarray);
        if (version_compare($libzip_installed_version, '1.2.0') >= 0) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Measure performance in terms of processing time, CPU and memory usage and log it for improvement
     * @param number $blog_id
     * @param array $ret
     * @param string $mode
     */
    public function computePerformanceStats($blog_id = 0, $ret = [], $mode = 'export')
    {
        $end_time = $this->getSystemInitialization()->getStartTime();
        $key = $mode . '_start_time';
        $elapsed_time = $end_time - $ret[$key];
        do_action('prime_mover_log_processed_events', "$mode successfully completed in $elapsed_time seconds", $blog_id, $mode, 'computePerformanceStats', $this);

        if ( is_array($ret['peak_memory_usage_log']) && ! empty($ret['peak_memory_usage_log'])) {
            
            $memory_log = array_filter($ret['peak_memory_usage_log']);
            $average_peak_memory_used = array_sum($memory_log)/count($memory_log);
            $megabytes_memory_used = ($average_peak_memory_used/1024/1024)." MiB";
            
            $maximum_peak_memory = max($memory_log);
            $peak_megabytes_memory_used = ($maximum_peak_memory/1024/1024)." MiB";
            
            do_action('prime_mover_log_processed_events', "Average peak memory used is $megabytes_memory_used", $blog_id, $mode, 'computePerformanceStats', $this);
            do_action('prime_mover_log_processed_events', "Maximum peak memory used is $peak_megabytes_memory_used", $blog_id, $mode, 'computePerformanceStats', $this);
            do_action('prime_mover_log_processed_events', "Actual memory values recorded: ", $blog_id, $mode, 'computePerformanceStats', $this);
            do_action('prime_mover_log_processed_events', $memory_log, $blog_id, $mode, 'computePerformanceStats', $this);
        }

        if ( ! $this->isWindows()) {
            $load = sys_getloadavg();
            do_action('prime_mover_log_processed_events', "Average CPU usage: ", $blog_id, $mode, 'computePerformanceStats', $this);
            do_action('prime_mover_log_processed_events', $load, $blog_id, $mode, 'computePerformanceStats', $this);
        }
    }
    
    /**
     * Deactivate theme on specific site
     * @param number $blog_id
     */
    public function deactivateThemeOnSpecificSite($blog_id = 0)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if (!$blog_id) {
            return;
        }
        $this->getSystemFunctions()->switchToBlog($blog_id);
        
        $this->getSystemFunctions()->maybeForceDeleteOptionCaches('template', true, false);
        $this->getSystemFunctions()->maybeForceDeleteOptionCaches('stylesheet', true, false);
        
        delete_option('template');
        delete_option('stylesheet');
        
        $this->getSystemFunctions()->restoreCurrentBlog();
    }
    
    /**
     * Get theme on specific site
     * @param number $blog_id
     * @return mixed[]|boolean[]|NULL[]|array[]
     */
    public function getThemeOnSpecificSite($blog_id = 0)
    {
        if (!$blog_id) {
            return ['', ''];
        }
        $this->getSystemFunctions()->switchToBlog($blog_id);
        
        $this->getSystemFunctions()->maybeForceDeleteOptionCaches('template', true, false);
        $this->getSystemFunctions()->maybeForceDeleteOptionCaches('stylesheet', true, false);
        
        $mainsite_template = get_option('template');
        $mainsite_stylesheet = get_option('stylesheet');
        
        $this->getSystemFunctions()->restoreCurrentBlog();
        
        return [$mainsite_template, $mainsite_stylesheet];
    }
    
    /**
     * Set theme on specific site 
     * Low level way since we don't need to execute 3rd party hooks as restore is ongoing.
     * @param number $blog_id
     * @param string $template
     * @param string $stylesheet
     */
    public function updateThemeOnSpecificSite($blog_id = 0, $template = '', $stylesheet = '')
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if (!$blog_id || !$template || !$stylesheet) {
            return;
        }
        $this->getSystemFunctions()->switchToBlog($blog_id);
        
        $this->getSystemFunctions()->maybeForceDeleteOptionCaches('template', true, false);
        $this->getSystemFunctions()->maybeForceDeleteOptionCaches('stylesheet', true, false);
        
        update_option('template', $template);
        update_option('stylesheet', $stylesheet);
        
        $this->getSystemFunctions()->restoreCurrentBlog();
    }
}
