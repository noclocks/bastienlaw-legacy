<?php
namespace Codexonics\PrimeMoverFramework\compatibility;

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

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Migration Options Class
 * Helper class for displaying advanced migration options
 */
class PrimeMoverMigrationOptions
{     
    private $prime_mover;
    
    /**
     * Construct
     * @param PrimeMover $prime_mover
     * @param array $utilities
     */
    public function __construct(PrimeMover $prime_mover, $utilities = [])
    {
        $this->prime_mover = $prime_mover;
    }
    
    /**
     * Get Prime Mover instance
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMover
     */
    public function getPrimeMover()
    {
        return $this->prime_mover;
    }
    
    /**
     * Get system authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     */
    public function getSystemAuthorization()
    {
        return $this->getPrimeMover()->getSystemAuthorization();
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
     * Initialize hooks
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverMigrationOptions::itAddsInitHooks()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverMigrationOptions::itChecksIfHooksAreOutdated()
     */
    public function initHooks()
    {
        add_action('prime_mover_dothings_export_dialog', [$this, 'addCheckBoxExportInsideServer'], 10, 1 );
        add_action('prime_mover_after_export_location', [$this, 'displaySaveToDropBoxOption'], 10, 5);
        add_action('prime_mover_after_export_location', [$this, 'displayForceUtf8CharSetOption'], 25, 5);
        
        add_action('prime_mover_after_export_location', [$this, 'displaySaveToGDriveOption'], 20, 5);
        add_action('prime_mover_dothings_export_dialog', [$this, 'addCheckBoxForDbEncryption'], 11, 1 );
        add_action('prime_mover_before_label_advanced_options', [$this, 'maybeDisplayLockIcon'], 10, 1);
    }

    /**
     * Display Force UTF-8 (utf8mb4) charset encoding of database dump.
     * A custom target charset can also be used on special cases.
     * @param number $blog_id
     * @param string $disabled
     */
    protected function displayForceUtf8Markup($blog_id = 0, $disabled = '')
    {        
        ?>
        <p class="prime-mover-migration-tools-p">
        <label <?php echo $this->returnFreeClass($disabled); ?>>
        <input <?php echo $disabled; ?> autocomplete="off" id="js-prime-mover-forceutf8dump-<?php echo esc_attr($blog_id); ?>" class="js-prime_mover_forceutf8dump_class" type="checkbox"
     name="prime-mover-forceutf8dump-<?php echo esc_attr($blog_id); ?>" value="1">
     <?php 
     if (defined('PRIME_MOVER_CUSTOM_TARGET_CHARSET') && PRIME_MOVER_CUSTOM_TARGET_CHARSET) {
     ?>
         <?php echo sprintf(esc_html__('Migrate to %s database character set'), PRIME_MOVER_CUSTOM_TARGET_CHARSET); ?>.     
     <?php 
     } else { 
     ?>
     <?php echo sprintf(esc_html__('Migrate to UTF-8 (%s) database character set'), PRIME_MOVER_MODERN_UNICODE_CHARSET); ?>.        
     <?php 
     } 
     ?>
    
    <?php do_action('prime_mover_before_label_advanced_options', $disabled); ?>
    		</label>
		</p>
    <?php   
    }
    
    /**
     * Display Force UTF-8 (utf8mb4) charset encoding of database dump.
     * @param number $blog_id
     */
    public function displayForceUtf8CharSetOption($blog_id = 0)
    {          
        $charset_current_site = $this->getSystemInitialization()->getDbCharSetUsedBySite();
        if (!$charset_current_site) {
            return;
        }
        
        if (defined('PRIME_MOVER_CUSTOM_TARGET_CHARSET') && PRIME_MOVER_CUSTOM_TARGET_CHARSET === $charset_current_site) {
            return;
        }
        
        if (in_array($charset_current_site, $this->getSystemInitialization()->getUtfEncoding()) && !defined('PRIME_MOVER_CUSTOM_TARGET_CHARSET')) {
            return;
        }        
        
        if (false === apply_filters('prime_mover_multisite_blog_is_licensed', false, $blog_id)) {            
            $this->displayForceUtf8Markup($blog_id, 'disabled');            
        } else {
            if (!$blog_id || !defined('PRIME_MOVER_TARGETBLOGID_VERSION') || !defined('PRIME_MOVER_PANEL_VERSION') ) {
                return;
            }            
            
            $this->displayForceUtf8Markup($blog_id, '');
        }
    }
    
    /**
     * Maybe display lock icon
     */
    public function maybeDisplayLockIcon($disabled = '')
    {
        if (!$disabled) {
            return;
        }
    ?>
        <span title="<?php esc_html_e('This is a PRO feature. Please upgrade to unlock this option.', 'prime-mover')?>" class="prime-mover-dashicon-lock-migration-tools dashicons dashicons-lock"></span>    
    <?php  
    }

    /**
     * Get users object
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverUsers
     */
    public function getUsersObject()
    {
        return $this->getPrimeMover()->getImporter()->getUsersObject();
    }
    
    /**
     * Return free class
     * @param string $disabled
     * @return string
     */
    protected function returnFreeClass($disabled = '')
    {
        if ($disabled) {
            return 'class="prime-mover-free"';
        } 
        
        return '';
    }
    
    /**
     * Added checkbox for dB encryption
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxExport::itRendersMarkupForAddingCheckBoxDbEncryption()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxExport::itDoesNotRenderMarkupWhenNoEncryptionKey()
     */
    public function addCheckBoxForDbEncryption($blog_id = 0) {
        if (!$blog_id) {
            return;
        }
        
        $encryption_supported = false;
        if ($this->getSystemInitialization()->getDbEncryptionKey()) {
            $encryption_supported = true;
        }
        
        $user_export_supported = true;
        if ($this->getUsersObject()->isExportUserDisabledInConfig()) {
            $user_export_supported = false;
        }
        
        if (false === apply_filters('prime_mover_multisite_blog_is_licensed', false, $blog_id)) {
            $disabled = 'disabled';
        } else {
            $disabled = '';
        }
        
        if ($encryption_supported) {
            $this->showEncryptionOptionMarkup($blog_id, $disabled);
        }
        if ($user_export_supported) {
            $this->showUsersExportMarkup($blog_id, $disabled);
        }   
    }    
    
    /**
     * Show encryption options markup
     * @param number $blog_id
     * @param string $disabled
     */
    protected function showEncryptionOptionMarkup($blog_id = 0, $disabled = '')
    {
        $checked = '';
        if (!$disabled) {
            $checked = checked(1, 1, false);
        }
    ?>
        <p class="prime-mover-migration-tools-p"><label <?php echo $this->returnFreeClass($disabled); ?>><input <?php echo $disabled; ?> autocomplete="off" id="js-prime-mover-encryptiondb-<?php echo esc_attr($blog_id); ?>" <?php echo $checked; ?> class="js-prime-mover-encryptiondb_class"
		    type="checkbox" name="prime-mover-encryptiondb-<?php echo esc_attr($blog_id); ?>" value="1"> 
		<?php 
		    $supported_encryption = esc_html__('package', 'prime-mover');
		?>
		    <?php printf( esc_html__('Encrypt %s with industry standard AES-256 encryption', 'prime-mover'), $supported_encryption);?>.
		    <?php do_action('prime_mover_before_label_advanced_options', $disabled); ?>
		    </label></p>	
		<?php 
    }
    
    /**
     * Show uers export markup
     * @param number $blog_id
     * @param string $disabled
     */
    protected function showUsersExportMarkup($blog_id = 0, $disabled = '')
    {
    ?>
        <p class="prime-mover-migration-tools-p"><label <?php echo $this->returnFreeClass($disabled); ?>><input <?php echo $disabled; ?> autocomplete="off" id="js-prime-mover-userexport-<?php echo esc_attr($blog_id); ?>" <?php checked(1, 1); ?> class="js-prime-mover-userexport_class"
		    type="checkbox" name="prime-mover-userexport-<?php echo esc_attr($blog_id); ?>" value="1"> 
		    <?php esc_html_e('Export users (include current site users in migration / backup)', 'prime-mover');?>.
		    <?php do_action('prime_mover_before_label_advanced_options', $disabled); ?>
		    </label></p>		
    <?php 
    }
    
    /**
     * Display save to Google Drive option
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGoogleDrive::itDisplaysSaveToGDriveOption()
     */
    public function displaySaveToGDriveOption($blog_id = 0)
    {
        if (false === apply_filters('prime_mover_multisite_blog_is_licensed', false, $blog_id)) {
            $this->displaySaveToGdriveMarkup($blog_id, 'disabled'); 
        } else {
            if ( ! $blog_id || ! defined('PRIME_MOVER_TARGETBLOGID_VERSION') || ! defined('PRIME_MOVER_PANEL_VERSION') ) {
                return;
            }
            
            if (false === apply_filters('prime_mover_multisite_blog_is_licensed', false, $blog_id)) {
                return;
            }
            $client = $this->getSystemInitialization()->getGDriveClient();
            if (!is_object($client)) {
                return;
            }
            if (!$client->getAccessToken()) {
                return;
            }
            
            $this->displaySaveToGdriveMarkup($blog_id, '');            
        }
    }
    
    /**
     * Display save to Gdrive markup
     * @param number $blog_id
     * @param string $disabled
     */
    protected function displaySaveToGdriveMarkup($blog_id = 0, $disabled = '')
    {
    ?>
        <p class="prime-mover-migration-tools-p">
        <label <?php echo $this->returnFreeClass($disabled); ?>>
        <input <?php echo $disabled; ?> autocomplete="off" id="js-prime-mover-savetogdrive-<?php echo esc_attr($blog_id); ?>" class="js-prime_mover_gdrive_class" type="checkbox"
     name="prime-mover-savetogdrive-<?php echo esc_attr($blog_id); ?>" value="1">
    <?php esc_html_e('Save a copy to Google Drive') ?>.
    	<?php do_action('prime_mover_before_label_advanced_options', $disabled); ?>	
    	</label>
		</p>
	<?php         
    }
    
    /**
     * Display save to dropbox option
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDropBox::itDisplaySaveToDropBoxOptionWhenAllSet()
     */
    public function displaySaveToDropBoxOption($blog_id = 0)
    {        
        if (false === apply_filters('prime_mover_multisite_blog_is_licensed', false, $blog_id)) {
            $this->displayDropBoxMarkup($blog_id, 'disabled');
        } else {
            if (!$blog_id || !defined('PRIME_MOVER_TARGETBLOGID_VERSION') || !defined('PRIME_MOVER_PANEL_VERSION') ) {
                return;
            }
            
            $dropbox_access_token = apply_filters('prime_mover_get_setting', '', 'dropbox_access_key', true, '', true);
            if (!$dropbox_access_token ) {
                return;
            }
            $this->displayDropBoxMarkup($blog_id, '');
        }
    }
    
    /**
     * Disply DropBox markup
     * @param number $blog_id
     * @param string $disabled
     */
    protected function displayDropBoxMarkup($blog_id = 0, $disabled = '')
    {
    ?>
        <p class="prime-mover-migration-tools-p">
        <label <?php echo $this->returnFreeClass($disabled); ?>>
        <input <?php echo $disabled; ?> autocomplete="off" id="js-prime-mover-savetodropbox-<?php echo esc_attr($blog_id); ?>" class="js-prime_mover_dropbox_class" type="checkbox"
     name="prime-mover-savetodropbox-<?php echo esc_attr($blog_id); ?>" value="1">
    <?php esc_html_e('Save a copy to Dropbox') ?>.
    <?php do_action('prime_mover_before_label_advanced_options', $disabled); ?>
    		</label>
		</p>
    <?php   
    }
    
    /**
     * Markup for customized export path
     * @param number $blog_id
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxExport::itAddsCheckBoxExportInsideServer()
     */
    public function addCheckBoxExportInsideServer($blog_id = 0)
    {
        if (!$blog_id) {
            return;
        }    
        $upgrade_url = $this->getSystemInitialization()->getUpgradeUrl();
        if (false === apply_filters('prime_mover_multisite_blog_is_licensed', false, $blog_id)) {
            $checked = '';
            $disabled = 'disabled';
        } else {
            $checked = checked(1, 1, false);
            $disabled = '';
        }
        if ($disabled) {
        ?>        
            <h3 <?php echo $this->returnFreeClass($disabled); ?>><?php esc_html_e('PRO export options', 'prime-mover' )?></h3>
                <p class="prime-mover-migration-tools-p">
                    <span class="dashicons dashicons-unlock prime-mover-dashicon-unlock-migration-tools"></span>
                    <a class="prime-mover-readable-link" href="<?php echo esc_url($upgrade_url); ?>"><?php esc_html_e('Upgrade to PRO', 'prime-mover'); ?></a>
                </p>
        <?php 
        } else {
        ?>
            <h3 <?php echo $this->returnFreeClass($disabled); ?>><?php esc_html_e('Advanced export options', 'prime-mover' )?></h3>  
        <?php
         } 
         ?>      
		<p class="prime-mover-migration-tools-p">
		    <label <?php echo $this->returnFreeClass($disabled); ?>><input <?php echo $disabled; ?> autocomplete="off" id="js-prime-mover-targetexportloc-<?php echo esc_attr($blog_id); ?>" <?php echo $checked; ?> class="js-prime_mover_target_export_location_class" type="checkbox" name="prime-mover-targetexportloc-<?php echo esc_attr($blog_id); ?>" value="1"> 
		    <?php echo esc_html__('Generate package URL for direct site migration.', 'prime-mover'); ?>
		    <?php do_action('prime_mover_before_label_advanced_options', $disabled); ?>
		    </label>
		</p>
		<?php do_action('prime_mover_after_export_location', $blog_id); ?>		       
    <?php     
    }
}
