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
use Codexonics\PrimeMoverFramework\utilities\PrimeMoverUploadSettingMarkup;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class for handling advance upload related settings
 *
 */
class PrimeMoverUploadSettings
{
    private $prime_mover;
    private $system_authorization;
    private $settings;
    private $upload_setting_markup;
    
    const UPLOAD_CHUNK_SIZE = 'upload_chunk_size';
    const UPLOAD_REFRESH_INTERVAL = 'upload_refresh_interval';
    const UPLOAD_RETRY_LIMIT = 'upload_retry_limit';
    
    const DROPBOX_UPLOAD_CHUNK_SIZE = 'dropbox_upload_chunk_size';    
    const UPLOAD_RETRY_LIMIT_DEFAULT = 150;
    const DROPBOX_UPLOAD_CHUNKSIZE_DEFAULT = 1048576;
    
    const GDRIVE_UPLOAD_CHUNKSIZE_DEFAULT = 1048576;
    const GDRIVE_UPLOAD_CHUNK_SIZE = 'gdrive_upload_chunk_size'; 
   
    const GDRIVE_DOWNLOAD_CHUNKSIZE_DEFAULT = 1048576;
    const GDRIVE_DOWNLOAD_CHUNK_SIZE = 'gdrive_download_chunk_size'; 
    
    /**
     * Constructor
     * @param PrimeMover $PrimeMover
     * @param PrimeMoverSystemAuthorization $system_authorization
     * @param array $utilities
     * @param PrimeMoverSettings $settings
     */
    public function __construct(PrimeMover $PrimeMover, PrimeMoverSystemAuthorization $system_authorization, 
        array $utilities, PrimeMoverSettings $settings,  PrimeMoverUploadSettingMarkup $upload_setting_markup) 
    {
        $this->prime_mover = $PrimeMover;
        $this->system_authorization = $system_authorization;
        $this->settings = $settings;
        $this->upload_setting_markup = $upload_setting_markup;
    }

    /**
     * Get system initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     */
    public function getSystemInitialization()
    {
        return $this->getPrimeMover()->getSystemInitialization();
    }
    
    /**
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     */
    public function getSystemFunctions()
    {
        return $this->getPrimeMover()->getSystemFunctions();
    }
    
    /**
     * Get default refresh upload interval
     * @return number|string
     */
    public function getDefaultUploadRefreshInterval()
    {
        return $this->getSystemInitialization()->getDefaultUploadRefreshInterval();
    }
    
