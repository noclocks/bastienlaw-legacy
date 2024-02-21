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
 * Prime Mover display encryption settings
 *
 */
class PrimeMoverDisplayEncryptionSettings
{
    private $prime_mover_settings;
    private $config_utilities;
    
    const PRIME_MOVER_AUTHKEY = 'prime_mover_backup_auth';
    
    /**
     * Constructor
     * @param PrimeMoverSettings $prime_mover_settings
     */
    public function __construct(PrimeMoverSettings $prime_mover_settings) 
    {
        $this->prime_mover_settings = $prime_mover_settings;
    }
    
    /**
     * Get config utilities
     */
    public function getConfigUtilities()
    {
        return $this->getPrimeMoverSettings()->getConfigUtilities();
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
     * Show encryption setting
     */
    public function showEncryptionSetting()
    {
        $this->outputMarkup();
    }
    
    /**
     * Get encryption from WordPress configuration file
     * @return string
     */
    protected function getEncryptionKeyFromConfig()
    {
        return $this->getPrimeMoverSettings()->getEncryptionKeyFromConfig();
    }
    
    /**
     * Checks if encryption key setting is possible
     * @return boolean
     */
    protected function canEditEncryptionKeySetting()
    {
        if (true === apply_filters('prime_mover_is_config_usable', false)) {
            return true;
        }
               
        $config_transformer = $this->getConfigUtilities()->getConfigTransformer();
        if ($config_transformer && $config_transformer->exists('constant', 'PRIME_MOVER_DB_ENCRYPTION_KEY')) {
            return false;            
        }        
        
        $mu_constant_script = $this->getPrimeMover()->getSystemInitialization()->getCliMustUseConstantScriptPath();
        if (!$this->getPrimeMover()->getSystemFunctions()->nonCachedFileExists($mu_constant_script)) {
            return false;
        }
       
        if (!wp_is_writable($mu_constant_script)) {
            return false;
        }  
       
        return true;
    }
    
    /**
     * Generate markup
     */
    private function outputMarkup()
    {
        $encryption_key = $this->getEncryptionKeyFromConfig();
        $readonly = '';
        $disabled = '';
        $title = esc_attr__('You can change the value of your encryption key here. Always backup this value before changing.', 'prime-mover');
       
        if (false === $this->canEditEncryptionKeySetting()) {
            $readonly = 'readonly';
            $disabled = 'disabled';
            $title = esc_attr__('You can only change the encryption key by editing PRIME_MOVER_DB_ENCRYPTION_KEY constant in the WordPress configuration file.', 'prime-mover');
        }
        
        $enc_key_location = $this->getConfigUtilities()->whereEncKeyAdded();
        ?>
        <table class="form-table">
        <tbody>
        <tr>
            <th scope="row">
                <label id="prime-mover-enckey-settings-label"><?php esc_html_e('Encryption Key', 'prime-mover')?></label>
                <?php do_action('prime_mover_last_table_heading_settings', 'https://codexonics.com/prime_mover/prime-mover/how-to-enable-encryption-support-in-prime-mover/'); ?>
            </th>
            <td>            
            <label>             
            <input <?php echo $readonly; ?> title="<?php echo $title;?>" name="prime_mover_encryption_key_panel" type="text" class="large-text conceal-authorization-keys" size="45" id="js-prime_mover_encryption_key_panel" 
            value="<?php echo esc_attr($encryption_key);?>"> 
            <?php 
               $display = '';                        
               if (!$encryption_key) {                            
                   $display = 'style="display:none"';
               }
            ?>           
            </label>            
                <div class="prime-mover-setting-description">
                   <p class="description">
                    <label for="js-prime_mover_encryption_key_panel_checkbox">
                        <input type="checkbox" id="js-prime_mover_encryption_key_panel_checkbox" autocomplete="off" name="prime_mover_showdropbox_keys" class="prime_mover_encryption_key_panel_checkbox" value="yes"> 
                        <?php if ($enc_key_location) { ?>
                            <span id="js-show-hide-encryption-key"><?php echo sprintf(esc_html__('Show encryption key saved in %s', 'prime-mover'), "<code>$enc_key_location</code>"); ?></span>
                        <?php } else { ?>
                             <span id="js-show-hide-encryption-key"><?php esc_html_e('Show encryption key', 'prime-mover');?></span>
                        <?php } ?>
                    </label>
                    </p>               
                    <p class="description prime-mover-settings-paragraph">
                    <?php esc_html_e('Prime Mover automatically generates encryption key after upgrading to PRO version. You can update this default encryption key using this setting. A correct encryption key is required to restore encrypted packages on this site.', 
                        'prime-mover'); ?>
                    </p>
                    <p class="description">
                    <?php esc_html_e(' If you already created encrypted backups, you might not be able to restore them unless you use the correct key used when encrypting those packages.', 
                        'prime-mover'); ?>
                    </p> 
                    <p class="description">
                    <?php printf(esc_html__('Please keep track and backup all your encryption keys in a piece of paper and store it somewhere safe. %s. ', 
                        'prime-mover'), '<strong>' . esc_html__('Once a key is lost, there is no way to restore encrypted packages') . '</strong>'); ?>
                    </p>                    
                    <?php  
                    if (!$readonly) {
                        $title = '';
                    }
                    $this->getPrimeMoverSettings()->getSettingsMarkup()->renderSubmitButton(
                        'prime_mover_save_encryptionkey_settings_nonce', 
                        'js-save-prime-mover-encryption-key', 
                        'js-save-prime-mover-encryption-key-spinner',
                        'div',
                        'button-primary',
                        '',
                        $disabled,
                        $title
                    );?> 
                     <p class="description prime-mover-settings-paragraph">                   
                        <button <?php echo $display; ?> class="button js-prime-mover-copy-encryption-key" type="button" data-saved-value="<?php echo esc_attr($encryption_key);?>"
                    data-clipboard-text="<?php echo esc_attr($encryption_key); ?>" title="<?php esc_attr_e('Copy encryption key of this site to clipboard.', 'prime-mover');?>">
                            <?php esc_html_e('Copy site encryption key to clipboard', 'prime-mover'); ?>
                        </button>
                        <span id="js-prime-mover-clipboard-encryption-key-confirmation" class="prime-mover-clipboard-key-confirmation">
                        <?php esc_html_e('Copied', 'prime-mover'); ?>!                   
                        </span>                    
                    </p>                        
                </div>                      
            </td>
        </tr>
        </tbody>
        </table>        
    <?php     
        echo $this->renderEncWarningDialogMarkup();
    }
  
    /**
     *Render delete dialog markup
     */
    private function renderEncWarningDialogMarkup()
    {
        ?>
        <div style="display:none;" id="js-prime-mover-panel-enc-warn-dialog" title="<?php esc_attr_e('Warning!', 'prime-mover')?>"> 
			<p><?php printf( esc_html__('Are you really sure you want to %s', 'prime-mover'), 
			    '<strong>' . esc_html__('UPDATE ENCRYPTION KEY', 'prime-mover') . '</strong>'); ?> ? </p>
			<p><?php esc_html_e('If you already created encrypted backups using previous key, you will not be able to restore them with different key.', 'prime-mover')?></p>	      	  	
        </div>
    <?php
    }    
}