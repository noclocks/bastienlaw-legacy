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

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover display maintenance settings
 *
 */
class PrimeMoverDisplayGDriveSettings
{
    private $prime_mover_settings;
  
    const GDRIVE_SETTING = 'gdrive_setting';
    
    /**
     * Constructor
     * @param PrimeMoverSettings $prime_mover_settings
     */
    public function __construct(PrimeMoverSettings $prime_mover_settings) 
    {
        $this->prime_mover_settings = $prime_mover_settings;
    }
    
    /**
     * Get Freemius integration instance
     */
    public function getFreemiusIntegration()
    {
        return $this->getPrimeMoverSettings()->getFreemiusIntegration();
    }
    
    /**
     * Settings instance
     * @return \Codexonics\PrimeMoverFramework\app\PrimeMoverSettings
     */
    public function getPrimeMoverSettings()
    {
        return $this->prime_mover_settings;
    }
        
    /**
     * Get Prime Mover instance
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMover
     */
    public function getPrimeMover()
    {
        return $this->getPrimeMoverSettings()->getPrimeMover();
    }
    
    /**
     * Get settings markup
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverSettingsMarkups
     */
    public function getSettingsMarkup()
    {
        return $this->getPrimeMoverSettings()->getSettingsMarkup();
    }
    
    /**
     * Show Gdrive settings
     */
    public function showGdriveSettings()
    {
        $this->outputMarkup();
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
     * Output markup
     */
    private function outputMarkup()
    {
        ?>
        <table class="form-table">
        <tbody>
        <tr>
            <th scope="row">
                <label id="prime-mover-gdrive-settings-label" for="prime-mover-gdrive-label"><?php esc_html_e('Google Drive OAuth 2.0', 'prime-mover')?></label>
                <?php do_action('prime_mover_last_table_heading_settings', 'https://codexonics.com/prime_mover/prime-mover/prime-mover-pro-google-drive-api-integration/'); ?>
            </th>
            <td>
            <?php 
            $setting = $this->getPrimeMoverSettings()->getSetting(self::GDRIVE_SETTING, true, '', true);
            $conceal_class = '';
            if ($setting) {
                $conceal_class = 'conceal-authorization-keys';
            }
            ?>
            <?php 
            $readonly = '';
            $php_requirement = '';
            $disabled = '';
            if (!is_php_version_compatible(PRIME_MOVER_GDRIVE_PHP_VERSION)) {
                $readonly = 'readonly="readonly"';
                $php_requirement = sprintf(esc_html__('This feature requires PHP %s+.', 'prime-mover'), PRIME_MOVER_GDRIVE_PHP_VERSION);
                $disabled = 'disabled';
            }
            ?>            
            <textarea <?php echo $readonly; ?> autocomplete="off" class="large-text <?php echo $conceal_class; ?>" name="prime-mover-gdrive-settings" id="js-prime-mover-gdrive-settings" rows="5" cols="45"><?php echo esc_textarea($setting);?></textarea>
                <div class="prime-mover-setting-description">
                     <?php if ($conceal_class) {?>
                     <p class="description">
                        <label for="js-prime_mover_edit_gdrive">
                        <input type="checkbox" id="js-prime_mover_edit_gdrive" autocomplete="off" name="prime_mover_edit_gdrive" 
                        class="prime_mover_edit_gdrive" value="yes"> 
                        <span id="js-show-hide-gdrive-text"><?php esc_html_e('Show credentials', 'prime-mover'); ?></span></label>
                    </p>
                    <?php } ?>                  
                    <p class="description prime-mover-settings-paragraph">
                    <strong>
                    <?php 
                    echo $php_requirement;
                    ?>
                    </strong>                    
                    <?php 
                        printf(esc_html__('Download the JSON credentials file in %s. Open the JSON file and paste all contents here. Please save the settings first. 
                   Once done, please go back here and connect to Google Drive API.',
                        'prime-mover'), '<strong>' . 'Google Developer Console -> Credentials -> OAuth 2.0 Client IDs' . '</strong>'
                        ); 
                    ?>
                    </p>
                    <?php $this->getPrimeMoverSettings()->getSettingsMarkup()->renderSubmitButton('prime_mover_save_gdrive_setting_nonce', 'js-save-prime-mover-gdrive-setting', 
                        'js-prime_mover_gdrive_setting-spinner', 'div', 'button-primary', '', $disabled); ?>                                                                            
                    <p class="description"> 
                    <?php 
                     /**
                      * Let's get Gdrive client and auth URL
                      */
                     $client = $this->getSystemInitialization()->getGDriveClient();
                     $authUrl = $this->getSystemInitialization()->getGdriveAuthUrl();

                     if ($authUrl && $setting): ?>                  
                         <a class="button" href='<?= $authUrl ?>'><?php esc_html_e('Connect to Google Drive!', 'prime-mover');?></a>                       
                     <?php endif ?>            
                     <?php 
                     if (is_object($client) && $client->getAccessToken()) {
                     ?>
                        <span class="notice notice-large notice-success">
                            <strong><em><?php esc_html_e('Success! You are connected to Google Drive!', 'prime-mover');?></em></strong>
                            <a title="<?php esc_attr_e('Logout from Google Drive API', 'prime-mover')?>" class="prime_mover_gdrive_logout_link" 
                            href="<?php echo $this->generateGdriveLogoutLink();?>"><?php esc_html_e('Logout', 'prime-mover'); ?></a>
                        </span>                        
                     <?php    
                     }
                     ?>                               
                     </p>
                </div>                      
            </td>
        </tr>
        </tbody>
        </table>
    <?php     
    }
    
    /**
     * Get Google drive logout link
     * @return string
     */
    protected function generateGdriveLogoutLink()
    {
        $settings = $this->getFreemiusIntegration()->getSettingsPageUrl();
        $action = 'prime_mover_gdrive_logout';
        $settings = add_query_arg('action', $action, $settings);
        
        return $this->getPrimeMover()->getSystemFunctions()->primeMoverNonceUrl($settings, $action, 'prime_mover_gdrive_logout_action_nonce');
    }
}