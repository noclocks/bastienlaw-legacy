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
use Codexonics\PrimeMoverFramework\interfaces\PrimeMoverSystemProcessor;
use Codexonics\PrimeMoverFramework\utilities\PrimeMoverExportUtilities;
use Codexonics\PrimeMoverFramework\utilities\PrimeMoverUploadUtilities;
if (! defined('ABSPATH')) {
    exit();
}

/**
 * Prime Mover System Processors Class
 *
 * The Prime Mover System Processor Class aims to provide the AJAX processor facility for exporting,importing and deleting temporary files.
 */
class PrimeMoverSystemProcessors implements PrimeMoverSystemProcessor
{
    private $importer;
    private $upload_utilities;
    private $export_utilities;

    /**
     * Constructor
     *
     * @param PrimeMoverImporter $importer
     * @param PrimeMoverUploadUtilities $upload_utilities
     * @param PrimeMoverExportUtilities $export_utilities
     */
    public function __construct(PrimeMoverImporter $importer, PrimeMoverUploadUtilities $upload_utilities, PrimeMoverExportUtilities $export_utilities)
    {
        $this->importer = $importer;
        $this->upload_utilities = $upload_utilities;
        $this->export_utilities = $export_utilities;
    }

    /**
     * Get progress handlers
     *
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverProgressHandlers
     * @compatible 5.6
     */
    public function getProgressHandlers()
    {
        return $this->getImporter()->getProgressHandlers();
    }

    /**
     * Get export utilities
     *
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverExportUtilities
     * @compatible 5.6
     */
    public function getExportUtilities()
    {
        return $this->export_utilities;
    }

    /**
     *
     * Get upload utilities
     *
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverUploadUtilities
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itProcessUploadsWhenAllSet()
     */
    public function getUploadUtilities()
    {
        return $this->upload_utilities;
    }

    /**
     *
     * Get Importer
     *
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverImporter
     * @compatible 5.6
     */
    public function getImporter()
    {
        return $this->importer;
    }

    /**
     *
     * Get system functions
     *
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     * @compatible 5.6
     */
    public function getSystemFunctions()
    {
        return $this->getUploadUtilities()->getSystemFunctions();
    }

    /**
     *
     * Get System Initialization
     *
     * @compatible 5.6
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     */
    public function getSystemInitialization()
    {
        return $this->getImporter()->getSystemInitialization();
    }

    /**
     * Get System checks
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemChecks
     */
    public function getSystemChecks()
    {
        return $this->getUploadUtilities()->getSystemChecks();
    }

    /**
     *
     * Get System authorization
     *
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     * @compatible 5.6
     */
    public function getSystemAuthorization()
    {
        return $this->getExportUtilities()->getSystemAuthorization();
    }

    /**
     *
     * Verify encrypted package as requested from client side
     *
     * @compatible 5.6
     */
    public function primeMoverVerifyEncryptedPackage()
    {
        $response = [];
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $response['result'] = false;
            $response['error'] = __('Unauthorized request', 'prime-mover');
            return wp_send_json($response);
        }

        $args = [
            'prime_mover_decryption_check_nonce' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'stringtocheck' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'encrypted_media' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'blog_id' => FILTER_SANITIZE_NUMBER_INT,
            'package_ext' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter()
        ];

        $verify_result = $this->getSystemInitialization()->getUserInput('post', $args, 'verify_encrypted_package', '', 0, true, true);
        if (empty($verify_result['prime_mover_decryption_check_nonce'])) {
            $response['result'] = false;
            $response['error'] = __('Incorrect nonce', 'prime-mover');
            return wp_send_json($response);
        }

        if (! $this->getSystemFunctions()->primeMoverVerifyNonce($verify_result['prime_mover_decryption_check_nonce'], 'prime_mover_decryption_check_nonce')) {
            $response['result'] = false;
            $response['error'] = __('Incorrect nonce', 'prime-mover');
            return wp_send_json($response);
        }

        if (empty($verify_result['stringtocheck']) && ! empty($verify_result['validation_errors'])) {
            $response['result'] = false;
            $response['error'] = $verify_result['validation_errors']['stringtocheck'];
            return wp_send_json($response);
        }

        if ('zip' === $verify_result) {
            $phpinfoarray = $this->getProgressHandlers()
            ->getShutDownUtilities()
            ->phpinfo2array();
            $can_decrypt_media = apply_filters('prime_mover_can_decrypt_media', true, $verify_result, $phpinfoarray);
            if (false === $can_decrypt_media) {
                $response['result'] = false;
                $response['error'] = $this->getSystemInitialization()->returnCommonMediaDecryptionError();
                return wp_send_json($response);
            }
        }  
        
