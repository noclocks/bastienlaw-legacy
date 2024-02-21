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
 * Prime Mover display security settings
 *
 */
class PrimeMoverDisplaySecuritySettings
{
    private $prime_mover_settings;
    
    const ALLOWED_DOMAINS = 'allowed_domains';
    
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
     * Show download security setting
     * @tested Codexonics\PrimeMoverFramework\helpers\TestPrimeMoverDownloadSecurity::itDoesNotShowDownloadSecuritySettingGearBoxDeactivated() 
     */
    public function showDownloadSecuritySetting()
    {
        ?>
       <h2><?php esc_html_e('Security settings', 'prime-mover')?></h2>
    <?php          
    }    
    
    /**
     * Show download security setting
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDownloadAuthentication::itDoesNotShowDownloadAuthenticationSettingGearBoxDeactivated()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDownloadAuthentication::itRendersOutputMarkup()
     */
    public function showDownloadAuthenticationSetting()
    {
        $this->outputMarkup();
    }
    
    /**
     * Get placeholder
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDownloadAuthentication::itRendersOutputMarkup()
     */
    protected function getPlaceHolder()
    {
        $host = $this->getPrimeMover()->getSystemInitialization()->getDomain();
        $placeholders =
        $host . ':thisisplaceholderexampleonly3d23sqwesdfcvbnmjkuy45gtyhbtgvrfcedx';
        return $placeholders;
    }
 
    /**
     * Get authorization key of current site
     * @return string
     */
    protected function getAuthorizationKeyCurrentSite()
    {
        $current_domain = $this->getPrimeMover()->getSystemInitialization()->getDomain();
        $authorized = $this->getPrimeMoverSettings()->getSetting(self::ALLOWED_DOMAINS, true);
        if (!$authorized || empty($authorized[$current_domain])) {
            return '';
        }
        
        $authorization_key = $current_domain . ':' . $authorized[$current_domain];
        return $authorization_key;
    }
    
    /**
     * Output markup
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDownloadAuthentication::itRendersOutputMarkup()
     */
    private function outputMarkup()
    {
        ?>
        <table class="form-table">
        <tbody>
        <tr>
            <th scope="row">
                <label id="prime-mover-downloadauth-settings-label" for="download-authentication"><?php esc_html_e('Authorization Keys', 'prime-mover')?></label>
                <?php do_action('prime_mover_last_table_heading_settings', 'https://codexonics.com/prime_mover/prime-mover/how-to-add-and-use-authorization-keys-in-migrations/'); ?>
            </th>
            <td>
            <?php 
            $setting = $this->getPrimeMoverSettings()->convertSettingsToTextAreaOutput(self::ALLOWED_DOMAINS, true, false, true);
            $conceal_class = '';
            if ($setting) {
                $conceal_class = 'conceal-authorization-keys';
            }
            ?>
            <textarea autocomplete="off" class="large-text <?php echo $conceal_class; ?>" placeholder="<?php echo esc_attr($this->getPlaceHolder())?>" name="prime-mover-authorized-domains" id="js-prime-mover-authorized-domains" rows="5" cols="45"><?php echo esc_textarea($setting);?></textarea>
                <div class="prime-mover-setting-description">
                     <?php if ($conceal_class) {?>
                     <p class="description">
                        <label for="js-prime_mover_edit_authorization_keys">
                        <input type="checkbox" id="js-prime_mover_edit_authorization_keys" autocomplete="off" name="prime_mover_edit_authorization_keys" 
                        class="prime_mover_edit_authorization_keys" value="yes"> 
                        <span id="js-show-hide-authorization-text"><?php esc_html_e('Show authorization keys', 'prime-mover'); ?></span></label>
                    </p>
                    <?php } ?>                  
                    <p class="description prime-mover-settings-paragraph">
                    <?php 
                        printf(esc_html__('Generate download authorization keys for this site using the button below. It must be ONE LINE PER DOMAIN:AUTHORIZATION_KEYS. 
                        Accepts alphanumeric keys only and it be should be at least 64-characters in length with no spaces. Any invalid characters will be removed.',
                        'prime-mover')
                        ); 
                    ?>
                    </p>
                     <p class="description prime-mover-settings-paragraph">
                    <?php 
                        $display = '';
                        $text = esc_html__('Update', 'prime-mover');
                        $current_site_authorization_key = $this->getAuthorizationKeyCurrentSite();
                        if ( ! $current_site_authorization_key) {
                            $text = esc_html__('Generate', 'prime-mover');
                            $display = 'style="display:none"';
                        }
                    ?>                      
                    <button id="js-prime-mover-autogenerate-key" class="button prime-mover-autogenerate-key" type="button"
                    title="<?php echo esc_attr(sprintf( esc_html__('%s authorization key of this site. This is 64-characters in length.', 'prime-mover'), $text));?>">
                        <?php echo esc_attr(sprintf( esc_html__('%s authorization key of this site.', 'prime-mover'), $text));?>
                    </button>
                    </p>
                    <?php $this->getPrimeMoverSettings()->getSettingsMarkup()->renderSubmitButton('prime_mover_save_download_authentication_nonce', 'js-save-prime-mover-download-authentication', 
                        'js-prime_mover_download_authentication-spinner'); ?>                                       
                    <p class="description">
                    <?php 
                        esc_html__('These keys will be used to authenticate download request. Only sites you have authorized will be able to download and migrate package.', 'prime-mover'); 
                    ?> 
                    <p class="description prime-mover-settings-paragraph">                   
                        <button <?php echo $display; ?> class="button js-prime-mover-copy-key" class="button" type="button" data-saved-value="<?php echo esc_attr($this->getAuthorizationKeyCurrentSite());?>"
                    data-clipboard-text="<?php echo esc_attr($this->getAuthorizationKeyCurrentSite());?>" title="<?php esc_attr_e('Copy authorization key of this site to clipboard.', 'prime-mover');?>">
                            <?php esc_html_e('Copy site authorization key to clipboard', 'prime-mover'); ?>
                        </button>
                        <span id="js-prime-mover-clipboard-key-confirmation" class="prime-mover-clipboard-key-confirmation">
                        <?php esc_html_e('Copied', 'prime-mover'); ?>!                   
                        </span>                    
                    </p>                                        
                    <p class="description prime-mover-authentication-instructions"> 
                        <strong>                 
                        <?php esc_html_e('IMPORTANT: Please COPY AND PASTE these DOMAIN:AUTHORIZATION_KEYS to all sites you manage in Prime Mover Control Panel settings. Otherwise the download request will be unauthorized - 401 error.', 'prime-mover'); ?>
                        </strong> 
                    </p>
                    <p class="description"> 
                        <strong>                 
                        <?php esc_html_e('Always keep these authorization keys confidential and private. Backup these keys by writing on a paper and put it on a safe location.', 'prime-mover'); ?>
                        </strong> 
                    </p>                                                                                
 
                </div>                      
            </td>
        </tr>
        </tbody>
        </table>
    <?php     
    }
}