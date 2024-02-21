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

use Codexonics\PrimeMoverFramework\utilities\PrimeMoverValidationUtilities;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Validation handling class
 *
 * The Prime Mover Validation Handling Class handles basic input data validation processes.
 * The class performs earliest/first validation of sanitized inputs.
 * This is done as soon as user input data is received via POST, GET or HEAD
 * Capability checks SHOULD already be done at this stage.
 * Succeeding or secondary validation might happen in specific implementations before the data is actually used.
 *
 */
class PrimeMoverValidationHandlers
{
    private $prime_mover;
    private $import_utilities;
    private $validation_utilities;
    private $download_utilities;
    private $openssl_utilities;
    
    /**
     *
     * Constructor
     */
    public function __construct(PrimeMover $prime_mover, $utilities = [], PrimeMoverValidationUtilities $validation_utilities = null) 
    {
        $this->prime_mover = $prime_mover;
        $this->import_utilities = $utilities['import_utilities'];
        $this->download_utilities = $utilities['download_utilties'];
        $this->validation_utilities = $validation_utilities;
        $this->openssl_utilities = $utilities['openssl_utilities'];
    }
    
    /**
     * 
     * Get openssl utilities
     */
    public function getOpenSSLUtilities()
    {
        return $this->openssl_utilities;
    }
    
    /**
     * 
     * Get download utilities
     */
    public function getDownloadUtilities()
    {
        return $this->download_utilities;
    }
    
    /**
     * Get import utilities
     */
    public function getImportUtilities()
    {
        return $this->import_utilities;
    }
    
    /**
     * Get Prime Mover instance
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMover
     */
    public function getMultisteMigration()
    {
        return $this->prime_mover;
    }
    
    /**
     * Get validation utilities
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverValidationUtilities
     */
    public function getValidationUtilities()
    {
        return $this->validation_utilities;        
    }
    
    /**
     * Initialize hooks
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverValidationHandlers::itAddsInitHooks() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverValidationHandlers::itChecksIfHooksAreOutdated()
     */
    public function initHooks()
    {
        add_filter('prime_mover_validate_user_input_data', [$this, 'validateInputParameters'], 10, 6);
    }
    
    /**
     * Log before validation
     * @param number $blog_id
     * @param string $mode
     * @param array $sanitized
     * @param array $validation_protocol
     */
    private function logBeforeValidation($blog_id = 0, $mode = 'common', $sanitized = [], $validation_protocol = [])
    {
        if ( ! $blog_id ) {
            return;
        }
        do_action('prime_mover_log_processed_events', 'Start validating post parameters, before validating INPUT parameters', $blog_id, $mode, 'logBeforeValidation', $this, true);
        do_action('prime_mover_log_processed_events', $sanitized, $blog_id, 'export', 'logBeforeValidation', $this, true);
        
        do_action('prime_mover_log_processed_events', 'Validation protocol used:', $blog_id, $mode, 'logBeforeValidation', $this, true);
        do_action('prime_mover_log_processed_events', $validation_protocol, $blog_id, $mode, 'logBeforeValidation', $this, true);
    }