        $response['result'] = true;
        return wp_send_json($response);
    }

    /**
     *
     * Delete temporary file when user cancels the import
     *
     * @compatible 5.6
     */
    public function primeMoverTempfileDeleteProcessor()
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $this->getSystemFunctions()->setTimeLimit();
        // Initialize response
        $response = [];
        $response['tempfile_deletion_status'] = false;

        // Initialize args
        $args = [
            'prime_mover_deletetmpfile_nonce' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'temp_file_to_delete' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'action' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'diff_reject' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'blog_id' => FILTER_SANITIZE_NUMBER_INT,
            'mode' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'tmp_file_mode' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter()
        ];

        // Validate
        $posted_blog_id = $this->getSystemInitialization()->getUserInput('post', [
            'blog_id' => FILTER_SANITIZE_NUMBER_INT
        ], 'delete_tmp_dir', '', 0, true);
        $posted_mode = $this->getSystemInitialization()->getUserInput('post', [
            'mode' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter()
        ], 'delete_tmp_dir', '', 0, true);
        $tmp_file_mode = $this->getSystemInitialization()->getUserInput('post', [
            'tmp_file_mode' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter()
        ], 'delete_tmp_dir', '', 0, true);

        $blog_id = 0;
        if (! empty($posted_blog_id['blog_id'])) {
            $blog_id = $posted_blog_id['blog_id'];
        }
        $mode = 'import';
        if (! empty($posted_mode['mode'])) {
            $mode = $posted_mode['mode'];
        }
        $tmp_file = '';
        if (! empty($tmp_file_mode['tmp_file_mode'])) {
            $tmp_file = $tmp_file_mode['tmp_file_mode'];
        }
        if ('yes' === $tmp_file) {
            $delete_tmpfile_post = $this->getSystemInitialization()->getUserInput('post', $args, 'delete_tmp_file', $mode, $blog_id, true);
        } else {
            $delete_tmpfile_post = $this->getSystemInitialization()->getUserInput('post', $args, 'delete_tmp_dir', $mode, $blog_id, true);
        }

        if (isset($delete_tmpfile_post['prime_mover_deletetmpfile_nonce']) && isset($delete_tmpfile_post['temp_file_to_delete']) && $this->getSystemFunctions()->primeMoverVerifyNonce($delete_tmpfile_post['prime_mover_deletetmpfile_nonce'], 'prime_mover_deletetmpfile_nonce')) {
            // Get temp directory to delete
            global $wp_filesystem;
            $temp_dir = $delete_tmpfile_post['temp_file_to_delete'];
            if ($temp_dir && $wp_filesystem->exists($temp_dir)) {
                $this->getSystemInitialization()->setProcessingDelay(3);
                $delete_result = $this->getSystemFunctions()->primeMoverDoDelete($temp_dir);
                $note = 'NOT deleted';
                if ($delete_result) {
                    $note = "DELETED";
                }
                do_action('prime_mover_log_processed_events', "Temporary package requested to be deleted: $temp_dir", $blog_id, 'import', 'primeMoverTempfileDeleteProcessor', $this);

                do_action('prime_mover_log_processed_events', "Delete result is: $note", $blog_id, 'import', 'primeMoverTempfileDeleteProcessor', $this);
                $response['tempfile_deletion_status'] = true;
            }
        }
        // Other actions
        do_action('prime_mover_do_things_tmp_deleted', $delete_tmpfile_post);

        // Update client of the status
        wp_send_json($response);
    }

    /**
     *
     * Export progress shutdown
     *
     * @compatible 5.6
     */
    public function primeMoverShutdownExportProcessor()
    {
        $this->getProgressHandlers()->commonShutDownProcessor('export');
    }

    /**
     *
     * Import progress shutdown
     *
     * @compatible 5.6
     */
    public function primeMoverShutdownImportProcessor()
    {
        $this->getProgressHandlers()->commonShutDownProcessor('import');
    }

    /**
     *
     * Import progress process
     *
     * @compatible 5.6
     */
    public function primeMoverImportProgressProcessor()
    {
        $this->getProgressHandlers()->commonProgressProcessor('import');
    }

    /**
     *
     * Export progress process
     *
     * @compatible 5.6
     */
    public function primeMoverExportProgressProcessor()
    {
        $this->getProgressHandlers()->commonProgressProcessor('export');
    }

    /**
     * AJAX Processor for "chunk" uploading
     *
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itProcessUploadsWhenAllSet()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itDoesNotProcessUploadsIfNotAuthorized()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itDoesNotProcessUploadsWhenNonceFailed()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itDoesNotProcessUploadsIfNotReallyUploaded()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itDoesNotProcessUploadsIfNotCorrectMimeType()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itProcessMissingChunks()
     */
    public function primeMoverUploadsProcessor()
    {
        $start_time = $this->getSystemInitialization()->getStartTime();
        $all_clear = false;
        $response = [];

        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $response['status'] = false;
            $response['error'] = esc_js(__('Unauthorized request.', 'prime-mover'));

            return wp_send_json($response);
        }

        $uploads_ajax_input = $this->getUploadUtilities()->getUploadsAjaxInput();
        if (! empty($uploads_ajax_input['multisite_blogid_to_import'])) {
            $this->getSystemInitialization()->setImportBlogID($uploads_ajax_input['multisite_blogid_to_import']);
        }
        if ($this->getUploadUtilities()->isRetryingZipMerging($uploads_ajax_input)) {
            do_action('prime_mover_log_processed_events', "A zip merging retry request is validated, proceed directly to merging process..", $uploads_ajax_input['multisite_blogid_to_import'], 'import', 'primeMoverUploadsProcessor', $this);
            $resume_parts_index = (int)$uploads_ajax_input['resume_parts_index'];
            $resume_filepath = $uploads_ajax_input['resume_filepath'];
            $resume_chunks = $uploads_ajax_input['resume_chunks'];
            $this->doUploadFinishingTasks(0, 0, '', false, $start_time, $uploads_ajax_input, true, $resume_parts_index, $resume_filepath, $resume_chunks);
            
        } else {
            $all_clear = $this->getUploadUtilities()->validateChunkUploadCall($uploads_ajax_input, $uploads_ajax_input['multisite_blogid_to_import']);
            $assembled = $this->getUploadUtilities()->maybePackageIsNowAssembled($uploads_ajax_input['multisite_blogid_to_import']);
            
            if ($assembled) {
                $response['assembled'] = true;
                do_action('prime_mover_log_processed_events', "Package already assembled, no more work needed", $uploads_ajax_input['multisite_blogid_to_import'], 'import', 'primeMoverUploadsProcessor', $this);
                return wp_send_json($response);
            }
            
            $isFixingMissingChunk = $this->getUploadUtilities()->isFixingMissingChunk($uploads_ajax_input);
            
            if (false === $all_clear) {
                do_action('prime_mover_log_processed_events', "Upload chunk validation failed.", $uploads_ajax_input['multisite_blogid_to_import'], 'import', 'primeMoverUploadsProcessor', $this);
                return wp_send_json($response);
            }
            
            $files_array = $all_clear;
            
            if (! isset($files_array['file'])) {
                $response['status'] = false;
                $response['error'] = esc_html__('No file uploaded', 'prime-mover');
                
                return wp_send_json($response);
            }
            
            $uploadedfile = $files_array['file'];
            $upload_overrides = [
                'test_form' => false,
                'test_type' => false
            ];
            $fileName = sanitize_file_name($files_array["file"]["name"]);            
            
            $chunk = (int) $uploads_ajax_input['chunk'];
            $chunks = (int) $uploads_ajax_input['chunks'];
            
            do_action('prime_mover_log_processed_events', "Server side processing chunk: $chunk of $chunks", $uploads_ajax_input['multisite_blogid_to_import'], 'import', 'primeMoverUploadsProcessor', $this);
            $this->getUploadUtilities()->setChunk($chunk);
            
            $ret = $this->getUploadUtilities()->moveUploadedChunkToUploads($uploadedfile, $upload_overrides, $fileName);
            
            global $wp_filesystem;
            if (! isset($ret['file']) || ! $wp_filesystem->exists($ret['file'])) {
                return wp_send_json($response);
            }
            
            if (isset($ret['error'])) {                
                $response['status'] = false;
                $response['error'] = esc_html__('Chunk upload error.', 'prime-mover');
                $this->getSystemFunctions()->primeMoverDoDelete($ret['file']);
                return wp_send_json($response);
            }
            
            $filePath = dirname($ret['file']) . DIRECTORY_SEPARATOR . $fileName;
            $this->doUploadFinishingTasks($chunks, $chunk, $filePath, $isFixingMissingChunk, $start_time, $uploads_ajax_input);            
        }               
    }
    
    /**
     * Do Upload finishing tasks
     * @param number $chunks
     * @param number $chunk
     * @param string $filePath
     * @param boolean $isFixingMissingChunk
     * @param number $start_time
     * @param array $uploads_ajax_input
     * @param boolean $retry_parts_merging
     * @param number $resume_parts_index
     * @param string $resume_filepath
     * @param number $resume_chunks
     */
    protected function doUploadFinishingTasks($chunks = 0, $chunk = 0, $filePath = '', $isFixingMissingChunk = false, $start_time = 0, 
        $uploads_ajax_input = [], $retry_parts_merging = false, $resume_parts_index = 0, $resume_filepath = '', $resume_chunks = 0)
    {
        $done = $this->getUploadUtilities()->maybeReassembleImportPackageChunks($chunks, $chunk, $filePath, $isFixingMissingChunk, 
            $start_time, $retry_parts_merging, $resume_parts_index, $resume_filepath, $resume_chunks);
        
        if (! is_bool($done) && ! is_array($done) && $done > 0) {
            do_action('prime_mover_log_processed_events', "Reporting missing chunk back to client browser: $done", $uploads_ajax_input['multisite_blogid_to_import'], 'import', 'doUploadFinishingTasks', $this);
            $response['missing_chunk'] = $done;
            return wp_send_json($response);
        }
        
        if (is_array($done) && $this->getUploadUtilities()->isRetryingZipMerging($done) ) {
            do_action('prime_mover_log_processed_events', "Sending back to client browser, the need to retry zip merging with parameters: ", $uploads_ajax_input['multisite_blogid_to_import'], 'import', 'doUploadFinishingTasks', $this);
            do_action('prime_mover_log_processed_events', $done, $uploads_ajax_input['multisite_blogid_to_import'], 'import', 'doUploadFinishingTasks', $this);
            
            $response['resume_parts_index'] = $done['resume_parts_index'];
            $response['resume_filepath'] = $done['resume_filepath'];
            $response['resume_chunks'] = $done['resume_chunks'];
            
            return wp_send_json($response);
        }
        
        while ($this->getUploadUtilities()->maybePackageIsUnderAssemblyNow($uploads_ajax_input['multisite_blogid_to_import'])) {
            $this->getSystemInitialization()->setProcessingDelay(1);
        }
        if ($resume_filepath) {
            $filePath = $resume_filepath;
        }
        if (true === $done && ! $this->getSystemFunctions()->isReallyValidFormat($filePath)) {
            $response['status'] = false;
            $response['error'] = sprintf(esc_html__('Corrupt package error: %s', 'prime-mover'), 
                '<a class="prime-mover-external-link" target="_blank" href="' . esc_url(CODEXONICS_CORRUPT_WPRIME_DOC) . '">' . esc_html__('How to fix?', 'prime-mover') . '</a>');
            
            do_action('prime_mover_log_processed_events', "File type error DETECTED: $filePath", $uploads_ajax_input['multisite_blogid_to_import'], 'import', 'doUploadFinishingTasks', $this);
            $this->getSystemFunctions()->primeMoverDoDelete($filePath);
            return wp_send_json($response);
        }
        $this->getSystemInitialization()->setSlowProcess();
        $response['status'] = true;
        $response['done'] = $done;
        
        $response['chunk'] = $chunk;
        $response['chunks'] = $chunks;
        $response['filepath'] = $filePath;
        
        $actual_progress = $this->getUploadUtilities()->getActualUploadProgress($uploads_ajax_input['multisite_blogid_to_import'], $chunk);
        $response['actualprogress'] = $actual_progress;
        
        do_action('prime_mover_log_processed_events', "Returning response $chunk of $chunks of $filePath", $uploads_ajax_input['multisite_blogid_to_import'], 'import', 'doUploadFinishingTasks', $this);
        do_action('prime_mover_log_processed_events', "Returning actual upload progress of $actual_progress out of $chunks", $uploads_ajax_input['multisite_blogid_to_import'], 'import', 'doUploadFinishingTasks', $this);
        wp_send_json($response);       
    }

    /**
     * Process import request via AJAX
     *
     * {@inheritdoc}
     * @see PrimeMoverSystemProcessor::primeMoverImportProcessor()
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itRunsPrimeMoverImportProcessorIfAllClear()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itDoesNotRunImportProcessorIfNotAuthorized()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itReturnsErrorIfImportProcessorFails()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itReturnsGenericErrorIfImportReturnsUndefined()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itDoesNotRunImportProcessorIfNonceFailed()
     */
    public function primeMoverImportProcessor()
    {
        $start_time = $this->getSystemInitialization()->getStartTime();
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $response = [];
        $all_clear = false;
        $ret = [];
        $files_array = [];
        $errors = [];
        $args = [
            'nonce_to_continue' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'data_to_continue' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'diff_blog_id' => FILTER_SANITIZE_NUMBER_INT,
            'prime_mover_next_import_method' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'prime_mover_current_import_method' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter()
        ];
        $continue_import_post = $this->getSystemInitialization()->getUserInput('post', $args, 'diff_import_processor_ajax', 'import');
        if (! empty($continue_import_post['nonce_to_continue']) && $this->getSystemFunctions()->primeMoverVerifyNonce($continue_import_post['nonce_to_continue'], 'prime_mover_continue_import') && ! empty($continue_import_post['data_to_continue'])) {

            $initiating_variables = $this->isProcessAlreadyInitiated('import', $continue_import_post);
            $import_method = $initiating_variables['next_process_method'];
            $import_initiated = $initiating_variables['process_initiated'];

            $data_to_continue = $continue_import_post['data_to_continue'];
            $data_to_continue = stripslashes(html_entity_decode($data_to_continue));
            $data_to_continue = str_replace('\\', '/', $data_to_continue);
            $data_to_continue = json_decode($data_to_continue, true);
            if ((is_array($data_to_continue)) && (! empty($data_to_continue))) {
                global $wp_filesystem;

                if (isset($data_to_continue['unzipped_directory']) && isset($data_to_continue['diff']) && isset($data_to_continue['blog_id'])) {
                    $unzipped_directory_to_check = $data_to_continue['unzipped_directory'];
                    if ($wp_filesystem->exists($unzipped_directory_to_check)) {
                        unset($data_to_continue['diff']);
                        $data_to_continue['diff_confirmation'] = true;
                        $blogid_to_import = (int) $data_to_continue['blog_id'];
                        if ($blogid_to_import > 0) {
                            $ret = $data_to_continue;
                            do_action('prime_mover_log_processed_events', 'Diff all cleared and validated, continuing the import', $blogid_to_import, 'import', 'primeMoverImportProcessor', $this);
                            $all_clear = true;
                        } else {
                            $errors[] = esc_html__('Diff blog ID is invalid.', 'prime-mover');
                        }
                    }
                } else {
                    $errors[] = esc_html__('Diff unzipped_directory and blog ID is not set.', 'prime-mover');
                }
            } else {
                $errors[] = esc_html__('Diff data continue variable is empty.', 'prime-mover');
            }
        } else {
            $methods_input = $this->getSystemInitialization()->getUserInput('post', [
                'prime_mover_next_import_method' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
                'prime_mover_current_import_method' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter()
            ], 'import_processor_ajax', 'import');
            $initiating_variables = $this->isProcessAlreadyInitiated('import', $methods_input);
            $import_method = $initiating_variables['next_process_method'];
            $import_initiated = $initiating_variables['process_initiated'];
            $args = [
                'prime_mover_import_nonce' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
                'multisite_blogid_to_import' => FILTER_SANITIZE_NUMBER_INT,
                'multisite_import_package_uploaded_file' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
                'prime_mover_next_import_method' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
                'prime_mover_current_import_method' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter()
            ];
            if ($import_initiated) {
                unset($args['multisite_import_package_uploaded_file']);
            }
            $import_ajax_input = $this->getSystemInitialization()->getUserInput('post', $args, 'import_processor_ajax', 'import');
            if (! empty($import_ajax_input['prime_mover_import_nonce']) && $this->getSystemFunctions()->primeMoverVerifyNonce($import_ajax_input['prime_mover_import_nonce'], 'prime_mover_import_nonce') && ! empty($import_ajax_input['multisite_blogid_to_import'])) {
                $blogid_to_import = (int) $import_ajax_input['multisite_blogid_to_import'];
                $requisites_meet = false;
                if ($blogid_to_import > 0 && true === $this->getSystemChecks()->primeMoverEssentialRequisites(true)) {
                    $requisites_meet = true;
                } else {
                    $errors[] = esc_html__('Essential requisites is NOT meet during importing package.', 'prime-mover');
                }

                $import_package_path = '';
                if (! $import_initiated && ! empty($import_ajax_input['multisite_import_package_uploaded_file'])) {
                    $import_package_path = $import_ajax_input['multisite_import_package_uploaded_file'];
                }
                if (! $import_initiated && $blogid_to_import > 0 && $requisites_meet && $this->getSystemFunctions()->fileExists($import_package_path)) {
                    do_action('prime_mover_log_processed_events', 'Original handler cleared and validated, starting the import.', $blogid_to_import, 'import', 'primeMoverImportProcessor', $this);
                    $all_clear = true;
                } else {
                    $errors[] = esc_html__('Package file for importing does not exist, aborting import.', 'prime-mover');
                }
                if ($import_initiated && $requisites_meet) {
                    do_action('prime_mover_log_processed_events', 'Original handler cleared and validated, continuing the import.', $blogid_to_import, 'import', 'primeMoverImportProcessor', $this);
                    $all_clear = true;
                } else {
                    $errors[] = esc_html__('Original handler NOT cleared during validation, aborting import.', 'prime-mover');
                }
            }
        }
        if (true === $all_clear) {
            $this->getProgressHandlers()->initializeProgressTracker($blogid_to_import, 'import');
            $this->getSystemInitialization()->setImportBlogID($blogid_to_import);
            if (! isset($ret['diff'])) {
                $import_progress = $this->getProgressHandlers()->getTrackerProgressNonCached($blogid_to_import, 'import');
                if (! $import_initiated && $import_progress && 'boot' !== $import_progress) {
                    do_action('prime_mover_log_processed_events', 'Import already started, aborting with progress: ' . $import_progress, $blogid_to_import, 'import', 'primeMoverImportProcessor', $this);
                    wp_die();
                }
                if (! $import_initiated) {
                    $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Starting import', 'prime-mover'));
                    do_action('prime_mover_log_processed_events', 'Import request received..', 0, 'import', 'importProcessorTriggeredStatus', $this);
                }
                if (isset($import_ajax_input)) {
                    do_action('prime_mover_log_processed_events', $import_ajax_input, 0, 'import', 'importProcessorTriggeredStatus', $this);
                }
            }

            if (defined('PRIME_MOVER_TEST_UPLOAD') && true === PRIME_MOVER_TEST_UPLOAD) {
                $response['status'] = true;
                $response['import_successful'] = '<img src="' . esc_url_raw(plugins_url('res/img/done.png', dirname(__FILE__))) . '" />';

                $this->saveImportResultForOutput($response, 'stoptracking');
                return wp_die();
            }

            $files_array = [];
            if (! empty($import_package_path)) {
                $files_array['file'] = $import_package_path;
            }
            $this->getSystemFunctions()->setTimeLimit();
            do_action('prime_mover_before_doing_import', $blogid_to_import, $import_initiated, $ret);

            do_action('prime_mover_log_processed_events', "Entering import process: $import_method", $blogid_to_import, 'import', 'primeMoverImportProcessor', $this);

            $results = apply_filters("prime_mover_do_import_{$import_method}", $ret, $blogid_to_import, $files_array, $start_time);

            $this->doExitProcessLog($import_method, $blogid_to_import, $results);
            $status = '';
            $stop_tracking = false;

            if (isset($results['error'])) {
                do_action('prime_mover_log_processed_events', 'Import error found', $blogid_to_import, 'import', 'primeMoverImportProcessor', $this);
                $stop_tracking = true;
                $status = 'stoptracking';
                $response['status'] = false;
                $error_message = $results['error'];
                $response['import_not_successful'] = esc_js($error_message);
                return $this->returnFatalRunTimeErrors($results);
            } elseif (isset($results['diff'])) {

                $stop_tracking = true;
                $status = 'diffdetected';
                $nonce_to_user = $this->getSystemFunctions()->primeMoverCreateNonce('prime_mover_continue_import');
                $diff = $results['diff'];
                $diff_friendly = $this->getSystemFunctions()->printFriendlyDiffMessages($diff);

                $response['diff_detected'] = true;
                $response['diff'] = $diff_friendly;
                $response['continue_nonce'] = $nonce_to_user;
                $response['results'] = $results;
                $response['next_method'] = $results['next_method'];
                $response['current_method'] = $results['current_method'];
                if (! empty($results['unzipped_directory'])) {
                    $response['unzipped_directory'] = $results['unzipped_directory'];
                }
                $response['process_id'] = $this->getSystemInitialization()->getImportId();
                do_action('prime_mover_log_processed_events', 'Diff detected', $blogid_to_import, 'import', 'primeMoverImportProcessor', $this);
            } elseif (isset($results['ongoing_import']) && isset($results['next_method']) && isset($results['current_method'])) {

                $response['ongoing_import'] = $results['ongoing_import'];
                $response['next_method'] = $results['next_method'];
                $response['current_method'] = $results['current_method'];
                if (! empty($results['unzipped_directory'])) {
                    $response['unzipped_directory'] = $results['unzipped_directory'];
                }
                $response['process_id'] = $this->getSystemInitialization()->getImportId();
                return wp_send_json($response);
            } elseif ((isset($results['import_success'])) && ((true === $results['import_success']))) {

                do_action('prime_mover_log_processed_events', 'Import success, returning stoptracking', $blogid_to_import, 'import', 'primeMoverImportProcessor', $this);
                $stop_tracking = true;
                $status = 'stoptracking';
                $response['status'] = true;
                $response['completion_text'] = '';
                if (isset($results['completion_text'])) {
                    $response['completion_text'] = $results['completion_text'];
                }                
                $response['import_successful'] = '<img src="' . esc_url_raw(plugins_url('res/img/done.png', dirname(__FILE__))) . '" />';
            } else {

                $stop_tracking = true;
                $response['status'] = false;
                $status = 'stoptracking';
                $response['import_not_successful'] = esc_js(__('Import failed for unknown reason.', 'prime-mover'));
            }

            if ($stop_tracking) {
                do_action('prime_mover_log_processed_events', 'Saving import result output:', $blogid_to_import, 'import', 'primeMoverImportProcessor', $this);
                do_action('prime_mover_log_processed_events', $response, $blogid_to_import, 'import', 'primeMoverImportProcessor', $this);

                $this->saveImportResultForOutput($response, $status);
            }
        } else {
            if (! empty($errors)) {
                $errors = print_r($errors, true);
            } else {
                $errors = esc_html__('An unknown error has occured', 'prime-mover');
            }
            do_action('prime_mover_shutdown_actions', [
                'type' => 1,
                'message' => $errors
            ]);
        }

        wp_die();
    }
     
    /**
     * Do exit process log
     * @param string $import_method
     * @param number $blogid_to_import
     * @param array $results
     */
    protected function doExitProcessLog($import_method = '', $blogid_to_import = 0, $results = [])
    {
        if (!isset($results['plugins_to_copy']) || empty($results['plugins_to_copy'])) {
            do_action('prime_mover_log_processed_events', "Exited import process: $import_method with the following results: ", $blogid_to_import, 'import', 'primeMoverImportProcessor', $this);
            do_action('prime_mover_log_processed_events', $results, $blogid_to_import, 'import', 'primeMoverImportProcessor', $this);
        }        
    }
    
    /**
     * Is Process already initiated
     *
     * @param string $mode
     * @param array $process_input
     * @return array
     */
    protected function isProcessAlreadyInitiated($mode = 'export', $process_input = [])
    {
        $initiated = false;
        $methods = $this->getSystemInitialization()->getPrimeMoverExportMethods();
        if ('import' === $mode) {
            $methods = $this->getSystemInitialization()->getPrimeMoverImportMethods();
        }
        $method = $methods[0];
        $key = "prime_mover_next_{$mode}_method";
        if (! empty($process_input[$key])) {
            $initiated = true;
            $method = $process_input[$key];
        }

        return [
            'process_initiated' => $initiated,
            'next_process_method' => $method
        ];
    }

    /**
     * Save import result for output
     *
     * @param array $response
     */
    protected function saveImportResultForOutput($response = [], $status = '')
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $import_id = $this->getSystemInitialization()->getImportId();
        $response['process_id'] = $import_id;

        $option = 'import_' . $import_id;
        $this->getSystemFunctions()->updateSiteOption($option, $response);
        if ($status) {
            $this->getProgressHandlers()->updateTrackerProgress($status);
        }
        $this->getSystemInitialization()->setProcessingDelay(3);
    }

    /**
     * Process export request via AJAX
     *
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itRunsPrimeMoverExportProcessorIfAllClear()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itReturnsErrorIfDetectedOnExportProcessor()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itDoesNotAllowExportProcessorCallIfNotAuthorized()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itDoesNotAllowExportProcessorCallIfNonceFailed()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itDoesNotAllowExportProcessorCallIfOptionInvalid()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemProcessors::itRunsPrimeMoverExportProcessorIfAlreadyInitiated()
     */
    public function primeMoverExportProcessor()
    {
        $start_time = $this->getSystemInitialization()->getStartTime();
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $all_clear = false;
        $errors = [];
        $args = apply_filters('prime_mover_export_processor_args', [
            'prime_mover_export_nonce' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'multisite_blogid_to_export' => FILTER_SANITIZE_NUMBER_INT,
            'multisite_export_options' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'prime_mover_export_targetid' => FILTER_SANITIZE_NUMBER_INT,
            'prime_mover_export_type' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'prime_mover_next_export_method' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'prime_mover_current_export_method' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter()
        ]);

        $multisite_export_options = '';
        $response = [];
        $export_ajax_input = $this->getSystemInitialization()->getUserInput('post', $args, 'export_processor_ajax', 'export');

        $initiating_variables = $this->isProcessAlreadyInitiated('export', $export_ajax_input);
        $export_method = $initiating_variables['next_process_method'];
        $export_initiated = $initiating_variables['process_initiated'];

        if (! empty($export_ajax_input['prime_mover_export_nonce']) && $this->getSystemFunctions()->primeMoverVerifyNonce($export_ajax_input['prime_mover_export_nonce'], 'prime_mover_export_nonce') && ! empty($export_ajax_input['multisite_blogid_to_export'])) {
            $blogid_to_export = $export_ajax_input['multisite_blogid_to_export'];
            $blogid_to_export = intval($blogid_to_export);

            $multisite_export_options = apply_filters('prime_mover_get_export_option', $export_ajax_input['multisite_export_options'], $blogid_to_export);
            if (($blogid_to_export > 0) && (true === $this->getSystemChecks()->primeMoverEssentialRequisites()) && $multisite_export_options) {
                $this->getSystemInitialization()->setExportBlogID($blogid_to_export);
                $all_clear = true;
                do_action('prime_mover_log_processed_events', 'Export parameters cleared and validated, starting the export.', $blogid_to_export, 'export', 'primeMoverExportProcessor', $this);
            } else {
                $errors[] = esc_html__('Export requisites NOT meet.', 'prime-mover');
            }
            
        } else {
            $errors[] = esc_html__('Export nonce and other POST parameters not set.', 'prime-mover');
        }

        if (true === $all_clear) {
            $export_options_array = [];
            $this->getSystemInitialization()->setExportBlogID($blogid_to_export);
            $this->getProgressHandlers()->initializeProgressTracker($blogid_to_export, 'export');
            do_action('prime_mover_before_doing_export', $blogid_to_export, $export_initiated);
            if (! $export_initiated) {
                $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Starting export', 'prime-mover'), 'export');
            }
            $export_options_array = [
                'multisite_export_options' => $multisite_export_options
            ];
            if (! empty($export_ajax_input['prime_mover_export_targetid'])) {
                $export_options_array['prime_mover_export_targetid'] = $export_ajax_input['prime_mover_export_targetid'];
            }
            if (! empty($export_ajax_input['prime_mover_export_type'])) {
                $export_options_array['prime_mover_export_type'] = $export_ajax_input['prime_mover_export_type'];
            }
            $this->getSystemFunctions()->setTimeLimit();
            $processor_array = apply_filters('prime_mover_export_processor_array', $export_options_array, $blogid_to_export, $export_ajax_input);

            $results = apply_filters("prime_mover_export_{$export_method}", $processor_array, $blogid_to_export, $start_time);
            $stop_tracking = false;

            if (isset($results['error'])) {
                $stop_tracking = true;
                $response['status'] = false;
                $error_message = $results['error'];
                $response['export_not_successful'] = esc_js($error_message);
                do_action('prime_mover_log_processed_events', 'Export result error found:' . $error_message, $blogid_to_export, 'export', 'primeMoverExportProcessor', $this);
                return $this->returnFatalRunTimeErrors($results);
            } elseif (isset($results['ongoing_export']) && isset($results['next_method']) && isset($results['current_method'])) {
                $response['ongoing_export'] = $results['ongoing_export'];
                $response['next_method'] = $results['next_method'];
                $response['current_method'] = $results['current_method'];
                if (! empty($results['temp_folder_path'])) {
                    $response['temp_folder_path'] = $results['temp_folder_path'];
                }
                $response['process_id'] = $this->getSystemInitialization()->getExportId();
                return wp_send_json($response);
            } elseif (isset($results['multisite_export_location']) && 'export_directory' === $results['multisite_export_location']) {
                $stop_tracking = true;
                do_action('prime_mover_log_processed_events', 'Export location defined', $blogid_to_export, 'export', 'primeMoverExportProcessor', $this);
                $response['status'] = true;
                $response['export_location'] = 'export_directory';
                $response['restore_url'] = esc_url_raw($results['download_url']);
                $response['message'] = apply_filters('prime_mover_filter_custom_export_success_message', esc_js(__('Export saved !', 'prime-mover')), $blogid_to_export);
            } elseif (isset($results['download_url']) && isset($results['generated_filename'])) {
                
                $stop_tracking = true;
                do_action('prime_mover_log_processed_events', 'Download URL defined', $blogid_to_export, 'export', 'primeMoverExportProcessor', $this);
                $download_url = $results['download_url'];
                $generated_filename = $results['generated_filename'];
                
                $response['status'] = true;
                $response['download_link'] = '<a class="button button-primary button-hero" href="' . esc_url_raw($download_url) . '">' . esc_html__('Download package', 'prime-mover') . '</a>';   
                
                $response['generated_filename'] = esc_js($generated_filename);
                $response['prime_mover_export_downloaded'] = $this->outputExportSuccessHTML($blogid_to_export);
                
            } else {
                $stop_tracking = true;
                do_action('prime_mover_log_processed_events', 'Generic export not successful logged', $blogid_to_export, 'export', 'primeMoverExportProcessor', $this);
                $response['status'] = false;
                $response['export_not_successful'] = esc_js(__('Export failed', 'prime-mover'));
            }

            if ($stop_tracking) {
                $this->logStopTracking($blogid_to_export, $results);
                $this->saveCompletedExportResponse($blogid_to_export, $response);
            }
        } else {
            if (! empty($errors)) {
                $errors = print_r($errors, true);
            } else {
                $errors = esc_html__('An unknown error has occured', 'prime-mover');
            }
            do_action('prime_mover_shutdown_actions', [
                'type' => 1,
                'message' => $errors
            ]);
        }
        wp_die();
    }

    /**
     * Output export success HTML
     * @return string
     */
    protected function outputExportSuccessHTML($blog_id = 0)
    {
        if ( ! $blog_id ) {
            return '';
        }
        $backup_menu_site = esc_url($this->getSystemFunctions()->getBackupMenuUrl($blog_id));
        
        $out = '';
        $out .= esc_js(__('Export completed.', 'prime-mover'));
        $out .= ' <a href="' . $backup_menu_site . '" class="prime-mover-export-directory-path" title="' . esc_attr($this->getSystemInitialization()->getMultisiteExportFolder()) . '">' . esc_html__('Package Saved !', 'prime-mover') . '</a>';
        
        return $out;
    }
    
    /**
     * Return fatal runtime errors with logs to user.
     * @param array $ret
     */
    protected function returnFatalRunTimeErrors($ret = [])
    {
        if (empty($ret['error'])) {
            return;
        }
        global $wpdb;
        $wpdb->query("UNLOCK TABLES;");
        $validation_error = print_r($ret['error'], true);
        do_action( 'prime_mover_shutdown_actions', ['type' => 1, 'message' => $validation_error] );
        wp_die();
    }
    /**
     * Log stop tracking
     *
     * @param number $blogid_to_export
     * @param array $results
     */
    protected function logStopTracking($blogid_to_export = 0, $results = [])
    {
        do_action('prime_mover_log_processed_events', 'Stop tracking requested', $blogid_to_export, 'export', 'logStopTracking', $this);
        do_action('prime_mover_log_processed_events', 'Export result:', $blogid_to_export, 'export', 'logStopTracking', $this);
        do_action('prime_mover_log_processed_events', $results, $blogid_to_export, 'export', 'logStopTracking', $this);
    }

    /**
     * Save completed export response
     *
     * @param number $blogid_to_export
     * @param array $response
     */
    protected function saveCompletedExportResponse($blogid_to_export = 0, $response = [])
    {
        do_action('prime_mover_log_processed_events', 'Saving export result output:', $blogid_to_export, 'export', 'primeMoverExportProcessor', $this);
        do_action('prime_mover_log_processed_events', $response, $blogid_to_export, 'export', 'primeMoverExportProcessor', $this);

        $this->saveExportResultForOutput($response, 'stoptracking');
    }

    /**
     * Save export result for outputting
     *
     * @param array $response
     * @param string $status
     */
    protected function saveExportResultForOutput($response = [], $status = '')
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $export_id = $this->getSystemInitialization()->getExportId();
        $response['process_id'] = $export_id;

        $option = 'export_' . $export_id;
        $this->getSystemFunctions()->updateSiteOption($option, $response);
        if ($status) {
            $this->getProgressHandlers()->updateTrackerProgress($status, 'export');
        }
        $this->getSystemInitialization()->setProcessingDelay(3);
    }
}
