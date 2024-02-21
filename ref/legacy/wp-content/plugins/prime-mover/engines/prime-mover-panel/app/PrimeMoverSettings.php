<?php
namespace Codexonics\PrimeMoverFramework\app;

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
use Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization;
use Codexonics\PrimeMoverFramework\utilities\PrimeMoverSettingsMarkups;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Settings class
 *
 */
class PrimeMoverSettings
{
    private $prime_mover;
    private $system_authorization;
    private $openssl_utilities;
    private $settings_markup;
    private $component_utilities;
    private $freemius_integration;
    private $config_utilities;
    
        
    /**
     * 
     * @param PrimeMover $PrimeMover
     * @param PrimeMoverSystemAuthorization $system_authorization
     * @param array $utilities
     */
    public function __construct(PrimeMover $PrimeMover, PrimeMoverSystemAuthorization $system_authorization, array $utilities, PrimeMoverSettingsMarkups $settings_markup) 
    {
        $this->prime_mover = $PrimeMover;
        $this->system_authorization = $system_authorization;
        $this->openssl_utilities = $utilities['openssl_utilities'];
        $this->settings_markup = $settings_markup;
        $this->component_utilities = $utilities['component_utilities'];
        $this->freemius_integration = $utilities['freemius_integration'];
        $this->config_utilities = $utilities['config_utilities'];
    }

    /**
     * Get config utilities
     */
    public function getConfigUtilities()
    {
        return $this->config_utilities;
    }
    
    /**
     * Get Freemius integration instance
     */
    public function getFreemiusIntegration()
    {
        return $this->freemius_integration;
    }
    
    /**
     * Get component utilities
     * @return array
     */
    public function getComponentUtilities()
    {
        return $this->component_utilities;
    }
    
    /**
     * Get settings markup
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverSettingsMarkups
     */
    public function getSettingsMarkup()
    {
        return $this->settings_markup;
    }
    
    /**
     * Get openssl utilities
     * @return array
     */
    public function getOpenSSLUtilities()
    {
        return $this->openssl_utilities;
    }
    
