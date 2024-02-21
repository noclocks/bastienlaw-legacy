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

use Codexonics\PrimeMoverFramework\streams\PrimeMoverResumableDownloadStream;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Download Utilities
 * Helper functionality for Download
 * Export success triggers a javascript redirect with the URL to download the package
 * This class it's processor.
 * It will stream the exported subsite package and then deletes it.
 *
 */
class PrimeMoverDownloadUtilities
{
    private $resume_download_stream;

    const CRON_BIWEEKLY_INTERVAL = 259200;
    const CRON_TESTING_INTERVAL = 600;
    
    /**
     * Constructor
     * @param PrimeMoverResumableDownloadStream $resume_download_stream
     */
    public function __construct(PrimeMoverResumableDownloadStream $resume_download_stream)
    {
        $this->resume_download_stream = $resume_download_stream;
    }
    
    /**
     * Get resume download stream
     * @return \Codexonics\PrimeMoverFramework\streams\PrimeMoverResumableDownloadStream
     */
    public function getResumeDownloadStream()
    {
        return $this->resume_download_stream;
    }
    
    /**
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     */
    public function getSystemFunctions()
    {
        return $this->getResumeDownloadStream()->getSystemFunctions();
    }
    
    /**
     * Get system authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     */
    public function getSystemAuthorization()
    {
        return $this->getResumeDownloadStream()->getSystemAuthorization();
    }
 
    /**
     *
     * Get System Initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     * @compatible 5.6
     */
    public function getSystemInitialization()
    {
        return $this->getResumeDownloadStream()->getSystemFunctions()->getSystemInitialization();
    }
    
    /**
     * Init hooks
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverExportUtilities::itAddsInitHooks() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDownloadUtilities::itChecksIfHooksAreOutdated() 
     */
    public function initHooks()
    {
        /**
         * Should be available after composeObjects.
         */
        add_action('init', [ $this, 'streamDownload' ], 2);
        
        /**
         * @since 1.0.7
         */
        add_action('prime_mover_after_generating_download_url', [$this, 'saveDownloadURLParameters'], 10, 5);         
        add_action('prime_mover_load_module_apps',[$this,'setUpCronToDeleteTmpFiles']);
        add_filter('cron_schedules', [$this, 'addCustomSchedule'], 10 , 1 );
        
        add_action('primeMoverDeleteSymlinkEvent', [$this,'doPrimeMoverDeleteSymlink']);        
        add_filter('cron_request', [$this, 'maybeEnableCronDebug'], 10, 1);
        add_action('prime_mover_maintenance_cron_tasks', [$this, 'readOldTmpFilesAndDelete']);
    }
 
    /**
     * Maybe enable cron debug
     * @param array $params
     * @return string
     */
    public function maybeEnableCronDebug($params = [])
    {        
        if (PRIME_MOVER_CRON_TEST_MODE && ! empty($params['url']) ) {
            $cron_url = $params['url'];
            $cron_url = add_query_arg('XDEBUG_SESSION_START', 'wordpress', $cron_url);
            $params['url'] = $cron_url;
        }        
        return $params;
    }
    
    /**
     * Get cron intervals
     * @param boolean $keys_only
     * @return string|number[]|string[]
     */
    protected function getCronInterval($keys_only = false)
    {
        $key = 'every_prime_mover_biweek';
        $interval = self::CRON_BIWEEKLY_INTERVAL;
        $display = esc_html__('Twice a week', 'prime-mover');
        
        if (PRIME_MOVER_CRON_TEST_MODE) {
            $key = 'every_prime_mover_ten_minutes';
            $display = esc_html__('Every 10 minutes', 'prime-mover');
            $interval = self::CRON_TESTING_INTERVAL;
        }
        
        if ($keys_only) {
            return $key;
        } else {
            return [$interval, $display, $key];
        }
    }
    
    /**
     * Added custom cron cleaning schedule of download tmp symlinks
     * @param array $schedules
     * @return array
     */
    public function addCustomSchedule( $schedules = []) {
        list($interval, $display, $key) = $this->getCronInterval();
        $schedules[$key] = array(
            'interval'  => $interval,
            'display'   => $display
        );
        
        return $schedules;
    }
    
