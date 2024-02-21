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
use Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization;
use Codexonics\PrimeMoverFramework\app\PrimeMoverSettings;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Utility Class for handling upload setting markups
 *
 */
class PrimeMoverUploadSettingMarkup
{
    private $prime_mover;
    private $system_authorization;
    private $settings;    
    
    /**
     * Constructor
     * @param PrimeMover $PrimeMover
     * @param PrimeMoverSystemAuthorization $system_authorization
     * @param array $utilities
     * @param PrimeMoverSettings $settings
     */
    public function __construct(PrimeMover $PrimeMover, PrimeMoverSystemAuthorization $system_authorization, array $utilities, PrimeMoverSettings $settings) 
    {
        $this->prime_mover = $PrimeMover;
        $this->system_authorization = $system_authorization;
        $this->settings = $settings;
    }

    /**
     * Gdrive download chunk size setting
     * @param string $setting
     * @param number $default
     */
    public function renderGDriveDownloadChunkSetting($setting = '', $default = 0)
    {
        $current_gdrivedownload_chunk_size = $this->getCloudChunkSizeSetting($setting, $default);
        $this->getPrimeMoverSettings()->getSettingsMarkup()->startMarkup(esc_html__('Google Drive download chunk', 'prime-mover'));
        ?>
        <p class="description">
          <label for="js-prime_mover_gdrivedownload_chunksize">
               <strong><?php esc_html_e('Google Drive download chunk size (bytes, integers only)', 'prime-mover');?></strong> : 
               <input id="js-prime_mover_gdrivedownload_chunksize" autocomplete="off" name="prime_mover_gdrivedownload_chunk_size" 
               class="prime_mover_gdrivedownload_chunk_size" type="text" name="prime_mover_gdrivedownload_chunk_size" value="<?php echo esc_attr($current_gdrivedownload_chunk_size);?>" > 
               (<?php esc_html_e('Default value', 'prime-mover');?> : <?php echo round($default, 0);?> 
               <?php esc_html_e('bytes', 'prime-mover') ?>)              
         </label>
        </p> 
        <p class="description">
          <?php esc_html_e('When you download a package from Google Drive, it is divided into chunks for efficiency. The default value is nearly 1MB per chunk which is suitable for most modern download speeds.', 'prime-mover'); ?>
        </p>
       <p class="description">
          <strong><?php esc_html_e('Remember: Setting this to small value can cause a lot of API requests that can add to your quota while setting a very high value can be prone to download timeouts.', 'prime-mover'); ?></strong>       
       </p>                                          
    <?php
        $this->getPrimeMoverSettings()->getSettingsMarkup()->renderSubmitButton('prime_mover_save_gdrivedownload_chunk_size_nonce', 'js-save-prime-mover-gdrivedownload-chunk-size', 
        'js-save-prime-mover-gdrivedownload-chunk-size-spinner');
       $this->getPrimeMoverSettings()->getSettingsMarkup()->endMarkup();
    }
    
