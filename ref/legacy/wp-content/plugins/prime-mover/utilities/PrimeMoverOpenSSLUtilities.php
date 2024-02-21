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
use Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover OpenSSL Utilities
 * Helper functionality for doing OpenSSL related operations
 * This implementation relies on PHP native OpenSSL library
 * And does not use any command line interface of OpenSSL for maximum compatibility
 */
class PrimeMoverOpenSSLUtilities
{   
    /**
     * Cipher method
     * @var string
     */
    private $cipher = 'AES-256-CBC';
    
    /**
     * 
     * $system_initialization
     */
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
     * Get system authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     */
    public function getSystemAuthorization()
    {
        return $this->getSystemInitialization()->getSystemAuthorization();
    }

    /**
     * Get system initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     */
    public function getSystemInitialization()
    {        
        return $this->system_initialization;
    }
    
    /**
     * Init hooks
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverOpenSSLUtilities::itAddsInitHooks() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverOpenSSLUtilities::itChecksIfHooksAreOutdated() 
     */
    public function initHooks()
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        add_filter('prime_mover_validate_imported_package', [$this, 'verifySignature'], 10, 2);
        add_action('prime_mover_encrypted_db_command_generated', [ $this, 'addKeySignature'], 10, 2);        
        
        add_filter('prime_mover_decrypt_data', [$this, 'decryptData'], 10, 1);
        add_action('prime_mover_after_copying_media', [$this, 'addMediaKeySignature'], 10, 3);        
        
