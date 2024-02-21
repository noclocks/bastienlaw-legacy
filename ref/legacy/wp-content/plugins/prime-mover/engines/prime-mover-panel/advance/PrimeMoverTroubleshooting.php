<?php
namespace Codexonics\PrimeMoverFramework\advance;

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
use Codexonics\PrimeMoverFramework\app\PrimeMoverSettings;
use Codexonics\PrimeMoverFramework\utilities\PrimeMoverTroubleshootingMarkup;
use WP_Debug_Data;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class for handling troubleshooting options
 *
 */
class PrimeMoverTroubleshooting
{
    private $prime_mover;
    private $system_authorization;
    private $settings;
    private $troubleshooting_markup;
    private $maybe_log;
    
    const TROUBLESHOOTING_KEY = 'enable_troubleshooting';
    const PERSIST_TROUBLESHOOTING_KEY = 'persist_troubleshooting';
    const ENABLE_JS_LOG = 'enable_js_troubleshooting';
    const ENABLE_UPLOAD_JS_LOG = 'enable_js_upload_troubleshooting';
    const ENABLE_TURBO_MODE = 'enable_turbo_mode';
    
    /**
     * Constructor
     * @param PrimeMover $PrimeMover
     * @param PrimeMoverSystemAuthorization $system_authorization
     * @param array $utilities
     * @param PrimeMoverSettings $settings
     */
    public function __construct(PrimeMover $PrimeMover, PrimeMoverSystemAuthorization $system_authorization, 
        array $utilities, PrimeMoverSettings $settings, PrimeMoverTroubleshootingMarkup $troubleshooting_markup) 
    {
        $this->prime_mover = $PrimeMover;
        $this->system_authorization = $system_authorization;
        $this->settings = $settings;
        $this->troubleshooting_markup = $troubleshooting_markup;
        $this->maybe_log = false;
    }
    
    /**
     * Get troubleshooting markup
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverTroubleshootingMarkup
     */
    public function getTroubleShootingMarkup()
    {
        return $this->troubleshooting_markup;
    }

    /**
     * Get Prime Mover settings
     * @return \Codexonics\PrimeMoverFramework\app\PrimeMoverSettings
     */
    public function getPrimeMoverSettings() 
    {
        return $this->settings;
    }

    /**
     * Init hooks
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverTroubleshooting::itChecksIfHooksAreOutdated()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverTroubleshooting::itAddsInitHooks()
     */
    public function initHooks() 
    {
        add_action('wp_ajax_prime_mover_save_troubleshooting_settings', [$this,'saveEnableTroubleShootingSetting']);
        add_action('wp_ajax_prime_mover_clear_troubleshooting_log', [$this,'clearTroublesShootingLog']);
        add_action('wp_ajax_prime_mover_save_persist_troubleshooting_settings', [$this,'saveEnablePersistTroubleShootingSetting']);
        
        add_action('wp_ajax_prime_mover_save_js_troubleshooting_settings', [$this,'saveEnableJsTroubleShootingSetting']);
        add_action('wp_ajax_prime_mover_save_uploadjs_troubleshooting_setting', [$this,'saveEnableUploadJsTroubleShootingSetting']);
        add_action('wp_ajax_prime_mover_save_turbomode_setting', [$this,'saveEnableTurboModeSetting']);
        
        add_action('prime_mover_advance_settings', [$this, 'showTroubleShootingSetting'], 10);
        add_action('init', [$this, 'streamDownloadTroubleShootingLog' ], 200);        
        add_filter('prime_mover_persist_troubleshooting_logs', [$this, 'maybePersistTroubleShootingLog'], 10, 1);

        add_action('prime_mover_render_troubleshooting_markup', [$this, 'renderEnableTroubleShootingLogMarkup'], 10);
        add_action('prime_mover_render_troubleshooting_markup', [$this, 'renderDownloadLogMarkup'], 11);
        add_action('prime_mover_render_troubleshooting_markup', [$this, 'renderClearLogCallBack'], 12);
        
        add_action('prime_mover_render_troubleshooting_markup', [$this, 'renderPersistTroubleShootingMarkup'], 13);
        add_action('prime_mover_render_troubleshooting_markup', [$this, 'renderJsTroubleShootingLogMarkup'], 14);
        add_action('prime_mover_render_troubleshooting_markup', [$this, 'renderUploadJsTroubleShootingLogMarkup'], 15);
        add_action('prime_mover_render_troubleshooting_markup', [$this, 'renderTurboModeMarkup'], 16);
        
        add_filter('prime_mover_enable_turbo_mode_setting', [$this, 'maybeEnableTurboModeSetting'], 10, 1);
        add_filter('prime_mover_enable_upload_js_debug', [$this, 'maybeEnableJsUploadErrorAnalysis'], 10, 1);
        add_action('prime_mover_advance_settings', [$this, 'showSiteInfoButton'], 20);
        add_filter('prime_mover_enable_js_error_analysis', [$this, 'maybeEnableJsErrorAnalysis'], 10, 1);
        
        add_action('admin_init', [$this,'exportSiteInformation'], 300);
        add_action('prime_mover_validated_streams', [$this, 'refreshSiteInfoLog'], 10, 1);
        add_filter('prime_mover_disable_serverside_log', [$this, 'maybeDisableTroubleShootingLog'], 10, 1);
        
        add_filter('prime_mover_control_panel_js_object', [$this, 'addSettingsToJs'], 10, 1);
        add_filter('prime_mover_register_setting', [$this, 'registerSetting'], 10, 1);
        
        add_action('prime_mover_before_doing_export', [$this, 'maybeLog']);
        add_action('prime_mover_before_doing_import', [$this, 'maybeLog']);
        add_filter( 'prime_mover_filter_error_output', [ $this, 'maybeAddWpDebugInfo'], 400, 1);
    }
    