    /**
     * Dropbox upload chunk size setting
     * @param string $setting
     * @param number $default
     */
    public function renderDropBoxUploadChunkSetting($setting = '', $default = 0)
    {
        $current_dropbox_chunk_size = $this->getCloudChunkSizeSetting($setting, $default);
        $this->getPrimeMoverSettings()->getSettingsMarkup()->startMarkup(esc_html__('Dropbox upload chunk', 'prime-mover'));
        ?>
        <p class="description">
          <label for="js-prime_mover_dropbox_chunksize">
               <strong><?php esc_html_e('Dropbox upload chunk size (bytes, integers only)', 'prime-mover');?></strong> : 
               <input id="js-prime_mover_dropbox_chunksize" autocomplete="off" name="prime_mover_dropbox_chunk_size" 
               class="prime_mover_dropbox_chunk_size" type="text" name="prime_mover_dropbox_chunk_size" value="<?php echo esc_attr($current_dropbox_chunk_size);?>" > 
               (<?php esc_html_e('Default value', 'prime-mover');?> : <?php echo round($default, 0);?> 
               <?php esc_html_e('bytes', 'prime-mover') ?>)              
         </label>
        </p> 
        <p class="description">
          <?php esc_html_e('When you export a package to Dropbox, it is broken down into chunks. This makes it easier to monitor the upload progress using Dropbox API upload sessions. The default value is nearly 2MB per chunk which is suitable in most cases.', 'prime-mover'); ?>
        </p>
       <p class="description">
          <strong><?php esc_html_e('Tip: When you set a very small chunk size, the API will log a lot of requests which counts towards your Dropbox API quota. When you set a very large chunk, it is prone to timeout and disconnection.', 'prime-mover'); ?></strong>       
       </p>                                          
    <?php
        $this->getPrimeMoverSettings()->getSettingsMarkup()->renderSubmitButton('prime_mover_save_dropbox_chunk_size_nonce', 'js-save-prime-mover-dropbox-chunk-size', 
        'js-save-prime-mover-dropbox-chunk-size-spinner');
       $this->getPrimeMoverSettings()->getSettingsMarkup()->endMarkup();
    }

    /**
     * Gdrive upload chunk size setting
     * @param string $setting
     * @param number $default
     */
    public function renderGDriveUploadChunkSetting($setting = '', $default = 0)
    {
        $current_gdrive_chunk_size = $this->getCloudChunkSizeSetting($setting, $default);
        $this->getPrimeMoverSettings()->getSettingsMarkup()->startMarkup(esc_html__('Google Drive upload chunk', 'prime-mover'));
        ?>
        <p class="description">
          <label for="js-prime_mover_gdrive_chunksize">
               <strong><?php esc_html_e('Google Drive upload chunk size (bytes, integers only)', 'prime-mover');?></strong> : 
               <input id="js-prime_mover_gdrive_chunksize" autocomplete="off" name="prime_mover_gdrive_chunk_size" 
               class="prime_mover_gdrive_chunk_size" type="text" name="prime_mover_gdrive_chunk_size" value="<?php echo esc_attr($current_gdrive_chunk_size);?>" > 
               (<?php esc_html_e('Default value', 'prime-mover');?> : <?php echo round($default, 0);?> 
               <?php esc_html_e('bytes', 'prime-mover') ?>)              
         </label>
        </p> 
        <p class="description">
          <?php esc_html_e('When you save a package to Google Drive, it is also broken down into chunks. The default value is nearly 1MB per chunk which is suitable in most upload speed cases today.', 'prime-mover'); ?>
        </p>
       <p class="description">
          <strong><?php esc_html_e('Tip: It is not recommended to set this below 1MB unless you have big package and slow upload speed. This is to comply with Google Drive API minimum chunk size requirement.', 'prime-mover'); ?></strong>       
       </p>                                          
    <?php
        $this->getPrimeMoverSettings()->getSettingsMarkup()->renderSubmitButton('prime_mover_save_gdrive_chunk_size_nonce', 'js-save-prime-mover-gdrive-chunk-size', 
        'js-save-prime-mover-gdrive-chunk-size-spinner');
       $this->getPrimeMoverSettings()->getSettingsMarkup()->endMarkup();
    }

    /**
     * Get upload and download chunk size setting 
     * Used with cloud solutions: Gdrive and Dropbox
     * @param string $setting
     * @param number $default
     * @return number
     */
    public function getCloudChunkSizeSetting($setting = '', $default = 0)
    {
        $return = 0;
        $current_chunk_size = $this->getPrimeMoverSettings()->getSetting($setting);
        if ($current_chunk_size) {
            $return = $current_chunk_size;
        } else {
            $return = $default;
        }
        return round($return, 0);
    }
    
    /**
     * Get upload chunk size setting
     * @param string $setting
     * @return boolean|string
     */
    public function getUploadChunkSizeSetting($setting = '')
    {
        $current_upload_chunk_size = $this->getPrimeMoverSettings()->getSetting($setting);
        if ( ! $current_upload_chunk_size) {
            $current_upload_chunk_size = $this->getPrimeMover()->getSystemFunctions()->getSliceSize(true);
        }        
        $current_upload_chunk_size = round($current_upload_chunk_size, 0);
        return $current_upload_chunk_size;
    }
 