    /**
     * Get upload setting markup
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverUploadSettingMarkup
     */
    public function getUploadSettingMarkup()
    {
        return $this->upload_setting_markup;
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
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverUploadSettings::itChecksIfHooksAreOutdated()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverUploadSettings::itAddsInitHooks()
     */
    public function initHooks() 
    {
        add_action('wp_ajax_prime_mover_save_uploadchunksize_settings', [$this,'saveUploadChunkSizeSetting']);
        add_action('wp_ajax_prime_mover_save_upload_refresh_interval', [$this,'saveUploadRefreshInterval']);
        add_action('wp_ajax_prime_mover_save_upload_retrylimit', [$this,'saveUploadRetryLimit']);
        
        add_action('wp_ajax_prime_mover_save_dropbox_chunksize_setting', [$this,'saveDropboxChunkUploadSetting']);        
        add_action('prime_mover_advance_settings', [$this, 'showUploadSetting'], 15);      
        add_filter('prime_mover_get_slice_size', [$this,'maybeUpdateUploadChunkSizeSetting'], 10, 1);         
        
        add_action('prime_mover_render_upload_params_markup', [$this, 'renderUploadChunkSizeSetting'], 10);        
        add_filter('prime_mover_filter_upload_refresh_interval', [$this,'maybeUpdateUploadRefreshIntervalSetting'], 10, 1);        
        add_action('prime_mover_render_upload_params_markup', [$this, 'renderUploadRefreshIntervalSetting'], 11);
       
        add_filter('prime_mover_filter_retry_limit', [$this,'maybeUpdateUploadRetryLimitSetting'], 10, 1);        
        add_action('prime_mover_render_upload_params_markup', [$this, 'renderUploadRetryLimitSetting'], 12);        
        add_filter('prime_mover_dropbox_chunk_size', [$this, 'maybeUpdateDropBoxChunkSize'], 10, 1);    
        
        add_action('prime_mover_render_upload_params_markup', [$this, 'renderDropBoxUploadChunkSetting'], 13);        
        add_filter('prime_mover_register_setting', [$this, 'registerSetting'], 10, 1);
        
        add_action('prime_mover_render_upload_params_markup', [$this, 'renderGdriveUploadChunkSetting'], 14); 
        add_action('wp_ajax_prime_mover_save_gdrive_chunksize_setting', [$this,'saveGDriveChunkUploadSetting']);  
        add_filter('prime_mover_gdrive_chunk_size', [$this, 'maybeUpdateGdriveChunkSize'], 10, 1);   
       
        add_action('prime_mover_render_upload_params_markup', [$this, 'renderGdriveDownloadChunkSetting'], 15);
        add_filter('prime_mover_gdrivedownload_chunk_size', [$this, 'maybeUpdateGdriveDownloadChunkSize'], 10, 1); 
        add_action('wp_ajax_prime_mover_save_gdrivedownload_chunksize_setting', [$this,'saveGDriveChunkDownloadSetting']);        
    }

    /**
     * Save Google Drive chunk download setting
     */
    public function saveGDriveChunkDownloadSetting()
    {
        $success = esc_html__('Google Drive chunk download size successfully saved.', 'prime-mover');
        $error = esc_html__('Google Drive chunk download size update failed', 'prime-mover');
        
        $this->getPrimeMoverSettings()->saveHelper('gdrive_chunk_download_size',  'prime_mover_save_gdrivedownload_chunk_size_nonce', true,
            FILTER_VALIDATE_INT, self::GDRIVE_DOWNLOAD_CHUNK_SIZE, false, $success, $error);
    }
    
    /**
     * Update GDrive download chunk size
     * @param number $gdrive_chunk
     * @return boolean|string|number
     */
    public function maybeUpdateGdriveDownloadChunkSize($gdrive_chunk = 0)
    {
        return $this->getUploadSettingMarkup()->getCloudChunkSizeSetting(self::GDRIVE_DOWNLOAD_CHUNK_SIZE, self::GDRIVE_DOWNLOAD_CHUNKSIZE_DEFAULT);
    }
    
    /**
     * Gdrive download chunk size setting
     */
    public function renderGdriveDownloadChunkSetting()
    {
        if (!defined('PRIME_MOVER_GDRIVE_DOWNLOAD_CHUNK')) {
            return;
        }
        $this->getUploadSettingMarkup()->renderGDriveDownloadChunkSetting(self::GDRIVE_DOWNLOAD_CHUNK_SIZE, self::GDRIVE_DOWNLOAD_CHUNKSIZE_DEFAULT);
    }
    
    /**
     * Update GDrive upload chunk size
     * @param number $gdrive_chunk
     * @return boolean|string|number
     */
    public function maybeUpdateGdriveChunkSize($gdrive_chunk = 0)
    {
        return $this->getUploadSettingMarkup()->getCloudChunkSizeSetting(self::GDRIVE_UPLOAD_CHUNK_SIZE, self::GDRIVE_UPLOAD_CHUNKSIZE_DEFAULT);
    }
    
    /**
     * Save Google Drive chunk upload setting
     */
    public function saveGDriveChunkUploadSetting()
    {
        $success = esc_html__('Google Drive chunk upload size successfully saved.', 'prime-mover');
        $error = esc_html__('Google Drive chunk upload size update failed', 'prime-mover');
        
        $this->getPrimeMoverSettings()->saveHelper('gdrive_chunk_upload_size',  'prime_mover_save_gdrive_chunk_size_nonce', true,
            FILTER_VALIDATE_INT, self::GDRIVE_UPLOAD_CHUNK_SIZE, false, $success, $error, 'text', 'forceMinimumGDriveChunk' );
    }
    
    /**
     * Gdrive upload chunk size setting
     */
    public function renderGdriveUploadChunkSetting()
    {
        if (!defined('PRIME_MOVER_GDRIVE_UPLOAD_CHUNK')) {
            return;
        }
        $this->getUploadSettingMarkup()->renderGDriveUploadChunkSetting(self::GDRIVE_UPLOAD_CHUNK_SIZE, self::GDRIVE_UPLOAD_CHUNKSIZE_DEFAULT);
    }
    
    /**
     * Register setting
     * @param array $settings
     * @return boolean[]
     */
    public function registerSetting($settings = [])
    {               
        $upload_keys = [self::UPLOAD_CHUNK_SIZE, self::UPLOAD_REFRESH_INTERVAL, self::UPLOAD_RETRY_LIMIT, self::DROPBOX_UPLOAD_CHUNK_SIZE, self::GDRIVE_UPLOAD_CHUNK_SIZE];
        foreach ($upload_keys as $troubleshooting_key) {
            if (!in_array($troubleshooting_key, $settings)) {
                $settings[$troubleshooting_key] = ['encrypted' => false];
            }
        }
        return $settings;
    }   

    /**
     * Maybe update upload chunk size setting
     * @param number $chunksize
     * @return boolean|string|number
     */
    public function maybeUpdateUploadChunkSizeSetting($chunksize = 0)
    {        
        return $this->getUploadSettingMarkup()->getUploadChunkSizeSetting(self::UPLOAD_CHUNK_SIZE);
    }

    /**
     * Save upload chunk size setting
     */
    public function saveUploadChunkSizeSetting()
    {
        $success = esc_html__('Upload chunk size update success', 'prime-mover');
        $error = esc_html__('Upload chunk size update failed', 'prime-mover');
        
        $this->getPrimeMoverSettings()->saveHelper('uploadchunksize', 'prime_mover_save_upload_chunk_size_nonce', true,
            FILTER_VALIDATE_INT, self::UPLOAD_CHUNK_SIZE, false, $success, $error );
    }
 
    /**
     * Render upload chunk size setting
     * @param string $setting
     */
    public function renderUploadChunkSizeSetting()
    {
        $this->getUploadSettingMarkup()->renderUploadChunkSizeSetting(self::UPLOAD_CHUNK_SIZE);
    }
    
    /**
     * Update dropbox chunk size
     * @param number $dropbox_chunk
     * @return boolean|string|number
     */
    public function maybeUpdateDropBoxChunkSize($dropbox_chunk = 0)
    {
        return $this->getUploadSettingMarkup()->getCloudChunkSizeSetting(self::DROPBOX_UPLOAD_CHUNK_SIZE, self::DROPBOX_UPLOAD_CHUNKSIZE_DEFAULT);
    }
    
    /**
     * Save Dropbox chunk upload setting
     */
    public function saveDropboxChunkUploadSetting()
    {
        $success = esc_html__('Dropbox chunk upload size successfully saved.', 'prime-mover');
        $error = esc_html__('Dropbox chunk upload size update failed', 'prime-mover');
        
        $this->getPrimeMoverSettings()->saveHelper('dropbox_chunk_upload_size',  'prime_mover_save_dropbox_chunk_size_nonce', true,
            FILTER_VALIDATE_INT, self::DROPBOX_UPLOAD_CHUNK_SIZE, false, $success, $error );
    }
    
    /**
     * Dropbox upload chunk size setting
     */
    public function renderDropBoxUploadChunkSetting()
    {
        if (!defined('PRIME_MOVER_DROPBOX_UPLOAD_CHUNK')) {
            return;
        }
        $this->getUploadSettingMarkup()->renderDropBoxUploadChunkSetting(self::DROPBOX_UPLOAD_CHUNK_SIZE, self::DROPBOX_UPLOAD_CHUNKSIZE_DEFAULT);
    }
    
    /**
     * Maybe update retry limit setting if overriden
     * @param number $retry_limit
     * @return boolean|string|number
     */
    public function maybeUpdateUploadRetryLimitSetting($retry_limit = 0)
    {
        return $this->getUploadSettingMarkup()->getUploadRetrySetting(self::UPLOAD_RETRY_LIMIT, self::UPLOAD_RETRY_LIMIT_DEFAULT);
    }
    
    /**
     * Save upload retry limit
     */
    public function saveUploadRetryLimit()
    {
        $success = esc_html__('Upload retry limit successfully saved.', 'prime-mover');
        $error = esc_html__('Upload retry limit update failed', 'prime-mover');
        
        $this->getPrimeMoverSettings()->saveHelper('upload_retry_limit',  'prime_mover_save_upload_retrylimit_nonce', true,
            FILTER_VALIDATE_INT, self::UPLOAD_RETRY_LIMIT, false, $success, $error );
    }
    
    /**
     * Render upload retry limit setting
     */
    public function renderUploadRetryLimitSetting()
    {
        $this->getUploadSettingMarkup()->renderUploadRetryLimitSetting(self::UPLOAD_RETRY_LIMIT, self::UPLOAD_RETRY_LIMIT_DEFAULT);
    }
    
    /**
     * Save upload refresh interval
     */
    public function saveUploadRefreshInterval()
    {
        $success = esc_html__('Upload refresh interval update saved.', 'prime-mover');
        $error = esc_html__('Upload refresh interval update failed', 'prime-mover');
        
        $this->getPrimeMoverSettings()->saveHelper('refresh_interval_setting', 'prime_mover_save_upload_refreshinterval_nonce', true,
            FILTER_VALIDATE_INT, self::UPLOAD_REFRESH_INTERVAL, false, $success, $error ); 
    }
    
    /**
     * Check if we need to override refresh interval setting
     * @param number $refresh_interval
     * @return boolean|string|number
     */
    public function maybeUpdateUploadRefreshIntervalSetting($refresh_interval = 0)
    {        
        return $this->getUploadSettingMarkup()->getUploadRefreshIntervalSetting(self::UPLOAD_REFRESH_INTERVAL, $this->getDefaultUploadRefreshInterval());
    }
    
    /**
     * Render refresh upload interval setting
     */
    public function renderUploadRefreshIntervalSetting()
    {
        $this->getUploadSettingMarkup()->renderUploadRefreshIntervalSetting(self::UPLOAD_REFRESH_INTERVAL, $this->getDefaultUploadRefreshInterval());
    }

    /**
     * Show upload setting
     * 
     */
    public function showUploadSetting()
    {
        ?>
        <h2><?php esc_html_e('Upload/Download Parameters', 'prime-mover') ?></h2>
    <?php     
        do_action('prime_mover_render_upload_params_markup');    
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