    /**
     * Added WP Site Health debug info to site information
     * @param array $error_output
     * @return array
     */
    public function maybeAddWpDebugInfo($error_output = [])
    {
        if (!$this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized()) {
            return $error_output;
        }        
        if (!is_array($error_output) || isset($error_output['wpsitehealth_info'])) {
            return $error_output;
        }      
        
        $debug_class = ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
        $site_health_class = ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
        
        if (!$this->getPrimeMover()->getSystemFunctions()->nonCachedFileExists($debug_class, true)) {
            return $error_output;
        }
        
        if (!$this->getPrimeMover()->getSystemFunctions()->nonCachedFileExists($site_health_class, true)) {
            return $error_output;
        }
        
        if (!class_exists('WP_Debug_Data')) {
            require_once $debug_class;
        }
        
        if (!class_exists('WP_Site_Health')) {
            require_once $site_health_class;
        }
        
        if (!method_exists('WP_Debug_Data', 'debug_data')) {
            return $error_output;
        }
        
        $error_output['wpsitehealth_info'] = WP_Debug_Data::debug_data();        
        return $error_output;
    }
    
    /**
     * Set maybe log property
     * @param boolean $log
     */
    public function setMaybeLog($log = false)
    {
        $this->maybe_log = $log;
    }
    
    /**
     * Get maybe log property value
     * @return boolean
     */
    public function getMaybeLog()
    {
        return $this->maybe_log;
    }
    
    /**
     * Maybe log set 
     */
    public function maybeLog()
    {        
        $setting = $this->getPrimeMoverSettings()->getSetting(self::TROUBLESHOOTING_KEY);
        if (!$setting ) {
            $this->setMaybeLog(false);
            return;
        }
        if ('true' === $setting) {
            $this->setMaybeLog(false);
            return;
        }
        
        $this->setMaybeLog(true);
    }
    
    
    /**
     * Register setting
     * @param array $settings
     * @return boolean[]
     */
    public function registerSetting($settings = [])
    {
        $troubleshooting_keys = [self::TROUBLESHOOTING_KEY, self::PERSIST_TROUBLESHOOTING_KEY, self::ENABLE_UPLOAD_JS_LOG, self::ENABLE_JS_LOG];
        foreach ($troubleshooting_keys as $troubleshooting_key) {
            if (!in_array($troubleshooting_key, $settings)) {
                $settings[$troubleshooting_key] = ['encrypted' => false];                
            }
        }
        return $settings;
    }   
    
    /**
     * Add settings to js object
     * @param array $js_object
     * @return array
     */
    public function addSettingsToJs($js_object = []) {
        
        $js_object['enable_troubleshooting'] = self::TROUBLESHOOTING_KEY;
        $js_object['enable_turbo_mode'] = self::ENABLE_TURBO_MODE;
        $js_object['enable_persist_troubleshooting'] = self::PERSIST_TROUBLESHOOTING_KEY;
        $js_object['enable_uploadjs_troubleshooting'] = self::ENABLE_UPLOAD_JS_LOG;
        $js_object['enable_js_troubleshooting'] = self::ENABLE_JS_LOG;
        
        return $js_object;
    }
    
    /**
     * Check if we need to persist troubleshooting log to several sites
     * @param boolean $ret
     * @return boolean
     */
    public function maybePersistTroubleShootingLog($ret = false)
    { 
        $setting = $this->getPrimeMoverSettings()->getSetting(self::PERSIST_TROUBLESHOOTING_KEY);
        if ( ! $setting ) {
            return false;
        }
        if ('true' === $this->getPrimeMoverSettings()->getSetting(self::PERSIST_TROUBLESHOOTING_KEY)) {
            return true;
        }
        return false;        
    }
    
