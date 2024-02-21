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
 * Utility Class for handling troubleshooting markups
 *
 */
class PrimeMoverTroubleshootingMarkup
{
    private $prime_mover;
    private $system_authorization;
    private $settings;
    private $freemius_integration;
    
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
        $this->freemius_integration = $utilities['freemius_integration'];
    }
    
    /**
     * 
     * Get freemius integration
     */
    public function getFreemiusIntegration()
    {
        return $this->freemius_integration;
    }

    /**
     * Refresh site info log
     */
    public function refreshSiteInfoLog()
    {
        if ( ! $this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized() ) {
            return;
        }
        $log_path = $this->getPrimeMover()->getSystemInitialization()->getTroubleShootingLogPath('siteinformation'); 
        $site_info = apply_filters('prime_mover_filter_error_output', [], 0);
        
        $site_info_string = print_r($site_info, true);
        file_put_contents($log_path, $site_info_string);
    }
    
    /**
     * Stream download helper for troubleshooting and site info logs
     * @param string $download_nonce
     * @param string $download_nonce_key
     * @param string $arg_key
     * @param string $log_type
     */
    public function streamDownloadHelper($download_nonce = '', $download_nonce_key = '', $arg_key = '', $log_type = 'migration')
    {
        if (!$this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized() ) {
            return;
        }
        if (!$download_nonce || !$download_nonce_key || !$arg_key || !$log_type ) {
            return;
        }
        $args = [$download_nonce => $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(), $arg_key => $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverSanitizeStringFilter()];
        $args = $this->getPrimeMover()->getSystemInitialization()->getUserInput('get', $args);
        if (empty($args[$download_nonce]) || empty($args[$arg_key])) {
            return;
        }
        $nonce = $args[$download_nonce];
        $display_log = $args[$arg_key];
        if ( ! $this->getPrimeMover()->getSystemFunctions()->primeMoverVerifyNonce($nonce, $download_nonce_key ) || 'yes' !== $display_log) {
            return;
        }
        wp_raise_memory_limit('admin');
        do_action('prime_mover_validated_streams', $log_type);
        $this->troubleShootingLogStreamer($log_type);
    }

    /**
     * Troubleshooting log streamer
     * @param string $logtype
     */
    public function troubleShootingLogStreamer($logtype = 'migration')
    {
        if ( ! $logtype ) {
            return;
        }
        $download_path = $this->getPrimeMover()->getSystemInitialization()->getTroubleShootingLogPath($logtype);
        $initialname = 'migration';
        if ('siteinformation' === $logtype) {
            $initialname = 'siteinformation';
        }
        if (file_exists($download_path)) {
            $generatedFilename = $initialname . date('m-d-Y_hia') . '.log';
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="'. $generatedFilename .'"');
            header('Content-Length: ' . filesize($download_path));
            $this->getSystemFunctions()->readfileChunked($download_path, false, true);
            if ('siteinformation' === $logtype) {
                unlink($download_path);
            }            
            exit;
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
     * Show site info button
     */
    public function showSiteInfoButton()
    {
        ?>
        <table class="form-table">
        <tbody>
        <tr>
            <th scope="row">
                <label><?php esc_html_e('Export site info', 'prime-mover')?></label>
            </th>
            <td>                      
                <div class="prime-mover-setting-description">
                     <p class="description prime-mover-settings-paragraph">
                      <?php esc_html_e('Note: This is the export button for site information. This contain the details of how the site is configured, PHP settings, etc. Developer or technical support might ask for this info for troubleshooting. ', 
                          'prime-mover'); ?>
                    </p>
                     <p class="description">
                        <strong><?php esc_html_e('Tip: The exported information can contain sensitive or private information. Please do not post this in public or share with anyone.', 'prime-mover'); ?></strong>       
                    </p>                                  
                    <p class="p_wrapper_prime_mover_setting">                        
                    <a class="button-primary" 
                    href="<?php echo $this->generateDownloadLogUrl('prime_mover_download_siteinfo', 'prime_mover_download_site_info_nonce', 'prime_mover_site_info');?>">
                     <?php esc_html_e('Export site info', 'prime-mover');?></a>
                    </p>   
                </div>                      
            </td>
        </tr>
        </tbody>
        </table>
    <?php    
    }
        
    /**
     * Render enable troubleshooting log markup
     * @param string $setting
     */
    public function renderEnableTroubleShootingLogMarkup($setting = '')
    {
        $this->getPrimeMoverSettings()->getSettingsMarkup()->startMarkup(esc_html__('Troubleshooting', 'prime-mover'));
        $export_path = $this->getPrimeMover()->getSystemInitialization()->getMultisiteExportFolderPath();
    ?>
        <p class="description">
          <label for="js-prime_mover_enable_log_checkbox">
              <input <?php checked( $this->getPrimeMoverSettings()->getSetting($setting, false, 'true'), 'true' ); ?> type="checkbox"
               id="js-prime_mover_enable_log_checkbox" autocomplete="off" name="prime_mover_enable_troubleshooting_log" 
               class="prime_mover_enable_troubleshooting_checkbox" value="yes"> 
               <?php esc_html_e('Enable troubleshooting log', 'prime-mover');?>
         </label>
        </p> 
        <p class="description prime-mover-settings-paragraph">
          <?php printf( esc_html__('%s : These logs can contain sensitive/private details. Please do not post this information publicly or share to anyone. 
          For maximum security, move your backup directory outside public html to prevent unauthorized access to these log. You can do this very easily in the %s.', 
              'prime-mover'), '<strong>' . esc_html__('Important', 'prime-mover') . '</strong>',
              '<a href="' . esc_url($this->getFreemiusIntegration()->getSettingsPageUrl()) . '">' . esc_html__('basic settings page', 'prime-mover') . '</a>'); ?>
        </p> 
        <p class="description prime-mover-settings-paragraph">
          <?php printf( esc_html__('Interpreting these logs requires advance knowledge of migration processes. It is recommended to enable this only if advised by the technical support or plugin developer to analyze these data. 
              The generated logs are stored in your %s.', 
              'prime-mover'), 
              '<a class="prime_mover_panel_backup_dir_title" title="' . esc_attr($export_path) . '">backup directory</a>'); ?>
        </p>                                                        
    <?php
        $this->getPrimeMoverSettings()->getSettingsMarkup()->renderSubmitButton('prime_mover_save_troubleshooting_settings_nonce', 'js-save-prime-mover-troubleshooting', 'js-save-prime-mover-troubleshooting-spinner', 
            'div', 'button-primary', '', '', '', false);
       
        $this->getPrimeMoverSettings()->getSettingsMarkup()->endMarkup();
    }
 
    /**
     * Render clear log markup
     */
    public function renderClearLogCallBack()
    {
        $this->getPrimeMoverSettings()->getSettingsMarkup()->startMarkup(esc_html__('Clear Log', 'prime-mover'));
    ?>        
        <?php 
        $this->getPrimeMoverSettings()->getSettingsMarkup()->renderSubmitButton('prime_mover_clear_troubleshooting_settings_nonce', 'js-clear-prime-mover-troubleshooting', 
            'js-save-prime-mover-clear-log-spinner', 'p', 'button-secondary prime-mover-deleteall-button', esc_html__('Clear logs', 'prime-mover'), '', '', false);
        ?>
        <p class="description prime-mover-settings-paragraph">
          <?php esc_html_e('You can clear the migration log using this button. It is recommended you clear the log and disable the troubleshooting once all migration analysis are completed.', 'prime-mover'); ?>
        </p>              
    <?php
        $this->getPrimeMoverSettings()->getSettingsMarkup()->endMarkup();
    }

    /**
     * Generate download URL for log
     * @param string $nonce_key
     * @param string $nonce_arg
     * @param string $arg_key
     * @return string
     */
    protected function generateDownloadLogUrl($nonce_key = '', $nonce_arg = '', $arg_key = '')
    {
        $nonce = $this->getSystemFunctions()->primeMoverCreateNonce($nonce_key);
        $params = [$arg_key => 'yes', $nonce_arg => $nonce];
        return esc_url(add_query_arg($params));
    }
    
    /**
     * Render download log markup
     */
    public function renderDownloadLogMarkup()
    {
        $this->getPrimeMoverSettings()->getSettingsMarkup()->startMarkup(esc_html__('Download Log', 'prime-mover'));
    ?>
        <p class="description prime-mover-settings-paragraph">
            <a class="button-primary" 
            href="<?php echo $this->generateDownloadLogUrl('prime_mover_download_troubleshooting_log', 'download_troubleshooting_log_nonce', 'prime_mover_troubleshooting_log');?>">
            <?php esc_html_e('Download log file', 'prime-mover');?></a>
        </p>
         <p class="description prime-mover-settings-paragraph">
          <?php esc_html_e('You can download the troubleshooting log using this button. By default, there is no data in the log. 
         You need to enable troubleshooting log first. Once troubleshooting is enabled, you need to export and import site. It will then log some migration data.', 'prime-mover'); ?>
        </p>
          <p class="description prime-mover-settings-paragraph">
          <?php esc_html_e("This will only log events related to migrations, that's all. If you need the log for PHP errors, you need to use the standard WordPress debug.log for this. 
        Take note that this log might contain sensitive info, so it's best to clear the log or delete it in your computer after analyzing them.", 'prime-mover'); ?>
        </p>       
    <?php   
         $this->getPrimeMoverSettings()->getSettingsMarkup()->endMarkup();
    }
    
    /**
     * Render Persist troubleshooting markup
     * @param string $setting
     */
    public function renderPersistTroubleShootingMarkup($setting = '')
    {
        $this->getPrimeMoverSettings()->getSettingsMarkup()->startMarkup(esc_html__('Persist / HTTP API Log', 'prime-mover'));
    ?>
        <p class="description">
            <label for="js-prime_mover_persist_log_checkbox">
                <input <?php checked( $this->getPrimeMoverSettings()->getSetting($setting), 'true' ); ?> type="checkbox" 
                id="js-prime_mover_persist_log_checkbox" autocomplete="off" name="prime_mover_persist_troubleshooting_log" class="prime_mover_persist_troubleshooting_checkbox" value="yes"> 
                <?php esc_html_e('Persist logs for simultaneous migrations and enable HTTP API debug', 'prime-mover');?>
             </label>
        </p>                                 
        <p class="description prime-mover-settings-paragraph">
            <?php esc_html_e('Note: By default, it only logs events for the current subsite being processed for either export/import. 
                If this is checked, it will log all events from ALL sites currently under migration.', 
                    'prime-mover'); ?>
         </p>
        <p class="description">
            <?php esc_html_e('This setting will also enable WordPress HTTP API debug. This is useful when troubleshooting remote URL package imports.', 
                    'prime-mover'); ?>
         </p>          
        <p class="description prime-mover-settings-paragraph">
            <strong><?php esc_html_e('Warning: This setting requires troubleshooting to be ENABLED.', 
                    'prime-mover'); ?></strong>
         </p>                                     
    <?php
        $this->getPrimeMoverSettings()->getSettingsMarkup()->renderSubmitButton('prime_mover_save_persist_troubleshooting_settings_nonce', 'js-save-prime-mover-persist-troubleshooting', 
        'js-save-prime-mover-persist-troubleshooting-spinner');
        $this->getPrimeMoverSettings()->getSettingsMarkup()->endMarkup();
    }

    /**
     * Render js troubleshooting markup
     * @param string $setting
     */
    public function renderJsTroubleShootingLogMarkup($setting = '')
    {
        $this->getPrimeMoverSettings()->getSettingsMarkup()->startMarkup(esc_html__('JavaScript Log', 'prime-mover'));
    ?>    
        <p class="description">
        <label for="js-prime_mover_enable_js_log_checkbox">
            <input type="checkbox" <?php checked( $this->getPrimeMoverSettings()->getSetting($setting), 'true' ); ?> id="js-prime_mover_enable_js_log_checkbox" autocomplete="off" 
            name="prime_mover_enable_js_log" 
            class="prime_mover_js_troubleshooting_checkbox" value="yes"> 
            <?php esc_html_e('Enable migration JavaScript console log', 'prime-mover');?>
        </label>
        </p>                                 
        <p class="description prime-mover-settings-paragraph">
        <?php esc_html_e('If this setting is checked, it will output logged-events to browser console when doing migrations.', 
            'prime-mover'); ?>
        </p>                         
    <?php
        $this->getPrimeMoverSettings()->getSettingsMarkup()->renderSubmitButton('prime_mover_save_js_console_setting_nonce', 'js-save-prime-mover-enable-js-log', 
            'js-save-prime-mover-enable-js-log-spinner', 'div', 'button-primary', '', '', '', false);
       $this->getPrimeMoverSettings()->getSettingsMarkup()->endMarkup();
    }

    /**
     * Render js upload log markup
     * @param string $setting
     */
    public function renderUploadJsTroubleShootingLogMarkup($setting = '')
    {
        $this->getPrimeMoverSettings()->getSettingsMarkup()->startMarkup(esc_html__('Chunk debug', 'prime-mover'));
        ?>
        <p class="description">
        <label for="js-prime_mover_enable_js_uploadlog_checkbox">
            <input type="checkbox" <?php checked( $this->getPrimeMoverSettings()->getSetting($setting), 'true' ); ?> id="js-prime_mover_enable_js_uploadlog_checkbox" autocomplete="off" 
            name="prime_mover_enable_js_uploadlog" 
            class="prime_mover_upload_debug_log_checkbox" value="yes"> 
            <?php esc_html_e('Enable upload chunk debug log', 'prime-mover');?>
        </label>
        </p>                                 
        <p class="description prime-mover-settings-paragraph">
        <?php esc_html_e('If this setting is checked, it will output upload chunk events to browser console when doing site imports via upload.', 
            'prime-mover'); ?>
        </p>                          
    <?php  
        $this->getPrimeMoverSettings()->getSettingsMarkup()->renderSubmitButton('prime_mover_save_uploadjs_console_setting_nonce', 'js-save-prime-mover-enable-js-uploadlog', 
            'js-save-prime-mover-enable-js-uploadlog-spinner', 'div', 'button-primary', '', '', '', false);
        $this->getPrimeMoverSettings()->getSettingsMarkup()->endMarkup();
    }

    /**
     * Render turbo mode markup
     * @param string $setting
     */
    public function renderTurboModeMarkup($setting = '')
    {
        $this->getPrimeMoverSettings()->getSettingsMarkup()->startMarkup(esc_html__('Turbo mode', 'prime-mover'));
        ?>
        <p class="description">
        <label for="js-prime_mover_enable_js_turbomode_checkbox">
            <input type="checkbox" <?php checked( $this->getPrimeMoverSettings()->getSetting($setting), 'true' ); ?> id="js-prime_mover_enable_js_turbomode_checkbox" autocomplete="off" 
            name="prime_mover_enable_js_turbomode" 
            class="prime_mover_js_turbomode_checkbox" value="yes"> 
            <?php esc_html_e('Enable turbo mode', 'prime-mover');?>
        </label>
        </p>                                 
        <p class="description prime-mover-settings-paragraph">
        <?php esc_html_e('If this setting is checked, it will speed up the export and restore process significantly. This is done by sending a lot of server requests at a given time.', 
            'prime-mover'); ?>
        </p>
         <p class="description">
            <?php esc_html_e('Enable this only if you are sure your hosting can support this feature. Otherwise, your host will block the export and restore process from completing because it is overloaded with requests.', 
                    'prime-mover'); ?>
         </p>  
         
          <p class="description prime-mover-settings-paragraph">
            <strong><?php esc_html_e('Important: Please clear your browser cache after changing this setting so that it will be enfored correctly.', 
                    'prime-mover'); ?></strong>
         </p>                          
    <?php  
        $this->getPrimeMoverSettings()->getSettingsMarkup()->renderSubmitButton('prime_mover_save_turbomode_setting_nonce', 'js-save-prime-mover-enable-turbomode', 
            'js-save-prime-mover-enable-turbomode-spinner', 'div', 'button-primary', '', '', '', false);
        $this->getPrimeMoverSettings()->getSettingsMarkup()->endMarkup();
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
    
    /**
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     */
    public function getSystemFunctions()
    {
        return $this->getPrimeMover()->getSystemFunctions();
    }
    
    /**
     *Render delete dialog markup
     */
    public function renderClearLogMarkup()
    {
        ?>
        <div style="display:none;" id="js-prime-mover-panel-clearall-dialog" title="<?php esc_attr_e('Warning!', 'prime-mover')?>"> 
			<p><?php printf( esc_html__('Are you really sure you want to %s', 'prime-mover'), 
			    '<strong>' . esc_html__('clear the log', 'prime-mover') . '</strong>'); ?> ? </p>			
			<p><strong><?php esc_html_e('Once cleared, the process cannot be undone.')?></strong></p>		      	  	
        </div>
    <?php
    }
}