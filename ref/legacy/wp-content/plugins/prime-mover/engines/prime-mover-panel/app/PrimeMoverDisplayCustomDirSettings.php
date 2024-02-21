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
 * Prime Mover display custom backup directory settings
 *
 */
class PrimeMoverDisplayCustomDirSettings
{
    private $prime_mover_settings;
    
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
     * Get backup base dir
     * @return string
     */
    private function getBackupBaseDirectory()
    {
        return $this->getPrimeMover()->getSystemInitialization()->getBackupBaseDirectory();
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
     * Show custom export directory setting
     */
    public function showCustomExportDirectorySetting()
    {
        ?>
       <h2><?php esc_html_e('Basic settings', 'prime-mover')?></h2>
        <table class="form-table">
        <tbody>
        <tr>
            <th scope="row">
                <label id="prime-mover-custombackupdir-settings-label"><?php esc_html_e('Custom backup directory', 'prime-mover')?></label>
                <?php do_action('prime_mover_last_table_heading_settings', 'https://codexonics.com/prime_mover/prime-mover/prime-mover-pro-custom-backup-directory/'); ?>
            </th>
            <td>
            <input  size="45" name="prime_mover_user_backup_dir_setting" autocomplete="off" type="text" id="js-prime_mover_user_backup_dir_setting" class="large-text" 
            value="<?php echo esc_attr($this->getBackupBaseDirectory())?>">
                <div class="prime-mover-setting-description">
                    <p class="description prime-mover-settings-paragraph">
                    <?php esc_html_e('By default, the export directory is located in WordPress uploads directory. 
                    This is automatically protected by .htaccess (for Apache servers) to prevent any unauthorized access.', 'prime-mover'); ?>
                    </p>
                    <p class="description">
                    <?php esc_html_e('If your server is Nginx or any server which does not use .htaccess. 
                    You can change the Custom backup directory setting by putting it outside the webroot for more security.', 'prime-mover'); ?>
                    </p> 
                    <p class="description prime-mover-settings-paragraph">
                    <?php esc_html_e('Any existing backup files will be automatically migrated to the new backup location and the old backup directory is removed.', 'prime-mover'); ?>
                    </p>
                    <p class="description">
                    <strong>
                    <?php esc_html_e('Important: Custom backup directory path should be FULL ABSOLUTE PATH.', 'prime-mover'); ?>
                    </strong>
                    </p>
                    <?php $this->getSettingsMarkup()->renderSubmitButton('prime_mover_save_custom_backup_base_dir_nonce', 'js-save-prime-mover-custom-basebackup-dir', 
                        'js-prime_mover_basedirsettings_spinner');?> 
                </div>                      
            </td>
        </tr>
        </tbody>
        </table>
    <?php 
    }    
}