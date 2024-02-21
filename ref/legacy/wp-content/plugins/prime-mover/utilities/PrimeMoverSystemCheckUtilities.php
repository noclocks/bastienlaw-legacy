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
use WP_Error;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use PDO;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover System Check Utilities
 * Helper functionality for System check class
 *
 */
class PrimeMoverSystemCheckUtilities
{      
    private $system_functions;
    private $system_utilities;
    
    /**
     * Constructor
     * @param PrimeMoverSystemFunctions $system_functions
     * @param PrimeMoverSystemUtilities $system_utilities
     */
    public function __construct(
        PrimeMoverSystemFunctions $system_functions, PrimeMoverSystemUtilities $system_utilities
        ) 
    {
            $this->system_functions = $system_functions;
            $this->system_utilities = $system_utilities;
    }
    
    /**
     * Get system utilities
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverSystemUtilities
     */
    public function getSystemUtilities()
    {
        return $this->system_utilities;
    }    
    
    /**
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     * @compatible 5.6
     */
    public function getSystemFunctions()
    {
        return $this->system_functions;
    }
    
    /**
     * Get System Initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     */
    public function getSystemInitialization()
    {
        return $this->getSystemFunctions()->getSystemInitialization();
    }
    
    /**
     * Get System authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     */
    public function getSystemAuthorization()
    {
        return $this->getSystemFunctions()->getSystemAuthorization();
    }
    
    /**
     * Initialize hooks
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemCheckUtilities::itAddsInitHoooks()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemCheckUtilities::itChecksIfHooksAreOutdated()
     */
    public function initHooks() 
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        add_filter('prime_mover_footprint_keys', [ $this, 'addLegacyBaseURLtoFootPrint' ], 25, 2);        
        add_filter('prime_mover_validate_site_footprint_data', [ $this, 'validateLegacySiteIfBaseURLSet' ], 25, 2);
        add_filter('prime_mover_filter_replaceables', [ $this, 'replaceLegacyBaseUrls' ], 75, 2);   
        
        add_filter('prime_mover_filter_site_footprint', [ $this, 'addVersionToFootPrint' ], 25, 2);
        add_filter('prime_mover_footprint_keys', [ $this, 'addVersionKeysToFootPrint' ], 30, 2); 
        add_filter('prime_mover_validate_site_footprint_data', [ $this, 'validateVersionIfSet' ], 35, 2);
        
        add_action('prime_mover_after_actual_import', [$this, 'maybeDeactivateSomePlugins'], 10, 2);
        add_filter('prime_mover_retry_timeout_seconds', [$this, 'cliRestartFallBackTimeout'], 10, 2);
        add_filter( 'prime_mover_ajax_rendered_js_object', [$this, 'setEnvironment'], 70, 1 );
        
