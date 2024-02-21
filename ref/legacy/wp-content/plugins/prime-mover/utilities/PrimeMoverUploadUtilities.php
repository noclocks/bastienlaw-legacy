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

use Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemChecks;
use Codexonics\PrimeMoverFramework\classes\PrimeMoverProgressHandlers;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Upload Utilities
 * Helper functionality for Uploads
 *
 */
class PrimeMoverUploadUtilities
{
    private $system_checks;
    private $chunk;
    private $progress_handlers;
    
    /**
     *
     * Constructor
     */
    public function __construct(
        PrimeMoverSystemChecks $system_checks,
        PrimeMoverProgressHandlers $progress_handlers
    ) 
    {
        $this->system_checks = $system_checks;
        $this->chunk = 0;
        $this->progress_handlers = $progress_handlers;        
    }
    
    /**
     * Get progress handlers
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverProgressHandlers
     */
    public function getProgressHandlers()
    {
        return $this->progress_handlers;
    }
    
    /**
     * Init hooks
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itAddsInitHooks()
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itChecksIfHooksAreOutdated()
     */
    public function initHooks() 
    {
        add_filter( 'prime_mover_ajax_rendered_js_object', [$this, 'setRetryLimit'], 10, 1 );
        add_filter( 'prime_mover_ajax_rendered_js_object', [$this, 'setUploadErrorAnalysis'], 15, 1 );
        
        add_filter( 'prime_mover_ajax_rendered_js_object', [$this, 'setRestoreAsDone'], 20, 1 );
        add_filter( 'prime_mover_ajax_rendered_js_object', [$this, 'setRestoreImageAsDone'], 25, 1 );
        
        add_filter( 'prime_mover_ajax_rendered_js_object', [$this, 'setUploadError'], 30, 1 );
        add_filter( 'prime_mover_ajax_rendered_js_object', [$this, 'setUploadRefreshInterval'], 40, 1 );
        add_filter( 'prime_mover_ajax_rendered_js_object', [$this, 'setMinutesText'], 50, 1 );
        
        add_filter( 'prime_mover_ajax_rendered_js_object', [$this, 'setIfLocal'], 60, 1 );
        add_filter( 'prime_mover_ajax_rendered_js_object', [$this, 'setBrowserUploadSizeLimit'], 70, 1 );
    }
    