    /**
     * Perform validation logic
     * @param mixed $input_data
     * @param mixed $protocol
     * @param mixed $input_parameter
     * @param number $blog_id
     * @param array $validation_errors
     * @param string $mode
     * @return mixed|NULL|array
     */
    private function doValidationLogic($input_data, $protocol, $input_parameter, $blog_id = 0, $validation_errors = [], $mode = 'common')
    {        
        if (is_array($protocol) && ! in_array($input_data, $protocol, true)) {
            $serialized = maybe_serialize($protocol);
            $validation_errors[$input_parameter] = print_r(sprintf(esc_html__("%s of %s array parameter is an invalid data", 'prime-mover'), maybe_serialize($input_data), $serialized) , true);            
            
        } elseif ('nonce' === $protocol && ! ctype_alnum($input_data)) {
            $validation_errors[$input_parameter] = print_r(sprintf(esc_html__("%s is not a valid nonce format", 'prime-mover'), maybe_serialize($input_data)) , true);            
            
        } elseif('positive_int' === $protocol && ! filter_var($input_data, FILTER_VALIDATE_INT, ["options" => ["min_range"=> 1]] ) ) {
            $validation_errors[$input_parameter] = print_r(sprintf(esc_html__("%s is not a positive integer", 'prime-mover'), maybe_serialize($input_data)) , true);           
            
        } elseif('migration_package' === $protocol && ! $this->isValidMigrationPackage($input_data, $blog_id)) {
            $validation_errors[$input_parameter] = print_r(sprintf(esc_html__("%s is not a valid migration package", 'prime-mover'), maybe_serialize($input_data)) , true);                  
            
        } elseif('diff_json' === $protocol && ! $this->isValidJson($input_data)) {
            $validation_errors[$input_parameter] = print_r(esc_html__("Diff data is not a valid json format", 'prime-mover'), true);             
            
        } elseif('migration_dir' === $protocol && ! $this->isMigrationFolder($input_data, $blog_id, $mode)) {
            $validation_errors[$input_parameter] = print_r(sprintf(esc_html__("%s is not a directory", 'prime-mover'), maybe_serialize($input_data)) , true);              
            
        } elseif('sha256' === $protocol && ! $this->getDownloadUtilities()->isShaString($input_data, 256)) {
            $validation_errors[$input_parameter] = print_r(sprintf(esc_html__("%s is not a sha256 string", 'prime-mover'), maybe_serialize($input_data)) , true);            
            
        } elseif('boolean' === $protocol && ! is_bool($input_data)) {
            $validation_errors[$input_parameter] = print_r(sprintf(esc_html__("%s is not boolean", 'prime-mover'), maybe_serialize($input_data)) , true);            
            
        } elseif('sha512' === $protocol && ! $this->getDownloadUtilities()->isShaString($input_data, 512)) {
            $validation_errors[$input_parameter] = print_r(sprintf(esc_html__("%s is not a sha512 string", 'prime-mover'), maybe_serialize($input_data)) , true);            
            
        } elseif('url' === $protocol && false === filter_var($input_data, FILTER_VALIDATE_URL)) {
            $validation_errors[$input_parameter] = print_r(sprintf(esc_html__("%s is not valid URL", 'prime-mover'), maybe_serialize($input_data)) , true);
            
        } elseif('float' === $protocol && ! filter_var($input_data, FILTER_VALIDATE_FLOAT) ) {
            $validation_errors[$input_parameter] = print_r(sprintf(esc_html__("%s is not a valid float", 'prime-mover'), maybe_serialize($input_data)) , true);
            
        } elseif('any_migration_package' === $protocol && ! $this->isAnyMigrationPackage($input_data, $blog_id)) {
            $validation_errors[$input_parameter] = print_r(sprintf(esc_html__("%s is not a migration package format", 'prime-mover'), maybe_serialize($input_data)) , true);
            
        } elseif('prime_mover_valid_request_method' === $protocol && ! (in_array(strtoupper($input_data), ['GET','HEAD', 'POST']))) {
            $validation_errors[$input_parameter] = print_r(sprintf(esc_html__("%s is not a valid request method", 'prime-mover'), maybe_serialize($input_data)) , true);
            
        } elseif('prime_mover_signature' === $protocol) {
            $signature_test = $this->verifyPrimeMoverPackageSignature($input_data, $blog_id);
            $test_result = $signature_test['result'];
            if ( ! $test_result && ! empty($signature_test['error'])) {
                $error_msg = $signature_test['error'];
                $validation_errors[$input_parameter] = print_r($error_msg, true);    
            }
                    
        } elseif('tmp_dir_file' === $protocol && ! $this->isFileInTmpDir($input_data)) {
            $validation_errors[$input_parameter] = print_r(sprintf(esc_html__("%s is not a file inside tmp directory", 'prime-mover'), maybe_serialize($input_data)) , true); 
        
        } elseif('merging_zip_path' === $protocol && ! $this->isValidMergingZipPath($input_data)) {
            $validation_errors[$input_parameter] = print_r(sprintf(esc_html__("%s is not a valid merging zip path", 'prime-mover'), maybe_serialize($input_data)) , true);
            
        } elseif('prime_mover_menu_backups' === $protocol && ! $this->isValidPrimeMoverBackupsArray($input_data, $blog_id)) {
            $validation_errors[$input_parameter] = print_r(sprintf(esc_html__("%s is not a valid backups array.", 'prime-mover'), maybe_serialize($input_data)) , true);
        }
        
        return apply_filters('prime_mover_perform_validation_logic', $validation_errors, $protocol, $input_parameter, $input_data);       
    }
    
