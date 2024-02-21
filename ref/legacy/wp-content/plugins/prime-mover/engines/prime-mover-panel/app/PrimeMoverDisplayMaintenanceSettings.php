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
class PrimeMoverDisplayMaintenanceSettings
{
    private $prime_mover_settings;
    
    const MAINTENANCE_MODE = 'enable_maintenance_mode';
    
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
     * Show maintenance mode setting
     */
    public function showMaintenanceModeSetting()
    {
        if (is_multisite()) {
            $this->outputMarkup();
        }
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
                <label for="maintenace-mode"><?php esc_html_e('Maintenance mode', 'prime-mover')?></label>
                <?php do_action('prime_mover_last_table_heading_settings', ''); ?>
            </th>
            <td>
            <label>
            <input name="prime_mover_enable_maintenance_mode" <?php checked( $this->getPrimeMoverSettings()->getSetting(self::MAINTENANCE_MODE), 'true' ); ?> 
            type="checkbox" id="js-prime_mover_enable_maintenance_mode" value="yes"> 
            <?php esc_html_e('Always turn off maintenance mode on entire network when migrating a site')?>
            </label>
                <div class="prime-mover-setting-description">
                    <p class="description prime-mover-settings-paragraph">
                    <?php esc_html_e('By default, maintenance mode is enabled when migrating a site that includes some plugins or themes. 
                    This is the safest mode to avoid possibility of data corruption. It is because plugin in multisite could be used in different sub-sites.
                    ',  'prime-mover'); ?>
                    </p>
                    <p class="description">
                    <?php esc_html_e('However this maintenance mode might disrupt important operations in all other subsites. You can choose to disable maintenance mode here.', 'prime-mover'); ?>
                    </p>
                    <?php $this->getPrimeMoverSettings()->getSettingsMarkup()->renderSubmitButton('prime_mover_save_maintenance_mode_nonce', 'js-save-prime-mover-maintenance-mode', 
                        'js-prime_mover_maintenance-mode-spinner');?> 
                </div>                      
            </td>
        </tr>
        </tbody>
        </table>
    <?php     
    }    
}