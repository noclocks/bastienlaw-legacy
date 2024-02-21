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

use ZipArchive;
use DateTime;
use DateTimeZone;
use WP_Screen;
use WP_Error;
use SplFixedArray;
use wpdb;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover System Functions Class
 *
 * The Prime Mover System Functions Class aims to provide the system functions for the facility of this plugin.
 *
 */
class PrimeMoverSystemFunctions
{    
    private $system_initialization;
    
    /**
     * Constructor
     * @param PrimeMoverSystemInitialization $system_initialization
     */
    public function __construct(PrimeMoverSystemInitialization $system_initialization)
    {
        $this->system_initialization = $system_initialization;
    }

    /**
     * Get files to restore
     * @param number $blog_id
     * @param string $custom_path
     * @return array|string[][]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsFilesToRestore()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itReturnsEmptyIfDirectoryNotDefined()
     */
    public function getFilesToRestore($blog_id = 0, $custom_path = '')
    {
        $zips = [];
        $dir = $this->getSystemInitialization()->getExportDirectoryPath($blog_id, true, $custom_path);
        if ( ! $dir ) {
            return $zips;
        }        
        
        return $this->readPrimeMoverDirectory($blog_id, $dir, $zips);
    }
    
    /**
     * Read given Prime Mover directory for packages
     * @param number $blog_id
     * @param string $dir
     * @param array $zips
     * @return string[]
     */
    public function readPrimeMoverDirectory($blog_id = 0, $dir = '', $zips = [])
    {
        $files = $this->getSystemInitialization()->getDirectoryIteratorInstance($dir);
        $this->switchToBlog($blog_id);
        foreach($files as $item) {
            if (!$item->isDot() && $item->isFile()) {
                $filename = $item->getFilename();
                $filepath = $dir . $filename;
                if ( $this->hasZipExtension($filename) || $this->hasTarExtension($filename)) {
                    $filesize = $this->fileSize64( $filepath );
                    $readable = $this->humanFileSize($filesize);
                    $zips[$filename] = ['filesize' => $readable, 'filepath' => $filepath, 'filesize_raw' => $filesize];
                }
            }
        }
        $this->restoreCurrentBlog();        
        return $zips;
    }
    
    /**
     * Has WPRIME extension
     * @param string $filename
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itChecksIfHasTarExtension() 
     */
    public function hasTarExtension($filename = '')
    {
        $ret = false;
        if ( ! $filename ) {
            return $ret;
        }
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ('wprime' === $extension) {
            $ret = true;
        }
        return $ret;
    }
    
    /**
     * Get package creation date time
     * @param number $timestamp
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsPackageCreationDateTime() 
     */
    public function getPackageCreationDateTime($timestamp = 0)
    {
        if ( ! $timestamp ) {
            return '';
        }
        $dt = new DateTime();
        $timezone = get_option('timezone_string');
        if ($timezone) {
            $dt->setTimezone(new DateTimeZone($timezone));
        }
        $dt->setTimestamp($timestamp);
        return $dt->format("M d Y h:i:s a");
    }

    /**
     * filesize() 64-bit/32-bit compatible
     * @param string $file
     * @return string
     * @codeCoverageIgnore
     */
    public function fileSize64($file = '')
    {
        if (8 === PHP_INT_SIZE) {
            //64-bit already
            return filesize($file);
        }
        
        return $this->realFileSize($file);
    }

    /**
     * Credits: https://www.php.net/manual/en/function.filesize.php#121406
     * 32-bit compatible
     * @param string $path
     * @return number
     * @codeCoverageIgnore
     */
    public function realFileSize($path = '')
    {
        if ( ! file_exists($path) )
            return false;
            
            $size = filesize($path);
            
            if (!($file = fopen($path, 'rb')))
                return false;
                
                if ($size >= 0) {
                    if (fseek($file, 0, SEEK_END) === 0) {
                        fclose($file);
                        return $size;
                    }
                }

                $size = PHP_INT_MAX - 1;
                if (fseek($file, PHP_INT_MAX - 1) !== 0) {
                    fclose($file);
                    return false;
                }                
                $length = 1024 * 1024;
                while (!feof($file)) {
                    $read = fread($file, $length);
                    $size = bcadd($size, $length);
                }
                $size = bcsub($size, $length);
                $size = bcadd($size, strlen($read));
                
                fclose($file);
                return $size;
    }
    
    /**
     * Check if has zip extension
     * @param string $filename
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itReturnsTrueIfFileHasZipExtension()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itReturnsFalseIfFileHasNoZipExtension() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itReturnsFalseIfNoFileIsProvided()
     */
    public function hasZipExtension($filename = '')
    {
        $ret = false;
        if ( ! $filename ) {
            return $ret;
        }
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ('zip' === $extension) {
            $ret = true;
        }
        return apply_filters('prime_mover_has_zip_extension', $ret);
    }

    /**
     * Get human readable filesize
     * @param $bytes
     * @param number $decimals
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsHumanFileSize()
     * Credits: original code improved by Sucuri Scanner plugin
     */
    public function humanFileSize($bytes, $decimals = 1)
    {
        $sz = 'BKMGTP';
        $factor = (int) floor((strlen((string) $bytes) - 1) / 3);
        $number = $bytes / pow(1024, $factor);
        $result = sprintf("%.{$decimals}f", $number) . @$sz[$factor];
        $zeroes = '.' . str_repeat('0', $decimals);
        $result = str_replace($zeroes, '', $result);
        
        return $result;
    }
    
    /**
     * Get System authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     */
    public function getSystemAuthorization()
    {
        return $this->getSystemInitialization()->getSystemAuthorization();
    }
    
    /**
     * Get System Initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     * @compatible 5.6
     */
    public function getSystemInitialization()
    {
        return $this->system_initialization;
    }
    