    /**
     * Setup cron delete tmp files
     * NORMAL MODE: This runs twice a week and check for files older than a day and deletes it.
     * TESTING MODE: This runs every 10 minutes and check for files older than two minutes then deletes it.
     */
    public function setUpCronToDeleteTmpFiles()
    {      
        if ( ! $this->getSystemAuthorization()->isUserAuthorized() ) {
            return;
        }          
        $key = $this->getCronInterval(true);        
        if ( ! wp_next_scheduled('primeMoverDeleteSymlinkEvent')) {
            wp_schedule_event(time(), $key, 'primeMoverDeleteSymlinkEvent');
        }                        
    }
    
    /**
     * Fires Prime Mover cron task runner for maintenance reasons.
     */
    public function doPrimeMoverDeleteSymlink()
    {
        if ( ! wp_doing_cron() ) {
            return;
        }
        do_action('prime_mover_maintenance_cron_tasks');
    }
    
    /**
     * Read old shortlinked tmp files and delete
     * Hooked to WP Cron
     */
    public function readOldTmpFilesAndDelete()
    {
        $tmp_downloads_dir = $this->getSystemInitialization()->getTmpDownloadsFolder();
        if ( ! $tmp_downloads_dir ) {
            return;
        }
        $tmp_downloads_dir = trailingslashit($tmp_downloads_dir);
        $this->getSystemInitialization()->multisiteInitializeWpFilesystemApiCli(false);
        $files = list_files($tmp_downloads_dir , 100, ['index.html']);
        if ( ! is_array($files) ) {
            return;
        }
        foreach ($files as $file) {
            if ( ! is_link($file)) {
                continue;
            }
            $target = readlink($file);
            if ( ! $target) {                
                $this->getSystemFunctions()->unLink($file);
                continue;
            }
            if ($target && ! file_exists($target)) {                
                $this->getSystemFunctions()->unLink($file);
                continue;
            }
            if ($target && ((time()-filectime($file)) > PRIME_MOVER_CRON_DELETE_TMP_INTERVALS && is_link($file))) {
                $this->getSystemFunctions()->unLink($file);
            }
        } 
    }
    