    /**
     * 
     * @param array $input_data
     * @param number $blog_id
     * @return boolean
     */
    private function isValidPrimeMoverBackupsArray($input_data = [], $blog_id = 0)
    {
        if (empty($input_data) || ! $blog_id ) {
            return false;
        }
        
        $filtered = array_filter( $input_data, function($path) use ($blog_id){
            return $this->isAnyMigrationPackage($path, $blog_id);
        });        
        
        return count($filtered) === count($input_data);
    }
    
    private function filterValidPackage($input_data = '')
    {
        if ( ! $input_data ) {
            return false;
        }
        return $this->isAnyMigrationPackage($input_data);
    }
    /**
     * Checks if valid merging zip path
     * @param string $input_data
     * @return boolean
     */
    public function isValidMergingZipPath($input_data = '')
    {
        if ( ! $this->getMultisteMigration()->getSystemFunctions()->nonCachedFileExists($input_data)) {
            return false;
        }
        
        $uploads = $this->getMultisteMigration()->getSystemInitialization()->getInitializedWpRootUploads();
        $basedir = $uploads['basedir'];
        $upload_path_slug = $this->getMultisteMigration()->getSystemInitialization()->getUploadTmpPathSlug();
        $target_directory = $basedir . DIRECTORY_SEPARATOR . $upload_path_slug;        
        
        if ( ! $this->getMultisteMigration()->getSystemFunctions()->fileIsInsideGivenDirectory($input_data , $target_directory)) {
            return false;
        }
        return true;
    }
    
    /**
     * Checks if given file is in tmp directory
     * @param string $input_data
     * @return boolean
     */
    public function isFileInTmpDir($input_data = '')
    {
        if ( ! $input_data ) {
            return false;
        }
        $given_dir = trailingslashit(dirname($input_data));
        $tmp_dir = $this->getMultisteMigration()->getSystemInitialization()->getTmpDownloadsFolder();
        
        $tmp_dir = trailingslashit($tmp_dir);
        return ($tmp_dir === $given_dir && $this->getMultisteMigration()->getSystemFunctions()->nonCachedFileExists($input_data));
    }
    
    /**
     *  Checks if prime mover signature is valid
     * @param string $data
     * @param number $blog_id
     * @return boolean[]|string[]
     */
    public function verifyPrimeMoverPackageSignature($data = '', $blog_id = 0)
    {
        return $this->getOpenSSLUtilities()->verifyPrimeMoverPackageSignature($data, $blog_id);
    }
    