    /**
     * Init hooks
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itAddsInitHooks() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itDoesNotAddInitHooksWhenNotAuthorized()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itChecksIfHooksAreOutdated()
     */
    public function initHooks()
    {
        if (!$this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        add_filter('prime_mover_get_setting', [$this, 'getSettingApi'], 10, 5); 
        add_filter('prime_mover_filter_error_output', [$this, 'appendControlPanelSettingsToLog'], 50, 1);         
        
        add_action('prime_mover_before_db_processing', [$this, 'backupControlPanelSettings']);
        add_action('prime_mover_before_only_activated', [$this, 'restoreControlPanelSettings']); 
    }   
 
    /**
     * Convert settings to text area output
     * @param string $setting
     * @param boolean $display_key
     * @param boolean $media_settings
     * @param boolean $decrypt
     * @return string
     */
    public function convertSettingsToTextAreaOutput($setting = '', $display_key = false, $media_settings = false, $decrypt = false)
    {
        $setting = $this->getSetting($setting, $decrypt, '', true);
        $ret = '';
        if (!is_array($setting) || empty($setting)) {
            return $ret;
        }
        
        if ($media_settings) {
            foreach($setting as $blog_id => $resources) {
                if ( ! is_array($resources) ) {
                    continue;
                }
                foreach ($resources as $resource => $values) {
                    $identifier = $resource . '-' . $blog_id;
                    $ret .= $identifier . ':' . $values . PHP_EOL;
                }
            }
            
        } else {
            foreach($setting as $data => $value) {
                if ($display_key) {
                    $ret .= $data . ':' . $value . PHP_EOL;
                } else {
                    $ret .= $value . PHP_EOL;
                }
            }
        }
        
        return $ret;
    }
    
    /**
     * Convert media settings to text area output
     * @return string
     */
    public function convertMediaSettingsToTextAreaOutput($upload_setting = '')
    {
        return $this->convertSettingsToTextAreaOutput($upload_setting, false, true, false);
    }
    
    /**
     * Restore control panel settings after dB processing
     */
    public function restoreControlPanelSettings()
    {        
        $this->getComponentUtilities()->restoreControlPanelSettings();
    }
    
    /**
     * Backup control panel settings before dB processing
     */
    public function backupControlPanelSettings()
    {
        $this->getComponentUtilities()->backupControlPanelSettings();
    }
   
    /**
     * Append control panel settings to log
     * @param array $error_output
     * @return array
     */
    public function appendControlPanelSettingsToLog($error_output = [])
    {
        if ( ! is_array($error_output) ) {
            return $error_output;
        }
        
        $error_output['prime_mover_panel_settings'] = $this->getAllPrimeMoverSettings();        
        return $error_output;
    }
    
    /**
     * Get setting API for other plugins
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itGetSettingsApiWhenSet()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itReturnsOriginalValueWhenSettingNotSet()
     * @param mixed $value
     * @param string $setting
     * @param boolean $decrypt
     * @param string $default
     * @param boolean $return_default_if_no_key
     * @return mixed|boolean|string
     */
    public function getSettingApi($value, $setting = '', $decrypt = false, $default = '', $return_default_if_no_key = false) 
    {
        if (!$setting ) {
            return $value;
        }
        return $this->getSetting($setting, $decrypt, $default, $return_default_if_no_key);
    }
    
    /**
     * Get control panel settings name
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itSavesSettingWhenAllIsSet
     */
    private function getControlPanelSettingsName()
    {        
        return $this->getPrimeMover()->getSystemInitialization()->getControlPanelSettingsName();
    }
    
    /**
     * Check if we load gearbox related settings
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itReturnsFalseIfGearBoxPluginIsDeactivated() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itReturnsTrueIfGearBoxPluginIsActivated()
     * @return boolean
     */
    public function maybeLoadGearBoxRelatedSettings()
    {
        $ret = false;
        if (defined('PRIME_MOVER_GEARBOX_VERSION')) {
            $ret = true;
        }
        return $ret;
    }
    
    /**
     * Handle setting error
     * @param array $settings_post
     * @param array $response
     * @param string $setting
     */
    private function handleSettingsError($settings_post = [], $response = [], $setting = '')
    {
        $error_msg = '<ul>';
        foreach ($settings_post['validation_errors'] as $error) {
            $error_msg .= '<li>' . $error . '</li>';
        }
        $error_msg .= '</ul>';
        
        $response['message'] = $error_msg;
        return $response;
    }
    
    /**
     * Prepare settings posted from AJAX
     * @tested TestPrimeMoverDeleteUtilities::itDeleteAllBackups()
     * @param array $response
     * @param string $setting_string
     * @param string $nonce_name
     * @param boolean $settings_exist_check
     * @return mixed
     */
    public function prepareSettings(array $response, $setting_string = '', $nonce_name = '', $settings_exist_check = true, 
        $setting_filter = FILTER_SANITIZE_FULL_SPECIAL_CHARS, $validation_id = '')
    {
        $response['save_status'] = false;
        
        if (! $this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized()) {
            $response['message'] = esc_html__('Error ! Unauthorized', 'prime-mover');
            wp_send_json($response);
        }
        
        if ( ! $nonce_name || ! $setting_string ) {
            $response['message'] = esc_html__('Error ! Undefined settings.', 'prime-mover');
            wp_send_json($response);
        }
        
        $args = [
            $setting_string => $setting_filter,
            'savenonce' => $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'action' => $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
        ];
        
        $settings_post = $this->getPrimeMover()->getSystemInitialization()->getUserInput('post', $args, $validation_id, '', 0, true, true);
        if ( ! empty($settings_post['validation_errors']) && is_array($settings_post['validation_errors'])) {
            $response = $this->handleSettingsError($settings_post, $response, $setting_string);
            wp_send_json($response);
        }
        if ( ! isset($settings_post['savenonce'] ) ) {
            $response['message'] = esc_html__('Error ! Unauthorized', 'prime-mover');
            wp_send_json($response);
        }
        
        if ( ! $this->getPrimeMover()->getSystemFunctions()->primeMoverVerifyNonce($settings_post['savenonce'], $nonce_name) ) {
            $response['message'] = esc_html__('Error ! Unauthorized', 'prime-mover');
            wp_send_json($response);
        } 
        
        if ($settings_exist_check && empty($settings_post[$setting_string])) {
            $response['message'] = esc_html__('Error ! Invalid setting being saved. Please check again.', 'prime-mover');
            wp_send_json($response);
        }
        if (FILTER_VALIDATE_INT === $setting_filter && $settings_post[$setting_string] < 0) {
            $response['message'] = esc_html__('Error ! This value cannot be negative. Please check again.', 'prime-mover');
            wp_send_json($response);
        }
        return trim($settings_post[$setting_string]);        
    }
    
    /**
     * Return settings processing to AJAX response
     * @param array $response
     * @param array $result
     * @tested TestPrimeMoverDeleteUtilities::itDeleteAllBackups()
     */
    public function returnToAjaxResponse(array $response, array $result)
    {
        $response['save_status'] = false;
        $response['message'] = esc_html__('Error!', 'prime-mover');
        
        if (isset($result['status'])) {
            $response['save_status'] = $result['status'];
        }
        if (isset($result['message'])) {
            $response['message'] = $result['message'];
        }       
        if (isset($result['saved_settings'])) {
            $response['saved_settings'] = $result['saved_settings'];
        }
        if (isset($result['reload'])) {
            $response['reload'] = $result['reload'];
        }
        wp_send_json($response);        
    }
    
    /**
     * Get Prime Mover
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMover
     * @compatible 5.6
     */
    public function getPrimeMover()
    {
        return $this->prime_mover;
    }
    
    /**
     * Get a specific Prime Mover setting
     * 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itReturnsFalseIfSettingDoesNotExists()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itReturnsSettingIfItExists()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itGetsEncryptedSetting()
     * 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itReturnsOriginalValueIfEncryptedKeyNotSet() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itReturnsOriginalValueIfValueIsNotEncoded()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itReturnsOriginalArraySettingIfNotEncoded() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itGetsEncryptedArraySetting() 
     * @param string $setting
     * @param boolean $decrypt
     * @param string $default
     * @param boolean $return_default_if_no_key
     * @return boolean|string
     */
    public function getSetting($setting = '', $decrypt = false, $default = '', $return_default_if_no_key = false) 
    {
        if (!$setting ) {
            return false;
        }
        if ($decrypt && !$this->getPrimeMover()->getSystemInitialization()->getDbEncryptionKey() && $return_default_if_no_key) {
            return $default;
        }
        if ($decrypt && $this->getPrimeMover()->getSystemInitialization()->getDbEncryptionKey() && $this->isKeySignatureChanged() && $return_default_if_no_key) {
            return $default;
        }
        $setting_name = $this->getControlPanelSettingsName();
        $settings = $this->getPrimeMover()->getSystemFunctions()->getSiteOption($setting_name, false, true, true);
        if (isset($settings[$setting])) {
            return $this->maybeDecryptSetting($settings[$setting], $decrypt);
        }
        if ($default) {
            return $default;
        }
        return false;
    }
    
    /**
     * Get all Prime Mover settings
     * @return mixed|boolean|NULL|array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itGetsAllPrimeMoverSettings()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itReturnsOriginalValueIfEncryptedKeyNotSet()
     */
    public function getAllPrimeMoverSettings() 
    {
        return $this->getComponentUtilities()->getAllPrimeMoverSettings();
    }
 
    /**
     * Delete all settings
     * @return boolean
     */
    public function deleteAllPrimeMoverSettings()
    {        
        return $this->getComponentUtilities()->deleteAllPrimeMoverSettings();
    }
    
    /**
     * Restore all Prime Mover settings
     * @param array $settings
     */
    public function restoreAllPrimeMoverSettings($settings = [])
    {        
        $this->getComponentUtilities()->restoreAllPrimeMoverSettings($settings);
    }
    
    /**
     * Decrypt array setting if requested
     * @param array $value
     * @param string $encryption_key
     * @return string[]
     */
    private function decryptArraySetting($value = [], $encryption_key = '')
    {
        return $this->getOpenSSLUtilities()->decryptArraySetting($value, $encryption_key);
    }
    
    /**
     * Maybe decrypt setting
     * @param $value
     * @param boolean $decrypt
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itGetsEncryptedSetting()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itReturnsOriginalValueIfEncryptedKeyNotSet()
     */
    protected function maybeDecryptSetting($value, $decrypt = false)
    {
        return $this->getOpenSSLUtilities()->maybeDecryptSetting($value, $decrypt);
    }
        
    /**
     * Encrypt array setting if requested
     * @param array $value
     * @param string $encryption_key
     * @return string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itSavesEncryptedArraySettings() 
     */
    private function encryptArraySetting($value = [], $encryption_key = '')
    {
        return $this->getOpenSSLUtilities()->encryptArraySetting($value, $encryption_key);
    }
    
    /**
     * Encrypt setting
     * @param $value
     * @param boolean $encrypt
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itSavesEncryptedSettings() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itDoesNotSaveEncryptedSettingWhenKeyIsNotSet() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itSavesEncryptedArraySettings() 
     */
    protected function maybeEncryptSetting($value, $encrypt = false) 
    {        
        return $this->getOpenSSLUtilities()->maybeEncryptSetting($value, $encrypt);
    }
    
    /**
     * Save setting
     * 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itSavesSettingWhenAllIsSet()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itReturnFalseIfSettingDoesNotExist()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itSavesNewSettings()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itSavesEncryptedSettings() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itDoesNotSaveEncryptedSettingWhenKeyIsNotSet() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSettings::itSavesEncryptedArraySettings() 
     * 
     * @param string $setting
     * @param $value
     * @param boolean $encrypt
     * @return boolean
     */
    public function saveSetting($setting = '', $value = null, $encrypt = false) 
    {
        if ( ! $setting ) {
            return false;
        }
        if ( ! $this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized()) {
            return false;
        }
        $settings_array = $this->getAllPrimeMoverSettings();
        $setting_name = $this->getControlPanelSettingsName();
        if ( ! is_array($settings_array) ) {
            $settings_array = [];
        }
        $value = $this->maybeEncryptSetting($value, $encrypt);
        $settings_array[$setting] = $value;
 
        $this->getPrimeMover()->getSystemFunctions()->updateSiteOption($setting_name, $settings_array, true);
    }
    
    /**
     * Save settings helper
     * @param string $data_indicator
     * @param string $nonce_name
     * @param boolean $settings_check
     * @param string $sanitizing_filter
     * @param string $setting_name
     * @param boolean $encrypt
     * @param string | array $success_message
     * @param string $error_message
     */
    public function saveHelper($data_indicator = '', $nonce_name = '', $settings_check = false,
        $sanitizing_filter = FILTER_SANITIZE_NUMBER_INT, $setting_name = '', $encrypt = false, $success_message = null, 
        $error_message = '', $datatype = 'text', $validation_id = '' )
    {
        $response = [];
        $setting_prepared = $this->prepareSettings($response, $data_indicator, $nonce_name, $settings_check, $sanitizing_filter, $validation_id);
        
        $this->saveSetting($setting_name, $setting_prepared, $encrypt);
        $message = $error_message;
        $status = false;
        
        if ($setting_prepared) {
            $status = true;
            if ('text' === $datatype) {
                $message = $success_message;
            }
            if ('checkbox' === $datatype && isset($success_message[$setting_prepared])) {
                $message = $success_message[$setting_prepared];
            }
        }
        
        $result = ['status' => $status, 'message' => $message];
        $this->returnToAjaxResponse($response, $result);
    }
    
    /**
     * Maybe update all encrypted settings when a key is changed
     * This should support even if the original key is empty (not yet encrypted before)
     * @param string $original_key
     * @param string $new_key
     */
    public function maybeUpdateAllEncryptedSettings($original_key = '', $new_key = '')
    {
        if (!$this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if (!$new_key) {
            return;
        }
        if ($original_key === $new_key) {
            return;   
        }
        
        $original_settings = $this->getAllPrimeMoverSettings();
        if (!$original_settings) {
            return;
        }
        $new_settings = $original_settings;     
        $encrypted_settings = $this->getEncryptedSettings();
        if (empty($encrypted_settings)) {
            return;
        }
        $encrypted_settings = array_keys($encrypted_settings);
        foreach ($original_settings as $setting => $original_value) {
            if (!in_array($setting, $encrypted_settings)) {
                continue;
            }
            
            $decrypted_array = [];            
            $decrypted_string = '';            
            
            if (is_array($original_value)) {
                $decrypted_array = $this->decryptArraySetting($original_value, $original_key);
                $new_settings[$setting] = $this->encryptArraySetting($decrypted_array, $new_key);
                
            } else {
               
                $decrypted_string = $this->getOpenSSLUtilities()->openSSLDecrypt($original_value, $original_key);
                $new_settings[$setting] = $this->getOpenSSLUtilities()->openSSLEncrypt($decrypted_string, $new_key); 
            }            
        }
        
        $this->restoreAllPrimeMoverSettings($new_settings);
        
        do_action('prime_mover_maybe_update_other_encrypted_settings', $original_key, $new_key);
    }
    
    /**
     * Get encrypted setting
     * @return array
     */
    protected function getEncryptedSettings()
    {
        $registered_settings = apply_filters('prime_mover_register_setting', []);
        if (is_array($registered_settings) && !empty($registered_settings)) {
            return wp_filter_object_list($registered_settings, ['encrypted' => true]);
        }
        return [];
    }
    
    /**
     * Get encryption from WordPress configuration file
     * @return string
     */
    public function getEncryptionKeyFromConfig()
    {
        $key = '';
        if (defined('PRIME_MOVER_DB_ENCRYPTION_KEY') && PRIME_MOVER_DB_ENCRYPTION_KEY) {
            $key = PRIME_MOVER_DB_ENCRYPTION_KEY;
        }
        return $key;
    }
    
    /**
     * Generate key
     * @param boolean $http_api
     * @return string
     */
    public function generateKey($http_api = false)
    {
        if (!$this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized()) {
            return wp_generate_password(64, false, false);
        }
        $use_native = false;
        $freemius_object = $this->getFreemiusIntegration()->getFreemius();
        if (!is_object($freemius_object)) {
            $use_native = true;
        }
        if (!$use_native && !method_exists($freemius_object, '_get_license')) {
            $use_native = true;
        }
        $license = $this->getFreemiusIntegration()->getFreemius()->_get_license();
        if (!is_object($license)) {
            $use_native = true;
        }
        if (false === $use_native && is_object($license) && !property_exists($license, 'secret_key')) {
            $use_native = true;
        }
        if ($use_native) {
            return wp_generate_password(64, false, false);
        } else {
            $key = $license->secret_key;
            return $this->getPrimeMover()->getSystemFunctions()->hashString($key, $http_api);
        }
    }
  
    /**
     * Get lock files folder path
     * As substitute for ABSPATH
     * @return string
     */
    public function getLockFilesFolderPath()
    {
        return trailingslashit(wp_normalize_path($this->getPrimeMover()->getSystemInitialization()->getLockFilesFolder()));
    }
    
    /**
     * Is key signature changed
     * @return boolean
     */
    public function isKeySignatureChanged()
    {
        $auth = $this->getPrimeMover()->getSystemInitialization()->getAuthKey();
        if (!$auth) {
            return true;
        }
        
        $enc_key_cons = $this->getPrimeMover()->getSystemInitialization()->getDbEncryptionKey();
        $auth = sha1($auth);
        $hash = sha1($enc_key_cons . $auth);
        
        $ext = '.primemoversignature_file';
        $file = $hash . $ext;
        $path = $this->getLockFilesFolderPath() . $file;
        
        $changed = true;
        if ($this->getPrimeMover()->getSystemFunctions()->nonCachedFileExists($path)) {
            $changed = false;
        }
        return $changed;
    }
}