        add_filter('prime_mover_ajax_rendered_js_object', [$this, 'setClientSidePackageMismatchParams'], 99, 1 );
        add_filter('prime_mover_get_dump_pdo_settings', [$this, 'maybeEnableRequireSecureTransportPdo'], 10, 2);        
    }

    /**
     * Check if we require secure transport when exporting database
     * This is used in some dB servers only
     * @param array $setting
     * @param array $ret
     * @return array
     */
    public function maybeEnableRequireSecureTransportPdo($setting = [], $ret = [])
    {
        if (empty($ret['prime_mover_req_secure_transport'])) {
            return $setting;
        }
        
        $require_secure_transport = $ret['prime_mover_req_secure_transport'];
        if ($require_secure_transport && is_string($require_secure_transport)) {
            $require_secure_transport = strtolower($require_secure_transport);
        }
        
        if ('on' === $require_secure_transport && defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT') 
            && defined('PDO::MYSQL_ATTR_SSL_CA') && defined('MYSQL_CLIENT_FLAGS') && defined('MYSQLI_CLIENT_SSL') && MYSQL_CLIENT_FLAGS === MYSQLI_CLIENT_SSL) {
            
            $setting[PDO::MYSQL_ATTR_SSL_CA] = true;
            $setting[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
        
        return $setting;
    }
    
    /**
     * Get require secure transport
     * @return boolean|number
     */
    public function getRequireSecureTransport()
    {
        return $this->querySQLConfig('require_secure_transport');        
    }
    
    /**
     * Client side package mismatch error texts
     * Applies to both FREE and PRO versions.
     * @param array $args
     */
    public function setClientSidePackageMismatchParams(array $args)
    {        
        $args['prime_mover_package_mismatch_generic'] = esc_js(__('Wrong import site package! Please check that the import zip package is correct for this site.', 'prime-mover'));
        $args['prime_mover_package_mismatch_undefined_blogid'] = esc_js(__('Undefined blog ID. Please try clearing browser cache and try again.', 'prime-mover'));
        
        $args['prime_mover_package_mismatch_subsite_mismatch'] = esc_js(__('You are restoring a package with incorrect blog ID to a target subsite.', 'prime-mover'));
        $args['prime_mover_package_mismatch_singlesite_mismatch'] = esc_js(__('It looks like you are restoring a multisite package to a single site installation.', 'prime-mover'));
        
        $args['prime_mover_package_mismatch_export_type'] = esc_js(__('It looks like you are restoring a {{SOURCE_PACKAGE_TYPE}} package to a {{TARGET_PACKAGE_TYPE}} installation.'), 'prime-mover');
        
        $args['prime_mover_mainsite_id'] = 1;
        if (is_multisite()) {
            $args['prime_mover_mainsite_id'] = $this->getSystemInitialization()->getMainSiteBlogId();
        }
        
        $args['prime_mover_package_mismatch_mainsite'] = esc_js(__('It looks like you are restoring a single site package to a main site installation.', 'prime-mover'));
        $args['prime_mover_package_mismatch_heading'] = esc_js(__('Package mismatch error!', 'prime-mover'));
        $args['prime_mover_package_error_heading'] = esc_js(__('Error!', 'prime-mover'));
        
        return $args;
    }
    
    /**
     * Set Prime Mover environment
     * @param array $args
     * @return array
     */
    public function setEnvironment(array $args)
    {
        $env = 'single-site';
        if (is_multisite()) {
            $env = 'multisite';
        }
        $args['prime_mover_environment'] = $env;
        return $args;
    }
    
    /**
     * Fall to CLI restart timeout on CLI environment
     * @param number $default_time
     * @param string $method
     * @return number|mixed|NULL|array
     */
    public function cliRestartFallBackTimeout($default_time = 0, $method = '')
    {
        if ( ! $default_time || ! $method || ! $this->getSystemInitialization()->isCliEnvironment()) {
            return $default_time;
        }
        
        return apply_filters('prime_mover_cli_timeout_seconds', PRIME_MOVER_CLI_TIMEOUT_SECONDS, $method);
    }
    
    /**
     * Maybe deactivate some plugins based on exported footprint config
     * @param array $ret
     * @param number $blogid_to_import
     * @return void
     */
    public function maybeDeactivateSomePlugins($ret = [], $blogid_to_import = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        if (isset($ret['error']) || isset($ret['diff'])) {            
            return;
        }        
        
        if (! isset($ret['imported_package_footprint']['plugins'])) {
            return;
        }
        $source_site_active_plugins = $ret['imported_package_footprint']['plugins'];
        if (empty($source_site_active_plugins) || ! is_array($source_site_active_plugins)) {
            return;
        }  
        $source_activated = array_keys($source_site_active_plugins);
        $this->getSystemFunctions()->switchToBlog($blogid_to_import);
        
        wp_cache_delete('alloptions', 'options');
        $active_plugins = $this->getSystemFunctions()->getBlogOption($blogid_to_import, 'active_plugins');
       
        if ( ! is_array($active_plugins)) {
            $this->getSystemFunctions()->restoreCurrentBlog();
            return $ret;
        }

        foreach ($active_plugins as $active_plugin) {           
            if ( ! in_array($active_plugin, $source_activated )) {
                $this->getSystemFunctions()->deactivatePlugins($active_plugin, true, false);
            }
        }        
        $this->getSystemFunctions()->restoreCurrentBlog();
    }
  
    /**
     *Validate if version set in footprint is valid
     * @param bool $overall_valid
     * @param array $footprint_temp
     * @return boolean
     * @compatible 5.6
     */
    public function validateVersionIfSet($overall_valid = true, array $footprint_temp = []) 
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $overall_valid;
        }
        if (! isset($footprint_temp['prime_mover_plugin_version'])) {
            $overall_valid = false;
        }        
        return $overall_valid;
    }
    
    /**
     * Add version to footprint
     * @param array $footprint
     * @param int $blogid_to_export
     * @return array
     * @compatible 5.6
     */
    public function addVersionToFootPrint(array $footprint, $blogid_to_export = 0) 
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $footprint;
        }
        $footprint['prime_mover_plugin_version'] = PRIME_MOVER_VERSION;        
        
        return $footprint;        
    }

    /**
     * Added version keys to footprint
     * @param array $footprint_keys
     * @param array $footprint
     * @return array
     * @compatible 5.6
     */
    public function addVersionKeysToFootPrint(array $footprint_keys, array $footprint)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $footprint_keys;
        }
        if ( ! isset( $footprint['prime_mover_plugin_version'] ) ) {
            return $footprint_keys;
        }
        if (in_array( 'prime_mover_plugin_version', $footprint_keys )) {
            return $footprint_keys;
        }
        
        $footprint_keys[] = 'prime_mover_plugin_version';
        return $footprint_keys;
    }
    
    /**
     * Add in replaceables the legacy base URLS whenever applicable
     * @param array $replaceables
     * @param array $ret
     * @compatible 5.6
     */
    public function replaceLegacyBaseUrls( array $replaceables, array $ret)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $replaceables;
        }
        if ( empty( $ret['imported_package_footprint']['legacy_upload_information_url'] ) ) {
            return $replaceables;
        }
        if ( empty( $replaceables['wpupload_url']['replace'] ) ) {
            return $replaceables;
        }
        if ( ! empty( $replaceables['legacybase_url'] ) ) {
            return $replaceables;
        }
        
        $reference_index = array_search("wpupload_url",array_keys($replaceables));
        $search_part = $ret['imported_package_footprint']['legacy_upload_information_url'];
        $scheme_search = parse_url($search_part, PHP_URL_SCHEME);
        $origin_scheme = '';
        if ( ! empty( $ret['imported_package_footprint']['scheme'] ) ) {
            $origin_scheme = $ret['imported_package_footprint']['scheme'];
        }
        
        $mixed_part = '';
        if ('https://' === $origin_scheme && 'http' === $scheme_search) {
            $mixed_part = str_replace('http://', 'https://', $search_part);
        }
        
        $offset = $reference_index + 1;
        
        if ($mixed_part) {
            $replaceables = array_slice($replaceables, 0, $offset, true) +
            array('legacybase_url' => array( 'search' => $search_part, 'replace' => $replaceables['wpupload_url']['replace'] ) ) +
            array('legacybase_mixed_url' => array( 'search' => $mixed_part, 'replace' => $replaceables['wpupload_url']['replace'] ) ) +
            array_slice($replaceables, $offset, NULL, true);
        } else {
            $replaceables = array_slice($replaceables, 0, $offset, true) +
            array('legacybase_url' => array( 'search' => $search_part, 'replace' => $replaceables['wpupload_url']['replace'] ) ) +
            array_slice($replaceables, $offset, NULL, true);
        }
        
        return $replaceables;
    }
    
     /**
     * Analyze if the site being exported uses legacy upload base URLs
     * @param int $blog_id
     * @return boolean
     * @compatible 5.6
     */
    public function isLegacyMultisiteBaseURL($blog_id = 0) 
    {
        $legacy = false;
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $legacy;
        }
        if ( ! $blog_id ) {
            return $legacy;
        }
        
        $this->getSystemFunctions()->switchToBlog($blog_id);
        if ( $this->getSystemFunctions()->getSiteOption( 'ms_files_rewriting' ) && defined( 'UPLOADS' ) ) {
            $legacy = true;
            $this->getSystemInitialization()->setLegacyMultisite($legacy);
        }  
        
        $this->getSystemFunctions()->restoreCurrentBlog();
        return $legacy;        
    }
    
    /**
     * Return legacy base URLs for old multisites
     * @param int $blog_id
     * @return string
     * @compatible 5.6
     */
    public function getLegacyBaseURL($blog_id = 0) 
    {
        $ret = '';
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        if ( ! $blog_id ) {
            return $ret;
        }
        
        $this->getSystemFunctions()->switchToBlog($blog_id);        
        $siteurl = get_option( 'siteurl' );
        if ( ! $siteurl ) {
            $this->getSystemFunctions()->restoreCurrentBlog();
            return $ret;
        }
        
        $this->getSystemFunctions()->restoreCurrentBlog();
        return trailingslashit( $siteurl ) . 'files';        
    }
    
    /**
     * Adds Legacy base URl to footprint config if site is legacy
     * @param array $footprint_keys
     * @param array $footprint
     * @return array
     * @compatible 5.6
     */
    public function addLegacyBaseURLtoFootPrint(array $footprint_keys, array $footprint) 
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $footprint_keys;
        }
        if ( ! $this->getSystemInitialization()->getLegacyMultisite()) {
            return $footprint_keys;
        }
        if ( ! isset( $footprint['legacy_upload_information_url'] ) ) {
            return $footprint_keys;
        }
        if (in_array( 'legacy_upload_information_url', $footprint_keys )) {
            return $footprint_keys;
        }
        
        $footprint_keys[] = 'legacy_upload_information_url';
        return $footprint_keys;
    }
    
    /**
     * 
     * @param bool $overall_valid
     * @param array $footprint_temp
     * @return boolean
     * @compatible 5.6
     */
    public function validateLegacySiteIfBaseURLSet($overall_valid = true, array $footprint_temp = []) 
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $overall_valid;
        }
        if ( ! $this->getSystemInitialization()->getLegacyMultisite()) {
            return $overall_valid;
        }
        if (! isset($footprint_temp['legacy_upload_information_url'])) {
            $overall_valid = false;
        }
        
        return $overall_valid;        
    }
    
    /**
     * Return MySQL executable based on base directory location
     * @param string $command
     * @return void|string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemCheckUtilities::itGetsMySQLBaseDirExecutablePath() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemCheckUtilities::itReturnsNullWhenThereIsNoResult()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemCheckUtilities::itReturnsEmptyWhenExecutableDoesNotExist()
     */
    public function getMySQLBaseDirExecutablePath($command = 'mysql', $is_windows = false)
    {
        global $wp_filesystem, $wpdb;
        $executable = '';
        
        $result = $wpdb->get_results("SHOW VARIABLES WHERE Variable_name = 'basedir'", ARRAY_N);
        if ( ! is_array($result) ) {
            return;
        }
        if (empty($result[0])) {
            return;
        }
        $data = $result[0];
        if ( ! is_array($data)) {
            return;
        }
        if ( empty($data[1]) ) {
            return;
        }
        $basedir = $data[1];
        $basedir = rtrim($basedir, DIRECTORY_SEPARATOR);
        if ($is_windows) {
            $command = $command . '.exe';
        }
        $path = $basedir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $command;
        if ($wp_filesystem->exists($path)) {
            $executable = $path;
        }
        return $executable;
    }
    
    /**
     * Process file helper of copyDir() method
     * Returns boolean false if there are errors copying.
     * Returns $ret array without offset if copying success
     * Returns $ret array with offset if need to retry copying 
     * @param string $source
     * @param string $destination
     * @param boolean $move
     * @param number $blog_id
     * @param boolean $copy_by_parts
     * @param number $start
     * @param string $source_id
     * @param array $ret
     * @return boolean|boolean|number
     */
    protected function processFile($source = '', $destination = '', $move = false, $blog_id = 0, $copy_by_parts = false, $start = 0, $source_id = '', $ret = [])
    {
        if (!$source || !$destination) {
            return false;
        }
        global $wp_filesystem;
        
        $copy_result = false;
        $delete_result = false;
        
        $maybe_skip = $this->maybeSkipFileCopying($source, $destination);        
        if (is_wp_error($maybe_skip)) {
            return $maybe_skip;
        }
        
        if (true === $maybe_skip) {
            return $this->postProcessFileSuccessProcessing($ret, $wp_filesystem, $move, $source, $destination, $blog_id, $delete_result);
        }
        
        if ($move) {       
            do_action('prime_mover_log_processed_events', "MOVING $source TO $destination", $blog_id, 'import', __FUNCTION__, $this, true);
            $copy_result = $this->streamCopy($source, $destination, $copy_by_parts, $start, $source_id, $blog_id, $ret);
        } else {  
            do_action('prime_mover_log_processed_events', "COPYING $source TO $destination", $blog_id, 'import', __FUNCTION__, $this, true);
            $copy_result = $this->streamCopy($source, $destination, $copy_by_parts, $start, $source_id, $blog_id, $ret);
        }
              
        return $this->postProcessFileSuccessProcessing($copy_result, $wp_filesystem, $move, $source, $destination, $blog_id, $delete_result);
    }
 
    /**
     * Post process file success processing
     */
    protected function postProcessFileSuccessProcessing($copy_result, $wp_filesystem, $move, $source, $destination, $blog_id, $delete_result)
    {
        if (is_array($copy_result) && !isset($copy_result['copychunked_offset']) && $move) {
            do_action('prime_mover_log_processed_events', "File $source SUCCESSFULY MOVED TO $destination", $blog_id, 'import', __FUNCTION__, $this, true);
            $delete_result = $wp_filesystem->delete( $source, true);
        }
        
        if ($delete_result) {
            do_action('prime_mover_log_processed_events', "File $source SUCCESSFULY DELETED AFTER MOVING.", $blog_id, 'import', __FUNCTION__, $this, true);
        }
        
        return $copy_result;
    }
    
    /**
     * Maybe skip file copying
     * @param string $source
     * @param string $destination
     * @param number $blog_id
     * @return boolean|\WP_Error
     */
    protected function maybeSkipFileCopying($source = '', $destination = '', $blog_id = 0)
    {      
        $source = wp_normalize_path($source);
        $destination = wp_normalize_path($destination);
        
        if (!$this->getSystemFunctions()->fileExists($source) ||
            !$this->getSystemFunctions()->fileExists($destination)) {
                return false;
        }
        
        if (wp_is_writable($destination)) {
            return false;
        }
        
        $filesize = $this->getSystemFunctions()->fileSize64($source);        
        if ($this->getSystemFunctions()->isLargeStreamFile($filesize)) {
            do_action('prime_mover_log_processed_events', 'FILE COPYING ISSUE DETECTED AND TOO LARGE FILE SIZE: ' . $source, $blog_id, 'import', __FUNCTION__, $this);
            return new WP_Error( 'permission_issue_copy_dir', sprintf(__( 'Could not copy file due to permission issue - please manually delete this path and try again: %s' ), $destination), $destination);
        }
               
        $hash_algo = $this->getSystemInitialization()->getFastHashingAlgo();
        $source_hash = $this->getSystemFunctions()->hashEntity($source, $hash_algo);
        $destination_hash = $this->getSystemFunctions()->hashEntity($destination, $hash_algo);        
        
        do_action('prime_mover_log_processed_events', "Comparing non-permissive entity using hash algo: $hash_algo", $blog_id, 'import', __FUNCTION__, $this);
        do_action('prime_mover_log_processed_events', "Source $source hash: $source_hash and target $destination hash: $destination_hash", $blog_id, 'import', __FUNCTION__, $this); 
        
        if ($source_hash && $source_hash !== $destination_hash) {
            return new WP_Error( 'permission_issue_copy_dir', sprintf(__( 'Could not restore file due to permission issue - please manually delete this path and try again: %s' ), $destination), $destination);
        }
              
        return true;            
    }
    
    /**
     * Copy by streams
     * Returns normal $ret without offset if file is successfully copied
     * Returns boolean FALSE if there are permissions issues opening the file.
     * Otherwise returns $ret array with offset to resume operations.
     * @param string $from
     * @param string $to
     * @param boolean $copy_by_parts
     * @param number $start
     * @param string $source_id
     * @param number $blog_id
     * @param array $ret
     * @return boolean|number
     */
    protected function streamCopy($from = '', $to = '', $copy_by_parts = false, $start = 0, $source_id = '', $blog_id = 0, $ret = [])
    {
        $from = wp_normalize_path($from);
        $to = wp_normalize_path($to);
        
        $buffer_size = PRIME_MOVER_STREAM_COPY_CHUNK_SIZE;
        $copy_mode = 'wb';
        
        $fin = fopen($from, "rb");
        if (!is_resource($fin)) {
            do_action('prime_mover_log_processed_events', "FROM - is resource returns FALSE ", $blog_id, 'import', __FUNCTION__, $this);
            return false;
        }
        
        if (!empty($ret['copychunked_under_copy']) && $from === $ret['copychunked_under_copy'] && !empty($ret['copychunked_offset']) && $this->getSystemFunctions()->nonCachedFileExists($to) && $copy_by_parts) {
            
            do_action('prime_mover_log_processed_events', "Resume copying on $from starting at position " . $ret['copychunked_offset'], $blog_id, 'import', __FUNCTION__, $this);
            $copy_mode = 'ab';
            $seek_res = fseek($fin, $ret['copychunked_offset']);
            if (-1 === $seek_res) {
                do_action('prime_mover_log_processed_events', "FIN - Fseek error ", $blog_id, 'import', __FUNCTION__, $this);
            }
            unset($ret['copychunked_under_copy']);
            unset($ret['copychunked_offset']);
            
        }
        
        $fout = fopen($to, $copy_mode);
        if (!is_resource($fout)) {
            do_action('prime_mover_log_processed_events', "FOUT - is resource returns FALSE ", $blog_id, 'import', __FUNCTION__, $this);
            return false;
        }
        
        while(!feof($fin)) {
            $this->maybeTestStreamCopyDelay();
            $chunk = fread($fin, $buffer_size);
            if (false === $chunk) {
                do_action('prime_mover_log_processed_events', "FIN - Fread returns FALSE ", $blog_id, 'import', __FUNCTION__, $this);
            }
            $write_res = fwrite($fout, $chunk);
            if (false === $write_res) {
                do_action('prime_mover_log_processed_events', "FOUT - fwrite returns FALSE ", $blog_id, 'import', __FUNCTION__, $this);
            }
            $retry_timeout = apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, __FUNCTION__);
            if ($copy_by_parts && ((microtime(true) - $start) > $retry_timeout)) {
                $offset = ftell($fin);
                $ret['copychunked_offset'] = $offset;
                $ret['copychunked_under_copy'] = $from;
                
                do_action('prime_mover_log_processed_events', "$retry_timeout seconds time out while doing stream copy $source_id. Need to resume copying $from at position $offset.", $blog_id, 'import', __FUNCTION__, $this);
                return $ret;
            }
        }
        
        fclose($fin);
        fclose($fout);
        
        if (isset($ret['copychunked_offset'])) {
            unset($ret['copychunked_offset']);
        }
        if (isset($ret['copychunked_under_copy'])) {
            unset($ret['copychunked_under_copy']);
        }
        
        return $ret;
    }
    
    /**
     * Test stream copy delay
     */
    protected function maybeTestStreamCopyDelay()
    {
        $delay = 0;
        if (!defined('PRIME_MOVER_TEST_STREAM_COPY_DELAY')) {
            return;    
        }        
        $delay = (int)PRIME_MOVER_TEST_STREAM_COPY_DELAY;
        if (!$delay) {
            return;
        }        
        $this->getSystemInitialization()->setProcessingDelay($delay, true);
    }
    
    /**
     * Maybe test slow copy
     * @param string $source_id
     * @param number $blog_id
     */
    protected function maybeTestSlowCopy($source_id ='', $blog_id = 0)
    {
        $delay = [];
        $sleep = 0;
        if (defined('PRIME_MOVER_TEST_SLOW_COPY') && PRIME_MOVER_TEST_SLOW_COPY) {
            $delay = unserialize (PRIME_MOVER_TEST_SLOW_COPY);
        }
        if (empty($delay)) {
            return;
        }
        if (!empty($delay[$source_id])) {
            $sleep = (int)$delay[$source_id];
            do_action('prime_mover_log_processed_events', "IMPLEMENTING SLOW COPY on $source_id PROCESS WITH $sleep MICROSECONDS", $blog_id, 'import', __FUNCTION__, $this);
            $this->getSystemInitialization()->setProcessingDelay($sleep, true);
        }
    }
    
    /**
     * Cross platform compatible copy dir function
     * Derived as improvement from WordPress core version:
     * Source: https://developer.wordpress.org/reference/functions/copy_dir/
     * @param string $from
     * @param string $to
     * @param array $skip_list
     * @param array $excluded_filetypes
     * @param boolean $copy_by_parts
     * @param boolean $move
     * @param number $start
     * @param number $blog_id
     * @param number $processed
     * @param boolean $delete_source
     * @param string $source_id
     * @param array $resource
     * @param array $ret
     * @return \WP_Error|number|boolean|\WP_Error|number|boolean
     * 
     * Returns:
     * WP_Error - in case of \Error
     * $ret array WITH OFFSET in case of copy retries
     * boolean TRUE in case of COMPLETE RECURSIVE COPYING!
     */
    public function copyDir($from = '', $to = '', $skip_list = [], $excluded_filetypes = [], $copy_by_parts = false, $move = false, $start = 0, 
        $blog_id = 0, $processed = 0, $delete_source = true, $source_id = 'default', $resource = [], $ret = []) 
    {
        global $wp_filesystem;        
        $dirlist = $wp_filesystem->dirlist($from);       
        
        $from = rtrim($from, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $to   = rtrim($to, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        
        $escape = DIRECTORY_SEPARATOR;
        if ('\\' === DIRECTORY_SEPARATOR) {
            $escape = '\\\\';
        }
        $file_resource = null;
        $dir_resource = null;
        if (!empty($resource)) {
            list($file_resource, $dir_resource) = $resource;
        }
        if (is_resource($dir_resource)) {
            fwrite($dir_resource, $to . PHP_EOL);
        }
        
        if (!isset($ret['copydir_processed'])) {
            $ret['copydir_processed'] = (int)$processed;            
        }
        do_action('prime_mover_log_processed_events', "IN COPYDIR SO FAR, number of files processed: " . $ret['copydir_processed'], $blog_id, 'import', __FUNCTION__, $this, true);
        
        foreach ( (array) $dirlist as $filename => $fileinfo ) {
            $this->maybeTestSlowCopy($source_id, $blog_id);
            $filename = (string)$filename;
            if ( in_array( $filename, $skip_list, true ) ) {
                do_action('prime_mover_log_processed_events', "$filename is in skip list, skipping copy.", $blog_id, 'import', __FUNCTION__, $this);
                $ret['copydir_processed']++;
                continue;
            }
            if ( 'f' == $fileinfo['type'] ) { 
                
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (in_array($ext, $excluded_filetypes, true)) {
                    do_action('prime_mover_log_processed_events', "$ext is skip extensions, skipping copy.", $blog_id, 'import', __FUNCTION__, $this);
                    $ret['copydir_processed']++;
                    continue;
                }  
                                
                $processfile = $this->processFile(
                    $from . $filename, 
                    $to . $filename, 
                    $move, 
                    $blog_id,
                    $copy_by_parts,
                    $start,
                    $source_id,                    
                    $ret
                    );
               
                if (is_array($processfile) && !isset($processfile['copychunked_offset'])) {
                    if (is_resource($file_resource)) {
                        fwrite($file_resource, $to . $filename . PHP_EOL);
                    }
                    
                    $ret['copydir_processed']++;
                    
                } elseif (is_wp_error($processfile)) {
                    return $processfile;
                    
                } elseif (false === $processfile) {
                    
                    do_action('prime_mover_log_processed_events', 'ERROR copying file ' . $from . $filename . ", retrying with improved permissions.", $blog_id, 'import', __FUNCTION__, $this);
                    $wp_filesystem->chmod( $to . $filename, FS_CHMOD_FILE );
                    if ( false === $this->processFile(
                        $from . $filename,
                        $to . $filename,
                        $move,
                        $blog_id,
                        $copy_by_parts,
                        $start,
                        $source_id,                        
                        $ret
                        )) {
                            
                        do_action('prime_mover_log_processed_events', 'STILL HAVING ERROR copying file ' . $from . $filename . " , bailing out.", $blog_id, 'import', __FUNCTION__, $this);
                        return new WP_Error( 'copy_failed_copy_dir', __( 'Could not copy file.' ), $to . $filename );
                    }
                    
                } else {
                   
                    return $processfile;
                    
                }
            } elseif ( 'd' == $fileinfo['type'] ) {
                if ( ! $wp_filesystem->is_dir( $to . $filename ) ) {
                    if ( ! $wp_filesystem->mkdir( $to . $filename, FS_CHMOD_DIR ) ) {                        
                        do_action('prime_mover_log_processed_events', 'ERROR: Unable to create directory: ' . $to . $filename . " as requisite for copying, bail out.", $blog_id, 'import', __FUNCTION__, $this);
                        return new WP_Error( 'mkdir_failed_copy_dir', __( 'Could not create directory.' ), $to . $filename );
                    } else {
                        
                        if ($copy_by_parts) {                            
                            $ret['copydir_processed']++;
                        }
                    }
                } else {
 
                    if ($copy_by_parts) {
                        $ret['copydir_processed']++;
                    }
                }
                
                $skip_dir_copying = $this->maybeSkipDirectoryCopying($from . $filename, $to . $filename, $blog_id, $start, $ret);
                if (is_wp_error($skip_dir_copying))  {                    
                    return $skip_dir_copying;
                } elseif (true === $skip_dir_copying && true === $delete_source) {                
                    do_action('prime_mover_log_processed_events',  'Copydir success FROM ' . $from . $filename . ' TO ' . $to . $filename . ' , deleting source.', $blog_id, 'import', __FUNCTION__, $this, true);
                    $wp_filesystem->delete($from . $filename, true);
                } elseif (is_array($skip_dir_copying) && isset($skip_dir_copying['copychunked_offset'])) {
                    return $skip_dir_copying;
                } else {
                    $sub_skip_list = array();
                    foreach ( $skip_list as $skip_item ) {
                        if ( 0 === strpos( $skip_item, $filename . DIRECTORY_SEPARATOR ) ) {
                            $sub_skip_list[] = preg_replace( '!^' . preg_quote( $filename, '!' ) . $escape . '!i', '', $skip_item );
                        }
                    }
                    $result = $this->copyDir( $from . $filename, $to . $filename, $sub_skip_list, $excluded_filetypes, $copy_by_parts, $move, $start, $blog_id, $ret['copydir_processed'], $delete_source, $source_id, $resource, $ret);
                    if (is_wp_error($result))  {
                        do_action('prime_mover_log_processed_events', 'ERROR: copyDir FROM ' . $from . $filename . ' TO ' . $to . $filename, $blog_id, 'import', __FUNCTION__, $this);
                        return $result;
                    }
                    if (is_array($result) && isset($result['copychunked_offset'])) {
                        return $result;
                    }
                    if (true === $result && true === $delete_source) {
                        do_action('prime_mover_log_processed_events',  'Copydir success FROM ' . $from . $filename . ' TO ' . $to . $filename . ' , deleting source.', $blog_id, 'import', __FUNCTION__, $this, true);
                        $wp_filesystem->delete($from . $filename, true);
                    }
                }
            }
        }        
        return true;
    }    
 
    /**
     * Maybe skip directory copying
     * Usually used with destination folder is not writable however already same content
     * So we can skip copying.
     * @param string $source
     * @param string $destination
     * @param number $blog_id
     * @param number $start
     * @param array $ret
     * @return boolean|\WP_Error
     */
    protected function maybeSkipDirectoryCopying($source = '', $destination = '', $blog_id = 0, $start = 0, $ret = [])
    {
        global $wp_filesystem;
        $source = wp_normalize_path(untrailingslashit($source));
        $destination = wp_normalize_path(untrailingslashit($destination));
        
        if (!$wp_filesystem->is_dir($source) || !$wp_filesystem->is_dir($destination)) {
            return false;
        }
        
        if (wp_is_writable($destination)) {
            return false;
        }
       
        $retry_timeout = apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, __FUNCTION__);
        $running_time = microtime(true) - $start; 
        if ($running_time > $retry_timeout) {
            return $this->bailoutAndReturn('timeout need retry', false, $blog_id, $source, $destination, $ret);
        }
           
        $time_left = $retry_timeout - $running_time;
        if ($time_left < 0) {
            return $this->bailoutAndReturn('negative time left', false, $blog_id, $source, $destination, $ret);
        }
                      
        $dirsize = get_dirsize($source, $time_left);
        if (null === $dirsize || false === $dirsize) {            
            if (!empty($ret['directory_for_sizing']) && $source === $ret['directory_for_sizing']) {
                return $this->bailoutAndReturn('repeated timeout', true, $blog_id, $source, $destination,  $ret);
            } else {                
                return $this->bailoutAndReturn('retry for first time', false, $blog_id, $source, $destination,  $ret, true);
            }            
        }
        
        if (!is_integer($dirsize)) {            
            return $this->bailoutAndReturn('invalid integer', true, $blog_id, $source, $destination,  $ret);
        }
        
        if ($this->getSystemFunctions()->isLargeStreamFile($dirsize)) {            
            return $this->bailoutAndReturn('large directory', true, $blog_id, $source, $destination,  $ret);
        }
        
        $running_time = microtime(true) - $start;
        if ($running_time > $retry_timeout) {
            return $this->bailoutAndReturn('timeout need retry', false, $blog_id, $source, $destination,  $ret);
        }
        
        $hash_algo = $this->getSystemInitialization()->getFastHashingAlgo();
        $source_hash = $this->getSystemFunctions()->hashEntity($source, $hash_algo);
        
        $running_time = microtime(true) - $start;
        if ($running_time > $retry_timeout) {
            return $this->bailoutAndReturn('timeout need retry', false, $blog_id, $source, $destination,  $ret);
        }
        
        $destination_hash = $this->getSystemFunctions()->hashEntity($destination, $hash_algo); 
 
        do_action('prime_mover_log_processed_events', "Comparing non-permissive uploads directory folders entity using hash algo: $hash_algo", $blog_id, 'import', __FUNCTION__, $this);
        do_action('prime_mover_log_processed_events', "Source $source hash: $source_hash and target $destination hash: $destination_hash", $blog_id, 'import', __FUNCTION__, $this); 
        
        if ($source_hash !== $destination_hash) {
            return new WP_Error( 'permission_issue_copy_folder', sprintf(__( 'Could not copy directory due to permission issue - please manually delete this directory and try again: %s' ), $destination), $destination);
        }
       
        do_action('prime_mover_log_processed_events', "Copying directory permission success since source and destination content are the same.", $blog_id, 'import', __FUNCTION__, $this);
        return true;        
    }
    
    /**
     * Bailout and return
     * @param string $log
     * @param boolean $return_error
     * @param number $blog_id
     * @param string $source
     * @param string $destination
     * @param array $ret
     * @param boolean $dir_for_sizing
     * @return \WP_Error|string
     */
    protected function bailoutAndReturn($log = '', $return_error = false, $blog_id = 0, $source = '', $destination = '',  $ret = [], $dir_for_sizing = false)
    {
        do_action('prime_mover_log_processed_events', "Copying directory permission bailout: $log", $blog_id, 'import', __FUNCTION__, $this);        
        if ($return_error) {
            return new WP_Error( 'permission_issue_copy_folder', sprintf(__( 'Could not copy directory due to permission issue - please manually delete this directory and try again: %s' ), $destination), $destination);
            
        } 
           
        $ret['copychunked_offset'] = 0;
        $ret['copychunked_under_copy'] = $source;
        
        if ($dir_for_sizing) {
            $ret['directory_for_sizing'] = wp_normalize_path($source); 
        }        
            
        return $ret;                  
    }
    
    /**
     * Resumable zip extractor
     * @param string $zipfile
     * @param string $extraction_path
     * @param number $start
     * @param number $index
     * @param number $bytes_offset
     * @param array $return
     * @param boolean $shell
     * @param string $mode
     * @param number $blogid
     * @param array $ret
     * @return array
     */
    protected function resumableZipExtractor($zipfile = '', $extraction_path = '', $start = 0, $index = 0, $bytes_offset = 0, $return = [],
        $shell = false, $mode = '', $blogid = 0, $ret = [])
    {
        $zip = $this->getSystemInitialization()->getZipArchiveInstance();
        if (true !== $zip->open($zipfile)) {
            $return['error'] = sprintf(esc_html__('%s Error opening zip path', 'prime-mover'), $mode);
            return $return;
        }
        
        $zip = $this->getSystemInitialization()->setEncryptionPassword($zip);
        $extraction_failed = sprintf(esc_html__('%s Extraction failed.', 'prime-mover'), $mode);
        
        $total_numfiles_zip = $zip->numFiles;
        $return['total_numfiles_zip'] = $total_numfiles_zip;
        $retry_timeout = apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, 'resumableZipExtractor');
        
        for($i = $index; $i < $total_numfiles_zip; $i++) {
            list($result, $resource, $size, $path, $directory, $name) = $this->computeExtractionParameters($shell, $i, $zip, $extraction_path, $blogid);            
            if ($directory !== $name ) {
                $result = wp_mkdir_p($path);
                continue;
            }  
            
            list($use_extract_to, $chunkSize) = $this->computeChunkSize($size);           
            list($use_extract_to, $resource) = $this->getResource($use_extract_to, $zip, $name, $resource);
            
            if ($use_extract_to) {
                $result = $zip->extractTo($extraction_path, [$name]);
                $finished = microtime(true) - $start;
                if ($result && $finished > $retry_timeout) {
                    return $this->maybeExtractionNeedsToRestart($return, 0, $i, $shell, $ret, $name, $blogid);
                }                
            } else {
                $result = $this->doExtractionByFileLevelChunks($bytes_offset, $name, $blogid, $path, $resource, $chunkSize, $shell, $start, $retry_timeout, $i, $ret, $return);
                if (is_array($result)) {
                    return $result;
                }
            }
            
            if ($result) {
                $bytes_offset = 0;                
            } else {
                return $this->handleExtractionError($zip, $mode, $blogid, $extraction_failed);
            }
        }
        
        $return['media_zip_extraction_done'] = true;
        return $return;
    }
    
    /**
     * 
     * @param boolean $use_extract_to
     * @param ZipArchive $zip
     * @param string $name
     * @param resource $resource
     * @return string[]|boolean[]|resource[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemCheckUtilities::itGetsResource() 
     */
    protected function getResource($use_extract_to = false, ZipArchive $zip = null, $name = '', $resource = null)
    {
        if ( ! $use_extract_to ) {
            $resource = $zip->getStream($name);
            if ( ! is_resource($resource) ) {
                $use_extract_to = true;
                $resource = null;
            }
        }
        return [$use_extract_to, $resource];
    }
    
    /**
     * Compute extraction parameters
     * @param boolean $shell
     * @param number $i
     * @param ZipArchive $zip
     * @param string $extraction_path
     * @param number $blogid
     * @return boolean[]|NULL[]|string[]|mixed[]
     */
    protected function computeExtractionParameters($shell = false, $i = 0, ZipArchive $zip = null, $extraction_path = '', $blogid = 0)
    {    
        $this->maybeThrottleExtraction($shell);
        $result = false;
        $resource = null;
        
        $statindex = $zip->statindex($i);
        $size = $statindex['size'];
        $name = $zip->getNameIndex($i);
        
        do_action('prime_mover_log_processed_events', "Extracting from main package: " . $name . " to $extraction_path", $blogid, 'import', 'resumableZipExtractor', $this, true);
        
        $path = $extraction_path . $name;
        $directory = rtrim($name, '/');
        
        return [$result, $resource, $size, $path, $directory, $name];
    }
    
    /**
     * Maybe throttle extraction
     * @param boolean $shell
     */
    protected function maybeThrottleExtraction($shell = false)
    {
        $throttle = 0;
        if ( defined('PRIME_MOVER_TEST_SLOW_CLI_EXTRACTION') && PRIME_MOVER_TEST_SLOW_CLI_EXTRACTION) {
            $throttle = (int) PRIME_MOVER_TEST_SLOW_CLI_EXTRACTION;
            usleep($throttle);
        }        
    }
    
    /**
     * Compute chunk size
     * @param number $size
     * @return boolean[]|number[]
     */
    protected function computeChunkSize($size = 0)
    {
        $use_extract_to = true;
        $chunkSize = $size;
        if ($size > 1048576) {
            $use_extract_to = false;
            $chunkSize = 1048576;
        } 
        
        return [$use_extract_to, $chunkSize];
    }
    
    /**
     * Do file level extraction by chunks
     * @param number $bytes_offset
     * @param string $name
     * @param number $blogid
     * @param string $path
     * @param resource $resource
     * @param number $chunkSize
     * @param boolean $shell
     * @param number $start
     * @param number $retry_timeout
     * @param number $i
     * @param array $ret
     * @param array $return
     * @return string|array|boolean
     */
    protected function doExtractionByFileLevelChunks($bytes_offset = 0, $name = '', $blogid = 0, $path = '', $resource = null, $chunkSize = 0, $shell = false, 
        $start = 0, $retry_timeout = 0, $i = 0, $ret = [], $return = [])
    {
        $mode = "wb";
        if ($bytes_offset) {
            $mode = "ab";
        }
        do_action('prime_mover_log_processed_events', "Opening file $name in $mode for writing.", $blogid, 'import', 'resumableZipExtractor', $this, true);
        $unzipped = fopen($path, $mode);
        $return = $this->validateResource($return, $unzipped, $resource);
        if ( ! empty($return['error']) ) {
            return $return;
        }
        $seek_position = $this->computeSeekPosition($bytes_offset);
        while (!feof($resource)) {
            if ( ! $chunkSize ) {
                break;
            }
            $chunk = fread($resource, $chunkSize);
            if (false === $chunk) {
                do_action('prime_mover_log_processed_events', "Fread error detected on seek position $seek_position for entry $name", $blogid, 'import', 'resumableZipExtractor', $this, true);
            }
            $current_position = ftell($resource);
            if (false === $current_position) {
                do_action('prime_mover_log_processed_events', "Ftell error detected on seek position $seek_position for entry $name", $blogid, 'import', 'resumableZipExtractor', $this, true);
            }
            if ($seek_position && $current_position <= $seek_position) {
                unset($chunk);
                continue;
            }
            if(false !== $chunk) {
                $result = fwrite($unzipped, $chunk);
                $this->maybeThrottleExtraction($shell);
            } else {
                do_action('prime_mover_log_processed_events', "Missed write error on seek position $seek_position for entry $name", $blogid, 'import', 'resumableZipExtractor', $this, true);
            }
            $finished = microtime(true) - $start;
            if ($finished > $retry_timeout) {
                return $this->maybeExtractionNeedsToRestart($return, $current_position, $i, $shell, $ret, $name, $blogid);
            }
        }        
        $result = fclose($unzipped);
        return $result;
    }
    
    /**
     * Compute seek position
     * @param number $bytes_offset
     * @return number
     */
    protected function computeSeekPosition($bytes_offset = 0)
    {
        $seek_position = 0;
        if ($bytes_offset) {
            $seek_position = $bytes_offset;
        }
        return $seek_position;
    }
    
    /**
     * Validate resource
     * @param array $return
     * @param resource $unzipped
     * @param resource $resource
     * @return string
     */
    protected function validateResource($return = [], $unzipped = null, $resource = null)
    {
        if (! is_resource($unzipped)) {
            $return['error'] = esc_html__("Target path cannot be written", 'prime-mover');
            return $return;
        }
        if (! is_resource($resource) ) {
            $return['error'] = esc_html__("Invalid zip stream resource", 'prime-mover');
            return $return;
        }
        
        return $return;
    }
    
    /**
     * Handle extraction error
     * @param ZipArchive $zip
     * @param string $mode
     * @param number $blogid
     * @param string $extraction_failed
     * @return string
     */
    protected function handleExtractionError(ZipArchive $zip, $mode = '', $blogid = 0, $extraction_failed = '')
    {
        $error = $zip->getStatusString();
        do_action('prime_mover_log_processed_events', "Some $mode extraction error found.", $blogid, 'import', 'resumableZipExtractor', $this);
        if ($error) {
            $extraction_failed = $extraction_failed . ' ' . $error;
        }
        
        $return['error'] = $extraction_failed;
        if (is_resource($zip)) {
            $zip->close();
        }
        
        return $return;
    }
    
    /**
     * Check if extraction needs to restart , $current_position needs to be zero for native extractor
     * @param array $return
     * @param number $current_position
     * @param number $i
     * @param boolean $shell
     * @param array $ret
     * @param string $name
     * @param number $blogid
     * @return array
     */
    protected function maybeExtractionNeedsToRestart($return = [], $current_position = 0, $i = 0, $shell = false, $ret = [], $name = '', $blogid = 0)
    {
        $return['media_zip_last_index'] = $i;
        $return['zip_bytes_offset'] = $current_position;
        
        if ($shell) {
            $this->getSystemFunctions()->logCliReProcessingArray($ret, ['media_zip_last_index' => $i, 'zip_bytes_offset' => $current_position]);
        }
        do_action('prime_mover_log_processed_events', "Zip extraction retry needed on index $i and last position $current_position for file $name", $blogid, 'import', 'resumableZipExtractor', $this);
        return $return;      
    }
    
    
    /**
     * Extract zip by parts
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemCheckUtilities::itExtractZipByParts()
     * @param string $media_path
     * @param array $ret
     * @param string $extraction_path
     * @param number $blogid_to_import
     * @param number $extract_start
     * @param string $mode
     * @param boolean $shell
     * @return array
     */
    public function primeMoverExtractZipByParts($media_path = '', $ret = [], $extraction_path = '', $blogid_to_import = 0, $extract_start = 0, $mode = '', $shell = false)
    {
        $return = [];
        if ( ! $mode ) {
            $mode = esc_html__('Media', 'prime-mover');
        }
        if ( ! $media_path ) {
            $return['error'] = sprintf(esc_html__('%s archive path does not exist', 'prime-mover'), $mode);
            return $return;
        }
        if ( ! $extraction_path) {
            $return['error'] = sprintf(esc_html__('%s extraction path does not exist', 'prime-mover'), $mode);
            return $return;
        }        
        
        $index = 0;
        if ( ! empty($ret['media_zip_last_index'])) {
            $index = (int) $ret['media_zip_last_index'];
        }
        
        $bytes_offset = 0;
        if ( ! empty($ret['zip_bytes_offset'])) {
            $bytes_offset = (int) $ret['zip_bytes_offset'];            
        }
        list($index, $bytes_offset) = $this->getExtractionReprocessingParameters($ret, $index, $bytes_offset, $shell);
        return $this->resumableZipExtractor($media_path, $extraction_path, $extract_start, $index, $bytes_offset, $return, $shell, $mode, $blogid_to_import, $ret);
    }  
    
    /**
     * Get extraction reprocessiong parameters
     * @param array $ret
     * @param number $index
     * @param number $bytes_offset
     * @param boolean $shell
     * @return number[]
     */
    protected function getExtractionReprocessingParameters($ret = [], $index = 0, $bytes_offset = 0, $shell = false)
    {
        if ( ! $shell ) {
            return [$index, $bytes_offset];
        }
        global $wp_filesystem;
        $cli_tmpname = $this->getSystemInitialization()->generateCliReprocessingTmpName($ret, $ret['process_id'], $ret['shell_progress_key']);
        if ( ! $this->getSystemFunctions()->nonCachedFileExists($cli_tmpname)) {
            return [$index, $bytes_offset];
        }

        $json_string = $wp_filesystem->get_contents($cli_tmpname);
        $extraction_parameters = json_decode($json_string, true);
        $this->getSystemFunctions()->primeMoverDoDelete($cli_tmpname, true);
       
        $index = 0;
        if ( ! empty($extraction_parameters['media_zip_last_index'])) {
            $index = (int) $extraction_parameters['media_zip_last_index'];
        }
        
        $bytes_offset = 0;
        if ( ! empty($extraction_parameters['zip_bytes_offset'])) {
            $bytes_offset = (int)$extraction_parameters['zip_bytes_offset'];
        }
        
        return [$index, $bytes_offset];
    }
    
    /**
     * Get max allowed packet
     * @return boolean|number
     */
    public function getMaxAllowedPacket()
    {
        return $this->querySQLConfig('max_allowed_packet');
    }
    
    /**
     * Query config in client dB
     * @param string $config
     * @return boolean|number
     */
    protected function querySQLConfig($config = 'max_allowed_packet')
    {
        global $wpdb;
        $result = false;
        
        if ('max_allowed_packet' === $config) {
            $result = $wpdb->get_row("SHOW VARIABLES LIKE 'max_allowed_packet'", ARRAY_N);
        } 
        
        if ('require_secure_transport' === $config) {
            $result = $wpdb->get_row("SHOW VARIABLES LIKE 'require_secure_transport'", ARRAY_N);
        }       
        
        if (!is_array($result)) {
            return false;
        }
        
        if (empty($result[0]) || empty($result[1])) {
            return false;
        }
        
        if ($config !== $result[0]) {
            return false;
        }
        
        $setting = $result[1];
        if ('max_allowed_packet' === $config) {
            $setting = (int)$setting;
        }
        
        if (!$setting) {
            return false;
        }
        
        return $setting;  
    }
    
    /**
     * Get total media files on import
     * Prioritizes the already computed count during export process
     * Before falling back on slower iterator methods.
     * @param array $ret
     * @param string $source
     * @return number
     */
    public function getTotalMediaFilesCountOnImport($ret = [], $source = '')
    {
        if (!empty($ret['imported_package_footprint']['wprime_media_files_count'])) {
            $count = (int)$ret['imported_package_footprint']['wprime_media_files_count'];
        } else {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
            $files->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);
            $count = iterator_count($files);
        }
        
        return $count;
    }
}