        add_filter('prime_mover_filter_export_user_data', [$this, 'maybeEncryptUserData'], 10, 1);
        add_filter('prime_mover_filter_export_usermeta_data', [$this, 'maybeEncryptUserData'], 10, 1);
        add_filter('prime_mover_force_encrypt_data', [$this, 'forceEncryptUserData'], 10, 1);
    }
    
    /**
     * Force encrypt user data when written to disk upon request
     * If encryption key is not set, it will return original data
     * @param string $userdata
     * @return string
     */
    public function forceEncryptUserData($userdata = '')
    {
        $db_encryption_key = $this->getSystemInitialization()->getDbEncryptionKey();
        if ($db_encryption_key) {
            return $this->openSSLEncrypt($userdata, $db_encryption_key);
        } else {
            return $userdata;
        } 
    }
    
    /**
     * Maybe encrypt user data
     * @param string $userdata
     * @return string
     */
    public function maybeEncryptUserData($userdata = '')
    {
        return $this->encryptData($userdata);
    }

    /**
     * Add encrypted media files signature
     * @param boolean $enable_media_encryption
     * @param number $blog_id
     * @param array $ret
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverOpenSSLUtilities::itAddsMediaKeySignatureToPackageIfEncrypted()
     */
    public function addMediaKeySignature($enable_media_encryption = false, $blog_id = 0, $ret = [])
    {
        if (isset($ret['encrypted_media']) && true === $ret['encrypted_media']) {
            $this->keySignatureGenerator($ret, $blog_id, $this->getSystemInitialization()->getMediaEncryptedSignature());
        }       
    }
    
    /**
     * Key signature generator
     * @param array $ret
     * @param number $blog_id
     * @param string $filename
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverOpenSSLUtilities::itAddsMediaKeySignatureToPackageIfEncrypted()
     */
    protected function keySignatureGenerator($ret = [], $blog_id = 0, $filename = '')
    {
        if ( ! is_array($ret) || ! $blog_id || ! $filename) {
            return;
        }
        if (empty($ret['temp_folder_path'])) {
            return;
        }
        
        global $wp_filesystem;
        $path = $ret['temp_folder_path'];
        
        $signature = $this->generateEncryptedSignatureString($ret, $blog_id);
        $source = $path . $filename;
        $wp_filesystem->put_contents($source, $signature);       
        
        $basename = basename($source);
        $local_name = trailingslashit(basename($path)) . $basename;
        
        apply_filters('prime_mover_add_file_to_tar_archive', $ret, $ret['target_zip_path'], 'ab', $source, $local_name , 0, 0, $blog_id, false, false); 
    }
    
    /**
     * Generate encrypted signature string
     * @param array $ret
     * @param number $blog_id
     * @return string
     */
    public function generateEncryptedSignatureString($ret = [], $blog_id = 0)
    {
        $db_encryption_key = $this->getSystemInitialization()->getDbEncryptionKey();
        
        $target_blog_id = $blog_id;
        if ( ! empty($ret['prime_mover_export_targetid']) ) {
            $target_blog_id = $ret['prime_mover_export_targetid'];
        }
        
        return $this->openSSLEncrypt($target_blog_id, $db_encryption_key);
    }
    
    /**
     * Decrypt data
     * @param string $encrypted
     * @return string|boolean
     */
    public function decryptData($encrypted = '')
    {
        $db_encryption_key = $this->getSystemInitialization()->getDbEncryptionKey();        
        return $this->openSSLDecrypt($encrypted, $db_encryption_key);
    }
    
    /**
     * Encrypt data
     * @param string $data
     * @param string $db_encryption_key
     * @param mixed $maybe_encrypt
     * @return string
     */
    public function encryptData($data = '', $db_encryption_key = '', $maybe_encrypt = null)
    {
        if (!$db_encryption_key) {
            $db_encryption_key = $this->getSystemInitialization()->getDbEncryptionKey();  
        }
        if (is_null($maybe_encrypt)) {
            $maybe_encrypt = $this->getSystemInitialization()->getMaybeEncryptExportData();
        }
        if ($maybe_encrypt) {
            return $this->openSSLEncrypt($data, $db_encryption_key);
        } else {
            return $data;
        }        
    }
    
    /**
     * Add key signature for encrypted exports
     * For easier validation in import end.
     * @param array $ret
     * @param number $blog_id
     */
    public function addKeySignature($ret = [], $blog_id = 0)
    {        
        $this->keySignatureGenerator($ret, $blog_id, $this->getSystemInitialization()->getSignatureFile());
    }
    
    /**
     * Verify package signature on the import end
     * @param array $ret
     * @param number $blogid_to_import
     * @return array
     */
    public function verifySignature($ret = [], $blogid_to_import = 0)
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        
        if (empty($ret['unzipped_directory'])) {
            return $ret;
        }
        
        $unzipped_directory	= $ret['unzipped_directory'];
        $signature_file	= $unzipped_directory . $this->getSystemInitialization()->getSignatureFile();
        
        global $wp_filesystem;        
        
        if ( ! $wp_filesystem->exists($signature_file)) {
            return $ret;           
        }
        $blogid_to_import = (int) $blogid_to_import;
        $signature_content = $wp_filesystem->get_contents($signature_file);
        
        if ( ! $this->verifyKeyHelper($signature_content, $blogid_to_import)) {
            $ret['error']	= esc_html__('Unable to read encrypted package.', 'prime-mover');
            return $ret;
        }        
        
        return $ret;
    }
    
    /**
     * Verify key helper
     * @param string $signature_content
     * @param number $blogid_to_import
     * @param boolean $ret_array_on_error
     * @return boolean|number[]
     */
    public function verifyKeyHelper($signature_content = '', $blogid_to_import = 0, $ret_array_on_error = false)
    {
        $ret = false;
        $ret_array = [];
        if ( ! $signature_content || ! $blogid_to_import) {
            return $ret;
        }
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        
        $db_encryption_key = $this->getSystemInitialization()->getDbEncryptionKey();        
        $imported_id = (int)$this->openSSLDecrypt($signature_content, $db_encryption_key, $ret_array_on_error);
        $blogid_to_import = (int) $blogid_to_import;
        if ($ret_array_on_error) {
            $ret_array['id_to_validate'] = $blogid_to_import;
            $ret_array['id_parsed_from_signature'] = $imported_id;
            return $ret_array;
        }
        
        if ($imported_id !== $blogid_to_import) {
           return $ret;
        }   
        
        return true;
    }
    
    /**
     * Checks if current site is capable to decrypt with a given encrypted package.
     * @param string $tmp_path
     * @param number $blog_id
     * @param string $signature_to_verify
     * @return boolean|string|boolean|number[]
     */
    public function isSiteCapableToDecrypt($tmp_path = '', $blog_id = 0, $signature_to_verify = '')
    {
        if ( ! $tmp_path || ! $blog_id ) {
            return false;
        }
        if ($signature_to_verify) {
            
            return $this->verifyPrimeMoverPackageSignature($signature_to_verify, $blog_id );  
            
        } else {
            $za = new \ZipArchive();
            $zip = $za->open($tmp_path);
            if (true !== $zip) {
                return false;
            }
            $signature_index = $za->locateName($this->getSystemInitialization()->getSignatureFile(), \ZIPARCHIVE::FL_NODIR);
            if ( ! $signature_index ) {
                return '';
            }
            $signature_to_verify = $za->getFromIndex($signature_index); 
        }
               
        return $this->verifyPrimeMoverPackageSignature($signature_to_verify, $blog_id );       
    }
  
    /**
     * is Site capable to decrypt this package?
     * @param string $tmp_path
     * @param number $blog_id
     * @param array $tar_config
     * @param boolean $tarmode
     * @param string $encryption_status
     * @return boolean
     * 
     * This is designed to work for both zip and tar package.
     */
    public function maybeCanDecryptPackage($tmp_path = '', $blog_id = 0, $tar_config = [], $tarmode = false, $encryption_status = 'false')
    {
        if ('false' === $encryption_status || false === $encryption_status) {
            return true;
        }
        $signature_to_verify = '';
        if ($tarmode && !empty($tar_config['prime_mover_encrypted_signature'])) {
            $signature_to_verify = $tar_config['prime_mover_encrypted_signature'];
        }
        $result = $this->isSiteCapableToDecrypt($tmp_path, $blog_id, $signature_to_verify);
        if (isset($result['result'])) {
            return $result['result'];
        }
        return false;
    }    
    
    /**
     * Checks if prime mover signature is valid
     * @param string $data
     * @param number $blog_id
     * @return boolean[]|string[]
     */
    public function verifyPrimeMoverPackageSignature($data = '', $blog_id = 0)
    {
        $ret = [];
        $ret['result'] = false;
        if ( ! $data || ! $blog_id) {
            $ret['error'] = esc_html__("Signature or blog ID is not set. Please check.", 'prime-mover');
            return $ret;
        }
        if (!$this->isOpenSSLCustomer($blog_id)) {
            $ret['error'] = sprintf(esc_html__("Restoring encrypted package is a PRO feature. Please %s to restore this package.", 'prime-mover'), '<a href="' . 
                esc_url($this->getSystemInitialization()->getUpgradeUrl()) . '">' . esc_html__('upgrade to Prime Mover PRO', 'prime-mover') . '</a>');
            return $ret;
        }
        $validation_result = $this->verifyKeyHelper($data, $blog_id, true);
        $id_to_validate = $validation_result['id_to_validate'];
        $id_parsed_from_signature = $validation_result['id_parsed_from_signature'];
        if ( ! $id_parsed_from_signature ) {
            $ret['error'] = esc_html__("Unable to read encrypted package database. A correct decryption key is required to restore this database.", 'prime-mover');
            return $ret;
        }
        if ($id_parsed_from_signature !== $id_to_validate) {
            $ret['error'] = $this->getSystemInitialization()->returnCommonWrongTargetSiteError();
            return $ret;
        }
        $ret['result'] = true;
        return $ret;
    }
    
    /**
     * Checks if OpenSSL customer
     * @param number $blog_id
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverOpenSSLUtilities::itChecksIfOpenSSLCustomer() 
     */
    protected function isOpenSSLCustomer($blog_id = 0)
    {
        $customer = false;
        if (true === apply_filters('prime_mover_is_loggedin_customer', false)) {
            $customer = true;
        }
        if (is_multisite() && $customer) {
            if (true === apply_filters('prime_mover_multisite_blog_is_licensed', false, $blog_id)) {
                $customer = true;
            } else {
                $customer = false;
            }
        }
        return $customer;        
    }
    
    /**
     * Get cipher
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itReturnsOriginalArraySettingIfNotEncoded()
     */
    public function getCipherMethod()
    {
        return $this->cipher;
    }
    
    /**
     * Decrypt setting using PHP openssl functions: http://php.net/manual/en/function.openssl-encrypt.php#example-968
     * @param string $ciphertext
     * @param string $key
     * @param boolean $return_null_on_false
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itReturnsOriginalArraySettingIfNotEncoded()
     */
    public function openSSLDecrypt($ciphertext = '', $key = '', $return_null_on_false = false)
    {
        if ( ! $ciphertext || ! $key ) {
            if ($return_null_on_false) {
                return null;
            } else {
                return $ciphertext;
            }            
        }
        $cipher_method = $this->getCipherMethod();
        $c = base64_decode($ciphertext);
        $ivlen = openssl_cipher_iv_length($cipher_method);
        $iv = substr($c, 0, $ivlen);
        $sha2len = 32;
        $hmac = substr($c, $ivlen, $sha2len);
        $ciphertext_raw = substr($c, $ivlen+$sha2len);
        $original_plaintext = @openssl_decrypt($ciphertext_raw, $cipher_method, $key, OPENSSL_RAW_DATA, $iv);
        if ( false === $original_plaintext  && $return_null_on_false) {
            return null;
        }
        $calcmac = hash_hmac('sha256', $ciphertext_raw, $key, true);
        if ( ! is_string($hmac) || ! is_string($calcmac)) {
            return $ciphertext;
        }
        if (hash_equals($hmac, $calcmac))
        {
            return $original_plaintext;
        }
        return $ciphertext;
    }
    
    /**
     * Encrypt setting using PHP openssl functions: http://php.net/manual/en/function.openssl-encrypt.php#example-968
     * @param string $plaintext
     * @param string $key
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itSavesEncryptedSettings()
     */
    public function openSSLEncrypt($plaintext = '', $key = '')
    {
        $cipher_method = $this->getCipherMethod();
        $ivlen = openssl_cipher_iv_length($cipher_method);
        $iv = openssl_random_pseudo_bytes($ivlen);
        
        $ciphertext_raw = openssl_encrypt($plaintext, $cipher_method, $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $ciphertext_raw, $key, true);
        return base64_encode( $iv.$hmac.$ciphertext_raw );
    }    

    /**
     * Encrypt setting
     * @param $value
     * @param boolean $encrypt
     * @param string $encryption_key
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itSavesEncryptedSettings()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itDoesNotSaveEncryptedSettingWhenKeyIsNotSet()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itSavesEncryptedArraySettings()
     */
    public function maybeEncryptSetting($value, $encrypt = false, $encryption_key = '')
    {
        if (!$encrypt ) {
            return $value;
        }
        
        if (!$encryption_key) {
            $encryption_key = $this->getSystemInitialization()->getDbEncryptionKey();
        }
        
        if (!$encryption_key ) {
            return $value;
        }
        
        if (is_array($value)) {
            return $this->encryptArraySetting($value, $encryption_key);
        }
        return $this->openSSLEncrypt($value, $encryption_key);
    }
    
    /**
     * Encrypt array setting if requested
     * @param array $value
     * @param string $encryption_key
     * @return string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itSavesEncryptedArraySettings()
     */
    public function encryptArraySetting($value = [], $encryption_key = '')
    {
        $encrypted = [];
        foreach ($value as $k => $v) {
            $encrypted[$k] = $this->openSSLEncrypt($v, $encryption_key);
        }
        return $encrypted;
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itGetsEncryptedSetting()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itReturnsOriginalValueIfEncryptedKeyNotSet()
     * @param $value
     * @param boolean $decrypt
     * @param string $encryption_key
     * @return string
     */
    public function maybeDecryptSetting($value, $decrypt = false, $encryption_key = '')
    {
        if (!$decrypt ) {
            return $value;
        }
        
        if (!$encryption_key) {
            $encryption_key = $this->getSystemInitialization()->getDbEncryptionKey();
        }
        
        if ( ! $encryption_key ) {
            return $value;
        }
        
        if (is_array($value)) {
            return $this->decryptArraySetting($value, $encryption_key);
        }
        return $this->openSSLDecrypt($value, $encryption_key);
    }
    
    /**
     * Decrypt array setting if requested
     * @param array $value
     * @param string $encryption_key
     * @return string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itReturnsOriginalArraySettingIfNotEncoded()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itGetsEncryptedArraySetting()
     */
    public function decryptArraySetting($value = [], $encryption_key = '')
    {
        if (!$encryption_key) {
            return $value;
        }
        $decrypted = [];
        foreach ($value as $k => $v) {
            $decrypted[$k] = $this->openSSLDecrypt($v, $encryption_key);
        }
        return $decrypted;
    }
}