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

use Codexonics\PrimeMoverFramework\classes\PrimeMover;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Validation Utilities
 * Helper functionality for data validation processes
 *
 */
class PrimeMoverValidationUtilities
{     
    private $prime_mover;
    private $import_utilities;
    private $utilities;
    
    /**
     * Construct
     * @param PrimeMover $prime_mover
     * @param array $utilities
     */
    public function __construct(PrimeMover $prime_mover, $utilities = [])
    {
        $this->prime_mover = $prime_mover;
        $this->import_utilities = $utilities['import_utilities'];
        $this->utilities = $utilities;
    }
    
    /**
     * Get utilities
     * @return array
     */
    public function getUtilities()
    {
        return $this->utilities;
    }
    
    /**
     * Get gearbox screen options
     */
    public function getGearBoxScreenOptions()
    {
        $utilities = $this->getUtilities();        
        return $utilities['screen_options'];
    }
    
    /**
     * Get Prime Mover
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMover
     */
    public function getPrimeMover()
    {
        return $this->prime_mover;
    }
    
    /**
     * 
     * Return export utilities
     */
    public function getExportUtilities()
    {
        return $this->getImportUtilities()->getExportUtilities();
    }
    
    /**
     * Get import utilities
     */
    public function getImportUtilities()
    {
        return $this->import_utilities;
    }
       
    /**
    * Initialize hooks
    * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverValidationUtilities::itAddsInitHooks()
    * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverValidationUtilities::itChecksIfHooksAreOutdated()
    */
    public function initHooks()
    {
        add_filter("prime_mover_get_validation_id_export_processor_ajax", [$this, 'returnValidationProtocolExportProcessor'], 10); 
        add_filter("prime_mover_get_validation_id_import_processor_ajax", [$this, 'returnValidationProtocolImportProcessor'], 10);        
        add_filter("prime_mover_get_validation_id_diff_import_processor_ajax", [$this, 'returnValidationProtocolDiffImportProcessor'], 10);
        add_filter("prime_mover_get_validation_id_delete_tmp_dir", [$this, 'returnValidationProtocolDeleteTmpFile'], 10);        
        add_filter("prime_mover_get_validation_id_shutdown_error_log", [$this, 'returnValidationShutdownErrorLog'], 10);
        
        add_filter("prime_mover_get_validation_id_download_stream_parameters", [$this, 'returnValidationDownloadStream'], 10);        
        add_filter("prime_mover_get_validation_id_chunk_upload_ajax", [$this, 'returnValidationChunkUpload'], 10);
        add_filter("prime_mover_get_validation_id_common_shutdown_processor", [$this, 'returnValidationCommonShutDown'], 10);
        add_filter("prime_mover_get_validation_id_common_progress_processor", [$this, 'returnValidationCommonProgress'], 10);
        
        add_filter("prime_mover_get_validation_id_errorlog_exist_check", [$this, 'returnValidationErrorLogExist'], 10);
        add_filter("prime_mover_filter_error_log", [$this, 'sanitizeLog'], 10, 2);
        add_filter("prime_mover_get_validation_id_download_authorization_parameters", [$this, 'returnValidationAuthorizationHeaders'], 10);
        add_filter("prime_mover_get_validation_id_verify_encrypted_package", [$this, 'returnValidationProtocolDecryptVerify'], 10);
        
        add_filter("prime_mover_get_validation_id_maintenance_control_parameters", [$this, 'returnValidationMaintenanceControlParameters'], 10);
        add_filter("prime_mover_get_validation_id_verify_contact_page", [$this, 'returnValidationContactPageParameters'], 10);
        add_filter("prime_mover_get_validation_id_disable_session", [$this, 'returnValidationAjaxActions'], 10);
        
        add_filter("prime_mover_get_validation_id_delete_tmp_file", [$this, 'returnValidationProtocolDeleteZipInTmpDir'], 10); 
        add_filter("prime_mover_get_validation_id_prime_mover_backups", [$this, 'returnValidationProtocolBackupsMenu'], 10);        
        add_filter("prime_mover_get_validation_id_prime_mover_free_deactivation", [$this, 'returnValidationFreeActivation'], 10);        
        
        add_filter("prime_mover_get_validation_id_gearbox_screen_option", [$this, 'returnValidationGearBoxScreenOption']);
        add_filter("prime_mover_get_validation_id_prime_mover_refresh_backups", [$this, 'returnValidationRefreshBackups']);
        add_filter("prime_mover_get_validation_id_initialize_usertaxonomy_subsite", [$this, 'returnValidationUserTaxHeaders'], 10);
        
        add_filter("prime_mover_get_validation_id_prime_mover_activate_validate", [$this, 'returnValidationActivation'], 10);  
        add_filter("prime_mover_get_validation_id_force_permalink_redirect", [$this, 'returnValidationForcePermalinkRedirect'], 10); 
    }
    