    /**
     * Save download URL parameters
     * @param string $results
     * @param string $hash
     * @param number $blogid_to_export
     * @param boolean $export_directory_on
     */
    public function saveDownloadURLParameters($results = '', $hash = '', $blogid_to_export = 0, $export_directory_on = false, $ret = [])
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized() ) {
            return;
        }
        if ( ! $blogid_to_export || ! $results || ! $hash || empty($ret['target_zip_path']) ) {
            return;
        }        
        $option_name = $this->getSystemInitialization()->generateZipDownloadOptionName(sanitize_html_class(basename($results)), $blogid_to_export);
        $option_value = [];
        $option_value = ['blogid' => $blogid_to_export, 'hash' => $hash];
        if ( ! empty($ret['multisite_export_options'])) {
            $option_value['export_option'] = $ret['multisite_export_options'];
        }
        if ( ! empty($ret['prime_mover_encrypt_db'])) {
            $option_value['encrypted_db'] = $ret['prime_mover_encrypt_db'];
        }
        if ( ! empty($ret['encrypted_media'] ) ) {
            $encrypted_media = $ret['encrypted_media'];
            $encrypted_media = ($encrypted_media) ? 'true' : 'false';
            $option_value['encrypted_media'] = $encrypted_media;
        }
        
        if (empty($ret['site_title'])) {
            $this->getSystemFunctions()->switchToBlog($blogid_to_export);
            $site_title = get_bloginfo( 'name' );
            $this->getSystemFunctions()->restoreCurrentBlog();
            $option_value['site_title'] = $site_title;
        } else {
            $option_value['site_title'] = $ret['site_title'];
        }        
        
        $zip_path = $ret['target_zip_path'];
        $timestamp = filectime($zip_path);
        $option_value['creation_timestamp'] = $timestamp;
        
        if (isset($ret['include_users'])) {
            $option_value['include_users'] = $ret['include_users'];
        }
        
        $this->getSystemFunctions()->updateSiteOption($option_name, $option_value, true);
    }
    
    /**
     * Prime Mover stream download helper
     * @compatible 5.6
     */
    public function streamDownload()
    {
        $params = $this->getParameters();
        if (empty($params['prime_mover_export_hash']) || empty($params['prime_mover_blogid'])) {
            return;
        }
        
        $authenticated_by_capability = false;
        $hash = $params['prime_mover_export_hash'];
        $blog_id = (int)$params['prime_mover_blogid'];
        
        if ( ! $hash || ! $blog_id ) {
            return;
        }
        do_action('prime_mover_log_processed_events', 'Stream download request received', $blog_id, 'export', 'streamDownload', $this);
        $proceed = false;
        $remote_url_request = false;
        
        if ($this->isShaString($hash, 256) && empty($params['prime_mover_download_nonce'])) {
            $remote_url_request = true;
        }
        if ( ! $remote_url_request && empty($params['prime_mover_download_nonce']) ) {
            return;
        }
        
        $nonce = $params['prime_mover_download_nonce'];
        $action = 'prime_mover_download_package_' . $blog_id;        
        $proceed = $this->validateIfUserIsAuthenticatedByCapability($proceed, $remote_url_request, $nonce, $action, $blog_id);        
        if ($proceed) {
            $authenticated_by_capability = true;
        }
        
        $input_server = $this->getSystemInitialization()->getUserInput('server',
            $this->returnSanitizingFilters(),
            'download_authorization_parameters', '', 0, true, true
            );
        
        if ($authenticated_by_capability) {
            $proceed = apply_filters('prime_mover_authenticated_capability_valid_license', $proceed, $blog_id, $remote_url_request, $hash, $input_server);
        }
        
        if (in_array($proceed, [401, 402], true)) {
            status_header($proceed);
            exit;
        }       
        
        if ( ! $proceed && ! empty($input_server['HTTP_X_PRIME_MOVER_DOMAIN']) && ! empty($input_server['HTTP_X_PRIME_MOVER_AUTHORIZATION']) ) {
            $proceed = apply_filters('prime_mover_stream_download', $proceed, $blog_id, $remote_url_request, $hash, $input_server);
        }        
        if ( false === $proceed ) {
            status_header(401);
            exit;
        }
        if (402 === $proceed) {
            status_header(402);
            exit;
        }
        if ($remote_url_request && $proceed) {
            do_action('prime_mover_log_processed_events', 'Stream download request authenticated using authorization headers', $blog_id, 'export', 'streamDownload', $this);
        }
        
        $download_path = $this->getSystemFunctions()->getSiteOption($hash, false, true, true);
        $download_filename = $this->getSystemFunctions()->getSiteOption($hash . "_filename", '', true, true);
        
        if ( ! $download_path ) {
            status_header(404);
            exit;
        }
        $download_path = $this->getSystemInitialization()->getMultisiteExportFolder() . $download_path;        
        if (file_exists($download_path)) {
            $this->streamDownloadHelper($blog_id, $download_path, $remote_url_request, $params, $input_server, $download_filename, $authenticated_by_capability);
        } else {
            do_action('prime_mover_log_processed_events', 'Stream download not found: ' . $download_path, $blog_id, 'export', 'streamDownload', $this);
            status_header(404);
        }        
        exit;
    }

    /**
     * Return sanitizing filters
     * @return string[]
     */
    protected function returnSanitizingFilters()
    {
        $filters = [
            'HTTP_X_PRIME_MOVER_AUTHORIZATION' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'HTTP_X_RESUME_BYTES_DOWNLOADING' => FILTER_SANITIZE_NUMBER_INT,
            'REQUEST_METHOD' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter()
        ];
        
        if (is_php_version_compatible(PRIME_MOVER_IDEAL_PHP_VERSION)) {
            $filters['HTTP_X_PRIME_MOVER_DOMAIN'] = FILTER_VALIDATE_DOMAIN;
        } else {
            $filters['HTTP_X_PRIME_MOVER_DOMAIN'] = FILTER_DEFAULT;
        }
        
        return $filters;
    }
    
    /**
     * Validate if user is authenticated by capability
     * @param boolean $proceed
     * @param boolean $remote_url_request
     * @param string $nonce
     * @param string $action
     * @param number $blog_id
     * @return string|boolean
     */
    private function validateIfUserIsAuthenticatedByCapability($proceed = false, $remote_url_request = false, $nonce = '', $action = '', $blog_id = 0)
    {
        if ( ! $remote_url_request && $this->getSystemAuthorization()->isUserAuthorized() && $this->getSystemFunctions()->primeMoverVerifyNonce($nonce, $action)) {
            do_action('prime_mover_log_processed_events', 'Stream download request authenticated using capability check and nonce', $blog_id, 'export', 'validateIfUserIsAuthenticatedByCapability', $this);
            $proceed = true;
        }
        if ( ! $proceed && $remote_url_request && is_user_logged_in() && $this->getSystemAuthorization()->isUserAuthorized()) {
            do_action('prime_mover_log_processed_events', 'Stream download request authenticated using capability check.', $blog_id, 'export', 'validateIfUserIsAuthenticatedByCapability', $this);
            $proceed = true;
        }
       
        return $proceed;
    }
    
    /**
     * Checks if its a HEAD request
     * @param array $input_server
     * @return boolean
     */
    protected function isHeadRequest($input_server = [])
    {
        return $this->getSystemFunctions()->isHeadRequest($input_server);     
    }
    
    /**
     * Maybe symlink download
     * @param string $original_path
     * @param number $blog_id
     * @param array $input_server
     * @return boolean|string
     */
    protected function maybeSymlinkDownload($original_path = '', $blog_id = 0, $input_server = [])
    {
        if (!$original_path || !$blog_id ) {
            return false;
        }
        
        if (!empty($input_server['HTTP_X_PRIME_MOVER_DOMAIN']) || !empty($input_server['HTTP_X_PRIME_MOVER_AUTHORIZATION']) ) {
            return false;
        }
        
        $filename = basename($original_path);
        $tmp_downloads_dir = $this->getSystemInitialization()->getTmpDownloadsFolder();
        $tmp_downloads_url = $this->getSystemInitialization()->getTmpDownloadsUrl();
        
        if (!$tmp_downloads_dir || !$tmp_downloads_url) {
            return false;
        }
        
        $tmp_downloads_dir = trailingslashit($tmp_downloads_dir);
        $tmp_downloads_url = trailingslashit($tmp_downloads_url);
        
        $unique_filename = wp_unique_filename($tmp_downloads_dir, $filename);
        
        $linkfile = $tmp_downloads_dir . $unique_filename;
        $linkurl = $tmp_downloads_url . $unique_filename;
        
        if (!$this->getSystemFunctions()->nonCachedFileExists($original_path) || !$this->getSystemFunctions()->nonCachedFileExists($tmp_downloads_dir)) {
            return false;
        }
        if (!function_exists('symlink')) {
            do_action('prime_mover_log_processed_events', 'Cannot stream using symlink method - LIMITATION : PHP symlink function does not exists.', $blog_id, 'common', __FUNCTION__, $this);
            return false;
        }
        
        $original_path = $this->maybeAdjustOriginalPath($original_path, $blog_id);
        $result = @symlink($original_path, $linkfile);
        
        if (false === $result) {
            do_action('prime_mover_log_processed_events', 'Cannot stream using symlink method - LIMITATION : Unable to create symlink to target file.', $blog_id, 'common', __FUNCTION__, $this);
            return false; 
        }        
        
        if (false === $this->validateDownloadSymlink($linkfile, $original_path)) {
            do_action('prime_mover_log_processed_events', 'Cannot stream using symlink method - LIMITATION : symlink validation is false.', $blog_id, 'common', __FUNCTION__, $this);
            return false;
        }
        
        if (false === $this->isSymlinkPubliclyAccessible($linkurl)) {
            do_action('prime_mover_log_processed_events', 'Cannot stream using symlink method - LIMITATION : public symlink URL forbidden by host.', $blog_id, 'common', __FUNCTION__, $this);
            return false;
        }
        
        do_action('prime_mover_log_processed_events', 'Stream download request using symlink method.', $blog_id, 'common', __FUNCTION__, $this);
        return $linkurl;        
    }
    
    /**
     * Checks if symlink is publicly accessible
     * It's possible a symlink could be created, readable and still valid.
     * Yet not publicly accessible e.g. 403 forbidden when accessing the URL.
     * @param string $linkurl
     * @return boolean
     */
    protected function isSymlinkPubliclyAccessible($linkurl = '')
    {
        $readable = false;
        $opts = [
            "ssl"=> [
                "verify_peer" => false,
                "verify_peer_name" => false,
            ],
        ];
        if ($this->getSystemFunctions()->iniGet('allow_url_fopen')) {           
            $readable = $this->getSystemFunctions()->fOpen($linkurl, "rb", false, $opts);
        }
        if (false === $readable) {            
            return false;
        }
        if ($this->getSystemFunctions()->isResource($readable)) {
            $this->getSystemFunctions()->fClose($readable);
        }
        return true;
    }
    
    /**
     * Maybe adjust original path and make sure it points to correct location.
     * @param string $original_path
     * @param number $blog_id
     * @return string|mixed
     */
    protected function maybeAdjustOriginalPath($original_path = '', $blog_id = 0)
    {                
        if (empty($_SERVER['SCRIPT_FILENAME'])) {
            do_action('prime_mover_log_processed_events', 'Empty script filenae, skipping adjustment', $blog_id, 'common', __FUNCTION__, $this);
            return $original_path;
        }
        
        $script_filename = $this->cleanScriptFilePath(wp_normalize_path($_SERVER['SCRIPT_FILENAME']));
        $root_filename = trailingslashit($script_filename);     
        
        $abspath = wp_normalize_path(ABSPATH);
        $wp_abspath = trailingslashit($abspath);
        
        if ($wp_abspath === $root_filename) {
            do_action('prime_mover_log_processed_events', 'Same script root filename, no further calculations needed..', $blog_id, 'common', __FUNCTION__, $this);
            return $original_path;
        }       
        
        do_action('prime_mover_log_processed_events', 'Original path: ' . $original_path, $blog_id, 'common', __FUNCTION__, $this);
        $new_original_path = $this->getAdjustedPath($root_filename, $wp_abspath, $original_path, $blog_id);
        do_action('prime_mover_log_processed_events', 'New original path: ' . $new_original_path, $blog_id, 'common', __FUNCTION__, $this);
        
        if ($new_original_path === $original_path) {
            do_action('prime_mover_log_processed_events', 'Same script root filename, skipping adjustment.', $blog_id, 'common', __FUNCTION__, $this);
            return $original_path;            
        }
        
        if (!is_file($new_original_path) || !is_readable($new_original_path) || !$this->getSystemFunctions()->hasTarExtension($new_original_path)) {
            do_action('prime_mover_log_processed_events', 'Adjusted file either does not exist, readable or invalid format.', $blog_id, 'common', __FUNCTION__, $this);
            return $original_path;
        }
        
        do_action('prime_mover_log_processed_events', 'Stream download request - original WPRIME path adjusted for symlinks.', $blog_id, 'common', __FUNCTION__, $this);
        return $new_original_path;       
    }
    
    /**
     * Clean script filename
     * @param string $script_filename (should be normalized already)
     * @return string|string|mixed
     */
    private function cleanScriptFilePath($script_filename = '')
    {
        if (!$script_filename) {
            return $script_filename;
        }
        
        $network = '/wp-admin/network/index.php';
        $single = '/wp-admin/index.php';
        
        if (is_multisite()) {
            $script_filename = str_replace($network, '', $script_filename);
        } else {
            $script_filename = str_replace($single, '', $script_filename);
        }
        
        return $script_filename;        
    }
    
    /**
     * Get adjusted path to make sure it points to permissible path
     * @param string $original_path
     * @param string $real_path
     * @param string $given_path
     * @param number $blog_id
     * @return string|mixed
     */
    protected function getAdjustedPath($original_path = '', $real_path = '', $given_path = '', $blog_id = 0)
    {
        if (!$original_path || !$real_path || !$given_path) {
            return $given_path;
        }
        
        $posssibilities = [];
        $dissect = $original_path;
        $posssibilities[] = $dissect;
        while ($path = dirname($dissect)) {
            $path = wp_normalize_path($path);
            $next = wp_normalize_path(dirname($path));
            
            if ($next === $path) {
                $posssibilities[] = $path;
                break;
            } else {
                $posssibilities[] = trailingslashit($path);
                $dissect = $path;
            }
        }
        
        $real_paths = [];
        $dissect = $real_path;
        $real_paths[] = $dissect;
        
        while ($path = dirname($dissect)) {
            $path = wp_normalize_path($path);
            $next = wp_normalize_path(dirname($path));
            
            if ($next === $path) {
                $real_paths[] = $path;
                break;
            } else {
                $real_paths[] = trailingslashit($path);
                $dissect = $path;
            }
        }
        
        $pos_counted = count($posssibilities);
        $real_path_counted = count($real_paths);
        
        if ($pos_counted !== $real_path_counted) {
            return $given_path;
        }
        
        foreach ($real_paths as $k => $search) {
            $replace = $posssibilities[$k];
            $res = str_replace($search, $replace, $given_path);
            if ($res !== $given_path) {
                return $res;
            }            
        }
        
        do_action('prime_mover_log_processed_events', 'Path computation done, new path is:' . $given_path, $blog_id, 'common', __FUNCTION__, $this);
        return $given_path;
    }
    
    /**
     * Validate download symlink
     * @param string $linkfile
     * @param string $original_path
     * @return boolean
     */
    protected function validateDownloadSymlink($linkfile = '', $original_path = '')
    {
        if ( ! $linkfile || ! $original_path) {
            return false;
        }
        if ( ! $this->getSystemFunctions()->nonCachedFileExists($linkfile) ) {
            return false;
        }
        if ( ! function_exists('is_link') || ! function_exists('readlink') ) {
            return false;
        }
        if ( ! is_link($linkfile) ) {
            return false;
        }
        $target_path = readlink($linkfile);        
        if ( ! $target_path ) {
            return false;
        }
        
        return is_readable($original_path) && $target_path === $original_path;        
    }
    
    /**
     * Stream download helper
     * @param number $blog_id
     * @param string $download_path
     * @param boolean $remote_url_request
     * @param array $params
     * @param array $input_server
     * @param string $generatedFilename
     * @param boolean $authenticated_by_capability
     */
    private function streamDownloadHelper($blog_id = 0, $download_path = '', $remote_url_request = false, $params = [], $input_server = [], $generatedFilename = '', $authenticated_by_capability = false)
    {        
        do_action('prime_mover_log_processed_events', 'Streaming download now', $blog_id, 'export', 'streamDownloadHelper', $this);
        
        $readfile_method = false;
        $header_location_method = false;
        $resumable_method = false;
        $linkurl = '';        
        
        $is_wprime = false;
        if ($this->getSystemFunctions()->hasTarExtension($download_path)) {
            $is_wprime = true;
        }       
        
        if ( ! $generatedFilename ) {
            $generatedFilename = $this->createFriendlyName($blog_id, $download_path, $is_wprime);
        }        
        
        $content_type = 'Content-Type: application/zip';
        if ($is_wprime) {
            $content_type = 'Content-Type: application/x-tar';
        }
        
        $offset = 0;        
        if ( ! empty($input_server['HTTP_X_RESUME_BYTES_DOWNLOADING']) ) {
            $offset = (int)$input_server['HTTP_X_RESUME_BYTES_DOWNLOADING'];
        }        
        $total_filesize = filesize($download_path) - $offset;               
        list($linkurl, $readfile_method, $header_location_method, $resumable_method) = $this->analyzeRenderingMethods($authenticated_by_capability, $input_server, $remote_url_request, $download_path,
            $header_location_method, $resumable_method, $blog_id, $linkurl);
        
        $this->renderDefaultHeaders($header_location_method, $readfile_method, $input_server, $generatedFilename, $total_filesize, $content_type);     
        if ($this->isHeadRequest($input_server)) {
            return;
        } 
        $this->renderDownloadHelper($header_location_method, $readfile_method, $linkurl, $download_path, $offset, $blog_id, $resumable_method);        
        if ( ! $remote_url_request ) {
            $this->getSystemFunctions()->deleteSiteOption($params['prime_mover_export_hash'] . "_filename", true);
            do_action('prime_mover_log_processed_events', 'Deleted download path and option', $blog_id, 'export', 'streamDownloadHelper', $this);
        }
    }
    
    /**
     * Analyze rendering methods
     * @param boolean $authenticated_by_capability
     * @param array $input_server
     * @param boolean $remote_url_request
     * @param string $download_path
     * @param boolean $header_location_method
     * @param boolean $resumable_method
     * @param number $blog_id
     * @param string $linkurl
     * @param boolean $readfile_method
     * @return string[]|boolean[]
     */
    protected function analyzeRenderingMethods($authenticated_by_capability = false, $input_server = [], $remote_url_request = false, $download_path = '', 
        $header_location_method = false, $resumable_method = false, $blog_id = 0, $linkurl = '', $readfile_method = false)
    {
        if ($this->maybeRenderDownloadByHeaderLocation($authenticated_by_capability, $input_server, $remote_url_request)) {            
            $linkurl = $this->maybeSymlinkDownload($download_path, $blog_id, $input_server);            
        } else {            
            $readfile_method = true;
        }        
        if ($linkurl) {            
            $header_location_method = true;
        }        
        if (false === $readfile_method && ! $linkurl) {            
            $resumable_method = true;
        }
        
        return [$linkurl, $readfile_method, $header_location_method, $resumable_method];
    }
    
    /**
     * Render default headers
     * @param boolean $header_location_method
     * @param boolean $readfile_method
     * @param mixed $input_server
     * @param string $generatedFilename
     * @param number $total_filesize
     * @param string $content_type
     */
    protected function renderDefaultHeaders($header_location_method = false, $readfile_method = false, $input_server = [], $generatedFilename = '', $total_filesize = 0, $content_type = 'Content-Type: application/zip')
    {
        if ($header_location_method || $readfile_method) {            
            header($content_type);
            header('Content-Disposition: attachment; filename="'. $generatedFilename .'"');
            header('Content-Length: ' . $total_filesize);  
            header('X-PrimeMover-Size: ' . $total_filesize);
        }                  
    }
    
    /**
     * Render download helper
     * @param boolean $header_location_method
     * @param boolean $readfile_method
     * @param string $linkurl
     * @param string $download_path
     * @param string $offset
     * @param number $blog_id
     * @param boolean $resumable_method
     */
    protected function renderDownloadHelper($header_location_method = false, $readfile_method = false, $linkurl = '', $download_path = '', $offset = '', $blog_id = 0, $resumable_method = false)
    {
        if ($header_location_method) { 
            
            do_action('prime_mover_log_processed_events', 'Rendering download by header location method.', $blog_id, 'export', 'renderDownloadHelper', $this);
            wp_redirect($linkurl, 302, PRIME_MOVER_PLUGIN_CODENAME);            
        } elseif ($readfile_method) {    
            
            do_action('prime_mover_log_processed_events', 'Rendering download by readfile method.', $blog_id, 'export', 'renderDownloadHelper', $this);
            $this->getSystemFunctions()->flush();
            $this->getSystemFunctions()->readfileChunked($download_path, false, false, $offset, $blog_id);            
        } elseif ($resumable_method) {
            
            do_action('prime_mover_log_processed_events', 'Rendering download by resumable method.', $blog_id, 'export', 'renderDownloadHelper', $this);
            $delay = 0;
            if (defined('PRIME_MOVER_TEST_CORE_DOWNLOAD') && PRIME_MOVER_TEST_CORE_DOWNLOAD) {
                $delay = 4000000;
            }
            $resume_download_stream = $this->getResumeDownloadStream();
            $resume_download_stream->initializeProperties($download_path, $delay);
            $resume_download_stream->process();
        }       
    }
    
    /**
     * Returns TRUE if downloads needs to be rendered by HEADER location method, otherwise FALSE (should defer to readfile method)
     * @param boolean $authenticated_by_capability
     * @param array $input_server
     * @param boolean $remote_url_request
     */
    protected function maybeRenderDownloadByHeaderLocation($authenticated_by_capability = false, $input_server = [], $remote_url_request = false)
    {
        $referer = wp_get_raw_referer();
        if ($this->getSystemFunctions()->isRefererBackupMenu($referer)) {
            return true;
        } 
        if (!$referer) {
            $referer = 'unset';
        }
        if (true === $remote_url_request && $referer) {
            return false;
        }
        
        if (true === $authenticated_by_capability) {
            return true;
        }        
        if (true === $remote_url_request && ! empty($input_server['HTTP_X_PRIME_MOVER_DOMAIN']) && ! empty($input_server['HTTP_X_PRIME_MOVER_AUTHORIZATION'])) {
            return false;
        }
        
        return false;
    }
    
    /**
     * Check if sha256 string
     * @param string $string
     * @return boolean
     */
    public function isShaString($string = '', $mode = 256) 
    {
        return $this->getSystemFunctions()->isShaString($string, $mode);
    }
    
    /**
     * Get parameters
     * @compatible 5.6
     */
    private function getParameters()
    {
        $args = [
            'prime_mover_download_nonce' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'prime_mover_export_hash' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'prime_mover_blogid' => FILTER_SANITIZE_NUMBER_INT,
        ];
        
        $args = apply_filters('prime_mover_filter_stream_download_args', $args );        
        return $this->getSystemInitialization()->getUserInput('get', $args, 'download_stream_parameters', '', 0, true);  
    }
    
    /**
     * Generate friendly name of the downloaded package
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
        return $this->getSystemFunctions()->createFriendlyName($blogid_to_export, $download_path, $is_wprime, $ret);
    }
}