    /**
     * Validate sanitized post parameters given the validation protocol
     * @param array $sanitized
     * @param string $validation_id
     * @param string $mode
     * @return array
     */
    public function validateInputParameters($sanitized = [], $validation_id = '', $mode = 'common', $blog_id = 0, $return_data = false, $return_error = false)
    {
        if ( ! $validation_id || ! is_array($sanitized) ) {
            return $sanitized;
        }
        $validation_errors = [];
        $validation_protocol = apply_filters("prime_mover_get_validation_id_{$validation_id}", [], $sanitized);
        
        if (empty($validation_protocol)) {
            return $sanitized;
        }
        if ( ! $mode ) {
            $mode = 'common';
        }
        
        $blog_id = $this->analyzeBlogId($sanitized, $blog_id);
        if ($blog_id) {
            $this->getMultisteMigration()->getSystemInitialization()->setImportBlogID($blog_id);
        }        
        $this->logBeforeValidation($blog_id, $mode, $sanitized, $validation_protocol);
        
        foreach ($sanitized as $input_parameter => $input_data) {
            if ( ! $input_data || ! isset($validation_protocol[$input_parameter] ) ) {
                continue;
            }
            $protocol = $validation_protocol[$input_parameter];
            $validation_errors = $this->doValidationLogic($input_data, $protocol, $input_parameter, $blog_id, $validation_errors, $mode);
        }
        if (empty($validation_errors)) {
            if ($blog_id) {
                do_action('prime_mover_log_processed_events', "$validation_id: Validation success", $blog_id, $mode, 'validateInputParameters', $this);
            }
            return $sanitized;
        } elseif ($return_data && ! empty($validation_errors)) {
            $serialized = maybe_serialize($validation_errors);
            $errors = array_keys($validation_errors);
            foreach($errors as $parameter) {
                unset($sanitized[$parameter]);
            }
            $this->afterInputValidationLog($validation_id, $serialized, $blog_id, $mode, $sanitized);
            if ($return_error) {
                $sanitized['validation_errors'] = $validation_errors;
            }
            return $sanitized;
        } else {
            $validation_error = print_r($validation_errors, true);
            $serialized = maybe_serialize($validation_error);
            $this->afterInputValidationLog($validation_id, $serialized, $blog_id, $mode, $sanitized);
            do_action( 'prime_mover_shutdown_actions', ['type' => 1, 'message' => $validation_error] );
            wp_die();
        }
    }

    /**
     * After input validation log
     * @param string $validation_id
     * @param string $serialized
     * @param number $blog_id
     * @param string $mode
     * @param array $sanitized
     */
    private function afterInputValidationLog($validation_id ='', $serialized = '', $blog_id = 0, $mode = '', $sanitized = [])
    {
        if ( ! $blog_id ) {
            return;
        }
        do_action('prime_mover_log_processed_events', "$validation_id: Validation error found: $serialized", $blog_id, $mode, 'afterInputValidationLog', $this);
        do_action('prime_mover_log_processed_events', "After input validation result:", $blog_id, $mode, 'afterInputValidationLog', $this);
        do_action('prime_mover_log_processed_events', $sanitized, $blog_id, $mode, 'afterInputValidationLog', $this);
    }
    
    /**
     * Is migration folder
     * @param string $input_data
     * @param number $blog_id
     * @param string $mode
     * @return boolean
     */
    private function isMigrationFolder($input_data = '', $blog_id = 0, $mode = 'import')
    {
        
        if ( ! $input_data || ! $blog_id) {
            return false;
        }
        if (is_file($input_data)) {
            return false;
        }
        $input_data = rtrim($input_data, DIRECTORY_SEPARATOR);        
        $valid_sql = false;        
        $footprint_file = $input_data . DIRECTORY_SEPARATOR . 'footprint.json';        
        $blogid_file = $input_data . DIRECTORY_SEPARATOR . 'blogid.txt';
        $sql_file = $input_data . DIRECTORY_SEPARATOR . $blog_id . '.sql';
        $encrypted_sql = $input_data . DIRECTORY_SEPARATOR . $blog_id . '.sql.enc';

        if ( file_exists($sql_file) || file_exists($encrypted_sql) ) {
            $valid_sql = true;
        }        
        if ('export' === $mode && $valid_sql) {
            return true;
        }
        if ( file_exists($footprint_file) && file_exists($blogid_file) && $valid_sql ) {
            return true;
        }
        $package_path = '';
        if ($mode && 'export' === $mode) {
            $package_path = $this->getMultisteMigration()->getSystemInitialization()->getTemporaryExportPackagePath($blog_id);
        }
        if ($mode && 'import' === $mode) {
            $package_path = $this->getMultisteMigration()->getSystemInitialization()->getTemporaryImportPackagePath($blog_id);
        }
        if ( ! $package_path ) {
            return false;
        }
        $package_path = rtrim($package_path, DIRECTORY_SEPARATOR); 
        return ($package_path === $input_data);
    }
    
    /**
     * Test if valid json
     * @param string $input_data
     * @return boolean
     */
    protected function isValidJson($input_data = '')
    { 
        return $this->getMultisteMigration()->getSystemFunctions()->isValidJson($input_data);
    }
    