    /**
     * Maybe disable troubleshooting log
     * Returning TRUE disables the log
     * Returning FALSE enables the log
     * @param boolean $ret
     * @return boolean
     */
    public function maybeDisableTroubleShootingLog($ret = false)
    { 
        return $this->getMaybeLog();
    }
    
    /**
     * Refresh site info log
     * @param string $log_type
     */
    public function refreshSiteInfoLog($log_type = '')
    {
        if ('siteinformation' !== $log_type) {
            return;
        }
        $this->getTroubleShootingMarkup()->refreshSiteInfoLog();
    }
    
    /**
     * Export site information
     */
    public function exportSiteInformation()
    {          
        $this->getTroubleShootingMarkup()->streamDownloadHelper('prime_mover_download_site_info_nonce', 'prime_mover_download_siteinfo', 'prime_mover_site_info', 'siteinformation');
    }
 
    /**
     * Show site info button
     */
    public function showSiteInfoButton()
    {
        $this->getTroubleShootingMarkup()->showSiteInfoButton();
    }
    
    /**
     * Maybe enable js upload error analysis
     * @param boolean $enable
     * @return boolean
     */
    public function maybeEnableJsUploadErrorAnalysis($enable = false)
    { 
        $setting = $this->getPrimeMoverSettings()->getSetting(self::ENABLE_UPLOAD_JS_LOG);
        if ( ! $setting ) {
            return false;
        }
        if ('true' === $this->getPrimeMoverSettings()->getSetting(self::ENABLE_UPLOAD_JS_LOG)) {
            return true;
        }
        return false;
    }

    /**
     * Maybe enable turbo mode setting
     * @param boolean $enable
     * @return boolean
     */
    public function maybeEnableTurboModeSetting($enable = false)
    {
        $setting = $this->getPrimeMoverSettings()->getSetting(self::ENABLE_TURBO_MODE);
        if (!$setting ) {
            return false;
        }
        if ('true' === $this->getPrimeMoverSettings()->getSetting(self::ENABLE_TURBO_MODE)) {
            return true;
        }
        return false;
    }
    
    /**
     * Save enable upload js troubleshooting
     */
    public function saveEnableUploadJsTroubleShootingSetting()
    {
        $success = [ 'true' => esc_html__('Upload chunk debug enabled.', 'prime-mover'), 'false' => esc_html__('Upload chunk debug disabled.', 'prime-mover') ];
        $error = esc_html__('Upload chunk debug update failed', 'prime-mover');
        
        $this->getPrimeMoverSettings()->saveHelper(self::ENABLE_UPLOAD_JS_LOG,  'prime_mover_save_uploadjs_console_setting_nonce', false,
            $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(), self::ENABLE_UPLOAD_JS_LOG, false, $success, $error, 'checkbox', 'settings_checkbox_validation');
    }
    
    /**
     * Save turbo mode setting
     */
    public function saveEnableTurboModeSetting()
    {
        $success = [ 'true' => esc_html__('Turbo mode enabled.', 'prime-mover'), 'false' => esc_html__('Turbo mode disabled.', 'prime-mover') ];
        $error = esc_html__('Turbo mode update failed', 'prime-mover');
        
        $this->getPrimeMoverSettings()->saveHelper(self::ENABLE_TURBO_MODE,  'prime_mover_save_turbomode_setting_nonce', false,
            $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(), self::ENABLE_TURBO_MODE, false, $success, $error, 'checkbox', 'settings_checkbox_validation');
    }
    
    /**
     * Maybe enable js error analysis
     * @param boolean $enable
     * @return boolean
     */
    public function maybeEnableJsErrorAnalysis($enable = false)
    {
        $setting = $this->getPrimeMoverSettings()->getSetting(self::ENABLE_JS_LOG);
        if ( ! $setting ) {
            return false;
        }
        if ('true' === $this->getPrimeMoverSettings()->getSetting(self::ENABLE_JS_LOG)) {
            return true;
        }
        return false;
    }

    /**
     * Save enable js troubleshooting setting
     */
    public function saveEnableJsTroubleShootingSetting()
    {        
        $success = [ 'true' => esc_html__('JavaScript troubleshooting enabled.', 'prime-mover'), 'false' => esc_html__('JavaScript troubleshooting disabled.', 'prime-mover') ];
        $error = esc_html__('JavaScript troubleshooting update failed', 'prime-mover');
        
        $this->getPrimeMoverSettings()->saveHelper(self::ENABLE_JS_LOG,  'prime_mover_save_js_console_setting_nonce', false,
            $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(), self::ENABLE_JS_LOG, false, $success, $error, 'checkbox', 'settings_checkbox_validation');
    }
    