    /**
     * Get upload refresh interval
     * @param string $setting
     * @param number $defaults
     * @return boolean|string|number
     */
    public function getUploadRefreshIntervalSetting($setting = '', $defaults = 0)
    {        
        $upload_refresh_interval = $this->getPrimeMoverSettings()->getSetting($setting);
        if ($upload_refresh_interval) {
            return $upload_refresh_interval;
        } else {
            return $defaults;
        }
    }
    
    /**
     * Upload refresh interval setting
     * @param string $setting
     * @param number $defaults
     */
    public function renderUploadRefreshIntervalSetting($setting = '', $defaults = 0)
    {
        $current_upload_refresh_interval = $this->getUploadRefreshIntervalSetting($setting, $defaults);
        $this->getPrimeMoverSettings()->getSettingsMarkup()->startMarkup(esc_html__('Upload refresh interval', 'prime-mover'));
        ?>
        <p class="description">
          <label for="js-prime_mover_upload_refreshinterval">
               <strong><?php esc_html_e('Upload refresh interval (milliseconds, integers only)', 'prime-mover');?></strong> : <input id="js-prime_mover_upload_refreshinterval" 
               autocomplete="off" name="prime_mover_upload_refreshinterval" 
               class="prime_mover_upload_refreshinterval" type="text" name="prime_mover_upload_refreshinterval" value="<?php echo esc_attr($current_upload_refresh_interval);?>" > 
               (<?php esc_html_e('Default value', 'prime-mover');?> : <?php echo $defaults; ?> 
               <?php esc_html_e('milliseconds', 'prime-mover') ?>)              
         </label>
        </p> 
        <p class="description">
          <?php esc_html_e('This is the JavaScript setTimeout setting for uploading chunks. Setting this to a very fast setting can overload your server with too many upload ajax requests. 
           Setting this to a very slow value will slow down the upload significantly.', 'prime-mover'); ?>
        </p>
       <p class="description">
          <strong><?php esc_html_e('Tip: The default setting which is 20 seconds is optimal in most cases. Only tweak this setting if its highly necessary. Please refresh the sites network page after saving this setting.', 'prime-mover'); ?></strong>       
       </p>                                    
    <?php
        $this->getPrimeMoverSettings()->getSettingsMarkup()->renderSubmitButton('prime_mover_save_upload_refreshinterval_nonce', 'js-save-prime-mover-refreshinterval-size', 
            'js-save-prime-mover-upload-refreshinterval-spinner', 'div', 'button-primary', '', '', '', false);
        $this->getPrimeMoverSettings()->getSettingsMarkup()->endMarkup();
    }
    
    /**
     * Upload chunk size setting
     * @param string $setting
     */
    public function renderUploadChunkSizeSetting($setting = '')
    {
        $current_upload_chunk_size = $this->getUploadChunkSizeSetting($setting);
        $this->getPrimeMoverSettings()->getSettingsMarkup()->startMarkup(esc_html__('Upload chunk size', 'prime-mover'));        
        ?>
        <p class="description">
          <label for="js-prime_mover_upload_chunksize">
               <strong><?php esc_html_e('Upload chunk size (bytes, integers only)', 'prime-mover');?></strong> : <input id="js-prime_mover_upload_chunksize" autocomplete="off" name="prime_mover_upload_chunk_size" 
               class="prime_mover_upload_chunk_size" type="text" name="prime_mover_upload_chunk_size" value="<?php echo esc_attr($current_upload_chunk_size);?>" > 
               (<?php esc_html_e('Default value', 'prime-mover');?> : <?php echo round($this->getPrimeMover()->getSystemFunctions()->getSliceSize(true), 0);?> 
               <?php esc_html_e('bytes', 'prime-mover') ?>)              
         </label>
        </p> 
        <p class="description">
          <?php esc_html_e('When you restore a site via upload, an upload package is broken down into slices or chunks. With this method, upload will still work even in servers with limited upload size configuration. The chunk size in bytes depends on your server limits.', 'prime-mover'); ?>
        </p>
        <p class="description">
          <?php esc_html_e('You can override the default upload chunk size here. The default value is automatically computed based on your server safe upload limits. You do not need to change this value as this is the optimal setting that works in most cases.', 'prime-mover'); ?>
        </p>
       <p class="description">
          <strong><?php esc_html_e('Tip: If you want to override this value, please do not put a value that is beyond your server limits. Know your server upload limits first before overriding this setting. 
       Please refresh the sites network page after saving this setting.', 'prime-mover'); ?></strong>       
       </p>                                  
    <?php
       $this->getPrimeMoverSettings()->getSettingsMarkup()->renderSubmitButton('prime_mover_save_upload_chunk_size_nonce', 'js-save-prime-mover-upload-chunk-size', 
           'js-save-prime-mover-upload-chunk-size-spinner', 'div', 'button-primary', '', '', '', false);
       $this->getPrimeMoverSettings()->getSettingsMarkup()->endMarkup();
    }
    
    /**
     * Render upload retry limit setting
     * @param string $setting
     * @param number $defaults
     */
    public function renderUploadRetryLimitSetting($setting = '', $defaults = 0)
    {
        $current_upload_retry_setting = $this->getUploadRetrySetting($setting, $defaults);
        $this->getPrimeMoverSettings()->getSettingsMarkup()->startMarkup(esc_html__('Upload retry limit', 'prime-mover'));
        ?>
        <p class="description">
          <label for="js-prime_mover_upload_retrylimit">
               <strong><?php esc_html_e('Upload retry limit (integers only)', 'prime-mover');?></strong> : <input id="js-prime_mover_upload_retrylimit" autocomplete="off" name="prime_mover_upload_retrylimit" 
               class="prime_mover_upload_retrylimit" type="text" name="prime_mover_upload_retrylimit" value="<?php echo esc_attr($current_upload_retry_setting);?>" > 
               (<?php esc_html_e('Default value', 'prime-mover');?> : <?php echo (int)$defaults; ?> 
               <?php esc_html_e('times', 'prime-mover') ?>)              
         </label>
        </p> 
        <p class="description">
          <?php esc_html_e('The client-side script will attempt re-sending the information to the server in the event of an error. This setting is the number of times Ajax will retry.', 'prime-mover'); ?>
        </p>
       <p class="description">
          <strong><?php esc_html_e('Tip: The default setting is 150 retries. This appears optimal in cases where a long 503 server header status is expected (e.g. when site is in maintenance mode). 
       You can adjust the retry limit to any sensible value. Please refresh the sites network page after saving this setting.', 'prime-mover'); ?></strong>       
       </p>                                  
    <?php
        $this->getPrimeMoverSettings()->getSettingsMarkup()->renderSubmitButton('prime_mover_save_upload_retrylimit_nonce', 'js-save-prime-mover-upload-retrylimit', 
            'js-save-prime-mover-upload-retrylimit-spinner', 'div', 'button-primary', '', '', '', false);
        $this->getPrimeMoverSettings()->getSettingsMarkup()->endMarkup();
    }
  
    /**
     * Get upload chunk size setting
     * @param string $setting
     * @param number $defaults
     * @return boolean|string|number
     */
    public function getUploadRetrySetting($setting = '', $defaults = 0)
    {
        $current_retry_limit = $this->getPrimeMoverSettings()->getSetting($setting);
        if ($current_retry_limit) {
            return $current_retry_limit;
        } else {
            return $defaults;    
        }
    }
    
    /**
     * Get multisite migration settings
     * @return \Codexonics\PrimeMoverFramework\app\PrimeMoverSettings
     */
    public function getPrimeMoverSettings() 
    {
        return $this->settings;
    }
    
    /**
     * Get multisite migration
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMover
     * @compatible 5.6
     */
    public function getPrimeMover()
    {
        return $this->prime_mover;
    }
}