    /**
     * Return validation of force permalink redirect
     * @return string[]
     */
    public function returnValidationForcePermalinkRedirect()
    {
        return [
            'prime_mover_force_redirect_to_permalinks' => 'yes',
            'prime_mover_force_redirect_nonce' => 'nonce',
            'prime_mover_target_blogid' => 'positive_int'
        ];
    }
    
    /**
     * Return activation validation
     * @return string[][]
     */
    public function returnValidationActivation()
    {
        return [
            'activate' => ['true', 'false'],
        ];
    }
    
    /**
     * Return validation free activation
     * @return string[]|string[][]
     */
    public function returnValidationFreeActivation()
    {
        return [
            'action' => ['deactivate', 'activate'],
            'plugin' => [PRIME_MOVER_DEFAULT_FREE_BASENAME, PRIME_MOVER_DEFAULT_PRO_BASENAME],
            '_wpnonce' => 'nonce'
        ];
    }
    
    /**
     * Return validation refresh backups
     * @return string[]
     */
    public function returnValidationRefreshBackups()
    {
        return [
            'prime_mover_refresh_backups' => 'nonce'
        ];
    }
    
    /**
     * Screen option basic validation
     * @return string[]|string[][]
     * @reviewed
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverValidationUtilities::itReturnsValidationGearBoxOptions() 
     */
    public function returnValidationGearBoxScreenOption()
    {
        $screen_option = $this->getGearBoxScreenOptions();
        $setting = $screen_option::SCREEN_OPTION_SETTING;
        
        return [
            $setting => ['yes'],
            'prime_mover_screen_option_nonce' => 'nonce'
        ];
    }
    
    /**
     * Return validation backups menu
     * @return string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverValidationUtilities::itReturnsValidationProtocolBackupsMenu()
     */
    public function returnValidationProtocolBackupsMenu()
    {
        return [
            '_wpnonce' => 'nonce',
            'prime_mover_backup' => 'prime_mover_menu_backups',
            'prime-mover-blog-id-menu' => 'positive_int',
            'prime-mover-select-blog-to-query' => 'positive_int'
        ];
    }
    
    /**
     * Return validation ajax actions
     * @return string[][]
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverValidationUtilities::itReturnsAjaxActionsForValidation()
     */
    public function returnValidationAjaxActions()
    {
        $prime_mover_ajax_actions = array_keys($this->getPrimeMover()->getSystemInitialization()->getPrimeMoverAjaxActions());
        return [
            'action' => $prime_mover_ajax_actions
        ];
    }
    