    /**
     * Save enable persist troubleshooting setting
     */
    public function saveEnablePersistTroubleShootingSetting()
    {
        $success = [ 'true' => esc_html__('Persist troubleshooting enabled.', 'prime-mover'), 'false' => esc_html__('Persist troubleshooting disabled.', 'prime-mover') ];
        $error = esc_html__('Persist troubleshooting update failed', 'prime-mover');
        
        $this->getPrimeMoverSettings()->saveHelper(self::PERSIST_TROUBLESHOOTING_KEY, 'prime_mover_save_persist_troubleshooting_settings_nonce', false,
            $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(), self::PERSIST_TROUBLESHOOTING_KEY, false, $success, $error, 'checkbox', 'settings_checkbox_validation' );        
    }
    
    /**
     * Clear troubleshooting log
     */
    public function clearTroublesShootingLog()
    {
        $response = [];
        $clearlog = $this->getPrimeMoverSettings()->prepareSettings($response, 'clear_confirmation', 
            'prime_mover_clear_troubleshooting_settings_nonce', false, $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverSanitizeStringFilter());

        global $wp_filesystem;
        $status = false;
        $message = esc_html__('Clear log error fails.', 'prime-mover');
        
        if ('clearlog' === $clearlog) {
            $log_path = $this->getPrimeMover()->getSystemInitialization()->getTroubleShootingLogPath('migration');
            $status = $wp_filesystem->put_contents($log_path, '', FS_CHMOD_FILE);
        }
        if ($status) {
            $message = esc_html__('Clear log success.', 'prime-mover');
        }
        $this->getPrimeMoverSettings()->returnToAjaxResponse($response, ['status' => $status, 'message' => $message]);
    }
    
    /**
     * Stream download troubleshooting log
     */
    public function streamDownloadTroubleShootingLog()
    {        
        $this->getTroubleShootingMarkup()->streamDownloadHelper('download_troubleshooting_log_nonce', 'prime_mover_download_troubleshooting_log', 'prime_mover_troubleshooting_log', 'migration');
    }
       
    /**
     * Save enable troubleshooting setting
     */
    public function saveEnableTroubleShootingSetting()
    {
        $success = [ 'true' => esc_html__('Troubleshooting enabled.', 'prime-mover'), 'false' => esc_html__('Troubleshooting disabled.', 'prime-mover') ];
        $error = esc_html__('Troubleshooting update failed', 'prime-mover');
        
        $this->getPrimeMoverSettings()->saveHelper(self::TROUBLESHOOTING_KEY, 'prime_mover_save_troubleshooting_settings_nonce', false,
            $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(), self::TROUBLESHOOTING_KEY, false, $success, $error, 'checkbox', 'settings_checkbox_validation' ); 
    }

    /**
     * Show troubleshooting setting
     */
    public function showTroubleShootingSetting()
    {
    ?>
       <h2><?php esc_html_e('Debugging Tools', 'prime-mover') ?></h2>
    <?php     
       $this->outputMarkup();       
    }
    
    /**
     * Render enable troubleshooting log markup
     */
    public function renderEnableTroubleShootingLogMarkup()
    {
        $this->getTroubleShootingMarkup()->renderEnableTroubleShootingLogMarkup(self::TROUBLESHOOTING_KEY);    
    }
 
    /**
     * Render clear log markup
     */
    public function renderClearLogCallBack()
    {
        $this->getTroubleShootingMarkup()->renderClearLogCallBack();    
    }
    
    /**
     * Render download log markup
     */
    public function renderDownloadLogMarkup()
    {
        $this->getTroubleShootingMarkup()->renderDownloadLogMarkup();     
    }
    
    /**
     * Render Persist troubleshooting markup
     */
    public function renderPersistTroubleShootingMarkup()
    {
        $this->getTroubleShootingMarkup()->renderPersistTroubleShootingMarkup(self::PERSIST_TROUBLESHOOTING_KEY);     
    }
    
    /**
     * Render js troubleshooting markup
     */
    public function renderJsTroubleShootingLogMarkup()
    {
        $this->getTroubleShootingMarkup()->renderJsTroubleShootingLogMarkup(self::ENABLE_JS_LOG);   
    }
    
    /**
     * Render js upload log markup
     */
    public function renderUploadJsTroubleShootingLogMarkup()
    {
        $this->getTroubleShootingMarkup()->renderUploadJsTroubleShootingLogMarkup(self::ENABLE_UPLOAD_JS_LOG);      
    }
   
    /**
     * Render turbo mode markup
     */
    public function renderTurboModeMarkup()
    {
        $this->getTroubleShootingMarkup()->renderTurboModeMarkup(self::ENABLE_TURBO_MODE);
    }
    
    /**
     * Output markup
     */
    private function outputMarkup()
    {
        do_action('prime_mover_render_troubleshooting_markup');  
        $this->getTroubleShootingMarkup()->renderClearLogMarkup();
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
}