    /**
     * Set browser file upload size limit
     * @param array $args
     * @return array
     */
    public function setBrowserUploadSizeLimit(array $args )
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $args;
        }
        $args['prime_mover_browser_upload_limit'] = $this->getSystemInitialization()->getBrowserFileUploadSizeLimit();
        return $args;
    }
 
    /**
     * Set if local
     * @param array $args
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverUploadUtilities::itSetsIfLocal()
     */
    public function setIfLocal(array $args )
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $args;
        }
        $args['is_local_server'] = $this->getSystemInitialization()->isLocalServer();       
        return $args;
    }
    
    /**
     * 
     * @param array $args
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itSetsMinutesText()
     */
    public function setMinutesText(array $args )
    {
        $args['prime_mover_minutes_text'] = esc_html__('minutes', 'prime-mover');
        $args['prime_mover_minute_text'] = esc_html__('minute', 'prime-mover');
        return $args;
    }
    
    /**
     * Setup an upload refresh interval
     * @param array $args
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itSetsUploadRefreshInterval() 
     */
    public function setUploadRefreshInterval(array $args ) 
    {
        $upload_refresh_interval = $this->getSystemInitialization()->getDefaultUploadRefreshInterval();
        $args['prime_mover_upload_refresh_interval'] = apply_filters('prime_mover_filter_upload_refresh_interval', 
            $upload_refresh_interval);
        return $args;
    }
    
    /**
     * Setup an upload error
     * @param array $args
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itSetsUploadError()
     */
    public function setUploadError(array $args )
    {
        $args['prime_mover_upload_error_message'] = esc_js(
            sprintf(__('Upload fails for blog ID {{BLOGID}}. Retry is attempted but still fails. %s',
                'prime-mover'), 
                '<strong>' . esc_html__('Server Error : {{UPLOADSERVERERROR}}', 'prime-mover') . '</strong>'));
        return $args;
    }
    
    /**
     * Set restore image as done
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itSetsRestoreImageAsDone()
     */
    public function setRestoreImageAsDone(array $args ) 
    {
        $args['prime_mover_complete_import_png'] = '<img src="' . esc_url_raw(plugins_url('res/img/done.png', dirname(__FILE__))) . '" />';
        return $args;
    }
    
    /**
     * Set restore as done
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itSetsRestoreAsDone()
     */
    public function setRestoreAsDone(array $args ) 
    {
        $args['prime_mover_restore_done'] = esc_js(__('Import done.', 'prime-mover'));
        return $args;
    }
    
    /**
     * Set upload error analysis
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itSetsUploadErrorAnalysis()
     */
    public function setUploadErrorAnalysis(array $args ) 
    {
        $args['prime_mover_upload_error_analysis'] = apply_filters('prime_mover_enable_js_error_analysis', PRIME_MOVER_JS_ERROR_ANALYSIS);
        return $args;
    }
    
    /**
     * Set Retry limit
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itSetsRetryLimit()
     */
    public function setRetryLimit(array $args ) 
    {        
        $retry_limit = (int) PRIME_MOVER_UPLOADRETRY_LIMIT;
        $args['prime_mover_retry_limit'] = apply_filters('prime_mover_filter_retry_limit', $retry_limit);
        return $args;
    }
    
    /**
     * Get system authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     */
    public function getSystemAuthorization()
    {
        return $this->getSystemChecks()->getSystemAuthorization();
    }
    
    /**
     * Get system initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     */
    public function getSystemInitialization()
    {
        return $this->getSystemChecks()->getSystemInitialization();
    }
    
    /**
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     */
    public function getSystemFunctions()
    {
        return $this->getSystemChecks()->getSystemFunctions();
    }
    
    /**
     *
     * Get system checks
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemChecks
     * @compatible 5.6
     */
    public function getSystemChecks()
    {
        return $this->system_checks;
    }
    
    /**
     *
     * Get uploaded chunk
     * @return number
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itIdentifiesChunk()
     */
    public function getChunk()
    {
        return $this->chunk;
    }
    
    /**
     *
     * Set uploaded chunk
     * @param int $chunk
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itProcessUploadsWhenAllSet()
     */
    public function setChunk($chunk = 0)
    {
        $this->chunk = $chunk;
    }
    
    /**
     *
     * Hooks to `wp_unique_filename` WP filter to append chunk name.
     * @param string $filename
     * @return string
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itIdentifiesChunk()
     */
    public function identifyChunk($filename = '')
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $filename;
        }
        
        if ( ! $filename) {
            return $filename;
        }
        
        if ( ! $this->getSystemInitialization()->isUploadingChunk()) {
            return $filename;
        }
        $current_chunk = $this->getChunk();
        if (! $current_chunk) {
            return $filename;
        }
        
        return $this->getChunkFilename($current_chunk, $filename);
    }
    
    /**
     *
     * Get chunk filename
     * @param int $current_chunk
     * @param string $filename
     * @return string
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itIdentifiesChunk()
     */
    public function getChunkFilename($current_chunk = 0, $filename = '')
    {
        return $filename . '-'. $current_chunk . '.part';
    }
    
    /**
     *
     * Verify chunk upload
     * @param array $upload_params
     * @param string $method
     * @return array
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itVerifiesChunkUpload() 
     */
    public function verifyChunkUpload(array $upload_params, $method = '')
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $upload_params['error'] = esc_js(__('Unauthorized upload.', 'prime-mover'));
            return $upload_params;
        }
        
        $upload_params['error'] = esc_js(__('Chunk upload error.', 'prime-mover'));
        if ('upload' !== $method) {
            return $upload_params;
        }
        
        if (empty($upload_params['file'])) {
            return $upload_params;
        }
        
        if ( ! $this->getSystemInitialization()->isUploadingChunk()) {
            return $upload_params;
        }
        
        $ext = pathinfo($upload_params['file'], PATHINFO_EXTENSION);
        if (! $ext) {
            return $upload_params;
        }
        if ('part' === $ext) {
            unset($upload_params['error']);
        }
        
        return $upload_params;
    }
    
    /**
     * 
     * @param string $tmp_name
     * @return boolean
     */
    protected function isUploadedFile($tmp_name = '')
    {
        return is_uploaded_file($tmp_name);
    }
    
    /**
     * Validate chunk upload call
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itProcessUploadsWhenAllSet() 
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itDoesNotProcessUploadsWhenNonceFailed()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itDoesNotProcessUploadsIfNotReallyUploaded()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itDoesNotProcessUploadsIfNotCorrectMimeType()
     * @param array $uploads_ajax_input
     * @return boolean|array
     */
    public function validateChunkUploadCall(array $uploads_ajax_input, $blog_id = 0)
    {
        if ( ! $blog_id ) {
            return false;
        }
        $files = $_FILES;
        if (! $this->getSystemAuthorization()->isUserAuthorized() || ! isset($uploads_ajax_input['chunk'])) {
            do_action('prime_mover_log_processed_events', "Unauthorized chunk processing.", $blog_id, 'import', 'validateChunkUploadCall', $this); 
            return false;
        }        
        if (empty($uploads_ajax_input)) { 
            do_action('prime_mover_log_processed_events', "Empty uploads ajax input.", $blog_id, 'import', 'validateChunkUploadCall', $this); 
            return false;
        }
        $validated_chunk = $uploads_ajax_input['chunk'];  
        if ( ! isset($uploads_ajax_input['prime_mover_uploads_nonce']) ) {
            do_action('prime_mover_log_processed_events', "Nonce not set", $blog_id, 'import', 'validateChunkUploadCall', $this); 
            return false;
        }          
        if (!$this->getSystemFunctions()->primeMoverVerifyNonce($uploads_ajax_input['prime_mover_uploads_nonce'], 'prime_mover_uploads_nonce')) {
            do_action('prime_mover_log_processed_events', "Nonce validation failed", $blog_id, 'import', 'validateChunkUploadCall', $this);
            return false;
        }               
        if (false === $this->getSystemChecks()->primeMoverEssentialRequisites()) {
            do_action('prime_mover_log_processed_events', "Essential requisites not set", $blog_id, 'import', 'validateChunkUploadCall', $this);
            return false;
        }                      
        if ( empty($files['file']['tmp_name'] ) || empty($files['file']['name'] ) ) {
            do_action('prime_mover_log_processed_events', "Files tmp_name not set", $blog_id, 'import', 'validateChunkUploadCall', $this);
            return false;
        }            
        if ( ! $this->isUploadedFile($files['file']['tmp_name']) ) { 
            do_action('prime_mover_log_processed_events', "This file is really not uploaded.", $blog_id, 'import', 'validateChunkUploadCall', $this);
            return false;
        }       
        if (! isset($uploads_ajax_input['start']) || empty($uploads_ajax_input['end']) ||
            empty($uploads_ajax_input['chunk']) || empty($uploads_ajax_input['chunks'])) {
            do_action('prime_mover_log_processed_events', "Incomplete chunk parameter error found on chunk $validated_chunk", $blog_id, 'import', 'validateChunkUploadCall', $this);  
            return false;
        }  
        $valid_extension = false;
        if ($this->getSystemFunctions()->hasZipExtension($files['file']['name']) || $this->getSystemFunctions()->hasTarExtension($files['file']['name'])) {
            $valid_extension = true;
        }
        if ( ! $valid_extension) {
            do_action('prime_mover_log_processed_events', "This file does not have zip or wprime file extension: $validated_chunk", $blog_id, 'import', 'validateChunkUploadCall', $this); 
            return false;
        }
        do_action('prime_mover_log_processed_events', "Successfully validated uploaded chunk $validated_chunk", $blog_id, 'import', 'validateChunkUploadCall', $this);
        return $files;  
    }
    
    /**
     * Checks if package is now under assembly status
     * @param number $blog_id
     * @return mixed
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itChecksIfPackageIsNowUnderAssembly()
     */
    public function maybePackageIsUnderAssemblyNow($blog_id = 0)
    {
        $process_id = $this->getProgressHandlers()->generateTrackerId($blog_id, 'import');
        $assembly_option = '_assembly_' . $process_id;
        
        return $this->getSystemFunctions()->getSiteOption($assembly_option, false, true, true);
    }
  
    /**
     * Checks if package is now assembled
     * @param number $blog_id
     * @return mixed
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itChecksMaybePackageIsAssembled() 
     */
    public function maybePackageIsNowAssembled($blog_id = 0)
    {
        $process_id = $this->getProgressHandlers()->generateTrackerId($blog_id, 'import');
        $assembled_option = '_assembled_' . $process_id;
        
        return $this->getSystemFunctions()->getSiteOption($assembled_option, false, true, true);
    }
    
    /**
     * Get the actual upload progress count
     * @param number $blog_id
     * @param number $chunk
     * @return number
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itGetsActualUploadProgress()
     */
    public function getActualUploadProgress($blog_id = 0, $chunk = 0)
    {
        if ( ! $blog_id &&  ! $chunk ) {
            return $chunk;
        }
        
        $progress = [];
        $chunk = (int)$chunk;
        
        $process_id = $this->getProgressHandlers()->generateTrackerId($blog_id, 'import');
        $uploadtracker_option = '_upload_progress_' . $process_id;        
        
        $last_progress = $this->getSystemFunctions()->getSiteOption($uploadtracker_option, false, true, true);
        if (is_array($last_progress)) {            
            $progress = $last_progress;
        }         
        
        if ( ! in_array($chunk, $progress, true) ) {
            $progress[] = $chunk;
        }        
        
        $progress_counted = count($progress);
        $last_progress = $this->getSystemFunctions()->updateSiteOption($uploadtracker_option, $progress, true); 

        return $progress_counted;        
    }
    
    /**
     * Move uploaded chunk to uploads directory
     * @param array $uploadedfile
     * @param array $upload_overrides
     * @param string $fileName
     * @return string
     * @compatible 5.6
     * @tested PrimeMoverFramework\classes\PrimeMoverSystemProcessors::primeMoverTempfileDeleteProcessor()
     */
    public function moveUploadedChunkToUploads(array $uploadedfile, array $upload_overrides, $fileName = '')
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret = [];
            $ret['error'] = esc_js(__('Unauthorized upload.', 'prime-mover'));
            return $ret;
        }
        $this->getSystemInitialization()->setUploaderIdentity(true);
        add_filter('upload_dir', [ $this, 'setSpecificUploadPathForChunk' ], PHP_INT_MAX, 1);
        
        $uploads = $this->getSystemInitialization()->getInitializedWpRootUploads();
        $target_file_path = $uploads['path'] . DIRECTORY_SEPARATOR . $fileName;
        global $wp_filesystem;
        
        $import_blog_id = $this->getSystemInitialization()->getImportBlogID();
        do_action('prime_mover_log_processed_events', "Moving chunk to uploads:", $import_blog_id, 'import', 'moveUploadedChunkToUploads', $this);
        do_action('prime_mover_log_processed_events', $uploadedfile, $import_blog_id, 'import', 'moveUploadedChunkToUploads', $this);
        
        $ongoing_assembly = $this->maybePackageIsUnderAssemblyNow($import_blog_id);        
        
        if ($wp_filesystem->exists($target_file_path) && ! $ongoing_assembly) {
            //we don't need the main file generated at this point. If it exists delete it.
            do_action('prime_mover_log_processed_events', "Main target path EXIST, deleting $target_file_path:", $import_blog_id, 'import', 'moveUploadedChunkToUploads', $this);
            $this->getSystemFunctions()->primeMoverDoDelete($target_file_path);
        }
        
        add_filter('wp_unique_filename', [ $this, 'identifyChunk' ], PHP_INT_MAX, 1);
        add_filter('wp_handle_upload', [ $this, 'verifyChunkUpload' ], PHP_INT_MAX, 2);
        add_filter("pre_transient_dirsize_cache", [$this, 'skipSlowTransient' ], PHP_INT_MAX, 2);
        
        $ret = wp_handle_upload($uploadedfile, $upload_overrides);
        
        remove_filter('wp_unique_filename', [ $this, 'identifyChunk' ], PHP_INT_MAX, 1);
        remove_filter('wp_handle_upload', [ $this, 'verifyChunkUpload' ], PHP_INT_MAX, 2);
        
        remove_filter('upload_dir', [ $this, 'setSpecificUploadPathForChunk' ], PHP_INT_MAX, 1);
        remove_filter("pre_transient_dirsize_cache", [$this, 'skipSlowTransient' ], PHP_INT_MAX, 2);
        
        $this->getSystemInitialization()->setUploaderIdentity(false);        
        
        return $ret;
    }
    
    /**
     * Skip slow transients that is creating performance issues when uploading
     * @param boolean $skip
     * @param string $transient
     * @return string|boolean
     */
    public function skipSlowTransient($skip = false, $transient = '')
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized() || !$transient) {
            return $skip;
        }
        
        if ('dirsize_cache' !== $transient) {
            return $skip;
        }
        
        return null;        
    }
    
    
    /**
     * Set specific upload path for chunk
     * @param array $uploads
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itSetsSpecificUploadPathForChunk()
     */
    public function setSpecificUploadPathForChunk($uploads = [])
    {
        if ( ! $this->getSystemInitialization()->isUploadingChunk() || empty($uploads['path']) || empty($uploads['basedir']) ) {
            return $uploads;
        }
        
        $basedir = $uploads['basedir'];
        $upload_path_slug = $this->getSystemInitialization()->getUploadTmpPathSlug();
        
        $new_uploads_path = $basedir . DIRECTORY_SEPARATOR . $upload_path_slug;
        $uploads['path'] = $new_uploads_path;
        
        return $uploads;
    }
    
    /**
     *
     * Retrieved uploads ajax input
     * @return mixed
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itProcessUploadsWhenAllSet()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itDoesNotProcessUploadsWhenNonceFailed() 
     */
    public function getUploadsAjaxInput()
    {
        $args		= [
            'prime_mover_uploads_nonce' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'multisite_blogid_to_import' => FILTER_SANITIZE_NUMBER_INT,
            'start' => FILTER_SANITIZE_NUMBER_INT,
            'end' => FILTER_SANITIZE_NUMBER_INT,
            'chunk' => FILTER_SANITIZE_NUMBER_INT,
            'chunks' => FILTER_SANITIZE_NUMBER_INT,
            'missing_chunk_to_fix' => FILTER_SANITIZE_NUMBER_INT,
            'resume_parts_index' => FILTER_SANITIZE_NUMBER_INT,
            'resume_filepath' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'resume_chunks' => FILTER_SANITIZE_NUMBER_INT
        ];

        return $this->getSystemInitialization()->getUserInput('post', $args, 'chunk_upload_ajax', 'import');
    }
    
    /**
     * Checks if we are fixing missing chunk
     * @param array $uploads_ajax_input
     * @return boolean
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itProcessUploadsWhenAllSet()
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itChecksIfFixingMissingChunk()
     */
    public function isFixingMissingChunk(array $uploads_ajax_input)
    {
        $ret = false;
        
        if (empty($uploads_ajax_input) || ! isset($uploads_ajax_input['missing_chunk_to_fix'])) {
            return $ret;
        }
        
        $missing_chunk_to_fix = (int) $uploads_ajax_input['missing_chunk_to_fix'];
        if ($missing_chunk_to_fix) {
            $ret = true;
        }
        
        return $ret;
    }
    
    /**
     * Maybe reassemble import package chunks after uploading
     * @param number $chunks
     * @param number $chunk
     * @param string $filePath
     * @param boolean $isFixingMissingChunk
     * @param number $start_time
     * @param boolean $retry_parts_merging
     * @param number $resume_parts_index
     * @param string $resume_filepath
     * @param number $resume_chunks
     * @return void|number|boolean|string[]|number[]
     */
    public function maybeReassembleImportPackageChunks($chunks = 0, $chunk = 0, $filePath = '', $isFixingMissingChunk = false, 
        $start_time = 0, $retry_parts_merging = false, $resume_parts_index = 0, $resume_filepath = '', $resume_chunks = 0)
    {
        $done = false;
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }        
        $import_blog_id = $this->getSystemInitialization()->getImportBlogID();
        if ($chunk === $chunks || $isFixingMissingChunk || $retry_parts_merging) {
            
            if ( ! $retry_parts_merging ) {
                $this->getSystemInitialization()->setProcessingDelay(1);
                if ( ! $this->maybePackageIsUnderAssemblyNow($import_blog_id) ) {
                    for ($i = 1; $i <= $chunks; $i++) {
                        $chunk_to_check = $filePath . '-' . $i . '.part';
                        if (! file_exists($chunk_to_check) && ! file_exists($filePath)) {
                            $i = (int) $i;
                            return $i;
                        }
                    }
                }
                if (file_exists($filePath)) {
                    return true;
                }
                
                do_action('prime_mover_log_processed_events', "Chunks all detected, READY TO ASSEMBLE", $import_blog_id, 'import', 'maybeReassembleImportPackageChunks', $this);                
            }  
            
            $process_id = $this->getProgressHandlers()->generateTrackerId($import_blog_id, 'import');
            $this->markPackageAsUnderAssembly($process_id);
            
            $index = 0;
            if ($resume_filepath && $retry_parts_merging && $resume_parts_index && $resume_chunks) {
                $filePath = $resume_filepath;
                $index = $resume_parts_index;
                $chunks = $resume_chunks;
            }
            if ( $retry_parts_merging && $this->getSystemFunctions()->nonCachedFileExists($filePath)) {
                $out = @fopen($filePath, 'ab');
            }
            
            if ( $retry_parts_merging && ! $this->getSystemFunctions()->nonCachedFileExists($filePath)) {
                $out = @fopen($filePath, 'wb');
            }
            if ( ! $retry_parts_merging ) {
                $out = @fopen($filePath, 'wb');
            }
            
            for ($i = $index; $i <= $chunks; $i++) {    
                $this->maybeTestSlowZipMerging();
                $retry_timeout = apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, 'maybeReassembleImportPackageChunks');
                if (microtime(true) - $start_time > $retry_timeout && $i) {
                    @fclose($out);
                    $retry_parameters = ['resume_parts_index' => $i, 'resume_filepath' => $filePath, 'resume_chunks' => $chunks];
                    
                    do_action('prime_mover_log_processed_events', "Merging timeout, need to retry with the following parameters: ", $import_blog_id, 'import', 'maybeReassembleImportPackageChunks', $this);
                    do_action('prime_mover_log_processed_events', $retry_parameters, $import_blog_id, 'import', 'maybeReassembleImportPackageChunks', $this);
                    
                    return $retry_parameters;
                } 
                $in = @fopen($filePath . '-' . $i . '.part', 'rb');
                if ($in) {
                    stream_copy_to_stream($in, $out);
                    @fclose($in);
                    
                    do_action('prime_mover_log_processed_events', "Asssembled chunk $i back to the main zip", $import_blog_id, 'import', 'maybeReassembleImportPackageChunks', $this);
                    unlink($filePath . '-' . $i . '.part');
                }
            }
            
            $done = true;
            $this->markPackageAsAssembled($process_id);
            $this->getProgressHandlers()->primeMoverDeleteAssemblyOption($process_id);
            @fclose($out);
        }
        
        return $done;
    }
    
    /**
     * Maybe test slow merging of zip parts
     */
    protected function maybeTestSlowZipMerging()
    {
        if (defined('PRIME_MOVER_TEST_SLOW_ZIP_MERGING') && PRIME_MOVER_TEST_SLOW_ZIP_MERGING) {
            $delay = (int) PRIME_MOVER_TEST_SLOW_ZIP_MERGING;
            $this->getSystemInitialization()->setProcessingDelay($delay);
        }
    }
    
    /**
     * Checks if retrying merging zip
     * @param array $input
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itChecksRetryingUploadMerging()
     */
    public function isRetryingZipMerging($input = [])
    {
        return ( ! empty($input['resume_parts_index']) && ! empty($input['resume_filepath']) && ! empty($input['resume_chunks']));
    }
    
    /**
     * Mark package as under assembly
     * @param string $process_id
     */
    protected function markPackageAsUnderAssembly($process_id = '')
    {        
        $assembly_option = '_assembly_' . $process_id;
        $this->getSystemFunctions()->updateSiteOption($assembly_option, 'yes', true); 
    }
    
    /**
     * Mark package as assembled
     * @param string $process_id
     */
    protected function markPackageAsAssembled($process_id = '')
    {
        $assembled_option = '_assembled_' . $process_id;
        $this->getSystemFunctions()->updateSiteOption($assembled_option, 'yes', true); 
    }
    
    /**
     * Checks if zip type by MIME check
     * @param string $filePath
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itChecksIfZipByMime() 
     */
    public function isZipByMime($filePath = '')
    {        
        return $this->getSystemFunctions()->isZipByMime($filePath);
    }
    
    /**
     * Do low-level checks if the file is really zip.
     * Check if the uploaded file is really zip (no WP filters here to be sure)
     * @param string $filePath
     * @return boolean
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverUploadUtilities::itChecksIfReallyZip() 
     */
    public function isReallyZip($filePath = '')
    {
        return $this->getSystemFunctions()->isReallyZip($filePath);
    }
}