    /**
     * Return validation contact page redirect
     * @return string[][]
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverValidationUtilities::itReturnsValidationPageContactParameters()
     */
    public function returnValidationContactPageParameters()
    {
        return [
            'page' => ['migration-panel-settings-contact']
        ];
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverValidationUtilities::itReturnsValidationMaintenanceControlParameters() 
     * @return string[]|string[][]
     */
    public function returnValidationMaintenanceControlParameters()
    {
        return [
            'prime_mover_maintenance' => ['off', 'on'],
            'prime_mover_blogid' => 'positive_int',
            'prime_mover_disable_wpnonce' => 'nonce'            
        ];
    }
    
    /**
     * Delete tmp dir validation
     * @reviewed
     * @return string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverValidationUtilities::itReturnsValidationControlDecryptVerify()
     */
    public function returnValidationProtocolDecryptVerify()
    {
        return [
            'prime_mover_decryption_check_nonce' => 'nonce',
            'stringtocheck' => 'prime_mover_signature',
            'blog_id' => 'positive_int',
            'encrypted_media' => ['true', 'false'],
            'package_ext' => ['wprime', 'zip']
        ];
    }
    
    /**
     * Validation protocol for authorization headers
     * @return array
     * @reviewed
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverValidationUtilities::itReturnsValidationAuthorizationHeaders()
     */
    public function returnValidationAuthorizationHeaders()
    {
        $current_domain = $this->getPrimeMover()->getSystemInitialization()->getDomain();
        return [
            'HTTP_X_PRIME_MOVER_DOMAIN' => [$current_domain],
            'HTTP_X_PRIME_MOVER_AUTHORIZATION' => 'sha512',
            'HTTP_X_RESUME_BYTES_DOWNLOADING' => 'positive_int',
            'REQUEST_METHOD' => 'prime_mover_valid_request_method'
        ];
    }
    
    /**
     * Validation protocol for user taxonomy headers
     * @return array
     */
    public function returnValidationUserTaxHeaders()
    {
        return [
            'HTTP_X_PRIME_MOVER_USERTAXONOMY' => 'sha512',
            'REQUEST_METHOD' => 'prime_mover_valid_request_method'
        ];
    }
    
    /**
     * Sanitize log
     * @param string $log
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverValidationUtilities::itSanitizesLog() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverValidationUtilities::itDoesNotSanitizeLogWhenNotDbDump()
     */
    public function sanitizeLog($log = '', $source = '')
    {        
        if ( ! $log || ! $source ) {
            return $log;
        }
        if ($source === 'dumpDbForExport') {
            global $wpdb;
            
            $log = str_replace($wpdb->dbuser, 'XXXXXXX', $log);
            $log = str_replace($wpdb->dbpassword, 'XXXXXXX', $log);
            $log = str_replace($wpdb->dbhost, 'XXXXXXX', $log);
            $log = str_replace($wpdb->dbname, 'XXXXXXX', $log);
        }
        return $log;
    }

    /**
     * Get validation protocol for error log exist check
     * @return string[]
     * @reviewed
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverValidationUtilities::itReturnsValidationErrorLogExists() 
     */
    public function returnValidationErrorLogExist()
    {
        return [
            'prime_mover_errorlog_nonce' => 'nonce',
            'error_blog_id' => 'positive_int'
        ];
    }
    
    /**
     * Return validation of commmon progress
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverValidationUtilities::itReturnsValidationCommonProgress()
     * @return string[]
     * @reviewed
     */
    public function returnValidationCommonProgress()
    {
        return [
            'prime_mover_import_progress_nonce' => 'nonce',
            'prime_mover_export_progress_nonce' => 'nonce',
            'blog_id' => 'positive_int',
            'trackercount' => 'positive_int',
            'diffmode' => 'boolean'
        ];
    }
    
    /**
     * @reviewed
     * @return string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverValidationUtilities::itReturnsValidationCommonShutdown()
     */
    public function returnValidationCommonShutDown()
    {
        return [
            'prime_mover_import_shutdown_nonce' => 'nonce',
            'prime_mover_export_shutdown_nonce' => 'nonce',
            'process_id' => 'sha256',
            'blog_id' => 'positive_int'
        ];        
    }
    
    /**
     * @reviewed
     * @return string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverValidationUtilities::itReturnsValidationChunkUpload()
     */
    public function returnValidationChunkUpload()
    {
        return [
            'prime_mover_uploads_nonce' => 'nonce',
            'multisite_blogid_to_import' => 'positive_int',
            'start' => 'float',
            'end' => 'float',
            'chunk' => 'positive_int',
            'chunks' => 'positive_int',
            'missing_chunk_to_fix' => 'positive_int',
            'resume_parts_index' => 'positive_int',
            'resume_filepath' => 'merging_zip_path',
            'resume_chunks' => 'positive_int'
        ];
    }
    
    /**
     * @reviewed
     * @return string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverValidationUtilities::itReturnsValidationDownloadStream()
     */
    public function returnValidationDownloadStream()
    {
        return [
            'prime_mover_download_nonce' => 'nonce',
            'prime_mover_export_hash' => 'sha256',
            'prime_mover_blogid' => 'positive_int',
        ];
    }
    
    /**
     * @reviewed
     * @return string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverValidationUtilities::itReturnsValidationShutdownErrorLog()
     */
    public function returnValidationShutdownErrorLog()
    {
        return [
            'prime_mover_errornonce' => 'nonce',
            'prime_mover_blogid' => 'positive_int',
        ];        
    }
    
    /**
     * Delete tmp dir validation
     * @reviewed
     * @return string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverValidationUtilities::itReturnsValidationProtocolDeleteTmpFile()
     */
    public function returnValidationProtocolDeleteTmpFile()
    {
        return [
            'prime_mover_deletetmpfile_nonce' => 'nonce',
            'temp_file_to_delete' => 'migration_dir',
            'diff_reject' => ['true', 'false'],
            'blog_id' => 'positive_int',
            'mode' => ['export', 'import'],
            'tmp_file_mode' => ['no', 'yes']
        ];
    }
    
    /**
     * Delete fragment of zip file being download at tmp dir but corrupted
     * @return string[]|string[][]
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverValidationUtilities::itReturnsValidationProtocolDeleteZipTmpDir()
     */
    public function returnValidationProtocolDeleteZipInTmpDir()
    {
        return [
            'prime_mover_deletetmpfile_nonce' => 'nonce',
            'temp_file_to_delete' => 'tmp_dir_file',
            'diff_reject' => ['true', 'false'],
            'blog_id' => 'positive_int',
            'mode' => ['export', 'import'],
            'tmp_file_mode' => ['no', 'yes']
        ];
    }
 
    /**
     * Diff validation protocol
     * @return string[]
     * @reviewed
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverValidationUtilities::itReturnsValidationProtocolDiffProcessor()
     */
    public function returnValidationProtocolDiffImportProcessor()
    {
        return [
            'nonce_to_continue' => 'nonce',
            'data_to_continue' => 'diff_json',
            'diff_blog_id' => 'positive_int',
            'prime_mover_next_import_method' => $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverImportMethods(),
            'prime_mover_current_import_method' => $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverImportMethods()
        ];
    }
    
    /**
     * Return validation protocol for export processor
     * @return string[]|string[][]
     * @reviewed
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverValidationUtilities::itReturnsValidationImportProcessor() 
     */
    public function returnValidationProtocolImportProcessor()
    {
        return [
            'prime_mover_import_nonce' => 'nonce',
            'multisite_blogid_to_import' => 'positive_int',
            'multisite_import_package_uploaded_file' => 'migration_package',
            'prime_mover_next_import_method' => $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverImportMethods(),
            'prime_mover_current_import_method' => $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverImportMethods()
        ];
    }
    
    /**
    * Return validation protocol for export processor
    * @return string[]|string[][]
    * @reviewed
    * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverValidationUtilities::itReturnsValidationExportProcessor()
    */
    public function returnValidationProtocolExportProcessor()
    {
        $args = [
            'prime_mover_export_nonce' => 'nonce',
            'multisite_blogid_to_export' => 'positive_int',
            'multisite_export_options' => $this->getExportUtilities()->getValidExportOptions(),
            'multisite_export_location' => ['export_directory', 'default'],
            'prime_mover_encrypt_db' => ['true', 'false'],
            'prime_mover_userexport_setting' => ['true', 'false'],
            'prime_mover_dropbox_upload' => ['true', 'false'],
            'prime_mover_force_utf8' => ['true', 'false'],
            'prime_mover_gdrive_upload' => ['true', 'false'],
            'prime_mover_export_targetid' => 'positive_int',
            'prime_mover_export_type' => ['single-site-export', 'multisite-export', 'multisitebackup-export'],
            'prime_mover_next_export_method' => $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverExportMethods(),
            'prime_mover_current_export_method' => $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverExportMethods()
        ]; 
        
        return $args;
    }    
}