    /**
     * Check if at least a valid migration package is used
     * This validation considers the target ID of the package
     * @param string $filepath
     * @param number $blog_id
     * @return boolean
     */
    private function isValidMigrationPackage($filepath = '', $blog_id = 0)
    {
        return $this->isMigrationPackage($filepath, $blog_id, true);
    }
 
    /**
     * Check if at least any migration package is used
     * @param string $filepath
     * @param number $blog_id
     * @return boolean
     */
    private function isAnyMigrationPackage($filepath = '', $blog_id = 0)
    {
        return $this->isMigrationPackage($filepath, $blog_id, false);
    }
    
    /**
     * Validates if Prime mover package zip
     * @param string $filepath
     * @param number $blog_id
     * @param boolean $blog_id_check
     * @return boolean
     */
    private function isMigrationPackage($filepath = '', $blog_id = 0, $blog_id_check = true)
    {
        if ( ! $filepath || ! $blog_id ) {
            return false;
        }        
        if ( ! file_exists($filepath) || ! is_file($filepath) ) {
            do_action('prime_mover_log_processed_events', 'Package zip does not seem to exist', $blog_id, 'common', 'isMigrationPackage', $this);
            return false;
        }
        if ( ! $this->getMultisteMigration()->getSystemFunctions()->isReallyValidFormat($filepath)) {
            do_action('prime_mover_log_processed_events', 'Package zip is not a zip file.', $blog_id, 'common', 'isMigrationPackage', $this);
            return false;
        }
        $package_description = '';
        $tar_config = [];
        if ($this->getMultisteMigration()->getSystemFunctions()->isReallyTar($filepath)) {
            $tar_config = apply_filters('prime_mover_get_tar_package_config_from_file', [], $filepath);
            $package_mode = $tar_config['export_options'];
            $export_modes = $this->getImportUtilities()->getExportUtilities()->getExportModes();
            if (!empty($export_modes[$package_mode])) {
                $package_description = $export_modes[$package_mode];
            }
        } else {
            $encrypted = $this->getImportUtilities()->isZipPackageDbEncrypted($filepath);
            $package_description = $this->getImportUtilities()->getZipPackageDescription($filepath, $blog_id, $encrypted, true, $blog_id_check);
        }
        
        if ( ! $package_description ) {
            do_action('prime_mover_log_processed_events', 'Package description test fails', $blog_id, 'common', 'isMigrationPackage', $this);
            return false;
        }
        return true;
    }
    
    /**
     * Get upload utilities
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverUploadUtilities
     */
    public function getUploadUtilities()
    {
        return $this->getMultisteMigration()->getSystemProcessors()->getUploadUtilities();
    }
    
    /**
     * Get shutdown utilities
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverShutdownUtilities
     */
    public function getShutDownUtilities()
    {
        return $this->getMultisteMigration()->getImporter()->getCliArchiver()->getShutDownUtilities();
    }
    
    /**
     * Analyze blog id depending in scenarios
     * @param array $sanitized
     * @param number $blog_id
     * @return number
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverValidationHandlers::itAnalyzesBlogID()
     */
    protected function analyzeBlogId($sanitized = [], $blog_id = 0)
    {
        if ($blog_id) {
            return $blog_id;
        }
        $processed_blog_id = $this->getShutDownUtilities()->primeMoverGetProcessedID();
        if ($processed_blog_id) {
            return $processed_blog_id;
        }
        if ( ! empty($sanitized['multisite_blogid_to_export'])) {
            return (int)$sanitized['multisite_blogid_to_export'];
        }
        if ( ! empty($sanitized['multisite_blogid_to_import'])) {
            return (int)$sanitized['multisite_blogid_to_import'];
        }
        if ( ! empty($sanitized['diff_blog_id'])) {
            return (int)$sanitized['diff_blog_id'];
        }
        if ( ! empty($sanitized['prime_mover_blogid'])) {
            return (int)$sanitized['prime_mover_blogid'];
        }
        if ( ! empty($sanitized['blog_id'])) {
            return (int)$sanitized['blog_id'];
        }
        if ( ! empty($sanitized['blogid'])) {
            return (int)$sanitized['blogid'];
        }
        return 0;
    }
}