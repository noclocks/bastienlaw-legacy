<?php
namespace Codexonics\PrimeMoverFramework\archiver;

/*
 * This file is part of the Codexonics.PrimeMoverFramework package.
 *
 * (c) Codexonics Ltd
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Codexonics\PrimeMoverFramework\cli\PrimeMoverCLIArchive;
use Codexonics\PrimeMoverFramework\build\splitbrain\PHPArchive\Tar;
use Codexonics\PrimeMoverFramework\classes\PrimeMoverUsers;
use Codexonics\PrimeMoverFramework\utilities\PrimeMoverOpenSSLUtilities;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Archiver
 * Provides API for WPRIME archive.
 * Supports archive encryption (PRO version).
 */
class PrimeMoverArchiver
{         
    private $cli_archiver;
    private $users;
    private $openssl_utilities;
   
    const WPRIME_EXTENSION = 'wprime';
    
    /**
     * Constructor
     * @param PrimeMoverCLIArchive $cli_archiver
     * @param PrimeMoverUsers $users
     */
    public function __construct(PrimeMoverCLIArchive $cli_archiver, PrimeMoverUsers $users, PrimeMoverOpenSSLUtilities $openssl_utilities)
    {
        $this->cli_archiver = $cli_archiver;    
        $this->users = $users;
        $this->openssl_utilities = $openssl_utilities;
    } 
    
    /**
     * Get openSSL utilities object
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverOpenSSLUtilities
     */
    public function getOpenSSLUtilities()
    {
        return $this->openssl_utilities;
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
     * Get CLI archiver
     * @return \Codexonics\PrimeMoverFramework\cli\PrimeMoverCLIArchive
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itAddsInitHooksWhenAuthorized()
     */
    public function getCliArchiver()
    {
        return $this->cli_archiver;
    }
    
    /**
     * Get system authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itAddsInitHooksWhenAuthorized()
     */
    public function getSystemAuthorization()
    {
        return $this->getCliArchiver()->getSystemAuthorization();
    }
    
    /**
     * Get system checks
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemChecks
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itAddsWprimeToExportPathJs()
     */
    public function getSystemChecks()
    {
        return $this->getCliArchiver()->getSystemChecks();
    }
    
    /**
     * Initialize hooks
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itAddsInitHooksWhenAuthorized()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itChecksIfHooksAreOutdated()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itDoesNotAddInitHooksWhenNotAuthorized()
     */
    public function initHooks()
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }  
        add_filter('prime_mover_add_file_to_tar_archive', [$this, 'addFile'], 10, 12);
        add_filter('prime_mover_add_directory_to_tar_archive', [$this, 'addDirectory'], 10, 11);
        add_filter('prime_mover_is_archive_clean', [$this, 'isArchiveClean'], 10, 2);
        
        add_filter('prime_mover_after_creating_tar_archive', [$this, 'addTarPackageConfiguration'], 10, 2);
        add_filter('prime_mover_get_tar_package_config_from_file', [$this, 'getTarPackageConfigFromFile'], 10, 4);
        add_filter('prime_mover_close_wprime_archive', [$this, 'closeArchive'], 10, 4);
        
        add_filter('prime_mover_tar_extractor', [$this, 'extractTar'], 10, 10);
        add_filter('prime_mover_download_zip_other_mimes', [$this, 'addTarMimeType'], 10, 1);
        add_filter('prime_mover_ajax_rendered_js_object', [$this, 'addWPrimeConfigToJs'], 10, 1 );
        
