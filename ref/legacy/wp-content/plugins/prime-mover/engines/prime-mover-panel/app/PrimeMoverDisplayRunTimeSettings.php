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
 * Prime Mover display runtime parameter settings
 *
 */
class PrimeMoverDisplayRunTimeSettings
{
    private $prime_mover_settings;
    private $runtime_settings;
    
    /**
     * Constructor
     * @param PrimeMoverSettings $prime_mover_settings
     */
    public function __construct(PrimeMoverSettings $prime_mover_settings) 
    {
        $this->prime_mover_settings = $prime_mover_settings;
        $this->runtime_settings = $this->getRunTimeSettingsDefinition();
    }
    
    /**
     * Get runtime settings
     * @return array
     */
    public function getRunTimeSettings()
    {
        return $this->runtime_settings;
    }
    
    /**
     * Get runtime settings definition
     * @return string[][]
     */
    protected function getRunTimeSettingsDefinition()
    {
        return [
            $this->getPrimeMover()->getSystemInitialization()->getSearchReplaceBatchSizeSetting() => 
             [
                'default' => PRIME_MOVER_SRCH_RLC_BATCH_SIZE,              
                'col_heading' => esc_html__('Search-replace batch size', 'prime-mover'),
                'identifier' => $this->getPrimeMover()->getSystemInitialization()->getSearchReplaceBatchSizeSetting(),                
                'setting_heading' => esc_html__('Search replace batch size (integers only)', 'prime-mover'),
                'first_description' => esc_html__('When doing search and replace during import - Prime Mover by default retrieves 25000 rows from database and then process it. This default value should work fine in most environments', 'prime-mover'),
                'second_description' => esc_html__('However if you get MySQL timeout/MySQL gone away errors - try lowering down the value to see if it works (e.g. like 5000 or 10000)', 'prime-mover'),
                'note' => esc_html__('Note: A small batch size can slow down the process but reduces timeout errors. Using a large value can speed up the process but risk of getting MySQL timeout or memory related errors',
                    'prime-mover'),
                'success_msg' => esc_html__('Search replace batch size update success', 'prime-mover'),
                'error_msg' => esc_html__('Search replace batch size update failed', 'prime-mover')
             ],
            $this->getPrimeMover()->getSystemInitialization()->getDbDumpBatchSizeSetting() =>
            [
                'default' => PRIME_MOVER_PHPDUMP_BATCHSIZE,
                'col_heading' => esc_html__('MySQLdump batch size', 'prime-mover'),
                'identifier' => $this->getPrimeMover()->getSystemInitialization()->getDbDumpBatchSizeSetting(),
                'setting_heading' => esc_html__('MySQLdump batch size (integers only)', 'prime-mover'),
                'first_description' => esc_html__('When doing export - Prime Mover by default dumps 5000 rows from database at a time. This default value should work fine in most hosting platforms.', 'prime-mover'),
                'second_description' => esc_html__('However if you get MySQL timeout/MySQL gone away errors - try lowering down the value to see if it works (e.g. like 500 or 1000)', 'prime-mover'),
                'note' => esc_html__('Note: A small batch size can slow down the MySQLdump but reduces timeout errors. Using a large value can speed up the dump process but risk of getting errors. This is not advisable.',
                    'prime-mover'),
                'success_msg' => esc_html__('MySQLdump batch size update success', 'prime-mover'),
                'error_msg' => esc_html__('MySQLdump batch size update failed', 'prime-mover')
            ]
        ];
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
     * Initialize hooks
     */
    public function initHooks()
    {
        $runtime_settings = array_keys($this->getRunTimeSettings());
        foreach ($runtime_settings as $setting) {
            add_action("wp_ajax_{$setting}", [$this,'saveRunTimeSetting']);
        }
        
        add_filter('prime_mover_register_setting', [$this, 'registerSetting'], 15, 1);
        add_filter('prime_mover_get_runtime_setting', [$this, 'getRunTimeSetting'], 10, 2);
    }

    /**
     * Register setting
     * @param array $settings
     * @return boolean[]
     */
    public function registerSetting($settings = [])
    {
        $runtime_keys = array_keys($this->getRunTimeSettings());
        foreach ($runtime_keys as $runtime_key) {
            if (!in_array($runtime_key, $settings)) {
                $settings[$runtime_key] = ['encrypted' => false];
            }
        }
        
        return $settings;
    }   
    
    /**
     * Save runtime settings
     */
    public function saveRunTimeSetting()
    {  
        $prefix = 'wp_ajax_';
        $setting = current_filter();
        
        if (substr($setting, 0, strlen($prefix)) == $prefix) {
            $setting = substr($setting, strlen($prefix));
        } 
        $runtime_settings = $this->getRunTimeSettings();
        if (!isset($runtime_settings[$setting])) {
            return;
        }
        
        $settings_data = $runtime_settings[$setting];
        $success = $settings_data['success_msg'];
        $error = $settings_data['error_msg'];
        $identifier = $settings_data['identifier'];
        
        $this->getPrimeMoverSettings()->saveHelper("{$identifier}_key", "{$identifier}_nonce", true,
            FILTER_VALIDATE_INT, $setting, false, $success, $error );
    }
    
    /**
     * Show runtime parameter settings
     */
    public function showRunTimeParameterSettings()
    {
    ?>
        <h2><?php esc_html_e('Runtime settings', 'prime-mover')?></h2>    
        <?php    
       foreach ($this->getRunTimeSettings() as $setting => $data) {
           $this->generateRunTimeSettingsMarkup($setting, $data['default'], $data['col_heading'], $data['identifier'], $data['setting_heading'], 
               $data['first_description'], $data['second_description'], $data['note']);
       }
    }
    
    /**
     * Get runtime setting given its setting name and default value
     * @param string $setting
     * @return boolean|string
     */
    public function getRunTimeSetting($setting = '', $constant_default_value = 0)
    {
        $setting_value = $this->getPrimeMoverSettings()->getSetting($setting);
        if (!$setting_value) {
            $setting_value = $constant_default_value;
        }
        return round($setting_value, 0);
    }
        
    /**
     * Render runtine settings markup
     * @param string $setting
     * @param number $constant_default_value
     * @param string $col_heading
     * @param string $identifier
     * @param string $setting_heading
     * @param string $first_description
     * @param string $second_description
     * @param string $note
     */
    protected function generateRunTimeSettingsMarkup($setting = '', $constant_default_value = 0, $col_heading = '', $identifier = '', $setting_heading = '', 
        $first_description = '', $second_description = '', $note = '')
    {
        $setting_value = $this->getRunTimeSetting($setting, $constant_default_value);
        $this->getPrimeMoverSettings()->getSettingsMarkup()->startMarkup($col_heading);
        ?>
        <p class="description">
          <label for="js-<?php echo esc_attr($identifier); ?>">
               <strong><?php echo $setting_heading; ?></strong> : <input id="js-<?php echo esc_attr($identifier); ?>" autocomplete="off" 
               name="<?php echo esc_attr($identifier); ?>" 
               class="<?php echo esc_attr($identifier); ?>" type="text" value="<?php echo esc_attr($setting_value);?>" > 
               (<?php esc_html_e('Default value', 'prime-mover');?> : <?php echo round($constant_default_value, 0);?>)             
         </label>
        </p> 
        <p class="description">
          <?php echo $first_description; ?>.
        </p>
        <p class="description">
          <?php echo $second_description; ?>.
        </p>
       <p class="description">
          <strong><?php echo $note; ?></strong>.       
       </p>                                  
       <?php
       $this->getPrimeMoverSettings()->getSettingsMarkup()->renderSubmitButton("{$identifier}_nonce", "js-save-{$identifier}", "js-save-{$identifier}-spinner", 
           'div', 'button-primary', '', '', '', false);
       $this->getPrimeMoverSettings()->getSettingsMarkup()->endMarkup();        
    }
}