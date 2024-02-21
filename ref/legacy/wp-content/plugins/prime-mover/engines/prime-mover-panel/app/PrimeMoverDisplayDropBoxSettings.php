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
 * Prime Mover display DropBox settings
 *
 */
class PrimeMoverDisplayDropBoxSettings
{
    private $prime_mover_settings;
    
    const DROPBOX_ACCESS_KEY = 'dropbox_access_key';
    
    /**
     * Constructor
     * @param PrimeMoverSettings $prime_mover_settings
     */
    public function __construct(PrimeMoverSettings $prime_mover_settings) 
    {
        $this->prime_mover_settings = $prime_mover_settings;
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
     * Show dropbox setting
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDropBoxSettings::itShowsDropBoxSettingIfAllSet()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDropBoxSettings::itDoesNotShowSettingIfGearBoxIsDeactivated()
     */
    public function showDropBoxSetting()
    {
    ?>
    <?php    
       $this->outputMarkup();       
    }

    /**
     * Output markup
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDropBoxSettings::itShowsDropBoxSettingIfAllSet()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDropBoxSettings::itDoesNotShowSettingIfGearBoxIsDeactivated()
     */
    private function outputMarkup()
    {
        $dropbox_setting = $this->getPrimeMoverSettings()->getSetting(self::DROPBOX_ACCESS_KEY, true, '', true);
        if (!$dropbox_setting ) {
            $dropbox_setting = '';
        }        
        ?>
        <table class="form-table">
        <tbody>
        <tr>
            <th scope="row">
                <label id="prime-mover-dropbox-settings-label"><?php esc_html_e('Dropbox access token', 'prime-mover')?></label>
                <?php do_action('prime_mover_last_table_heading_settings', 'https://codexonics.com/prime_mover/prime-mover/prime-mover-dropbox-integration/'); ?>
            </th>
            <td>            
            <label>
            <input name="prime_mover_dropbox_access_key" type="text" class="large-text conceal-authorization-keys" size="45" id="js-prime_mover_dropbox_access_key" value="<?php echo esc_attr($dropbox_setting);?>"> 
            </label>            
                <div class="prime-mover-setting-description">
                   <p class="description">
                    <label for="js-prime_mover_dropbox_token_checkbox">
                        <input type="checkbox" id="js-prime_mover_dropbox_token_checkbox" autocomplete="off" name="prime_mover_showdropbox_keys" class="prime_mover_dropbox_token_checkbox" value="yes"> 
                        <span id="js-show-hide-dropbox-token"><?php esc_html_e('Show Dropbox access token', 'prime-mover');?></span>
                    </label>
                    </p>               
                    <p class="description prime-mover-settings-paragraph">
                    <?php esc_html_e('This is an export option support for saving a copy of exported package to Dropbox file hosting service. You need to enter a valid Dropbox access token for this to work.', 
                        'prime-mover'); ?>
                    </p>
                    <p class="description">
                    <?php printf( esc_html__('To get an access token, you need to %s. Then in your app, go to %s.', 'prime-mover'), 
                        '<a target="_blank" class="prime-mover-external-link" href="https://www.dropbox.com/developers">' . esc_html__('create Dropbox app', 'prime-mover') . '</a>',
                        '<strong>' . esc_html__('Oauth 2 - Generate Access token', 'prime-mover') . '</strong>'
                        ); ?>
                    </p>                    
                    <?php $this->getPrimeMoverSettings()->getSettingsMarkup()->renderSubmitButton('prime_mover_save_dropbox_settings_nonce', 'js-save-prime-mover-dropbox-access-token', 
                        'js-save-prime-mover-dropbox-access-token-spinner');?> 
                </div>                      
            </td>
        </tr>
        </tbody>
        </table>
    <?php     
    }
}