    /**
     * System hooks
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itLoadsSystemhooks())
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itChecksIfHooksAreOutdated()
     */
    public function systemHooks()
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }        
        add_filter('prime_mover_get_plugin_differences', [$this, 'pluginsDifference'], 10, 3);        
        add_filter('prime_mover_get_themeDifferences', [$this, 'themeDifference'], 10, 3);      
        add_action('prime_mover_after_actual_import', [$this, 'removeTmpImportFilesDir'], 15, 3);
    
        add_filter('prime_mover_validate_site_footprint_data', [$this, 'validateIfFootprintHasScheme'], 10, 2);
        add_filter('prime_mover_filter_replaceables', [$this, 'generateReplaceableForSiteSchemeAdjustment'], 10, 2);
        add_filter('prime_mover_footprint_keys', [$this, 'addSchemeFootprintKey' ], 10, 2);
      
        add_filter('prime_mover_validate_site_footprint_data', [$this, 'validateIfFootprintHasUploadInfo'], 10, 2);
        add_filter('prime_mover_footprint_keys', [$this, 'addUploadinfoFootprintKey'], 10, 2);
        add_filter('prime_mover_filter_replaceables', [$this, 'generateReplaceableForUploadInfoAdjustment'], 10, 2);
    }

    /**
     * Generate replaceable for site scheme adjustments
     * @param array $replaceables
     * @return array
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGeneratesReplaceableForUploadInfoAdjustment()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itDoesNotGenerateReplaceablesIfNotSet()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itDoesNotGenerateReplaceablesIfNotAuthorized() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itDoesNotGenerateReplaceablesIfRetUrlsNotSet() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itDoesNotGenerateReplaceablesIfBlogIdNotSet() 
     */
    public function generateReplaceableForUploadInfoAdjustment(array $replaceables, array $ret)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $replaceables;
        }
        if (empty($replaceables)) {
            return $replaceables;
        }
        if (empty($ret['imported_package_footprint']['upload_information_path']) || empty($ret['imported_package_footprint']['upload_information_url'])) {
            return $replaceables;
        }
        $blog_id = 0;
        if (! empty($ret['blog_id'])) {
            $blog_id = (int) $ret['blog_id'];
        }
        if (! $blog_id) {
            return $replaceables;
        }
        
        $target_site_upload_url = $this->primeMoverGetBlogsDirUrl($blog_id, $ret);
        $target_site_upload_path = $this->primeMoverGetBlogsDirPath($blog_id, true, $ret);
        $target_site_upload_path = rtrim($target_site_upload_path, DIRECTORY_SEPARATOR);
        
        if (! $target_site_upload_path || ! $target_site_upload_url) {
            return $replaceables;
        }
        
        $source_site_upload_url = $ret['imported_package_footprint']['upload_information_url'];
        $source_site_upload_path =  $ret['imported_package_footprint']['upload_information_path'];
        
        $add_alternative = false;
        if ( ! empty($ret['imported_package_footprint']['alternative_upload_information_url'])) {
            $add_alternative = true;
        }
 
        $add_edge_canonical = false;
        if ( ! empty($ret['imported_package_footprint']['edge_canonical_upload_information_url'])) {
            $add_edge_canonical = true;
        }
        
        $upload_phrase = [ 'wpupload_path' => [
            'search' => $source_site_upload_path,
            'replace' => $target_site_upload_path
        ] ]
        +
        [ 'wpupload_url' => [
            'search' => $source_site_upload_url,
            'replace' => $target_site_upload_url
        ] ];
        
        if ($add_alternative) {
            $upload_phrase = $upload_phrase
            +
            [ 'alternative_wpupload_url' => [
                'search' => $ret['imported_package_footprint']['alternative_upload_information_url'],
                'replace' => $target_site_upload_url
            ] ];            
        }
        
        if ($add_edge_canonical && $add_alternative) {
            $upload_phrase = $upload_phrase
            +
            [ 'alternative_edge_wpupload_url' => [
                'search' => $ret['imported_package_footprint']['edge_canonical_upload_information_url'],
                'replace' => $target_site_upload_url
            ] ];  
        }
        
        $basic_parameters = [
            $target_site_upload_url,
            $target_site_upload_path,
            $source_site_upload_url,
            $source_site_upload_path
        ];
        
        $upload_phrase = apply_filters('prime_mover_filter_upload_phrase', $upload_phrase, $ret, $replaceables, $basic_parameters, $blog_id);
        $replaceables = $upload_phrase + $replaceables;        
        return $replaceables;
    }
    
    /**
     * Add upload info to footprint key
     * @param array $footprint_keys
     * @param array $footprint
     * @return array
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itDoesNotAddFootPrintKeyIfUnAuthorized() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itAddsUploadInfoFootprintKey()
     */
    public function addUploadinfoFootprintKey(array $footprint_keys, array $footprint)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $footprint_keys;
        }
        if (! in_array('upload_information_path', $footprint_keys)) {
            $footprint_keys[] = 'upload_information_path';
        }
        if (! in_array('upload_information_url', $footprint_keys)) {
            $footprint_keys[] = 'upload_information_url';
        }
        
        return $footprint_keys;
    }
    
    /**
     * Validate if footprint has upload info.
     * @param bool $overall_valid
     * @param array $footprint_temp
     * @return boolean
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itReturnsTrueIfIfFootPrintHasUploadInfo()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itReturnsFalseIfFootPrintDoesNotHaveUploadInfo() 
     */
    public function validateIfFootprintHasUploadInfo($overall_valid = true, array $footprint_temp = [])
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $overall_valid;
        }
        if (! isset($footprint_temp['upload_information_path']) || ! isset($footprint_temp['upload_information_url'])) {
            $overall_valid = false;
        }
        return $overall_valid;
    }
    
    /**
     * Add scheme to footprint key
     * @param array $footprint_keys
     * @param array $footprint
     * @return array
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itAddsSchemeToFootPrintKey()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itDoesNotAddSchemeToFootPrintKeyNotAuthorized()
     */
    public function addSchemeFootprintKey(array $footprint_keys, array $footprint)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $footprint_keys;
        }
        if (! in_array('scheme', $footprint_keys)) {
            $footprint_keys[] = 'scheme';
        }
        return $footprint_keys;
    }
    
    
    /**
     * Generate replaceable for site scheme adjustments
     * @param array $replaceables
     * @return array
     * @compatible 5.6
     */
    public function generateReplaceableForSiteSchemeAdjustment(array $replaceables, array $ret)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $replaceables;
        }
        
        if (empty($replaceables)) {
            return $replaceables;
        }
        if (empty($ret['imported_package_footprint']['scheme']) ||
             empty($replaceables['domain_replace']['search']) ||
             empty($replaceables['domain_replace']['replace'])) {
            return $replaceables;
        }
       
        $target_key = 'domain_replace';
        $target_site = $replaceables['domain_replace']['replace'];
        
        if (isset($replaceables[$target_key])) {           
            if (is_ssl()) {               
                $source_scheme = PRIME_MOVER_NON_SECURE_PROTOCOL . $target_site;
                $target_site_scheme = PRIME_MOVER_SECURE_PROTOCOL . $target_site;
                
            } else {                
                $source_scheme = PRIME_MOVER_SECURE_PROTOCOL . $target_site;
                $target_site_scheme = PRIME_MOVER_NON_SECURE_PROTOCOL . $target_site;
            }

            $offset = array_search($target_key, array_keys($replaceables)) + 1;
            $start = array_slice($replaceables, 0, $offset, true);
            $scheme_params = ['scheme_replace' => [
                'search' => $source_scheme,
                'replace' => $target_site_scheme
            ]];
            
            $scheme_replaceables = $this->computeSchemeReplaceables($scheme_params);
            $end = array_slice($replaceables, $offset, null, true);
            
            $replaceables = $start + $scheme_replaceables + $end;
        }
        
        return $replaceables;
    }
    
    /**
     * Compute scheme replaceables considering all possibilities
     * @param array $scheme_params
     * @return array
     */
    protected function computeSchemeReplaceables($scheme_params = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $scheme_params;
        }
        
        if (empty($scheme_params['scheme_replace']['search']) || empty($scheme_params['scheme_replace']['replace'])) {
            return $scheme_params;
        }
        
        if (!is_multisite()) {
            return $scheme_params;
        }
        $new_params = [];
        $source_scheme = $scheme_params['scheme_replace']['search'];
        $target_scheme = $scheme_params['scheme_replace']['replace'];
        
        $origin_protocol = parse_url($source_scheme, PHP_URL_SCHEME);
        $target_protocol = parse_url($target_scheme, PHP_URL_SCHEME);
        
        $origin_protocol = $origin_protocol . "://";
        $target_protocol = $target_protocol . "://";
        
        $source_no_scheme = $this->removeSchemeFromUrl($source_scheme);
        $domain_current_parameter = [];
        if (defined('DOMAIN_CURRENT_SITE') && DOMAIN_CURRENT_SITE && $source_no_scheme !== DOMAIN_CURRENT_SITE) {
            $domain_current_parameter = ['scheme_replace_domain_current_site' => [
                'search' => $origin_protocol . DOMAIN_CURRENT_SITE,
                'replace' => $target_protocol . DOMAIN_CURRENT_SITE
            ]];
        }       
        
        $new_params = $scheme_params;
        if (!empty($domain_current_parameter)) {
            $new_params = $new_params + $domain_current_parameter;
        }
                
        return $new_params;        
    }
    /**
     * Validate if footprint has scheme
     * @param bool $overall_valid
     * @param array $footprint_temp
     * @return bool
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itValidatesIfFootPrintHasScheme()
     */
    public function validateIfFootprintHasScheme($overall_valid = true, array $footprint_temp = [])
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $overall_valid;
        }
        if (! isset($footprint_temp['scheme'])) {
            $overall_valid = false;
        }
        return $overall_valid;
    }
    
    /**
     * Check if zip extension is loaded
     * @return boolean
     * @compatible 5.6
     * @codeCoverageIgnore
     */
    public function primeMoverCheckIfZipEnabled()
    {
        $zip_extension_loaded	= false;
        if (extension_loaded('zip')) {
            $zip_extension_loaded	= true;
        }
        
        return $zip_extension_loaded;
    }
    
    /**
     * Gets the blogs directory of a given blog
     * @param number $blog_id
     * @param boolean $dirname
     * @param array $ret
     * @return string
     */
    public function primeMoverGetBlogsDirPath($blog_id = 0, $dirname = true, $ret = [])
    {
        
        //Initialize
        $blogs_dir_path	= '';
        
        if ($blog_id > 0) {
            $dirname	= false;
        }
        if ((0 === $blog_id) && (true === $dirname)) {
            //Root dirname request
            return ABSPATH . UPLOADBLOGSDIR . DIRECTORY_SEPARATOR;
        }
        
        //Switch to this blog
        $this->switchToBlog($blog_id);
        
        //Retrieved uploads information
        if ( ! empty($ret['canonical_uploads_information']) ) {
            $uploads_information = $ret['canonical_uploads_information'];
        } else {
            $uploads_information = $this->getSystemInitialization()->getWpUploadsDir(false, true);
        }
        
        //Get basedir
        if (isset($uploads_information['basedir'])) {
            $basedir = $uploads_information['basedir'];
            if (!empty($basedir)) {
                $blogs_dir_path	= $basedir . DIRECTORY_SEPARATOR;
            }
        }
        //restore current blog
        $this->restoreCurrentBlog();
        return $blogs_dir_path;
    }
    
    /**
     * Get blogs dir URL
     * @param int $blog_id
     * @return string|string
     * @compatible 5.6
     */
    public function primeMoverGetBlogsDirUrl($blog_id = 0, $ret = [])
    {
        $blogs_dir_url	= '';
        if (! $blog_id) {
            return $blogs_dir_url;
        }
        
        //Switch to this blog
        $this->switchToBlog($blog_id);
        
        //Retrieved uploads information
        if ( ! empty($ret['canonical_uploads_information']) ) {
            $uploads_information = $ret['canonical_uploads_information'];
        } else {
            $uploads_information = $this->getSystemInitialization()->getWpUploadsDir(true, true);
        }        
        
        //Get baseurl
        if (isset($uploads_information['baseurl'])) {
            $baseurl	= $uploads_information['baseurl'];
            if (! empty($baseurl)) {
                $blogs_dir_url	= $baseurl;
            }
        }
        //restore current blog
        $this->restoreCurrentBlog();
        return $blogs_dir_url;
    }
    
    /**
     * Delete all temporary files
     * @param string $path_supplied
     * @param boolean $authorization_required
     * @param number $start_time
     * @return void|void|boolean
     */
    public function primeMoverDoDelete($path_supplied	= '', $authorization_required = true, $start_time = 0)
    {
        if ($authorization_required && ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        global $wp_filesystem;    
        if ( ! $this->isWpFileSystemUsable($wp_filesystem)) {
            return;
        }
        if (empty($path_supplied)) {            
            $path_supplied	= $this->getSystemInitialization()->getMultisiteExportFolderPath();
        }        
        $ret = $this->delete($path_supplied, true, false, $start_time, $authorization_required);
        return $ret;
    }
    
    /**
     * Helper function for delete
     * @param string $file
     * @param boolean $recursive
     * @param boolean $type
     * @param number $start_time
     * @return boolean|[]
     */
    protected function delete($file = '', $recursive = false, $type = false, $start_time = 0, $authorization_required = true) 
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized() && $authorization_required) {
            return false;
        }
        global $wp_filesystem; 
        if (empty($file)) {
            return false;
        }        
        $file = str_replace( '\\', '/', $file);        
        if ('f' == $type || $wp_filesystem->is_file($file)) {
            return @unlink( $file );
        }
        if (!$recursive && $wp_filesystem->is_dir($file)) {
            return @rmdir( $file );
        }        
        $file = trailingslashit($file);
        $filelist = $wp_filesystem->dirlist($file, true);        
        $retval = true;
        if (is_array($filelist)) {
            foreach ($filelist as $filename => $fileinfo) {
                if (!$this->delete($file . $filename, $recursive, $fileinfo['type'])) {
                    $retval = false;
                }                
                $retry_timeout = apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, __FUNCTION__);
                if ($start_time && ((microtime(true) - $start_time) > $retry_timeout)) {                     
                    return ['retry' => true];
                }
            }
        }        
        if ($this->nonCachedFileExists($file) && !@rmdir($file)) {
            $retval = false;
        }        
        return $retval;
    }
    
    /**
     * Validates system footprint
     * @param array $footprint
     * @return array
     */
    public function primeMoverValidateFootprintData($footprint = [])
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $ret = [];
        $overall_valid = false;
        $structure_valid = false;
        $error_string = '';
        $correct_keys = apply_filters('prime_mover_footprint_keys', ['plugins', 'stylesheet', 'template', 'using_child_theme', 'footprint_blog_id', 'site_url', 'wp_root', 'db_prefix'], $footprint);
        $footprint_temp = $footprint;
        $errors = [];
        $invalid_keys = [];
        if ((is_array($footprint)) && (!empty($footprint))) {
            foreach ($correct_keys as $v) {
                if (isset($footprint[ $v ])) {                    
                    unset($footprint[ $v ]);
                }
            }            
            $counted	= count($footprint);
            $counted	= intval($counted);
            if (0 === $counted) {
                $structure_valid  = true;
            }
        }
        if ( ! $structure_valid ) {  
            $invalid_keys = array_keys($footprint);            
            $errors[] = sprintf( esc_html__('Invalid footprint keys detected : %s', 'prime-mover'), implode(",", $invalid_keys));
        }

        $data_validity_check = false;        
        $is_using_site_url_data_valid = false;
        if ((isset($footprint_temp['site_url'])) && (!empty($footprint_temp['site_url']))) {
            $is_using_site_url_data_valid	= true;
        }
        if ( ! $is_using_site_url_data_valid ) {
            $errors[] = esc_html__('Error: Missing site URL, are you sure site is not misconfigured?', 'prime-mover');
        }
        
        if (true === $is_using_site_url_data_valid) {
            $data_validity_check = true;
        }        
        
        if ((true === $data_validity_check) && (true === $structure_valid)) {
            $overall_valid = true;
        }
        
        $overall_valid = apply_filters('prime_mover_validate_site_footprint_data', $overall_valid, $footprint_temp);
        
        if ( ! empty($errors) ) {
            $error_string = implode(",", $errors);
        }
        $ret = ['overall_valid' => $overall_valid, 'errors' => $error_string];        
        return $ret;
    }
    
    /**
     * Get detailed differences between source and target site configuration
     * @param array $system_footprint_package_array
     * @param array $system_footprint_target_site
     * @compatible 5.6
     */
    public function getDetailedDifferencesBetweenFootprints($system_footprint_package_array = [], $system_footprint_target_site = [])
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        //Initialize
        $differences	= [];
        
        //Compute plugin difference
        $plugins_diff	= apply_filters('prime_mover_get_plugin_differences', [], $system_footprint_package_array, $system_footprint_target_site);
        
        //Compute theme difference
        $themes_diff	= apply_filters('prime_mover_get_themeDifferences', [], $system_footprint_package_array, $system_footprint_target_site);
        
        //Return
        $differences['plugins']	= $plugins_diff;
        $differences['themes']	= $themes_diff;
        
        return $differences;
    }
    
    /**
     * Compute plugins differences
     * Hooked to 'prime_mover_get_plugin_differences'
     * @param array $pluginsDifference
     * @param array $system_footprint_package_array
     * @param array $system_footprint_target_site
     * @return string[]|array[]
     * @compatible 5.6
     */
    public function pluginsDifference($pluginsDifference	= [], $system_footprint_package_array = [], $system_footprint_target_site = [])
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        /**
         * Get source package plugins
         * We need to check:
         * ->Source plugins exist at target site
         * ->Differences in between versions
         */
        if ((isset($system_footprint_package_array['plugins'])) && (isset($system_footprint_target_site['plugins']))) {
            $source_plugins	= $system_footprint_package_array['plugins'];
            $target_plugins	= $system_footprint_target_site['plugins'];
            foreach ($source_plugins as $source_plugin_name => $source_version) {
                if (array_key_exists($source_plugin_name, $target_plugins)) {
                    /**
                     * Handling case #2, case #3
                     */
                    $target_plugin_version	= $target_plugins[ $source_plugin_name ];
                    if ($target_plugin_version !== $source_version) {
                        $pluginsDifference[ $source_plugin_name ]	= [
                                'source' => $source_version,
                                'target' => $target_plugin_version
                        ];
                    }
                } else {
                    /**
                     * Handling case #4, case #5 and case #6
                     */
                    $status = apply_filters('multsite_migration_targetplugin_status', '', $source_plugin_name, $source_version);
                    if ($status) {
                        $pluginsDifference[ $source_plugin_name ] = [
                            'source' => $source_version,
                            'target' => $status
                        ];
                    }
                }
            }
        }
        
        return $pluginsDifference;
    }
    
    /**
     * Computes theme difference
     * @param array $themeDifference
     * @param array $system_footprint_package_array
     * @param array $system_footprint_target_site
     * @compatible 5.6
     */
    public function themeDifference($themeDifference	= [], $system_footprint_package_array = [], $system_footprint_target_site = [])
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        //Initialize variables
        $source_template	=	'';
        $source_stylesheet	=	'';
        $source_using_child	=	'';
        $target_template	=	'';
        $target_stylesheet	=	'';
        $target_using_child	=	'';
                
        //Information about source site parent theme
        if (isset($system_footprint_package_array['template'])) {
            $source_template	= $system_footprint_package_array['template'];
        }

        //Information about source site child theme
        if (isset($system_footprint_package_array['stylesheet'])) {
            $source_stylesheet	= $system_footprint_package_array['stylesheet'];
        }
        
        //Information if source is using child theme
        if (isset($system_footprint_package_array['using_child_theme'])) {
            $source_using_child	=	$system_footprint_package_array['using_child_theme'];
        }
        
        //Information about target site parent theme
        if (isset($system_footprint_target_site['template'])) {
            $target_template	= $system_footprint_target_site['template'];
        }
        
        //Information about target site child theme
        if (isset($system_footprint_target_site['stylesheet'])) {
            $target_stylesheet	= $system_footprint_target_site['stylesheet'];
        }
        
        //Information if target is using child theme
        if (isset($system_footprint_target_site['using_child_theme'])) {
            $target_using_child	=	$system_footprint_target_site['using_child_theme'];
        }
        
        //Source array
        $source_array	= [
                'template'			=> $source_template,
                'stylesheet'		=> $source_stylesheet,
                'is_using_child'	=> $source_using_child
        ];
        
        //Target array
        $target_array	= [
                'template'			=> $target_template,
                'stylesheet'		=> $target_stylesheet,
                'is_using_child'	=> $target_using_child
        ];

        //Compare source and target array
        if ($source_array !== $target_array) {
            //Difference in theme setup detected

            //Compose output
            //Parent
            $source_parent_theme			= key($source_template);
            $source_parent_version			= reset($source_template);
            $target_parent_theme			= key($target_template);
            $target_parent_theme_version	= reset($target_template);
            
            $themeDifference['parent']	= [
                    'source_parent'		=> $source_parent_theme,
                    'source_version'	=> $source_parent_version,
                    'target_parent'		=> $target_parent_theme,
                    'target_version'	=> $target_parent_theme_version
            ];
            
            //Compose output
            //Child
            $source_child_theme			= key($source_stylesheet);
            $source_child_version		= reset($source_stylesheet);
            $target_child_theme			= key($target_stylesheet);
            $target_child_theme_version	= reset($target_stylesheet);
                
            $themeDifference['child']	= [
                    'source_child'		=> $source_child_theme,
                    'source_version'	=> $source_child_version,
                    'target_child'		=> $target_child_theme,
                    'target_version'	=> $target_child_theme_version
            ];
            
            //Using child themes
            $themeDifference['using_child_theme']['source'] = $source_using_child;
            $themeDifference['using_child_theme']['target'] = $target_using_child;
        }
        
        return apply_filters('prime_mover_filter_theme_diff', $themeDifference);
    }
    /**
     * Checks whether a sub-site in a multisite is using child theme
     * @param number $blog_id
     * @return string
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itChecksIfUsingChildTheme()
     */
    public function isUsingChildTheme($blog_id = 0)
    {
        $ret = 'no';
       
        $this->switchToBlog($blog_id);
        $template_path = get_template_directory();
        $stylesheet_path = get_stylesheet_directory();
        if ($template_path !== $stylesheet_path) {
            $ret = 'yes';
        }
        $this->restoreCurrentBlog();
        
        return $ret;
    }
    
    /**
     * Converts an array diff to a user friendly messages
     * @param array $diff
     * @return string
     * @compatible 5.6
     */
    public function printFriendlyDiffMessages($diff = [])
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $msg	 = '';
        $extra_space = false;
        $msg	.= sprintf(esc_html__(
            'Some dependencies required for the imported site is not meet. Please review and do %s proceed if this can adversely affect your site',
            'prime-mover'
        ) . ':' . PHP_EOL . PHP_EOL, '<strong>' . esc_html__('NOT', 'prime-mover') . '</strong>');
        /**
         * Deal with plugin differences first
         */
        if ((isset($diff['plugins'])) && (! empty($diff['plugins']))) {
            //Plugin differences detected
            //Loop over plugin differences
            $msg .= '<h3 class="prime_mover_diff_header">' . esc_html__('Plugin differences', 'prime-mover') . ' :' . '</h3>';
            foreach ($diff['plugins'] as $plugin_path	=> $plugin_diff_details) {
                $msg		.= esc_html__('Plugin', 'prime-mover') . ':' . ' ' . '<strong>' . $plugin_path . '</strong>' . PHP_EOL;
                if (isset($plugin_diff_details['source'])) {
                    //Deal with source
                    $source_site_plugins	= $plugin_diff_details['source'];
                    $source_info			= $source_site_plugins;
                    
                    //Check if this is valid versin number
                    $valid_version			= $this->isValidVersionNumber($source_site_plugins);
                    if (true === $valid_version) {
                        //Valid version
                        $source_info	= esc_html__('Version', 'prime-mover') . ' ' . $source_site_plugins;
                    }
                    $msg					.= esc_html__('Source site', 'prime-mover') . ':' . ' ' . '<em>' . $source_info . '</em>' . PHP_EOL ;
                }
                if (isset($plugin_diff_details['target'])) {
                    //Deal with target
                    $target_site_plugins	= $plugin_diff_details['target'];
                    $target_info			= $target_site_plugins;
                    
                    //Check if this is valid versin number
                    $valid_version			= $this->isValidVersionNumber($target_site_plugins);
                    if (true === $valid_version) {
                        //Valid version
                        $target_info	= esc_html__('Version', 'prime-mover') . ' ' . $target_site_plugins;
                    }
                    $msg	.= esc_html__('Target site', 'prime-mover') . ':' . ' ' . '<em>' . $target_info . '</em>' . PHP_EOL . PHP_EOL;
                }
            }
        }
        /**
         * Deal with theme differences next
         */
        if ((isset($diff['themes'])) && (! empty($diff['themes']))) {
            //Theme differences detected
            /**
             * Deal with source theme differences first
             */
            $msg .= '<h3 class="prime_mover_diff_header">' . esc_html__('Theme differences', 'prime-mover') . ' :' . '</h3>';
            
            //Whether to output any child diff sections
            $output_child_sections			= false;
            $source_using_child_theme		= '';
            $target_using_child_theme		= '';
            $no_child_theme_diff = false;
            if (empty($diff['themes']['child'])) {
                $no_child_theme_diff = true;
            }
            if (isset($diff['themes']['using_child_theme']['source'])) {
                $source_using_child_theme	=  $diff['themes']['using_child_theme']['source'];
            }
            if (isset($diff['themes']['using_child_theme']['target'])) {
                $target_using_child_theme	=  $diff['themes']['using_child_theme']['target'];
            }
            if (('yes' === $source_using_child_theme || 'yes' === $target_using_child_theme) && false ===  $no_child_theme_diff) {
                //Either a source or target is using child, we output child sections for comparison.
                $output_child_sections		= true;
            }
            //Get mandatory parent theme information of source site
            if (isset($diff['themes']['parent']['source_parent'])) {
                $source_parent_theme			= $diff['themes']['parent']['source_parent'];
                $source_parent_theme_version	= $diff['themes']['parent']['source_version'];
                $string_parent_output			= $source_parent_theme . ' ' . esc_html__('version', 'prime-mover') . ' '. $source_parent_theme_version;
                $string_parent_label			= '';
                if (true === $output_child_sections) {
                    //We want to properly 'parent' theme for clarity.
                    $string_parent_label		= esc_html__('Source site parent theme', 'prime-mover');
                } else {
                    $string_parent_label		= esc_html__('Source site theme', 'prime-mover');
                }
                $msg							.= $string_parent_label . ':' . ' ' . '<em>' . $string_parent_output . '</em>' . PHP_EOL ;
            }
            if ('yes' === $source_using_child_theme) {
                //Source site is using child theme, mandatory display also.
                if (isset($diff['themes']['child']['source_child'])) {
                    $source_child_theme			= $diff['themes']['child']['source_child'];
                    $source_child_theme_version	= $diff['themes']['child']['source_version'];
                    $string_child_output		= $source_child_theme . ' ' . esc_html__('version', 'prime-mover') . ' ' . $source_child_theme_version;
                    $msg						.= esc_html__('Source site child theme', 'prime-mover') . ':' . ' ' . '<em>' . $string_child_output . '</em>' . PHP_EOL ;
                    $extra_space = true;
                }
            } else {
                //Conditional display only if one of them is using child theme
                if (true === $output_child_sections) {
                    //Source site not using child theme
                    $string_child_output			= esc_html__('Not using child theme.', 'prime-mover');
                    $msg							.= esc_html__('Source site child theme', 'prime-mover') . ':' . ' ' . '<em>' . $string_child_output . '</em>' . PHP_EOL ;
                    $extra_space = true;
                }
            }
            /**
             * Deal with target theme differences first
             */
            //Get mandatory parent theme information of target site
            if (isset($diff['themes']['parent']['target_parent'])) {
                if ($extra_space) {
                    $msg .= PHP_EOL;
                }
                $target_parent_theme			= $diff['themes']['parent']['target_parent'];
                $target_version_string = '';
                if (isset($diff['themes']['parent']['target_version'])) {
                    $target_parent_theme_version	= $diff['themes']['parent']['target_version'];
                    $target_version_string = esc_html__('version', 'prime-mover') . ' ' . $target_parent_theme_version;
                }
                $string_parent_output			= $target_parent_theme . ' ' . $target_version_string;
                $string_parent_label_target		= '';
                if (true === $output_child_sections) {
                    //Using child theme, we properly parent
                    $string_parent_label_target	= esc_html__('Target site parent theme', 'prime-mover');
                } else {
                    //Not using child theme
                    $string_parent_label_target	= esc_html__('Target site theme', 'prime-mover');
                }
                $msg							.= $string_parent_label_target . ':' . ' ' . '<em>' . $string_parent_output . '</em>' . PHP_EOL ;
            }
            if ('yes' === $target_using_child_theme) {
                //Target site is using child theme, mandatory display also.
                if (isset($diff['themes']['child']['target_child'])) {
                    $target_child_theme			= $diff['themes']['child']['target_child'];
                    $target_version_string_child = '';
                    if (isset($diff['themes']['child']['target_version'])) {
                        $target_child_theme_version	= $diff['themes']['child']['target_version'];
                        $target_version_string_child = esc_html__('version', 'prime-mover') . ' ' . $target_child_theme_version;
                    }
                    $string_child_output_target	= $target_child_theme . ' ' . $target_version_string_child;
                    $msg						.= esc_html__('Target site child theme', 'prime-mover') . ':' . ' ' . '<em>' . $string_child_output_target . '</em>' . PHP_EOL ;
                }
            } else {
                //Conditional display only if one of them is using child theme
                if (true === $output_child_sections) {
                    //Target site not using child theme
                    $string_child_output_target		= esc_html__('Not using child theme.', 'prime-mover');
                    $string_child_output_target = apply_filters('prime_mover_filter_target_childtheme_msg', $string_child_output_target, $diff);
                    $msg							.= esc_html__('Target site child theme', 'prime-mover') . ':' . ' ' . '<em>' . $string_child_output_target . '</em>' . PHP_EOL ;
                }
            }
        }
        $msg .= PHP_EOL;
        $msg .= esc_html__('Are you sure you want to proceed with the import?', 'prime-mover') . PHP_EOL;
        $msg = wpautop($msg);
        return $msg;
    }
    
    /**
     * Checks if a string is a valid version number
     * @param string $str
     * @return boolean
     * @compatible 5.6
     */
    public function isValidVersionNumber($str = '')
    {
        $valid	= false;
        if (preg_match('/[0-9]/', "$str")) {
            $valid = true;
        }
        return $valid;
    }
    
    /**
     * Check if the system meets the minimum PHP version requirement.
     * @return boolean
     * @compatible 5.6
     */
    public function compliedMinimumRequirement()
    {
        $complied	= false;
        if (version_compare(PHP_VERSION, '5.6.0') >= 0) {
            $complied	= true;
        }
        return $complied;
    }
    
    /**
     * Returns TRUE if wp_filesystem is usable, false otherwise
     * @param $wp_filesystem
     * @return boolean
     */
    public function isWpFileSystemUsable($wp_filesystem = null)
    {
        if ( ! is_object($wp_filesystem)) {
            return false;
        }
        return true;
    }
    
    /**
     * Enable maintenance mode during import
     * @compatible 5.6
     */
    public function enableMaintenanceDuringImport()
    {
        if ( ! is_multisite() ) {
            return;
        }
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if (apply_filters('prime_mover_bypass_maintenance_mode', false)) {
            return;
        }
        global $wp_filesystem;
        if ( ! $this->isWpFileSystemUsable($wp_filesystem)) {
            return;
        }
        $file = $wp_filesystem->abspath() . '.maintenance';
        $maintenance_string = '<?php $upgrading = ' . time() . '; ?>';
        $wp_filesystem->delete($file);
        $wp_filesystem->put_contents($file, $maintenance_string, FS_CHMOD_FILE);
        do_action('prime_mover_log_processed_events', "Maintenance mode successfully ENABLED", 0, 'common', 'enableMaintenanceDuringImport', $this);
    }
    /**
     * Disable maintenance mode during import
     * @compatible 5.6
     */
    public function disableMaintenanceDuringImport()
    {
        if ( ! is_multisite() ) {
            return;
        }
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        global $wp_filesystem;
        if ( ! $this->isWpFileSystemUsable($wp_filesystem)) {
            return;
        }
        $file = $wp_filesystem->abspath() . '.maintenance';
        if ($wp_filesystem->exists($file)) {
            $wp_filesystem->delete($file);
            do_action('prime_mover_log_processed_events', "Maintenance mode successfully DISABLED", 0, 'common', 'disableMaintenanceDuringImport', $this);
        }
    }
    
    /**
     * Checks if maintenance mode is enabled
     * @compatible 5.6
     * @return boolean
     */
    public function isMaintenanceModeEnabled()
    {
        $ret = false;
        global $wp_filesystem;
        if ( ! $this->isWpFileSystemUsable($wp_filesystem)) {
            return $ret;
        }
        $file = $wp_filesystem->abspath() . '.maintenance';
        if ($wp_filesystem->exists($file)) {
            $ret = true;
        }
        return $ret;        
    }
    
    /**
     * Given the blog ID, retrieved the export subsite directory
     * @compatible 5.6
     * @param number $blog_id
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsExportPathofSubsite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itDoesNotGetExportPathIfNotAuthorized() 
     */
    public function getExportPathOfThisSubsite($blog_id = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $ret = '';
        $blog_id = (int) $blog_id;
        $export_path = $this->getSystemInitialization()->getMultisiteExportFolderPath();
        if ($blog_id > 0 && $export_path) {
            $ret= $export_path . $blog_id . DIRECTORY_SEPARATOR;
        }
        return $ret;
    }
    /**
     * Check if directory is empty
     * @param string $path
     * @return boolean
     * @compatible 5.6
     * @codeCoverageIgnore
     */
    public function checkIfDirectoryIsEmpty($path = '')
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $empty = false;
        if (!empty($path)) {
            if (count(glob("$path*")) === 0) {
                $empty	= true;
            }
        }
        return $empty;
    }
    
    /**
     * Generate import replaceables
     * @param array $ret
     * @return array
     * @compatible 5.6
     */
    public function generateImportReplaceables($ret = [])
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $replaceables = [];
        /**
         * Validate info
         */
        $valid_input_replaceables	= false;

        if (!empty($ret['origin_site_url']) && !empty($ret['origin_wp_root']) && !empty($ret['target_site_url']) && !empty($ret['target_wp_root'])) {
            $valid_input_replaceables	= true;
        }
        
        if (false === $valid_input_replaceables) {
            return $replaceables;
        }
        
        //All data inputs is valid at this point
        $origin_wp_root = $ret['origin_wp_root'];
        $target_wp_root = $ret['target_wp_root'];
        $rtrimmed_origin_wp_root = rtrim($origin_wp_root, '/');
        $rtrimmed_target_wp_root = rtrim($target_wp_root, '/');

        $original_host = $ret['origin_site_url'];
        $target_host = $ret['target_site_url'];
        
        /**
        * 1.) Trailing slash appended WordPress root paths
        */
        $replaceables['wproot_slash_appended'] = [
                'search' => $origin_wp_root,
                'replace' => $target_wp_root
        ];
        /**
        * 2.) Removed trailing slash WordPress root paths
        */
        $replaceables['removed_trailing_slash_wproot'] = [
                'search' => $rtrimmed_origin_wp_root,
                'replace' => $rtrimmed_target_wp_root
        ];
        
        /**
         * 3.) Lastly the domains
         */
        $replaceables['domain_replace']	= [
                'search' => $original_host,
                'replace' => $target_host
        ];
        
        return apply_filters('prime_mover_filter_replaceables', $replaceables, $ret);
    }
    
    /**
     * Encapsulated method to retrieved post_max_size
     * @return string
     * @compatible 5.6
     */
    public function getPostMaxSizeCorePhpValue()
    {
        $post_max_size	= ini_get('post_max_size');
        return $post_max_size;
    }
    
    /**
     * This method retrieves the actual post_max_size setting as it was set in php_ini
     * The return value is in bytes
     * @return number
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetRetrievesPostMaxSizeInPhpiniCorrectlyIfMbNotated()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itRetrievesPostMaxSizeInPhpiniCorrectlyIfGbNotated()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itRetrievesPostMaxSizeInPhpiniCorrectlyIfKbNotated()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itRetrievesPostMaxSizeInPhpiniCorrectlyIfBytesNotated()
     */
    public function getPostMaxSizeInPhpini()
    {
        $bytes		= 0;
        $post_max_size	= $this->getPostMaxSizeCorePhpValue();
        
        //Translate to bytes
        $bytes	=	$this->getBytes($post_max_size);
        return $bytes;
    }
    
    /**
     * Get md5 file
     * @param string $file
     * @return string
     */
    public function getMd5File($file = '')
    {
        return md5_file($file);
    }
    
    /**
     * Encapsulated method to retrieved upload_max_filesize
     * Publicly overridable no auth check
     * @return string
     * @compatible 5.6
     */
    public function getUploadMaxFilesizeCorePhpValue()
    {
        $upload_max_size = ini_get('upload_max_filesize');
        return $upload_max_size;
    }
    
    /**
     * Checks if upload parameters in PHP setting is misconfigured
     * Ideally, post_max_size should be large than upload_max_filesize
     * @return boolean
     */
    public function maybeUploadParametersMisconfigured()
    {
        $post_max_size = $this->getPostMaxSizeInPhpini();
        $upload_max_filesize = $this->getUploadmaxFilesizeInPhpini();
        
        return ($upload_max_filesize > $post_max_size);
    }
    
    /**
     * This method retrieves the actual upload max filesize setting as it was set in php_ini
     * The return value is in Megabytes already.
     * @return number
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itRetrievesUploadMaxsizeInPhpiniCorrectlyIfMbNotated()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itRetrievesUploadMaxsizeInPhpiniCorrectlyIfGbNotated()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itRetrievesUploadMaxsizeInPhpiniCorrectlyIfKbNotated() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itRetrievesUploadMaxsizeInPhpiniCorrectlyIfKbNotated()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itRetrievesUploadMaxsizeInPhpiniCorrectlyIfBytesNotated()
     */
    public function getUploadmaxFilesizeInPhpini()
    {
        $bytes	= 0;
        $upload_max_size = $this->getUploadMaxFilesizeCorePhpValue();
        
        //Translate to bytes
        $bytes = $this->getBytes($upload_max_size);
        return $bytes;
    }
    
    /**
     * get Slice size that depends on the server uploads configuration
     * @return number
     * @compatible 5.6
     */
    public function getSliceSize($default = false)
    {
        global $is_nginx;        
        $upload_max_filesize = $this->getUploadmaxFilesizeInPhpini();
        if ($is_nginx) {
            $upload_max_filesize = 1048576;
        }
        $slice_size = $upload_max_filesize * 0.95;
        if ($default) {
          return $slice_size;
        } 
        if (defined('PRIME_MOVER_SLICE_SIZE')) {
            $override = (int) PRIME_MOVER_SLICE_SIZE;
            $post_max_size = $this->getPostMaxSizeInPhpini();
            if ($override > 0 && $post_max_size > $override) {
                $slice_size = $override;
            }
        }

        return apply_filters('prime_mover_get_slice_size', $slice_size);                
    }
    /**
     * Delete tmp import files
     * @param array $ret
     * @param number $blogid_to_import
     * @param array $files_array
     * @compatible 5.6
     */
    public function removeTmpImportFilesDir($ret = [], $blogid_to_import = 0, $files_array = [])
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        global $wp_filesystem;
        if ( ! $this->isWpFileSystemUsable($wp_filesystem)) {
            return;
        }
        if (isset($ret['unzipped_directory']) && $wp_filesystem->exists($ret['unzipped_directory'])) {
            $wp_filesystem->rmdir($ret['unzipped_directory'], true);
        }
    }
    
    /**
     * Adopted from here:
     * https://stackoverflow.com/questions/1336581/is-there-an-easy-way-in-php-to-convert-from-strings-like-256m-180k-4g-to
     * @param string $byte_data
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsBytes()
     */
    public function getBytes($str = '')
    {
        if (empty($str)) {
            return 0;
        }
        $str = trim($str);
        $last = strtolower($str[ strlen($str)-1]);

        if (is_numeric($last)) {
            $val = (int) $str;
        } else {
            $val = (int) substr($str, 0, -1);
        }
        switch ($last) {
            case 'g': case 'G': $val *= 1024;
            // no break
            case 'm': case 'M': $val *= 1024;
            // no break
            case 'k': case 'K': $val *= 1024;
        }
        return $val;
    }
    
    /**
     * @param string $zip_path
     * @return string|string|mixed
     * @compatible 5.6
     */
    public function getCorrectFolderNameFromZip($zip_path = '')
    {
        $dir_or_file = '';
        $archive = $this->getZipArchiveInstance();
        $archive->open($zip_path);
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $data = $archive->statIndex($i);
            if (isset($data['name']) && is_string($data['name']) & ! empty($data['name'])) {
                $dir_or_file = $data['name'];
                $ds_detection = substr($dir_or_file, -1);
                if ('/' === $ds_detection || '\\' === $ds_detection) {
                    return basename($dir_or_file);
                }
            }
        }
        return $dir_or_file;
    }
    
    /**
     * @param number $blog_id
     * @return string
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsDbPrefixOfSite()
     */
    public function getDbPrefixOfSite($blog_id = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $prefix = '';
        if (! $blog_id) {
            return $prefix;
        }
        
        //Switch to this blog
        $this->switchToBlog($blog_id);
        
        global $wpdb;
        if (isset($wpdb->prefix)) {
            $prefix = $wpdb->prefix;
        }
        //restore current blog
        $this->restoreCurrentBlog();
        return $prefix;
    }
    
    /**
     * Clean dB tables for exporting
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverExporter::itDumpsDbforExportWhenAllSet()
     * @param array $inputs
     * @param number $blog_id
     * @param \wpdb $wpdb
     * @param string $mode
     * @return void|array|mixed[]
     * @mainsitesupport_affected
     */
    public function cleanDbTablesForExporting($inputs = [], $blog_id = 0, wpdb $wpdb = null, $mode = 'export')
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        $table_names = [];
        $excluded = [];
        $multisite = false;
        $separator = '';
            
        if (is_multisite()) {
            $multisite = true;
        }
            
        if ($multisite && !$this->isMultisiteMainSite($blog_id, true)) {           
            $separator = '_';
        } 
        
        if ('export' === $mode ) {
            $db_prefix = $wpdb->prefix;
            $match_prefix = $db_prefix;
            
            if ($multisite) {
                $match_prefix = apply_filters('prime_mover_filter_match_prefix', $db_prefix . $blog_id, $wpdb, $blog_id);
            }
            
            $users_table = $match_prefix . $separator . 'users';
            $usermeta_table = $match_prefix . $separator . 'usermeta';
            $excluded = [$users_table, $usermeta_table];
        }
        
        if (empty($inputs) || ! is_array($inputs)) {
            return $table_names;
        }
        
        foreach ($inputs as $table_detail) {
            if (is_array($table_detail)) {
                $table_to_export = reset($table_detail);
                if ( ! in_array($table_to_export, $excluded)) {
                    $table_names[] = $table_to_export;
                }                
            }
        }
        return $table_names;
    }
    
    /**
     * 
     * @param string $path
     * @return boolean
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itRunsPrimeMoverImportProcessorIfAllClear()
     */
    public function fileExists($path = '')
    {
        $exist = false;
        if ( ! $path ) {
            return $exist;
        }
        global $wp_filesystem;
        if ( ! $this->isWpFileSystemUsable($wp_filesystem)) {
            return;
        }
        return $wp_filesystem->exists($path);
    }
    
    /**
     * PHP set_time_limit maximum timeout possible (if supported)
     */
    public function setTimeLimit()
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
    }
    
    /**
     * Temporarily increase limits
     * @codeCoverageIgnore
     */
    public function temporarilyIncreaseMemoryLimits()
    {
        $this->setTimeLimit();
        if (defined('PRIME_MOVER_TEST_LOW_MEMORY') && PRIME_MOVER_TEST_LOW_MEMORY) {
            do_action('prime_mover_log_processed_events', 'Test low memory scenario', 0, 'common', 'temporarilyIncreaseMemoryLimits', $this);
            return;
        }
        
        @ini_set('memory_limit', '2048M');
    }
    
    /**
     * Non-cached file exist call
     * @param string $given
     * @return boolean
     * @codeCoverageIgnore
     * 
     */
    public function nonCachedFileExists($given = '', $clear_cache = false)
    {
        if ( ! $given ) {
            return false;
        }
        
        if ($clear_cache) {
            clearstatcache();
        }
        return @file_exists($given);
    }
  
    /**
     * Sha256 File
     * @param string $results
     * @return string
     * @codeCoverageIgnore
     * 
     */
    public function sha256File($results = '')
    {        
        return $this->hashFile($results, 'sha256');
    }
    
    /**
     * Hash file given with a defined algorithm
     * @param string $file
     * @param string $algo
     * @return string
     */
    public function hashFile($file = '', $algo = 'sha256')
    {
        if (is_readable($file)) {
            return hash_file($algo, $file);
        }
        return '';  
    }
    
    /**
     * Hash string
     * @param string $string
     * @param boolean $double_hash
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itHashString()
     */
    public function hashString($string = '', $double_hash = false)
    {
        if ($double_hash) {
            $output_binary = hash('sha256', $string, true);
            return hash('sha256', $output_binary);
        } else {
            return hash('sha256', $string);
        }        
    }
    
    /**
     * Generate hash string from the contents of a directory or a file.
     * @param string $input
     * @param string $algo
     * @return string|boolean
     * @codeCoverageIgnore
     */
    public function hashEntity($input = '', $algo = 'md4')
    {
        $input = wp_normalize_path(untrailingslashit($input));
        if (is_file($input)) {
            return $this->hashFile($input, $algo);
        } elseif (is_dir($input ) ) {
            $files = [];
            $dir = dir($input);
            while (false !== ($file = $dir->read())) {
                if ($file != '.' and $file != '..') {
                    if (is_dir(wp_normalize_path($input . '/' . $file))) {
                        $files[] = $this->hashEntity(wp_normalize_path($input . '/' . $file), $algo);
                    } else {
                        $files[] = $this->hashFile(wp_normalize_path($input . '/' . $file), $algo);
                    }
                }
            }
            $dir->close();
            return hash($algo, (implode('', $files)));
        }
        return false;
    }
    
    /**
     * Get file size
     * @param string $path
     * @return int
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsFileSize() 
     */
    public function getFileSize($path = '')
    {
        
        return $this->fileSize64($path);
    }
        
    /**
     * Single-site compatible switch to blog
     * @param number $blog_id
     */
    public function switchToBlog($blog_id = 0)
    {
        $this->getSystemInitialization()->switchToBlog($blog_id);
    }
    
    /**
     * Single-site compatible restoreCurrentBlog
     */
    public function restoreCurrentBlog()
    {
        $this->getSystemInitialization()->restoreCurrentBlog();
    }
    
    /**
     * Single-site compatible getBlogOption
     * @param number $blog_id
     * @param string $option
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsBlogOptionOnMultisite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsBlogOptionOnSingleSite() 
     * 
     */
    public function getBlogOption($blog_id = 0, $option = '')
    {
        $this->removePluginManager();
        if (is_multisite()) {
            return get_blog_option($blog_id, $option);
        } else {
            return get_option($option);
        }
        $this->addPluginManager();
    }

    /**
     * Single-site compatible update blog option
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itUpdatesBlogOptionInMultisite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itUpdatesBlogOptionInSingleSite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itDoesNotUpdateBlogOptionUnauthorized()
     * @param number $blog_id
     * @param string $option
     * @param mixed $value
     * @param boolean $super_admin_check_only
     */
    public function updateBlogOption($blog_id = 0, $option = '', $value = null, $super_admin_check_only = true)
    {
        if (!$this->getSystemInitialization()->isAdministrator($super_admin_check_only)) {
            return;
        }
        $this->removePluginManager();
        if (is_multisite()) {
            update_blog_option($blog_id, $option, $value);
        } else {
            update_option($option, $value);
        }
        $this->addPluginManager();
    }
    
    /**
     * Get option wrapper by disabling plugin manager
     * @param string $option
     * @param boolean $default
     * @return mixed|boolean|NULL|array
     */
    public function getOption($option = '', $default = false)
    {
        $this->removePluginManager();
        $opt_val = get_option($option, $default);
        $this->addPluginManager();
        
        return $opt_val;
    }
    
    /**
     * Update option wrapper by disabling plugin manager
     * @param string $key
     * @param mixed $value
     */
    public function updateOption($key = '', $value = null)
    {        
        if (!$key) {
            return;
        }
        $this->removePluginManager();
        update_option($key, $value);
        $this->addPluginManager();
    }
        
    /**
     * Force delete option caches
     * @param string $option
     * @param boolean $force_delete_cache
     * @param boolean $multisite
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itForcesDeleteOptionCachesInMultisite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itForcesDeleteOptionCachesSingleSite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itDoesNotForcesDeleteOptionCachesUnauthorized()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itDoesNotForceDeleteCacheWhenFalse()
     */
    public function maybeForceDeleteOptionCaches($option = '', $force_delete_cache = false, $multisite = false )
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $perform = false;        
        if ($force_delete_cache && $option) {
            $perform = true; 
        }
        if (!$perform ) {
            return;
        }
        if ($multisite) {
            $network_id = get_current_network_id();
            $cache_key = "$network_id:$option";
            wp_cache_delete( $cache_key, 'site-options' );
        } else {
            wp_cache_delete( $option, 'options' );
            wp_cache_delete('alloptions', 'options');
        }
    }

    /**
     * Single-site compatible get site option 
     * @param string $option
     * @param boolean $default
     * @param boolean $use_cache
     * @param boolean $force_delete_cache
     * @return mixed
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsSiteOptionWhenMultisite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsSiteOptionWhenSingleSite() 
     */
    public function getSiteOption($option = '', $default = false, $use_cache = true, $force_delete_cache = false)
    {        
        $this->removePluginManager();
        if (is_multisite()) {
            $this->maybeForceDeleteOptionCaches($option, $force_delete_cache, true);
            return get_site_option($option, $default, $use_cache);
        } else {
            $this->maybeForceDeleteOptionCaches($option, $force_delete_cache, false);   
            return get_option($option, $default);
        }
        $this->addPluginManager();
    }
    
    /**
     * Single-site compatible update site option 
     * @param string $key
     * @param $value
     * @param boolean $force_delete_cache
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itUpdatesSiteOptionWhenMultisite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itUpdatesSiteOptionWhenSingleSite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itDoesNotUpdatesSiteOptionWhenUnauthorized() 
     */
    public function updateSiteOption($key = '', $value = null, $force_delete_cache = false) 
    {
        $ret = false;
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        $this->removePluginManager();
        if (is_multisite()) {
            $this->maybeForceDeleteOptionCaches($key, $force_delete_cache, true);
            $ret = update_site_option($key, $value);
        } else {
            $this->maybeForceDeleteOptionCaches($key, $force_delete_cache, false);
            $ret = update_option($key, $value);
        }
        $this->addPluginManager();
        return $ret;
    }
    
    /**
     * Single-site compatible delete site option
     * @param string $key
     * @param boolean $force_delete_cache
     */
    public function deleteSiteOption($key = '', $force_delete_cache = false)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $ret = false;
        $this->removePluginManager();
        if (is_multisite()) {
            $this->maybeForceDeleteOptionCaches($key, $force_delete_cache, true);
            $ret = delete_site_option($key);
        } else {
            $this->maybeForceDeleteOptionCaches($key, $force_delete_cache, false);
            $ret = delete_option($key);
        }
        $this->addPluginManager();     
        return $ret;
    }
    
    /**
     * Add plugin manager
     * @codeCoverageIgnore
     */
    public function addPluginManager()
    {
        $plugin_manager = $this->getSystemInitialization()->getPrimeMoverPluginManagerInstance();
        if (is_object($plugin_manager) && $plugin_manager->primeMoverMaybeLoadPluginManager()) {
            add_filter('option_active_plugins', [$plugin_manager, 'loadOnlyPrimeMoverPlugin']);
            add_filter('site_option_active_sitewide_plugins', [$plugin_manager, 'loadOnlyPrimeMoverPlugin']);
            add_filter('stylesheet_directory', [$plugin_manager, 'disableThemeOnPrimeMoverProcesses'], 10000);
            add_filter('template_directory', [$plugin_manager, 'disableThemeOnPrimeMoverProcesses'], 10000);
        }
    }
    
    /**
     * Remove plugin manager
     * @codeCoverageIgnore
     */
    public function removePluginManager()
    {
        $plugin_manager = $this->getSystemInitialization()->getPrimeMoverPluginManagerInstance();
        if (is_object($plugin_manager)) {
            remove_filter('option_active_plugins', [$plugin_manager, 'loadOnlyPrimeMoverPlugin']);
            remove_filter('site_option_active_sitewide_plugins', [$plugin_manager, 'loadOnlyPrimeMoverPlugin']);
            remove_filter('stylesheet_directory', [$plugin_manager, 'disableThemeOnPrimeMoverProcesses'], 10000);
            remove_filter('template_directory', [$plugin_manager, 'disableThemeOnPrimeMoverProcesses'], 10000); 
        }
    }
    
    /**
     * Checks if maybe we need to load menu assets
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itLoadsMenuAssetsIfMultisiteAndNetworkAdmin() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itLoadsMenuAssetsIfSingleSiteAndAdmin()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itDoesNotLoadMenuAssetsIfMultisiteNotNetworkAdmin()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itDoesNotLoadMenuAssetsIfSingleSiteNotAdmin()
     */
    public function maybeLoadMenuAssets()
    {
        if ( is_multisite() && is_network_admin() ) {
            return true;
        }
        if ( ! is_multisite() && is_admin() ) {
            return true;
        }
        return false;
    }   
    
    /**
     * Get activated plugins
     * @param number $blogid_to_export
     * @param array $activated_plugins_list
     * @return array
     */
    public function getActivatedPlugins($blogid_to_export = 0, $activated_plugins_list = [])
    {
        //Get active plugins in this site
        $active_plugins = $this->getBlogOption($blogid_to_export, 'active_plugins');
        
        //Get all network activated plugins
        $active_sitewide_plugins = [];
        if (is_multisite()) {
            $active_sitewide_plugins = $this->getSiteOption('active_sitewide_plugins');
        }
        
        //Loop over active plugins
        if (is_array($active_plugins)) {
            foreach ($active_plugins as $active_plugin) {
                $activated_plugins_list[] = $active_plugin;
            }
        }
        
        //Loop over network activated plugins
        if (is_array($active_sitewide_plugins)) {
            $active_sitewide_plugins = array_keys($active_sitewide_plugins);
            foreach ($active_sitewide_plugins as $network_active_plugin) {
                $activated_plugins_list[] = $network_active_plugin;
            }
        }
     
        //Filter unique activated plugins list
        return array_unique($activated_plugins_list);
    }
    
    /**
     * Memory efficient readfile() version
     * @param string $filename
     * @param boolean $retbytes
     * @param boolean $flush_var
     * @param number $offset
     * @param number $blog_id
     * @return boolean|number
     * @codeCoverageIgnore
     */
    public function readfileChunked($filename = '', $retbytes = true, $flush_var = false, $offset = 0, $blog_id = 0) 
    {
        if ( ! $filename ) {
            return false;
        }
        $buffer = '';
        $cnt    = 0;
        $handle = fopen($filename, 'rb');
        
        if ($handle === false) {
            return false;
        }
   
        if ($offset) {
            do_action('prime_mover_log_processed_events', "Offset requested: $offset", $blog_id, 'import', 'readfileChunked', $this);
            fseek($handle, $offset);
        }
        while (!feof($handle)) {
            $buffer = fread($handle, 1024*1024);
            echo $buffer;
            
            if ($flush_var && ob_get_level() > 0) {
                ob_flush();
                flush();
            }
            
            if ($retbytes) {
                $cnt += strlen($buffer);
            }
        }
        
        $status = fclose($handle);
        
        if ($retbytes && $status) {
            return $cnt;
        }
        
        return $status;
    }
    
    /**
     * Unzip file , chunked method.
     * @param string $file_path
     * @param string $destination
     * @param number $blog_id
     * @codeCoverageIgnore
     */
    public function unzipFileChunked($file_path = '', $destination = '', $blog_id = 0)
    {
        if ( ! $file_path || ! $destination ) {
            return false;
        }
        $filename = $file_path;
        if ( ! file_exists($filename) ) {
            do_action('prime_mover_log_processed_events', "File does not exist so it cannot be zip_open: $filename", $blog_id, 'import', 'unzipFileChunked', $this);
            return false;
        }
        $archive = zip_open($filename);
        if ( ! is_resource($archive) ) {
            do_action('prime_mover_log_processed_events', "Not a zip resource after being opened: $filename", $blog_id, 'import', 'unzipFileChunked', $this);
            return false;
        }
        while($entry = zip_read($archive)){
            $size = zip_entry_filesize($entry);
            $name = zip_entry_name($entry);
            
            $path = $destination . $name;
            $directory = rtrim($name, '/');
            if ($directory !== $name ) {
                wp_mkdir_p($path);
            } else {
                $unzipped = @fopen($path, 'wb');
                if ($unzipped) {
                    while($size > 0){
                        $this->temporarilyIncreaseMemoryLimits();
                        $chunkSize = ($size > 33554432) ? 33554432 : $size;
                        $size -= $chunkSize;
                        $chunk = zip_entry_read($entry, $chunkSize);
                        if($chunk !== false) fwrite($unzipped, $chunk);
                    }
                    fclose($unzipped);
                } else {
                    do_action('prime_mover_log_processed_events', "Failed to open file for writing while unzipping: $path", $blog_id, 'import', 'unzipFileChunked', $this);
                }
            }
        }
        do_action('prime_mover_log_processed_events', "Unzip success", $blog_id, 'import', 'unzipFileChunked', $this);
        return true;
    }
    
    /**
     * Get libzip version
     * @param array $phpinfo
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsLibZipVersion() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itReturnsLibZipVersionEvenPhpInfoDisabled() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itReturnsEmptyLibZipVersionIfCannotDetermine()
     */
    public function getLibZipversion($phpinfo = [])
    {
        $ret = '';
        if (empty($phpinfo)) {
            $ziparchive = $this->getSystemInitialization()->getZipArchiveInstance(true);
            if (is_wp_error($ziparchive)) {
                return $ret;
            }
            if(defined(get_class($ziparchive).'::LIBZIP_VERSION')) {
                return ZipArchive::LIBZIP_VERSION;
            } else {
                return $ret;
            }
        }        
        
        if (!is_array($phpinfo) || empty($phpinfo['zip'])) {
            return $ret;
        }
        $server_zip_info = $phpinfo['zip'];
        foreach ($server_zip_info as $zip_configuration => $setting) {
            $zip_configuration = strtolower($zip_configuration);
            if (false !== strpos($zip_configuration, 'libzip') && false !== strpos($zip_configuration, 'version') && false === strpos($zip_configuration, 'header') ) {
                return $setting;
            }
        }
        return $ret;       
    }
    
    /**
     * Check if we need to load asset on settings page
     * @param WP_Screen $current_screen
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itLoadsAssetsProperlyOnBasicAndAdvancesettings() 
     */
    public function maybeLoadAssets(WP_Screen $current_screen)
    {
        $prime_mover_slug = $this->getSystemInitialization()->getPrimeMoverMenuSlug();
        $panel_identifier = "{$prime_mover_slug}_page_migration-panel-basic-settings-network";
        $advance_panel_identifier = "{$prime_mover_slug}_page_migration-panel-advance-settings-network";
        if ( ! is_multisite() ) {
            $panel_identifier = "{$prime_mover_slug}_page_migration-panel-basic-settings";
            $advance_panel_identifier = "{$prime_mover_slug}_page_migration-panel-advance-settings";
        }
        return ( $panel_identifier === $current_screen->id ||
            $advance_panel_identifier === $current_screen->id );
    }
    
    /**
     * Check if we need to load asset on dashboard
     * @param WP_Screen $current_screen
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itLoadsAssetsOnDashboardIfItsPanelSettingsPage() 
     */
    public function maybeLoadAssetsOnDashboard(WP_Screen $current_screen)
    {
        $prime_mover_slug = $this->getSystemInitialization()->getPrimeMoverMenuSlug();
        $panel_identifier = 'toplevel_page_migration-panel-settings-network';
        if ( ! is_multisite() ) {
            $panel_identifier = 'toplevel_page_migration-panel-settings';
        }
        if ( $panel_identifier === $current_screen->id ) {
            return true;
        }
        if ($this->maybeLoadAssets($current_screen)) {
            return true;
        }
        $account_page_id = "{$prime_mover_slug}_page_migration-panel-settings-account";
        if (is_multisite()) {
            $account_page_id = "{$prime_mover_slug}_page_migration-panel-settings-account-network";
        }
        return ($account_page_id === $current_screen->id);
    } 

    /**
     * Linode manage DB compatibility
     * Related: https://github.com/wp-cli/wp-cli/issues/5109
     * @param string $host
     * @return string|mixed
     */
    protected function linodeHostCompatibility($host = '')
    {
        if (substr($host, 0, 2) == 'p:') {
            $host = substr_replace($host, '', 0, 2);
        }
        
        return $host;
    }
    
    /**
     * Parse DB_HOST for PDO
     * @return boolean|string[]|
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itParsesDbHostForPdo() 
     */
    public function parsedBHostForPDO()
    {
        global $wpdb;
        $ret = [];
        if ( ! is_object($wpdb) ) {
            return false;
        }
        if (! is_callable([$wpdb, 'parse_db_host'])) {
            return false;
        }
        
        /**
         * Handle edge case scenarios DB_HOST
         */
        $db_host = $this->linodeHostCompatibility(DB_HOST);
        $host_array = $wpdb->parse_db_host($db_host);
        
        if ( ! $host_array ) {
            return false;
        }
        if (empty($host_array[0])) {
            return false;
        }
        $ret['host'] = $host_array[0];
        $ret['port'] = '';
        if ( ! empty( $host_array[1] ) ) {            
            $ret['port'] = intval($host_array[1]);
        }
        $ret['socket'] = '';
        if ( ! empty( $host_array[2] ) ) {
            $ret['socket'] = $host_array[2];
        }
        return $ret;
    }
    
    /**
     * Get plugin full path
     * @param string $plugin
     * @return string
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsPluginFullPath() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itReturnsNullIfWpFileSystemNotSet()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itReturnsNullIfPluginsNotExist()
     */
    public function getPluginFullPath($plugin = '', $exist_check = true)
    {
        $fullpath = '';
        if (! $plugin) {
            return $fullpath;
        }
        global $wp_filesystem;
        if ( ! $this->isWpFileSystemUsable($wp_filesystem)) {
            return $fullpath;
        }
        if ($exist_check && ! $wp_filesystem->exists(PRIME_MOVER_PLUGIN_CORE_PATH . $plugin)) {
            return $fullpath;
        }
        
        $fullpath = dirname(PRIME_MOVER_PLUGIN_CORE_PATH . $plugin) . DIRECTORY_SEPARATOR;
        if ( $fullpath === PRIME_MOVER_PLUGIN_CORE_PATH ) {
            return PRIME_MOVER_PLUGIN_CORE_PATH . $plugin;
        }
        return $fullpath;
    }
    
    /**
     * Activate Prime Mover Plugin Only (in cases when restoring a site and we are deactivating all plugins)
     * @param number $blog_id
     * @return void|boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itSkipsPrimeMoverPluginActivationOnMultisite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itSkipsPrimeMoverActivationNotAuthorized()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itActivatesPrimeMoverOnlySingleSite()
     */
    public function activatePrimeMoverPluginOnly($blog_id = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        $active_plugins_option = 'active_plugins';
        $active_plugins = [];
        $this->switchToBlog($blog_id);
        $deactivated_plugins = delete_option($active_plugins_option);     
        
        do_action('prime_mover_before_only_activated', $blog_id);  
        
        if (is_multisite()) { 
            $this->restoreCurrentBlog();
            return $deactivated_plugins;
        }
        
        $this->maybeForceDeleteOptionCaches($active_plugins_option, true, false);        
        
        if ( $this->getPluginFullPath(PRIME_MOVER_DEFAULT_PRO_BASENAME, true) ) {   
            $active_plugins[] = PRIME_MOVER_DEFAULT_PRO_BASENAME;
            
            
        } elseif ( $this->getPluginFullPath(PRIME_MOVER_DEFAULT_FREE_BASENAME, true) ) {
            $active_plugins[] = PRIME_MOVER_DEFAULT_FREE_BASENAME;
        }              
              
        $this->updateOption($active_plugins_option, $active_plugins);        
        $this->restoreCurrentBlog();        

        return $deactivated_plugins;
    }
    
    /**
     * Checks if zip package is encrypted
     * @param string $tmp_path
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itChecksIfZipPackageHasEntityEncrypted() 
     */
    public function isZipPackageHasEntityEncrypted($tmp_path = '', $signature_file = '')
    {
        if ( ! $tmp_path ) {
            return false;
        }
        if ( ! $signature_file ) {
            $signature_file = $this->getSystemInitialization()->getSignatureFile();
        }
        $za = $this->getZipArchiveInstance();
        $zip = $za->open($tmp_path);
        if (true === $zip && false !== $za->locateName($signature_file, \ZIPARCHIVE::FL_NODIR)) {
            return true;
        }
        return false;
    }
    
    /**
     * Maybe use store mode
     * @param boolean $store_mode
     * @param ZipArchive $ziparchive
     * @param string $local_name
     * @codeCoverageIgnore
     */
    protected function maybeUseStoreMode($store_mode = false, ZipArchive $ziparchive = null, $local_name = '')
    {
        if ( ! $store_mode || ! $ziparchive || ! $local_name) {
            return;
        }
        if ( ! method_exists($ziparchive, 'setCompressionName') ) {
            return;
        }         
        $ziparchive->setCompressionName($local_name, ZipArchive::CM_STORE);
    }
    
    /**
     * Add entity to Prime Mover zip package
     * @param string $zippath
     * @param boolean $createzip
     * @param string $entity
     * @param string $temp_folder_path
     * @param boolean $delete_on_success
     * @param boolean $encrypt
     * @param boolean $store_mode
     * @param boolean $inside_tmp_dir
     * @return boolean
     */
    public function addToPrimeMoverZipPackage($zippath = '', $createzip = false, $entity = '', $temp_folder_path = '', 
        $delete_on_success = false, $encrypt = false, $store_mode = false, $inside_tmp_dir = false, $close = true, $zip = null)
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return false;
        }
        if ( ! $zippath || ! $entity) {
            return false;
        }
        if (!$store_mode) {
            $store_mode = true;
        }
        if ( ! $this->nonCachedFileExists($zippath ) && ! $createzip ) {
            do_action('prime_mover_log_processed_events', "Zip path DOES NOT exist: $zippath", 0, 'common', 'addToPrimeMoverZipPackage', $this);
            return false;
        }
        $res = false;
        if ( ! $zip ) {
            /**
             * An opened zip instance is NOT passed, create and set
             */
            $zip = $this->getZipArchiveInstance();
            if ($createzip) {
                $res = $zip->open($zippath, ZipArchive::CREATE);
            } else {
                $res = $zip->open($zippath);
            } 
        }
        $zip_instance = $zip instanceof ZipArchive;
        if (true === $zip_instance) {
            $res = true;
        } else {
            return false;
        }
        
        $result = false;
        $entity = realpath($entity);
        $entity = wp_normalize_path($entity);
        $entity_to_delete = $entity;
        $encrypt_name = '';
        
        $opened = false;
        if (true === $res) {
            do_action('prime_mover_log_processed_events', "Zip OPENED: $zippath", 0, 'common', 'addToPrimeMoverZipPackage', $this);
            $opened = true;
        }
        
        if ( ! $opened ) {
            do_action('prime_mover_log_processed_events', "Zip NOT OPENED: $zippath", 0, 'common', 'addToPrimeMoverZipPackage', $this);
            return $result;
        }        
        
        if ($encrypt) {
            do_action('prime_mover_log_processed_events', "Setting encryption password", 0, 'common', 'addToPrimeMoverZipPackage', $this);
            $zip = $this->getSystemInitialization()->setEncryptionPassword($zip);
        }  
        
        $local_name = '';
        if ($temp_folder_path) {
            $temp_folder_path = basename($temp_folder_path);
            $local_name = $temp_folder_path . '/' . basename($entity);
            $encrypt_name = $local_name;
        }
        
        $is_file = false;        
        if (is_file($entity)) {
            $is_file = true;
        }
        
        if ($is_file && $local_name) {
            do_action('prime_mover_log_processed_events', "Adding file to archive: $entity", 0, 'common', 'addToPrimeMoverZipPackage', $this);
            $result = $zip->addFile($entity, $local_name);            
            $this->maybeUseStoreMode($store_mode, $zip, $local_name);
        }         
        
        if ( ! $is_file && ! $inside_tmp_dir) {
            $entity = basename($entity);
            $encrypt_name = $entity;
            do_action('prime_mover_log_processed_events', "Adding empty dir to archive: $entity", 0, 'common', 'addToPrimeMoverZipPackage', $this);
            $result = $zip->addEmptyDir($entity);
        }   
        
        if ( ! $is_file && $inside_tmp_dir && $local_name) {
            $entity = $local_name;
            $encrypt_name = $local_name;
            do_action('prime_mover_log_processed_events', "Adding empty dir to archive: $entity", 0, 'common', 'addToPrimeMoverZipPackage', $this);
            $result = $zip->addEmptyDir($entity);
        }
        
        if ($encrypt && $result && $encrypt_name) {
            do_action('prime_mover_log_processed_events', "Setting encryption name: $encrypt_name", 0, 'common', 'addToPrimeMoverZipPackage', $this);
            $zip->setEncryptionName($encrypt_name, \ZipArchive::EM_AES_256);
        }
        
        if ( ! $result ) {
            do_action('prime_mover_log_processed_events', "Result FALSE, bailing out.", 0, 'common', 'addToPrimeMoverZipPackage', $this);
            return $result;
        }
        
        do_action('prime_mover_log_processed_events', "Closing ZIP archive after entity is added.", 0, 'common', 'addToPrimeMoverZipPackage', $this);
        if ( ! $close ) {
            return $zip;
        }
        
        $result = $zip->close(); 
        do_action('prime_mover_log_processed_events', "Zipped archive successfully closed.", 0, 'common', 'addToPrimeMoverZipPackage', $this);
        
        if ($result && $delete_on_success) {
            do_action('prime_mover_log_processed_events', "Zipped archive successfully closed and we delete the entity.", 0, 'common', 'addToPrimeMoverZipPackage', $this);
            $this->primeMoverDoDelete($entity_to_delete);
        }
        
        return $result;
    }
    
    /**
     * Flush
     * @codeCoverageIgnore
     */
    public function flush()
    {
        flush();
        if (ob_get_level()) {
            ob_end_clean();
        }
    }
  
    /**
     * Create friendly file name for package
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverDownloadUtilities::itCreatesFriendlyName()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverDownloadUtilities::itUsesFilenaNameWhenBlogIdNotSet()
     * @param number $blogid_to_export
     * @param string $download_path
     * @param boolean $is_wprime
     * @param array $ret
     * @return string
     */
    public function createFriendlyName($blogid_to_export = 0, $download_path = '', $is_wprime = false, $ret = [])
    {
        if (! $blogid_to_export) {
            return basename($download_path);
        }
        $blog_name = $this->getBlogOption($blogid_to_export, 'blogname');
        $target_blog_id = 0;
        if (is_array($ret) && isset($ret['prime_mover_export_targetid'])) {
            $target_blog_id = $ret['prime_mover_export_targetid'];
        }
        $target_blog_id = (int)$target_blog_id;
        $blog_identification = '_export_package';
        if ($target_blog_id) {
            $blog_identification = "_blogid_{$target_blog_id}";
        }
        $sanitized_site_name = mb_strimwidth(sanitize_key($blog_name), 0, 20, '');
        $ext = ".zip";
        if ($is_wprime) {
            $ext = ".wprime";
        }
        $file_name = $sanitized_site_name . '_' . date('m-d-Y_hia') . $blog_identification . $ext;
        
        return sanitize_file_name($file_name);
    }
    
    /**
     * Testable wp_die() for non-ajax requests
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itStreamsErrorLog() 
     */
    public function wpDie()
    {        
        add_filter( 'wp_die_handler', function() {
            return '_ajax_wp_die_handler';
        });
        wp_die('', '', 200);
        remove_filter( 'wp_die_handler', function() {
            return '_ajax_wp_die_handler';
        });
    }
    
    /**
     * File is inside given directory
     * @param string $file
     * @param string $directory
     * @return boolean
     * @codeCoverageIgnore
     */
    public function fileIsInsideGivenDirectory($file = '', $directory = '')
    {
        if ( wp_is_stream( $file ) ) {
            $real_file = $file;
            $real_directory = $directory;
        } else {
            $real_file = realpath( wp_normalize_path( $file ) );
            $real_directory = realpath( wp_normalize_path( $directory ) );
        }
        
        if ( false !== $real_file ) {
            $real_file = wp_normalize_path( $real_file );
        }
        
        if ( false !== $real_directory ) {
            $real_directory = wp_normalize_path( $real_directory );
        }
        
        if ( false === $real_file || false === $real_directory || strpos( $real_file, trailingslashit( $real_directory ) ) !== 0 ) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Checks if the download URL is a default Prime Mover download zip URL format
     * @param string $url
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itReturnsFalseIfNotPrimeMoverDownloadUrl()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itChecksIfPrimeMoverDownloadZipUrl()
     */
    public function isPrimeMoverDownloadZipURLFormat($url = '', $return_params = false)
    {
        $parsed = wp_parse_url($url);
        if ( ! $parsed || ! isset($parsed['query'])) {
            return false;
        }
        $query_array = [];
        wp_parse_str($parsed['query'], $query_array);
        if (empty($query_array['prime_mover_export_hash']) || empty($query_array['prime_mover_blogid'])) {
            return false;
        }
        if ($return_params) {
            return ['hash' => $query_array['prime_mover_export_hash'], 'blog_id' => $query_array['prime_mover_blogid']];
        }        
        return true;
    }
    
    /**
     * Get upgrade URL
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsUpgradeUrlInMultisite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsUpgradeUrlInSingleSite()
     */
    public function getUpgradeUrl()
    {
        return $this->getSystemInitialization()->getUpgradeUrl();
    }
 
    /**
     * Checks if zip type by MIME check
     * @param string $filePath
     * @return boolean
     * @codeCoverageIgnore
     */
    public function isZipByMime($filePath = '')
    {
        if ( ! $filePath || ! is_readable($filePath)) {
            do_action('prime_mover_log_processed_events', "File is not readable", 0, '', 'isZipByMime', $this);
            return false;
        }
        $fh = @fopen($filePath, "r");
        if ( ! $fh) {
            do_action('prime_mover_log_processed_events', "Cannot open file for checking", 0, '', 'isZipByMime', $this);
            return false;
        }
        $blob = fgets($fh, 5);
        fclose($fh);
        if (false !== strpos($blob, 'PK')) {
            return true;
        }
        do_action('prime_mover_log_processed_events', "File is not zip.", 0, '', 'isZipByMime', $this);
        return false;
    }
    
    /**
     * Get zip archive instance
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsZipArchiveInstance()
     * @param boolean $return_wp_error
     * @return WP_Error|ZipArchive
     */
    public function getZipArchiveInstance($return_wp_error = false)
    {
        return $this->getSystemInitialization()->getZipArchiveInstance($return_wp_error);
    }
  
    /**
     * Checks if valid format
     * @param string $filename
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itChecksIfReallyValidFormat() 
     */
    public function isReallyValidFormat($filename = '')
    {
        if ($this->hasZipExtension($filename)) {
            return $this->isReallyZip($filename);
        }
        if ($this->hasTarExtension($filename)) {
            return $this->isReallyTar($filename);
        }
        return false;
    }
    
    /**
     * Do low-level checks if the file is really zip.
     * Check if the uploaded file is really zip (no WP filters here to be sure)
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itReturnsTrueIfReallyZip()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itReturnsFalseIfNotZip()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itReturnsFalseIfNotZipByMime() 
     * @param string $filePath
     * @param boolean $return_wp_error
     * @return boolean|\ZipArchive
     */
    public function isReallyZip($filePath = '', $return_wp_error = false)
    {
        $really_zip = false;
        if (!$this->isZipByMime($filePath)) {
            return $really_zip;
        }   
      
        $zip = $this->getZipArchiveInstance($return_wp_error);
        if (is_wp_error($zip)) {
            return $zip;
        }
               
        $res = $zip->open($filePath, \ZipArchive::CHECKCONS);
        if (true === $res) {
            $really_zip = true;
        }
        
        return $really_zip;
    }
    
    /**
     * Checks if the package is really a Prime Mover WPRIME package
     * @param string $filename
     * @param boolean $config_check_only
     * @param boolean $source_is_url
     * @return boolean|mixed|NULL|array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itChecksIfReallyTar()
     */
    public function isReallyTar($filename = '', $config_check_only = false, $source_is_url = false)
    {
        $tar_config = apply_filters('prime_mover_get_tar_package_config_from_file', [], $filename, $source_is_url);
        $config_exist = (is_array($tar_config) && !empty($tar_config));
        if ($config_check_only) {           
            return $config_exist;
        }
        
        if ($config_exist) {
            return apply_filters('prime_mover_is_archive_clean', false, $filename);
        }
        return false;
    }
    
    /**
     * Initialize site export directory
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itInitializesSiteExportDirectoryInMultisite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itInitializesSiteExportDirectoryInSingleSite() 
     * @mainsitesupport_detected
     */
    public function initializeSiteExportDirectory()
    {
        $blog_id = 1;
        if (is_multisite()) {
            $blog_id = get_current_blog_id();
        }
        $this->createSiteExportDirectory($blog_id);
    }
    
    /**
     * Create site export directory
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itCreatesSiteExportDirectory()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itDoesNotCreateDirectoryIfNoBlogId()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itDoesNotCreateSiteExportDirectoryWhenWpFileSystemUnusable()
     */
    public function createSiteExportDirectory($blog_id = 0) {
        if ( ! $blog_id ) {
            return;    
        }
        $blogexport_path = $this->getExportPathOfThisSubsite($blog_id);
        
        global $wp_filesystem;
        if ( ! $this->isWpFileSystemUsable($wp_filesystem)) {
            return;
        }
        $created = false;
        if ($blogexport_path) {
            $created = wp_mkdir_p($blogexport_path);  
        }
        if ($created) {
            $this->getSystemInitialization()->setSiteSpecificFolderCreated(wp_normalize_path($blogexport_path));
        }
    }
    
    /**
     * Log peak memory usage
     * @param number $blog_id
     * @param string $method
     * @param string $mode
     * @codeCoverageIgnore
     */
    public function logPeakMemoryUsage($blog_id = 0, $method = '', $mode = 'export', $memory_used = 0)
    {
        if ( ! $blog_id || ! $method || ! $memory_used) {
            return;
        }
        $megabytes_memory_used = ($memory_used/1024/1024)." MiB";
        do_action('prime_mover_log_processed_events', "PEAK MEMORY USAGE : $megabytes_memory_used", $blog_id, $mode, $method, $this);
    }
    
    /**
     * Handler for interacting with screen option setting.
     * @param string $mode
     * @param number $blog_id
     * @return void|number[]
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itShowsOnlySitesContainingBackups()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itReturnsNoSitesIfNoSitesContainingBackups()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itRemovesSitesWithNoBackupRecords()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itMarkSitesWithBackups()
     */
    public function doScreenOptionSettings($mode = 'get', $blog_id = 0)
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized() ) {
            return;
        }
        $option_name = $this->getSystemInitialization()->getBackupSitesOptionName();        
        $update = false;        
        
        $site_ids = $this->getSiteOption($option_name, false, true, true);
        if ( ! is_array($site_ids) ) {
            $site_ids = [];
        }
        if ('update' === $mode) {
            $update = true;
        }
        $key = array_search($blog_id, $site_ids);
        if ('delete' === $mode && false !== $key) {
            unset($site_ids[$key]);
            $update = true;
        }
        if ($update && false === $key) {
            $site_ids[] = $blog_id;
        }
        if ($update) {
            $this->updateSiteOption($option_name, $site_ids, true);
        }
        
        if ('get' === $mode) {
            return $site_ids;
        }
    }
    
    /**
     * Get backup menu URL
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsBackupMenuUrlOnMultisite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsBackupMenuUrlInSingleSite()
     * @param number $blog_id
     * @return string
     */
    public function getBackupMenuUrl($blog_id = 0)
    {
        $blog_id = (int)($blog_id);
        if (is_multisite() && ! $blog_id) {
            return network_admin_url( 'admin.php?page=migration-panel-backup-menu');
            
        } elseif (is_multisite() && $blog_id) {            
            return add_query_arg(['prime-mover-select-blog-to-query' => $blog_id], network_admin_url( 'admin.php?page=migration-panel-backup-menu'));
            
        } else {
            return admin_url( 'admin.php?page=migration-panel-backup-menu');
        }
    }
    
    /**
     * Get peak memory usage
     * @return number
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsPeakMemoryUsage()
     */
    public function getPeakMemoryUsage()
    {
        return memory_get_peak_usage(false);
    }
    
    /**
     * Do memory logging
     * @param array $ret
     * @param string $method
     * @param string $mode
     * @param number $blog_id
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itDoMemoryLogs()
     */
    public function doMemoryLogs($ret = [], $method = '', $mode = 'export', $blog_id = 0)
    {
        $memory_used = $this->getPeakMemoryUsage();
        
        $ret['peak_memory_usage_log'][] = $memory_used;
        $this->logPeakMemoryUsage($blog_id, $method, $mode, $memory_used);
        
        return $ret;
    }
    
    /**
     * Returns true if referer is backup menu
     * @param string $referer
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itRefererBackupMenu() 
     */
    public function isRefererBackupMenu($referer = '')
    {
        if (empty($referer)) {
            $referer = wp_get_raw_referer();
        }
        
        $parsed = [];
        $query_array = [];
        
        if ($referer) {
            $parsed = wp_parse_url($referer);
        }
        if ( ! empty( $parsed['query'] ) ) {
            wp_parse_str($parsed['query'], $query_array);
        }
        return ( ! empty($query_array['page']) && 'migration-panel-backup-menu' === $query_array['page']);
    }
    
    /**
     * Log cli re-processing array
     * @param array $ret
     * @param array $pending_to_process
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itLogsCliReprocessingArray() 
     */
    public function logCliReProcessingArray($ret = [], $pending_to_process = [])
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized() ) {
            return;
        }
        
        if ( ! $this->getSystemInitialization()->isCliEnvironment()) {
            return;
        }
        
        $cli_tmpname = $this->getSystemInitialization()->generateCliReprocessingTmpName($ret);  
        if ( ! $cli_tmpname ) {
            return;
        }
        if ($this->nonCachedFileExists($cli_tmpname)) {            
            return;
        }
        $pending_to_process_string = json_encode($pending_to_process);        
        global $wp_filesystem;
        $wp_filesystem->put_contents($cli_tmpname, $pending_to_process_string);            
    }
    
    /**
     * Maybe test slow copy
     * @codeCoverageIgnore
     */
    protected function maybeTestSlowCopy()
    {
        $delay = 0;
        if (defined('PRIME_MOVER_TEST_SLOW_CLI_RECURSE_COPY') ) {
            $delay = (int) PRIME_MOVER_TEST_SLOW_CLI_RECURSE_COPY;
        }
        if ($delay) {
            usleep($delay);
        }
    }
    
    /**
     * Log recursive copy restart
     * @param array $identifier
     * @codeCoverageIgnore
     */
    protected function doLogRecurseCopyRestart($identifier = [])
    {
        if ( empty($identifier['source']) || empty($identifier['mode'] ) || empty($identifier['blog_id']) ) {
            return;
        }
        
        $source = $identifier['source'];
        $mode = $identifier['mode'];
        $blog_id = $identifier['blog_id'];
        
        do_action('prime_mover_log_processed_events', "Recurse copy needs to restart - calling method: $source", $blog_id, $mode, 'recurseCopy', $this);        
    }
    
    /**
     * Recursive copy used in shell mode
     * @param string $src
     * @param string $dst
     * @param array $processor_array
     * @param array $pending_to_copy
     * @param array $identifier
     * @param array $resource
     * @return void|\WP_Error
     * @codeCoverageIgnore
     */
    public function recurseCopy($src = '', $dst = '', $processor_array = [], $pending_to_copy = [], $identifier = [], $resource = []) {
        $dir = opendir($src);
        if ( ! is_resource($dir) ) {
            return new WP_Error( 'recurseCopyCannotOpen', __( 'Recurse copy invalid input resource' ), $src);
        }
        $file_resource = null;
        $dir_resource = null;
        if (!empty($resource)) {
            list($file_resource, $dir_resource) = $resource;
        }        
        @mkdir($dst);
        if (is_resource($dir_resource)) {
            fwrite($dir_resource, trailingslashit($dst) . PHP_EOL);
        }
        $retried = false;
        while(false !== ( $file = readdir($dir)) ) {
            $retry_timeout = apply_filters('prime_mover_cli_timeout_seconds', PRIME_MOVER_CLI_TIMEOUT_SECONDS, 'recurseCopy');
            if (microtime(true) - $processor_array['cli_start_time'] > $retry_timeout && $this->getSystemInitialization()->isCliEnvironment()) {
                $this->logCliReProcessingArray($processor_array, $pending_to_copy);
                $retried = true;
                $this->doLogRecurseCopyRestart($identifier);
                break;
            }
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir(trailingslashit($src . '/') . $file) ) {
                    $source = trailingslashit($src . '/') . $file;
                    $destination = trailingslashit($dst . '/') . $file;
                    $this->recurseCopy($source, $destination, $processor_array, $pending_to_copy, $identifier, $resource);
                } else {
                    $ret = true;
                    if ( ! $this->nonCachedFileExists($dst . '/' . $file) ) {                        
                        $ret = copy(trailingslashit($src . '/' ). $file, trailingslashit($dst . '/') . $file);
                        $this->maybeTestSlowCopy();
                    }
                    if (true === $ret) {
                        if (is_resource($file_resource)) {
                            fwrite($file_resource, trailingslashit($dst) . $file . PHP_EOL);
                        }
                        do_action('prime_mover_log_processed_events', "File $src SUCCESSFULY COPIED TO $dst", 0, 'export', 'recurseCopy', $this, true);
                    }
                }
            }
        }
        
        closedir($dir);
        if ($retried) {
            return;
        }
    }
    
    /**
     * File put contents wrapper - append mode supported by default
     * @param string $path
     * @param string $data
     * @param boolean $append
     * @return void|number
     */
    public function filePutContentsAppend($path = '', $data = '', $append = true)
    {
        if (!$path || !$data) {
            return;
        }
        if ($append) {
            return file_put_contents($path, $data, FILE_APPEND);
        } else {
            return file_put_contents($path, $data);
        }        
    }
    
    /**
     * Remove prefix from a given string
     * Credits: https://stackoverflow.com/questions/4517067/remove-a-string-from-the-beginning-of-a-string
     * @param string $prefix
     * @param string $str
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itRemovesDbPrefix()
     */
    public function removePrefix($prefix = '', $str = '')
    {
        if ( ! $prefix ) {
            return '';
        }
        if (substr($str, 0, strlen($prefix)) == $prefix) {
            $str = substr($str, strlen($prefix));
        }
        
        return $str;
    }
    
    /**
     * Checks if its a HEAD request
     * @param array $input_server
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itChecksIfHeadRequest() 
     */
    public function isHeadRequest($input_server = [])
    {
        if (! isset($input_server['REQUEST_METHOD'])) {
            return false;
        }
        
        return ('HEAD' === strtoupper($input_server['REQUEST_METHOD']));
    }
    
    /**
     * Get create export URL
     * @param number $blog_id
     * @param boolean $dashboard_mode
     * @param string $backup_filepath
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsCreateExportUrl() 
     */
    public function getCreateExportUrl($blog_id = 0, $dashboard_mode = false, $backup_filepath = '')
    {
        $migration_tools = $this->getSystemInitialization()->getMigrationToolsUrl();
        
        if ($dashboard_mode) {
            $params = ['action' => 'blogs'];
        } else {
            
            $params = [
                'blog_id' => $blog_id,
                'action' => 'prime_mover_create_backup_action',
            ];
        }
        
        if ($dashboard_mode && ! is_multisite() && isset($params['action'] ) ) {
            unset($params['action']);
        }
        
        if (is_multisite()) {
            $params['s'] = $blog_id;
        }
        if ($backup_filepath && $blog_id) {
            $params['prime_mover_backup_path'] = urlencode(wp_normalize_path($backup_filepath));
            $params['prime_mover_backup_blogid'] = $blog_id;
        }
        
        return esc_url(add_query_arg($params, $migration_tools));
    }
    
    /**
     * Checks if file resides in export dir given filepath by default
     * If $given is provided - can be used to check any given directory
     * This does not do realpath check.
     * Use fileIsInsideGivenDirectory() if realpath check is needed.
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itChecksIfFileResidesInExportDir()
     */
    public function isFileResideInExportDir($filepath = '', $given = '')
    {
        $ret = false;
        if (!$filepath ) {
            return $ret;
        }
        $filepath = wp_normalize_path($filepath); 
        
        if (!$given) {
            $given = $this->getSystemInitialization()->getMultisiteExportFolderPath();
        }
        
        $directory = wp_normalize_path($given);
        if (false !== strpos($filepath, $directory)) {
            $ret = true;
        }
        return $ret;
    }
    
    /**
     * add New element to SPLFixedArray
     * @param SplFixedArray $array
     * @param number $index
     * @param number $data
     * @return SplFixedArray
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itAddsNewElementToSplFixedArray()
     */
    public function addNewElement(SplFixedArray $array, $index = 0, $data = 0)
    {
        $size = $array->getSize();
        $maximum_supported_ids = $size - 1;
        if ($maximum_supported_ids < 0) {
            return $array;
        }
        
        if ($index > $maximum_supported_ids) {
            $array->setSize($index + 1);
        }
        
        $array[$index] = $data;
        return $array;
    }
    
    /**
     * Get theme full path
     * @param string $theme
     * @return string
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsThemeFullPath()
     */
    public function getThemeFullPath($theme = '', $exist_check = true)
    {
        $fullpath = '';
        if (! $theme) {
            return $fullpath;
        }
        global $wp_filesystem;
        if ($exist_check && ! $wp_filesystem->exists(PRIME_MOVER_THEME_CORE_PATH . DIRECTORY_SEPARATOR . $theme)) {
            return $fullpath;
        }
        
        $fullpath =  PRIME_MOVER_THEME_CORE_PATH . DIRECTORY_SEPARATOR . $theme . DIRECTORY_SEPARATOR;
        return $fullpath;
    }
    
    /**
     * Get site title given blog ID
     * @param number $blog_id
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsSiteTitleGivenBlogId()
     */
    public function getSiteTitleGivenBlogId($blog_id = 0)
    {
        $this->switchToBlog($blog_id);
        $blog_title = get_bloginfo('name');        
        $this->restoreCurrentBlog();
        
        return $blog_title;
    }
    
    /**
     * Check if sha256 string
     * @param string $string
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itChecksIfShaString()
     */
    public function isShaString($string = '', $mode = 256)
    {
        return primeMoverIsShaString($string, $mode);
    }
    
    /**
     * Unlink file wrapper, not recursive.
     * @param string $path
     * @codeCoverageIgnore
     */
    public function unLink($path = '')
    {
        if ( ! $path ) {
            return;
        }
        unlink($path);
    }
    
    /**
     * It checks if multisite main site 
     * When $force_one is TRUE - returns true for original main site (usually blog id of 1 for default/standard multisite compatibility)
     * When $force_domain_current_site is TRUE - returns TRUE if $blog_id is the current DOMAIN_CURRENT_SITE
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itChecksIfMultisiteMainSite()
     * @param number $blog_id
     * @param boolean $force_one
     * @param boolean $force_domain_current_site
     * @return boolean
     */
    public function isMultisiteMainSite($blog_id = 0, $force_one = false, $force_domain_current_site = false)
    {
        if (!$blog_id || !is_multisite() ) {
            return false;
        }
        $blog_id = (int)$blog_id;
        $main_site_blog_id = (int)$this->getSystemInitialization()->getMainSiteBlogId();
        $force_one_is_set = false;
        if ($main_site_blog_id > 1 && true === $force_one) {
            $force_one_is_set = true;
        }
        
        if ($force_one_is_set && 1 === $blog_id) {
            return true;
        }
        
        if ($force_one_is_set && $main_site_blog_id === $blog_id) {
            return false;
        }
        
        $domain_current_site_id = 0;
        if ($force_domain_current_site && defined('DOMAIN_CURRENT_SITE') && DOMAIN_CURRENT_SITE) {
            $domain_current_site_id = (int)get_blog_id_from_url(DOMAIN_CURRENT_SITE);
        }
        if ($force_domain_current_site) {
            return ($domain_current_site_id === $blog_id);
        }

        return ($blog_id === $main_site_blog_id);       
    }
    
    /**
     * Generalize export type
     * @param mixed $export_type
     * @return string
     * Possible values, non-translatable: single-site / multisite
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGeneralizeExportTypeBasedOnGiven()
     */
    public function generalizeExportTypeBasedOnGiven($export_type)
    {
        if (is_array($export_type) && !empty($export_type['prime_mover_export_type'])) {
            $export_type = $export_type['prime_mover_export_type'];
        }
        if ('single-site-export' === $export_type) {
            return 'single-site';            
        } else {
            return 'multisite';
        }
    }
    
    /**
     * Get tables for replacement
     * @mainsitesupport_affected
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsTableForReplacement()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsTableForReplaceOnMainSite()  
     * @param number $blog_id
     * @param array $ret
     * @return mixed|NULL|array
     */
    public function getTablesforReplacement($blog_id = 0, $ret = [])
    {        
        global $wpdb;
        $all_tables	= [];
        
        if ($this->isMultisiteMainSite($blog_id, true)) {    
            
            $target_prefix = $wpdb->prefix;
            $escaped_like = $wpdb->esc_like($target_prefix);
            $target_prefix = $escaped_like . '%';
            
            $regex = $escaped_like . '[0-9]+';
            $table_name = DB_NAME;
            $db_search = $this->getMultisiteMainSiteTableQuery($table_name);
            
            $prepared = $wpdb->prepare($db_search, $target_prefix, $regex);
            $all_tables = $wpdb->get_col($prepared);
            
        } else {
            
            $specific_site_prefix	= str_replace('_', '\_', $wpdb->prefix);
            $all_tables = $wpdb->get_col("SHOW TABLES LIKE '{$specific_site_prefix}%'");
        }
        
        return apply_filters('prime_mover_tables_for_replacement', $all_tables, $blog_id, $ret);
    }
    
    /**
     * Multisite Main site Table Query
     * @param string $db
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetstMultisiteMainSiteTableQuery()
     */
    public function getMultisiteMainSiteTableQuery($db = '')
    {
        return "SHOW TABLES FROM `{$db}` WHERE `Tables_in_{$db}` LIKE %s AND `Tables_in_{$db}` NOT REGEXP %s";
    }
    
    /**
     * Checks if valid JSON
     * @param string $input_data
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itChecksIfValidJson()
     */
    public function isValidJson($input_data = '')
    {
        if ( ! $input_data ) {
            return false;
        }
        $input_data	= stripslashes(html_entity_decode($input_data));
        $input_data	= str_replace('\\', '/', $input_data);
        json_decode($input_data);
        return (json_last_error() == JSON_ERROR_NONE);   
    }
    
    /**
     * Returns TRUE if its a Prime Mover settings page OR migration tools/Sites page
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itChecksIfPrimeMoverPage()
     */
    public function isPrimeMoverPage()
    {
        if (!is_admin()) {
            return false;
        }
        
        if (!function_exists('get_current_screen')) {
            return false;
        }
        
        $current_screen = get_current_screen();     
        if (!is_object($current_screen)) {
            return false;
        }
        
        if ($this->isSettingsPage($current_screen)) {
            return true;
        }
        
        if ($this->isMigrationToolsSitesPage($current_screen)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Checks if migration tools or network sites page
     * @param WP_Screen $current_screen
     * @return boolean
     */
    public function isMigrationToolsSitesPage(WP_Screen $current_screen)
    {
        if (is_multisite() && $current_screen->in_admin('network') && 'sites-network' === $current_screen->id) {
            return true;
        }
        
        if (!is_multisite() && 'tools_page_migration-tools' === $current_screen->id) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Checks if settings page
     * @param WP_Screen $current_screen
     * @return boolean
     */
    public function isSettingsPage(WP_Screen $current_screen)
    {
        return ($this->maybeLoadAssetsOnDashboard($current_screen));
    }
    
    /**
     * Maybe reset opcache
     * @codeCoverageIgnore
     */
    public function maybeResetOpCache()
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        if (function_exists('opcache_reset') && (!ini_get('opcache.restrict_api') || stripos(realpath($_SERVER['SCRIPT_FILENAME']), ini_get('opcache.restrict_api')) === 0)) {
            @opcache_reset();
        }
    }
    
    /**
     * Activate plugin - Low level version and multisite compat.
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itActivatesPlugin() 
     * @param number $blog_id
     * @param string $plugin_path_target
     * @param string $plugin
     * @param boolean $network_activated
     * @param boolean $addheaders_to_cache
     * @return boolean
     */
    public function activatePlugin($blog_id = 0, $plugin_path_target = '', $plugin = '', $network_activated = false, $addheaders_to_cache = true)
    {
        $this->switchToBlog($blog_id);
        if ($addheaders_to_cache) {
            $this->addUpdatedPluginHeadersToCache($plugin_path_target, $plugin);
        }
        
        $this->removePluginManager();       
        activate_plugins($plugin, '', $network_activated, true);
        $this->addPluginManager();
        $this->restoreCurrentBlog();
        
        return true;
    }
    
    /**
     * Add updated plugin headers to cache to make sure this plugin is detected on plugin activation
     * See https://developer.wordpress.org/reference/functions/activate_plugin/#more-information
     * @param string $plugin_path_target
     * @param string $plugin
     */
    protected function addUpdatedPluginHeadersToCache($plugin_path_target = '', $plugin = '')
    {
        $orig = $plugin;
        $basename = basename($plugin);
        if ($basename === $orig) {
            $absolute_path = wp_normalize_path($plugin_path_target);
        } else {
            $absolute_path = trailingslashit(wp_normalize_path($plugin_path_target)) . basename($plugin);
        }
        
        $new_plugin = get_plugin_data($absolute_path);        
        $cache_plugins = wp_cache_get('plugins', 'plugins');
        if (is_array($cache_plugins) && !isset($cache_plugins[$plugin])) {
            $cache_plugins[''][$plugin] = $new_plugin;
            wp_cache_set( 'plugins', $cache_plugins, 'plugins' );
        }
    }

    /**
     * Checks if config path is writable
     * @return boolean
     */
    public function isConfigFileWritable()
    {
        if (!function_exists('primeMoverGetConfigurationPath')) {
            return false;
        }
        $config_path = primeMoverGetConfigurationPath();
        if (!$config_path) {
            return false;
        }
        
        return wp_is_writable($config_path);         
    }
    
    /**
     * File get contents wrapper for reading small strings.
     * @param string $path
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsFileContents()
     */
    public function fileGetContents($path = '')
    {
        if (!$path || !$this->nonCachedFileExists($path)) {
            return '';
        }
        return file_get_contents($path);
    }
    
    /**
     * Checks if resource is directory
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itChecksIfDirectoryIsResource()
     */
    public function isDir($path = '')
    {
        if (!$path) {
            return false;
        }
        global $wp_filesystem;
        if ( ! $this->isWpFileSystemUsable($wp_filesystem)) {
            return false;
        }
        return $wp_filesystem->is_dir($path);
    }
    
    /**
     * Multisite/single-site compatible way of getting user meta table name
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsUserMetaTableName()
     * @param boolean $users_table
     * @return string
     */
    public function getUserMetaTableName($users_table = false)
    {
        global $wpdb;
        $main_site_id = 1;
        if (is_multisite()) {
            $main_site_id = $this->getSystemInitialization()->getMainSiteBlogId();
        }
        
        if ($main_site_id > 1) {
            $main_site_id = 1;
        }
        
        $db_prefix = $wpdb->get_blog_prefix($main_site_id);  
        
        if ($users_table) {
            return "{$db_prefix}users";
        } else {
            return "{$db_prefix}usermeta";
        }               
    }
    
    /**
     * Get abspath value
     * @return mixed
     */
    public function getAbsPath()
    {   
        $abspath = ABSPATH;
        if (defined('PRIME_MOVER_ABSPATH') && PRIME_MOVER_ABSPATH) {
            $abspath = PRIME_MOVER_ABSPATH;
        }
    
        $abspath = trailingslashit($abspath);        
        return str_replace( '\\', '/', $abspath);
    }
    
    /**
     * Compute wp-content info
     * No trailing slash at the end
     * No URL scheme also.
     * @return string[]|
     */
    public function getWpContentInfo()
    {
        global $wp_filesystem;
        if ( ! $this->isWpFileSystemUsable($wp_filesystem)) {
            return ['content_url' => '', 'content_path' => ''];
        }
        
        $content_url = untrailingslashit($this->removeSchemeFromUrl(content_url()));
        $content_dir = untrailingslashit($wp_filesystem->wp_content_dir());
        
        return [$content_url, $content_dir];
    }
    
    /**
     * Remove scheme from URL
     * This includes support for hostname with port number implementation
     * @param string $given_url
     * @return string
     */
    public function removeSchemeFromUrl($given_url = '')
    {
        $url_parsed = parse_url($given_url);
        $given_url_parsed = '';
        if ((isset($url_parsed['host'])) && (!empty($url_parsed['host']))) {
            $given_url_parsed .= $url_parsed['host'];
        }

        if ((isset($url_parsed['port'])) && (!empty($url_parsed['port']))) {
            $given_url_parsed .= ":";
            $given_url_parsed .= $url_parsed['port'];
        }
        
        if ((isset($url_parsed['path'])) && (!empty($url_parsed['path']))) {
            $given_url_parsed .= $url_parsed['path'];
        }
        
        return $given_url_parsed;
    }
    
    /**
     * Get URL scheme of this site
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsUrlSchemeOfThisSite()
     */
    public function getUrlSchemeOfThisSite()
    {
        if (is_ssl()) {
            return PRIME_MOVER_SECURE_PROTOCOL;
        } else {
            return PRIME_MOVER_NON_SECURE_PROTOCOL;
        }
    }
    
    /**
     * Initialize WP Filesystem during cleanup
     * @param boolean $admin_check
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itInitializesFs()
     */
    public function initializeFs($admin_check = true)
    {
        if (is_admin() && $admin_check) {
            return;
        }
        global $wp_filesystem;
        if (!$this->isWpFileSystemUsable($wp_filesystem)) {
            $this->getSystemInitialization()->multisiteInitializeWpFilesystemApiCli(false);
        }
    }
    
    /**
     * Returns TRUE if site uses root uploads
     * Returns FALSE if site does not use root uploads
     * Returns NULL if undefined and insensible.
     * @return NULL|boolean
     */
    public function maybeCreateFoldersInMu()
    {
        if (!is_multisite()) {
            return null;
        }
        
        $main_site_id = $this->getSystemInitialization()->getMainSiteBlogId();
        $current_blog_id = get_current_blog_id();        
        $main_site_id = (int)$main_site_id;
        $current_blog_id =(int)$current_blog_id;
        
        if (!$main_site_id || !$current_blog_id) {
            return null;
        }
        
        if ($main_site_id === $current_blog_id) {
            return true;
        }
        
        if (!defined('DOMAIN_CURRENT_SITE')) {
            return null;
        }
        $domain_current_site = trim(DOMAIN_CURRENT_SITE);
        if (!$domain_current_site) {
            return null;
        }
        
        $domain_current_site = DOMAIN_CURRENT_SITE;
        $domain_current_site_id = (int)get_blog_id_from_url($domain_current_site);        
        if ($domain_current_site_id === $current_blog_id) {
            return true;
        }
        
        return false;        
    }
    
    /**
     * ini_get wrapper
     * @param string $directive
     * @return boolean|string
     */
    public function iniGet($directive = '')
    {
        if (!$directive) {
            return false;
        }
        
        return ini_get($directive);
    }
    
    /**
     * fopen wrapper
     * @param string $path
     * @param string $mode
     * @param boolean $include_path
     * @param array $context_options
     * @return boolean|resource
     */
    public function fOpen($path = '', $mode = "rb", $include_path = false, $context_options = [])
    {
        if (!$path || !$mode) {
            return false;
        }
        
        if (empty($context_options)) {
            return @fopen($path, $mode, $include_path);
        } else {
            return @fopen($path, $mode, $include_path, stream_context_create($context_options));
        }
    }
    
    /**
     * is_resource wrapper
     * @param resource $readable
     * @return boolean
     */
    public function isResource($readable = null)
    {
        if (!$readable) {
            return false;
        }
        
        return is_resource($readable);
    }
    
    /**
     * Check if string end with $test
     * @param string $string
     * @param string $test
     * @return boolean
     * Credits: https://stackoverflow.com/questions/619610/whats-the-most-efficient-test-of-whether-a-php-string-ends-with-another-string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itEndsWith()
     */
    public function endsWith($string = '', $test = '') {
        $strlen = strlen($string);
        $testlen = strlen($test);
        if ($testlen > $strlen) return false;
        return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
    }
    
    /**
     * fclose wrapper
     * @param resource $handle
     * @return boolean
     * @codeCoverageIgnore
     */
    public function fClose($handle = null)
    {
        if (!is_resource($handle)) {
            return false;    
        }
        
        return @fclose($handle);
    }
    
    /**
     * Deactivate plugins wrapper.
     * @param mixed $plugins
     * @param boolean $silent
     * @param mixed $network_wide
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDeactivatesAllPlugins() 
     */
    public function deactivatePlugins($plugins = null, $silent = true, $network_wide = null)
    {
        if (!$plugins) {
            return;
        }
        $this->removePluginManager();
        deactivate_plugins($plugins, true, $network_wide);
        $this->addPluginManager();
    }
    
    /**
     * Checks if plugin is active wrapper.
     * @param string $plugin
     * @param boolean $network_check
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itChecksIfPluginActive() 
     */
    public function isPluginActive($plugin = '', $network_check = false)
    {
        $this->removePluginManager();
        
        if ($network_check) {
            $is_plugin_active = is_plugin_active_for_network($plugin);
        } else {
            $is_plugin_active = is_plugin_active($plugin);
        }        
        $this->addPluginManager();
        
        return $is_plugin_active;        
    }
 
    /**
     * Add nonce filters
     * @param boolean $skip
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itAddsPrimeMoverNonceFilters()
     */
    protected function addPrimeMoverNonceFilters($skip = false)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized() || $skip) {
            return;
        }
        
        add_filter('nonce_life', [$this, 'primeMoverNonceLife'], PRIME_MOVER_LOWEST_PRIORITY, 1);
        add_filter('salt', [$this, 'primeMoverNonceSalt'], PRIME_MOVER_LOWEST_PRIORITY, 2);
    }
    
    /**
     * Prime Mover nonce life - default
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsNonceLife() 
     */
    public function primeMoverNonceLife($life = 0)
    {
        return DAY_IN_SECONDS;
    }
    
    /**
     * Prime Mover nonce salt
     * @param string $salt
     * @param string $scheme
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itReturnsNonceSalt()
     */
    public function primeMoverNonceSalt($salt = '', $scheme = '')
    {
        if ('nonce' !== $scheme) {
            return $salt;
        }
        
        $nonce_key = '';
        $nonce_salt = '';
        if (defined('NONCE_KEY') && NONCE_KEY) {
            $nonce_key = NONCE_KEY;
        }
        if (!$nonce_key) {
            $nonce_key = $this->getSiteOption('nonce_key');
        }
        if (defined('NONCE_SALT') && NONCE_SALT) {
            $nonce_salt = NONCE_SALT;
        }
        if (!$nonce_salt) {
            $nonce_salt = $this->getSiteOption('nonce_salt');
        }
        if ($nonce_key && $nonce_salt) {
            return $nonce_key . $nonce_salt;
        }
        return $salt;        
    }
    
    /**
     * Remove Prime Mover nonce filters
     * @param boolean $skip
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itRemovesPrimeMoverNonceFilters()
     */
    protected function removePrimeMoverNonceFilters($skip = false)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized() || $skip) {
            return;
        }
        remove_filter('nonce_life', [$this, 'primeMoverNonceLife'], PRIME_MOVER_LOWEST_PRIORITY, 1);
        remove_filter('salt', [$this, 'primeMoverNonceSalt'], PRIME_MOVER_LOWEST_PRIORITY, 2);
    }
    
    /**
     * Prime Mover nonce URL
     * @param mixed $actionurl
     * @param mixed $action
     * @param string $name
     * @param boolean $skip
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itCreatesNonceUrl()
     */
    public function primeMoverNonceUrl($actionurl, $action = -1, $name = '_wpnonce', $skip = false)
    {
        $this->addPrimeMoverNonceFilters($skip);
        $nonce_url = wp_nonce_url($actionurl, $action, $name);
        $this->removePrimeMoverNonceFilters($skip);
        
        return $nonce_url;
    }
    
    /**
     * Prime Mover nonce field
     * @param mixed $action
     * @param string $name
     * @param boolean $referer
     * @param boolean $echo
     * @param boolean $skip
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itCreatesNonceField() 
     */
    public function primeMoverNonceField($action = -1, $name = '_wpnonce', $referer = true, $echo = true, $skip = false)
    {
        $this->addPrimeMoverNonceFilters($skip);
        wp_nonce_field($action, $name, $referer, $echo);
        $this->removePrimeMoverNonceFilters($skip);
    }
    
    /**
     * Prime Mover create nonce
     * @param mixed $action
     * @param boolean $skip
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itCreatesNonce()
     */
    public function primeMoverCreateNonce($action = -1, $skip = false)
    {
        $this->addPrimeMoverNonceFilters($skip);
        $nonce_val = wp_create_nonce($action);
        $this->removePrimeMoverNonceFilters($skip);
        
        return $nonce_val;
    }
    
    /**
     * Drop table helper method
     * @param string $drop_query
     * @param boolean $foreign_key_checks
     * @param wpdb $wpdb
     * @param boolean $return_result
     */
    public function dropTable($drop_query = '', $foreign_key_checks = true, wpdb $wpdb = null, $return_result = false)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized() || !$drop_query) {
            return;
        } 
        
        if (defined('PRIME_MOVER_DISABLE_FK_CHECKS') && true === PRIME_MOVER_DISABLE_FK_CHECKS) {
            $foreign_key_checks = false;
        }
        
        if ($foreign_key_checks) {
            $wpdb->query('SET FOREIGN_KEY_CHECKS=0;');
        }
        
        $drop_result = $wpdb->query($drop_query);
        
        if ($foreign_key_checks) {
            $wpdb->query('SET FOREIGN_KEY_CHECKS=1;');
        }
        
        if ($return_result) {
            return $drop_result;
        }
    }
    
    /**
     * Prime Mover verify nonce
     * @param string $nonce
     * @param mixed $action
     * @param boolean $skip
     * @return number|false
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itVerifiesNonce() 
     */
    public function primeMoverVerifyNonce($nonce = '', $action = -1, $skip = false)
    {
        $this->addPrimeMoverNonceFilters($skip);
        $verify = wp_verify_nonce($nonce, $action);
        $this->removePrimeMoverNonceFilters($skip);
        
        return $verify;
    }
    
    /**
     * Prime Mover check admin referer
     * @param mixed $action
     * @param string $query_arg
     * @param boolean $skip
     * @return number|false
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itChecksAdminReferer()
     */
    public function primeMoverCheckAdminReferer($action = -1, $query_arg = '_wpnonce', $skip = false)
    {
        $this->addPrimeMoverNonceFilters($skip);
        $nonce_referer_check = check_admin_referer($action, $query_arg);
        $this->removePrimeMoverNonceFilters($skip);
        
        return $nonce_referer_check;
    }
    
    /**
     * Get publicly accessible site URL of the site
     * @param number $blog_id
     * @return string
     */
    public function getPublicSiteUrl($blog_id = 0)
    {
        $home_url = get_home_url($blog_id);
        $site_url = get_site_url($blog_id);
        
        if ($home_url && $site_url && $home_url !== $site_url) {
            return $home_url;
        }
        return $site_url;
    }
    
    /**
     * Get refresh package URL
     * @param number $blog_id
     * @return string
     */
    public function getRefreshPackageUrl($blog_id = 0)
    {
        if (!$blog_id) {
            return '';
        }
        $user_id = get_current_user_id();
        $backup_menu_url = $this->getBackupMenuUrl();
        $refresh_url = $this->primeMoverNonceUrl($backup_menu_url, 'refresh_backups_'.$user_id, 'prime_mover_refresh_backups');
        
        if (is_multisite()) {            
            $refresh_url = add_query_arg('prime-mover-select-blog-to-query', $blog_id, $refresh_url);
        }
        return $refresh_url;
    }
    
    /**
     * Checks if blog is usable provided by blog ID
     * @param number $blog_id
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupUtilities::itChecksIfBlogIdIsUsable()
     * @mainsitesupport_affected
     *
     * Since 1.2.0, its possible to have blog ID of 1 and that is on a multisite main site.
     * Remove the > 1 check and simply just check if $blog_id is truth value.
     */
    public function blogIsUsable($blog_id = 0)
    {
        if (!$blog_id ) {
            return false;
        }
        
        $blog_id = (int)$blog_id;
        
        if (!is_multisite() && 1 === $blog_id) {
            return true;
        }
        
        if (is_multisite() && $blog_id && get_blogaddress_by_id($blog_id)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Checks if extracting package as TAR (for WPRIME)
     * @param string $file_path
     * @return boolean
     */
    public function isExtractingPackageAsTar($file_path = '')
    {
        if (!$file_path) {
            return false;
        }
        $extracting_tar = false;
        if ($this->hasTarExtension($file_path)) {
            $extracting_tar = true;
        }
        
        return $extracting_tar;
    }
    
    /**
     * Get language folder
     * @return boolean|string|false
     * Returned path is normalized.
     */
    public function getLanguageFolder()
    {
        global $wp_filesystem;
        if (!$this->isWpFileSystemUsable($wp_filesystem)) {
            return false;
        }
        
        return wp_normalize_path($wp_filesystem->find_folder(WP_LANG_DIR));
    }
    
    /**
     * Checks if large stream file
     * This is usually used to limit files that could be for hashes
     * Beyond this limit - hash functions could become slower.
     * @param number $filesize
     * @return boolean
     */
    public function isLargeStreamFile($filesize = 0)
    {
        return ($filesize > $this->getSystemInitialization()->getPrimeMoverLargeFileSizeStream());
    }
}