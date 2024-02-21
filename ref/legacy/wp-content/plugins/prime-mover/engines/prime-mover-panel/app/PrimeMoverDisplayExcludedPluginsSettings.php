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
 * Prime Mover display excluded plugins
 *
 */
class PrimeMoverDisplayExcludedPluginsSettings
{
    private $prime_mover_settings;
    
    const EXCLUDED_PLUGINS = 'excluded_plugins';
    
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
     * Build activated plugins markup
     */
    protected function buildPluginsMarkup($setting = '')
    {
        global $wp_filesystem;
        $plugins = get_plugins();
        $saved = [];
        if ($setting) {
            $saved = array_filter(preg_split('/\r\n|\r|\n/', $setting));
        }
        $empty = false;
        if ( is_array($plugins) && 1 === count($plugins)) {
            $key = key($plugins);
            if ('prime-mover.php' === basename($key)) {
                $empty = true;
            }
        }
        if ($empty) {
            ?>
        <p><?php esc_html_e('No other plugins found', 'prime-mover'); ?>  
        <?php   
        } else {
        ?>    
            <ul>
            <?php 
           foreach ($plugins as $plugin_basename => $plugin_details) {
                $plugin_file = basename($plugin_basename);
                if ('prime-mover.php' === $plugin_file) {
                    continue;    
                }
                $plugin_full_path = PRIME_MOVER_PLUGIN_CORE_PATH . $plugin_basename;
                
                if (! $wp_filesystem->exists($plugin_full_path)) {
                    continue;
                }            
                if ( ! empty($plugin_details['Name']) ) {
                    $checked = false;
                    if (in_array($plugin_basename, $saved, true)) {
                        $checked = true;    
                    }
            ?> 
                <li><label><input <?php checked($checked); ?> type="checkbox" name="prime-mover-activated-plugins-checkboxes" value="<?php echo esc_attr($plugin_basename); ?>">
                <?php echo $plugin_details['Name']; ?> (<em><?php echo $plugin_basename; ?></em>)</label></li> 
         <?php 
                }
            }
        }
     ?>
       </ul>
     <?php 
    }
    
    /**
     * Show download security setting
     */
    public function showExcludedPluginsSetting()
    {
        ?>
        <table class="form-table">
        <tbody>
        <tr>
            <th scope="row">
                <label id="prime-mover-excludedplugins-settings-label"><?php esc_html_e('Excluded plugins', 'prime-mover')?></label>
                <?php do_action('prime_mover_last_table_heading_settings', 'https://codexonics.com/prime_mover/prime-mover/how-to-exclude-plugins-in-prime-mover-pro/'); ?>
            </th>
            <td>
            <?php 
            $setting = $this->getPrimeMoverSettings()->convertSettingsToTextAreaOutput(self::EXCLUDED_PLUGINS, false, false);
            ?>
            <textarea readonly="readonly" class="large-text" name="prime-mover-excluded-plugins" id="js-prime-mover-excluded-plugins" rows="5" cols="45"><?php echo esc_textarea($setting);?></textarea>
                <div class="prime-mover-setting-description">
                    <p class="description prime-mover-settings-paragraph">
                    <?php esc_html_e('By default, all plugins that is activated for the exported site will be included in the export package.',
                        'prime-mover'); ?>
                    </p>
                    <p class="description">
                    <?php printf( 
                        esc_html__('It is possible to exclude plugins from being exported by adding the %s in the above text area. 
                  Use the tool below to add or updated excluded plugins to the text area (Prime Mover is already excluded by default):',
                        'prime-mover'), esc_html__('plugin basename', 'prime-mover')) ?>
                    </p>
                    <p class="description">
                   <button id="js-prime-mover-toggle-activated-plugins" class="button" type="button"
                       title="<?php echo esc_attr(esc_html__('Click this button to expand activated plugins.', 'prime-mover')); ?>">
                       <?php echo esc_attr(esc_html__('Click to expand', 'prime-mover')); ?>
                   </button> 
                   </p>
                    <div id="js-prime-mover-activated-plugins-helper" class="prime-mover-activated-plugins-helper">
                        <?php $this->buildPluginsMarkup($setting); ?>
                    </div>
                     <p class="description prime-mover-settings-paragraph">
                         <?php esc_html_e('Take note this is a global setting and applies to every export generated in this site. You can use this setting to exclude plugins that is is not needed in the target site.',
                        'prime-mover'); ?>
                    </p>
                    <p class="description">
                    <?php printf( esc_html__('As a result, this excluded plugin is %s at the target site after the package is imported.',
                        'prime-mover'), '<strong>' . esc_html__('DEACTIVATED', 'prime-mover') . '</strong>') ?>
                    </p> 
                    <?php 
                    if (is_multisite()) {
                    ?>
                        <p class="description">
                            <strong><em>
                            <span><?php echo esc_html__('IMPORTANT: ', 'prime-mover'); ?>
                                <?php echo esc_html__('This feature only works for subsites with active PRO licenses.', 'prime-mover'); ?></span>
                            </em></strong>
                        </p>             
                    <?php 
                    }
                    ?>                                                                                                                      
                    <?php $this->getSettingsMarkup()->renderSubmitButton('prime_mover_excluded_plugins_nonce', 'js-save-prime-mover-excluded-plugins', 
                        'js-save-prime-mover-excluded-plugins-spinner'); ?> 
                </div>                      
            </td>
        </tr>
        </tbody>
        </table>
    <?php     
    }
    
}