        add_filter('prime_mover_ajax_rendered_js_object', [$this, 'wPrimeExportPathToJs'], 10, 1);        
        add_filter('prime_mover_filter_export_footprint', [$this, 'addMediaFilesCountToFootPrint'], 10, 3);
        add_filter('prime_mover_excluded_filetypes_export', [$this, 'excludeWprimeFromAnyArchive'], 10, 1);
    }
    
    /**
     * WPRIME archive should be excluded inside any WPRIME archive by default to save space and bandwidth
     * @param array $ext
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itExcludesWprimeFromAnyArchive()
     */
    public function excludeWprimeFromAnyArchive($ext = [])
    {
        if (!in_array(self::WPRIME_EXTENSION, $ext)) {
            $ext[] = 'wprime';
        }        
        return $ext;
    }
    
    /**
     * Add Media files count to footprint
     * @param array $export_system_footprint
     * @param array $ret
     * @param number $blogid_to_export
     * @return []
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itAddsMediaFilesCountToFootPrint()
     */
    public function addMediaFilesCountToFootPrint($export_system_footprint = [], $ret = [], $blogid_to_export = 0)
    {
        if (!isset($ret['wprime_media_files_count'])) {
            return $export_system_footprint;
        }
        $export_system_footprint['wprime_media_files_count'] = $ret['wprime_media_files_count'];
        return $export_system_footprint;
    }
    
    /**
     * Formulate wprime export paths to JS usage
     * @param array $args
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itAddsWprimeToExportPathJs()
     */
    public function wPrimeExportPathToJs($args = [])
    {        
        $args['wprime_export_path'] = trailingslashit($this->getSystemChecks()->getSystemInitialization()->getMultisiteExportFolderPath());        
        return $args;
    }
    
    /**
     * Checks if WPRIME archive is clean
     * @param boolean $clean
     * @param string $tar_path
     * @return boolean|string|boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itChecksIfArchiveIsClean()
     */
    public function isArchiveClean($clean = false, $tar_path = '')
    {        
        $tar = $this->openTar($tar_path);
        if (!$tar) {
            return false;
        }
        
        return $tar->isArchiveCorrupted();
    }
  
    /**
     * Close WPRIME archive when requested
     * @param boolean $close_result
     * @param string $tar_file
     * @param array $ret
     * @param int $blog_id
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itRetunsFalseWhenClosingArchiveIfReadmeIsNotSet()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itRetunsFalseWhenClosingArchiveIfFileDoesntExist()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itRetunsFalseWhenClosingArchiveIfTmpFolderDoesntExist()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itRetunsFalseWhenClosingArchiveIfReadMeDoesntExist()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itReturnsTrueWhenClosingArchiveIfAlreadyClosed()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itReturnsFalseWhenClosingArchiveIfTarInstanceNotSet()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itReturnsTrueWhenArchiveSuccessfullyClosed() 
     */
    public function closeArchive($close_result = false, $tar_file = '', $ret = [], $blog_id = 0)
    {
        if (!$this->getSystemChecks()->getSystemFunctions()->nonCachedFileExists($tar_file)) {
           return false; 
        }        
        if (empty($ret['wprime_readme_path'])) {
            return false;
        }
        if (empty($ret['temp_folder_path'])) {
            return false;
        }
        if (!$this->getSystemChecks()->getSystemFunctions()->nonCachedFileExists($ret['wprime_readme_path'])) {
            return false;
        }
        if (isset($ret['wprime_closed']) && true === $ret['wprime_closed']) {
            return true;
        }
        
        $tar_file = untrailingslashit($tar_file);
        $tar = $this->createTarInstance($tar_file, 'ab');
        if (!is_object($tar)) {
            return false;
        }
        $temp_folder_path = $ret['temp_folder_path'];        
        $local_name = trailingslashit(basename($temp_folder_path)) . PRIME_MOVER_WPRIME_CLOSED_IDENTIFIER;        
        $addresult = $tar->addFile($ret['wprime_readme_path'], $local_name, 0, 0, $blog_id, false, '', '');        
        if (is_int($addresult)) {
            $tar->close();
            return true;
        } else {
            return false;
        }    
    }
    
    /**
     * Add WPrime Config file
     * @param array $args
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itAddsWprimeConfigToJs()
     */
    public function addWPrimeConfigToJs($args = [])
    {
        $args['prime_mover_wprime_config'] = PRIME_MOVER_WPRIME_CONFIG;
        return $args;
    }
    
    /**
     * Add tarball mime type
     * @param array $mimes
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itAddsTarMimeType()
     */
    public function addTarMimeType($mimes = [])
    {
        $tar = 'application/x-tar';
        if (!in_array($tar, $mimes)) {
            $mimes[] = $tar;
        }
        return $mimes;
    }
    
    /**
     * Extract WPRIME archive
     * @param array $ret
     * @param number $blog_id
     * @param string $tar_path
     * @param string $extraction_path
     * @param number $base_read_offset
     * @param number $start
     * @param number $offset
     * @param number $index
     * @param string $key
     * @param string $iv
     * @return \Codexonics\PrimeMoverFramework\build\splitbrain\PHPArchive\FileInfo|array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itReturnsErrorWhenExtractingIncompleteTarData()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itReturnsErrorWhenUnableToOpenTar()
     */
    public function extractTar($ret = [], $blog_id = 0, $tar_path = '', $extraction_path = '', $base_read_offset = 0, $start = 0, $offset = 0, $index = 0, $key = '', $iv = '')
    {
        if (!is_array($ret) || !$blog_id || !$tar_path || !$extraction_path) {
            $ret['error'] = esc_html__('Incomplete wprime archive extraction parameters.', 'prime-mover');
            return $ret;
        }
        $tar = $this->openTar($tar_path, $base_read_offset);
        if (!$tar) {
            $ret['error'] = esc_html__('Unable to open wprime, please check if WPRIME file exists.', 'prime-mover');
            return $ret;
        }
        do_action('prime_mover_log_processed_events', "Extraction task started.", $blog_id, 'export', __FUNCTION__, $this, true);
        
        $result = $tar->extract($extraction_path, '', '', '', $start, $offset, $index, $base_read_offset, $blog_id, $key, $iv);        
        if (is_array($result) && !empty($result['tar_read_offset']) && !empty($result['base_read_offset'] && !empty($result['index']) && isset($result['iv']))) {            
            $ret['prime_mover_tar_extract_offset'] = $result['tar_read_offset'];
            $ret['prime_mover_tar_extract_index'] = $result['index'];
            $ret['prime_mover_tar_extract_base_offset'] = $result['base_read_offset'];
            $ret['prime_mover_tar_extract_iv'] = $result['iv'];
            
            return $ret;
        }
        return $this->cleanUpExtractionRetArray($ret);
    }
    
    /**
     * Open WPRIME
     * @param string $tar_path
     * @param number $base_read_offset
     * @param boolean $source_is_url
     * @return \Codexonics\PrimeMoverFramework\build\splitbrain\PHPArchive\Tar
     */
    protected function openTar($tar_path = '', $base_read_offset = 0, $source_is_url = false)
    {
        $tar = new Tar();        
        $tar->open($tar_path, $base_read_offset, $source_is_url);
        
        return $tar;
    }
    
    /**
     * Clean up extraction ret array
     * @param array $ret
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itCLeanUpExtractionRetArray()
     */
    protected function cleanUpExtractionRetArray($ret = [])
    {
        if (isset($ret['prime_mover_tar_extract_offset'])) {
            unset($ret['prime_mover_tar_extract_offset']);
        }
        if (isset($ret['prime_mover_tar_extract_index'])) {
            unset($ret['prime_mover_tar_extract_index']);
        }
        if (isset($ret['prime_mover_tar_extract_base_offset'])) {
            unset($ret['prime_mover_tar_extract_base_offset']);
        }
        if (isset($ret['prime_mover_tar_extract_iv'])) {
            unset($ret['prime_mover_tar_extract_iv']);
        }
       
        return $ret;
    }
    
    /**
     * Get WPRIME config from file
     * @param array $config
     * @param string $tar_file
     * @param boolean $source_is_url
     * @param boolean $encoding_safe
     * @return array
     */
    public function getTarPackageConfigFromFile($config = [], $tar_file = '', $source_is_url = false, $encoding_safe = false)
    {
        if (!$tar_file) {
            return $config;
        }
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $config;
        }    
        if (!$this->getSystemChecks()->getSystemFunctions()->hasTarExtension($tar_file)) {
            return $config;
        }
        
        $hash = $this->getSystemChecks()->getSystemFunctions()->hashString($tar_file);
        $signature = 'prime_mover_tar_config_' . $hash;
        $config = wp_cache_get($signature);
        $decoded = [];
        
        if ( false === $config) {
            $tar_file = untrailingslashit($tar_file);            
            $tar = $this->openTar($tar_file, 0, $source_is_url);
            $result = $tar->isPrimeMoverTarBall();
            if (false === $result) {
                wp_cache_set($signature, []);
            } 
            if (is_string($result)) {
                $result = trim($result);
                $decoded = json_decode($result, true);
            }
            if (is_array($decoded)) {
                wp_cache_set($signature, $decoded);
                $config = $decoded;
            } else {
                wp_cache_set($signature, []);
                $config = [];
            }
        }        
        $valid = false;
        if (isset($config['export_options'], $config['encrypted'], $config['site_title'], $config['include_users'], $config['prime_mover_export_targetid'], 
            $config['tar_root_folder'], $config['prime_mover_encrypted_signature'])) {
            $valid = true;
        }
        if (!$valid) {
            return []; 
        }
                
        if ($encoding_safe) {
            unset($config['site_title']);            
        } 
        
        return $config;              
    }
    
    /**
     * Add WPRIME package configuration that is important for restoration.
     * @param array $ret
     * @param number $blogid_to_export
     * @return array
     * @mainsitesupport_affected
     */
    public function addTarPackageConfiguration($ret = [], $blogid_to_export = 0)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unable to write package configuration. Please check authorization.', 'prime-mover');
            return $ret;
        }
        if ( !is_array($ret) || empty($ret) || ! $blogid_to_export) {
            $ret['error'] = esc_html__('Insufficient parameters to add wprime package configuration.', 'prime-mover');
            return $ret;
        }
        if (!$blogid_to_export) {
            $ret['error'] = esc_html__('Blog Id is not set for wprime package configuration.', 'prime-mover');
            return $ret;
        }
        $temp_folder_path = '';
        if (!empty($ret['temp_folder_path'])) {
            $temp_folder_path = $ret['temp_folder_path'];
        }
        if (!$temp_folder_path) {
            $ret['error'] = esc_html__('Undefined temp folder path for adding WPRIME config file.', 'prime-mover');
            return $ret;
        }
        $tar_config = trailingslashit($temp_folder_path) . PRIME_MOVER_WPRIME_CONFIG;
        $config = [];
        if (isset($ret['multisite_export_options'])) {
            $config['export_options'] = $ret['multisite_export_options'];
        }
        if (isset($ret['enable_db_encryption'])) {
            $config['encrypted'] = $ret['enable_db_encryption'];
        }        
        $config['site_title'] = $this->getSystemChecks()->getSystemFunctions()->getSiteTitleGivenBlogId($blogid_to_export); 
        $config['include_users'] = $this->getUsersObject()->maybeExportUsers($ret);
        $config['tar_root_folder'] = basename($temp_folder_path);
        
        if (isset($ret['prime_mover_export_targetid'])) {
            $config['prime_mover_export_targetid'] = $ret['prime_mover_export_targetid'];
        }
        if (!empty($ret['prime_mover_export_type'])) {
            $config['prime_mover_export_type'] = $this->getSystemChecks()->getSystemFunctions()->generalizeExportTypeBasedOnGiven($ret['prime_mover_export_type']);
        }        
        
        $config['prime_mover_encrypted_signature'] = '';
        if (true === $ret['enable_db_encryption']) {
            $config['prime_mover_encrypted_signature'] = $this->getOpenSSLUtilities()->generateEncryptedSignatureString($ret, $blogid_to_export);
        }
        $config = apply_filters('prime_mover_define_other_package_configuration', $config, $ret, $blogid_to_export);
        if (count($config) < 7) {
            $ret['error'] = esc_html__('Corrupted package configuration. Aborting.', 'prime-mover');
            return $ret;
        }
        global $wp_filesystem;
        $config_written = $wp_filesystem->put_contents($tar_config, json_encode($config));  
        if (!$config_written) {
            $ret['error'] = esc_html__('Unable to write package configuration. Please check permissions.', 'prime-mover');
            return $ret;
        }
        
        $closed_identifier = wp_normalize_path(trailingslashit($temp_folder_path) . PRIME_MOVER_WPRIME_CLOSED_IDENTIFIER);
        $wp_filesystem->put_contents($closed_identifier, esc_html__('This is a WPRIME archive created by WordPress Prime Mover Plugin (developer: Codexonics Ltd). This is not designed to be read or extracted by any third party software. Attempting to manually read and write this archive will result in data corruption errors.'));
        $ret['wprime_readme_path'] = $closed_identifier;
        
        $local_name = trailingslashit(basename($temp_folder_path)) . PRIME_MOVER_WPRIME_CONFIG;
        return apply_filters('prime_mover_add_file_to_tar_archive', $ret, $ret['target_zip_path'], 'ab', $tar_config, $local_name , 0, 0, $blogid_to_export, false, false);
    }
    
    /**
     * Create tarball instance
     * @param string $tar_path
     * @param string $mode
     * @return NULL|\Codexonics\PrimeMoverFramework\build\splitbrain\PHPArchive\Tar
     */
    protected function createTarInstance($tar_path = '', $mode = 'wb')
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return null;
        }
        $tar = new Tar();
        $tar->create($tar_path, $mode);  
        
        return $tar;
    }
    
    /**
     * Add file to WPRIME archive
     * @param array $ret
     * @param string $tar_path
     * @param string $mode
     * @param string $entity_to_add
     * @param string $local_name
     * @param number $start
     * @param number $file_position
     * @param number $blog_id
     * @param boolean $enable_retry
     * @param boolean $close
     * @param string $key
     * @param string $iv
     * @return boolean|number
     * 
     * Returns:
     * $ret['error'] in case error is detected when adding file to tarball
     * $ret['tar_add_file_offset'] in case a retry is needed if requested
     * 
     * $ret unchanged array if success
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itAddsFileToArchive()
     */
    public function addFile($ret = [], $tar_path = '', $mode = 'wb', $entity_to_add = '', $local_name = '', $start = 0, $file_position = 0, 
        $blog_id = 0, $enable_retry = false, $close = false, $key = '', $iv = '')
    {   
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized to archive', 'prime-mover');
            return $ret;
        }
        if (!$tar_path || !$entity_to_add || !$local_name) {
            $ret['error'] = esc_html__('Incomplete add file to WPRIME configuration.', 'prime-mover');
            return $ret;
        }
        if ($enable_retry && !$start) {
            $ret['error'] = esc_html__('Incorrect time start configuration.', 'prime-mover');
            return $ret;
        }
        
        $tar = $this->createTarInstance($tar_path, $mode);
        if (!is_object($tar)) {
            $ret['error'] = esc_html__('Unable to initialize WPRIME object. Please check capabilities.', 'prime-mover');
            return $ret;
        }
        $addresult = $tar->addFile($entity_to_add, $local_name, $start, $file_position, $blog_id, $enable_retry, $key, $iv);            
        if (is_string($addresult)) {
            $ret['error'] = $addresult;
            return $ret;
        }
        
        if (is_array($addresult) && isset($addresult['tar_add_offset'])) {
            $ret['tar_add_file_offset'] = (int)$addresult['tar_add_offset'];            
        }
        
        if (is_array($addresult) && isset($addresult['iv'])) {
            $ret['tar_encrypt_iv'] = $addresult['iv'];
        }
        
        if (is_array($addresult)) {
            return $ret;
        }
        
        if (is_int($addresult)) {
            $ret = $this->cleanUpAddFileRet($ret);         
        }
        
        return $ret;
    }
    
    /**
     * Clean up add file ret array
     * @param array $ret
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itCleanUpAddFileRet()
     */
    protected function cleanUpAddFileRet($ret = [])
    {
        if (!empty($ret['tar_add_file_offset'])) {
            unset($ret['tar_add_file_offset']);
        }
        if (!empty($ret['tar_encrypt_iv'])) {
            unset($ret['tar_encrypt_iv']);
        }
        return $ret;
    }
    
    /**
     * Get resume positions
     * @param array $resume_positions
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itGetsResumePositions()
     */
    protected function getResumePositions($resume_positions = [])
    {
        $list_position = 0;
        $file_position = 0;
        $bytes_written = 0;
        $files_archived = 0;
        $initialization_vector = '';
        
        if (empty($resume_positions) || !is_array($resume_positions)) {           
            return [0, 0, 0, 0, ''];
        }
        
        if (!empty($resume_positions['list_position'])) {
            $list_position = $resume_positions['list_position'];
        }
        
        if (!empty($resume_positions['file_position'])) {
            $file_position = $resume_positions['file_position'];
        }
        
        if (!empty($resume_positions['bytes_written'])) {
            $bytes_written = $resume_positions['bytes_written'];
        }
        
        if (!empty($resume_positions['files_archived'])) {
            $files_archived = $resume_positions['files_archived'];
        }
        
        if (!empty($resume_positions['initialization_vector'])) {
            $initialization_vector = $resume_positions['initialization_vector'];
        }
        
        return [$list_position, $file_position, $bytes_written, $files_archived, $initialization_vector];
    }
    
    /**
     * Add directory to WPRIME archive
     * @param array $ret
     * @param string $tar_path
     * @param string $filelist
     * @param string $rootpath
     * @param number $start
     * @param array $resume_positions
     * @param boolean $close_on_finish
     * @param number $blog_id
     * @param array $archiving_params
     * @param string $mode
     * @param string $encryption_key
     * @return array
     * 
     * Returns:
     * $ret['error'] in case of \Error
     * $ret['tar_add_dir_offsets'] in case of retries
     * $ret normal array if done successfully
     */
    public function addDirectory($ret = [], $tar_path = '', $filelist = '', $rootpath = '', $start = 0, $resume_positions = [], $close_on_finish = false, $blog_id = 0, 
        $archiving_params = [], $mode = 'wb', $encryption_key = '')
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized to archive', 'prime-mover');
            return $ret;
        }
        $rootpath = wp_normalize_path($rootpath);
        if (!file_exists($filelist)) {
            $ret['error'] = esc_html__("ERROR: Missing file list - unable to add directory to archive.", 'prime-mover');
            return $ret;
        }
        if (!$tar_path) {
            $ret['error'] = esc_html__("ERROR: Tar path undefined - unable to add directory to archive.", 'prime-mover');
            return $ret;
        }
        if (!file_exists($rootpath)) {
            $ret['error'] = esc_html__("ERROR: Missing root path - unable to add directory to archive.", 'prime-mover');
            return $ret;
        }
        $handle = fopen($filelist, 'rb');        
        list($list_position, $file_position, $bytes_written, $files_archived, $initialization_vector) = $this->getResumePositions($resume_positions);
        
        if ($list_position) {
            fseek($handle, $list_position);            
            do_action('prime_mover_log_processed_events', "Starting archiving in resume mode at file list position $list_position", $blog_id, 'export', __FUNCTION__, $this);
        }
        if ($list_position) {
            $mode = 'ab';
        }
        $tar = $this->createTarInstance($tar_path, $mode);        
        if (!is_resource($handle)) {
            $ret['error'] = esc_html__("Unable to create WPRIME archive resource, please check permissions.", 'prime-mover');
            return $ret;
        }
        list($archive_alias, $exclusion_rules, $exporting_mode) = $archiving_params;        
        while(!feof($handle)) {
            $pos = ftell($handle);
            $line = trim(fgets($handle));
            do_action('prime_mover_log_processed_events', "Processing $line for archiving", $blog_id, 'export', __FUNCTION__, $this, true);
            if (!$line) {
                continue;
            }
            if (!file_exists($line)) {
                continue;
            }            
            if ($this->getSystemChecks()->isGitSvnRepo($line)) {
                do_action('prime_mover_log_processed_events', "File $line is excluded from the archive because its SVN or GIT repo signature", $blog_id, 'export', __FUNCTION__, $this, true);
                continue;
            }
            if ($this->maybeExcludeMedia($exporting_mode, $line, $exclusion_rules, $rootpath, $blog_id)) {
                continue;
            }            
            $archfilepath = str_replace(untrailingslashit($rootpath), $archive_alias, $line);      
            $archfilepath = $this->maybeHandleLocalizedLanguageFolders($archfilepath, $archive_alias, $ret, $rootpath, $blog_id);
            $res = $tar->addFile($line, $archfilepath, $start, $file_position, $blog_id, true, $encryption_key, $initialization_vector, $bytes_written);
            
            if ($initialization_vector) {
                $initialization_vector = '';
            }
            
            if ($file_position) {
                $file_position = 0;
            }
            
            if (is_array($res) && isset($res['tar_add_offset']) && isset($res['iv']) && isset($res['bytes_written'])) { 
                $file_offset = (int)$res['tar_add_offset'];
                $bytes_written = (int)$res['bytes_written'];
                do_action('prime_mover_log_processed_events', "Stopping archiving on $line due to timeout.", $blog_id, 'export', __FUNCTION__, $this);
                do_action('prime_mover_log_processed_events',"Need to resume on file list position $pos and $line position $file_offset, already processed $bytes_written bytes.", $blog_id, 'export', __FUNCTION__, $this);               
                
                $ret['tar_add_dir_offsets'] = ['list_position' => $pos, 'file_position' => $file_offset, 'files_archived' => $files_archived, 'bytes_written' => $bytes_written, 'initialization_vector' => $res['iv']];
                return $ret;
            }
            
            if (is_string($res)) {
                $ret['error'] = $res;
                return $ret;
            }            
            
            if (is_int($res)) {
                $bytes_written = $res;
            }
            
            $files_archived++;
        }
        
        do_action('prime_mover_log_processed_events', "End of file is reach for file list. Closing.", $blog_id, 'export', __FUNCTION__, $this);
        return $ret;
    }

    /**
     * Maybe handle localized language folders
     * @param string $archfilepath
     * @param string $archive_alias
     * @param array $ret
     * @param string $root_path
     * @param number $source_blogid
     * @return string|mixed
     */
    protected function maybeHandleLocalizedLanguageFolders($archfilepath = '', $archive_alias = '', $ret = [], $root_path = '', $source_blogid = 0)
    {
        if (empty($ret['prime_mover_export_targetid']) || !$source_blogid || !$archive_alias || !$root_path || !$archfilepath) {
            return $archfilepath;
        }
        
        if (!$this->isArchivingLanguageFolders($ret, $root_path)) {
            return $archfilepath;
        }
        
        $target_export_blogid = $ret['prime_mover_export_targetid'];
        $target_export_blogid = (int)$target_export_blogid;        
        $source_blogid = (int)$source_blogid;
        
        if (!$target_export_blogid || !$source_blogid) {
            return $archfilepath;
        }
        
        $source_localized_folder = $this->computeLocalizedFolder($archive_alias, $source_blogid);
        $target_localized_folder = $this->computeLocalizedFolder($archive_alias, $target_export_blogid);
        
        return str_replace($source_localized_folder, $target_localized_folder, $archfilepath);
    }
    
    /**
     * Checks if archiving language folders
     * @param array $ret
     * @param string $root_path
     * @return boolean
     */
    protected function isArchivingLanguageFolders($ret = [], $root_path = '')
    {
        $result = false;
        if (isset($ret['languages_folder_path']) && $root_path && $root_path === $ret['languages_folder_path']) {
            $result = true;
        }
        return $result;
    }
    
    /**
     * Compute localized folder for custom language files
     * If a blog ID is passed (so its multisite subsite) - the language files are inside a blog ID folder.
     * @param string $archive_alias
     * @param number $blog_id
     * @return string
     */
    protected function computeLocalizedFolder($archive_alias = '', $blog_id = 0)
    {
        $localized_folder = untrailingslashit(wp_normalize_path($archive_alias)) . '/wpml';
        if ($blog_id > 1) {
            $localized_folder = untrailingslashit(wp_normalize_path($archive_alias)) . '/wpml/' . $blog_id;
        }
        
        return $localized_folder;
    }
    
    /**
     * Maybe exclude media
     * @param string $exporting_mode
     * @param string $line
     * @param array $exclusion_rules
     * @param string $rootpath
     * @param number $blog_id
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverArchiver::itMaybeExcludeMedia() 
     */
    protected function maybeExcludeMedia($exporting_mode = '', $line = '', $exclusion_rules = [], $rootpath = '', $blog_id = 0)
    {
        $exporting_media = false;
        if ('exporting_media' === $exporting_mode) {
            $exporting_media = true;
        }
        if (apply_filters('prime_mover_media_resource_is_excluded', false, $line, $exclusion_rules, $rootpath, $exporting_media, $blog_id)) {
            do_action('prime_mover_log_processed_events', "File $line is excluded from the archive because of media exclusion settings", $blog_id, 'export', __FUNCTION__, $this, true);
            return true;
        }
        return false;
    }
}
