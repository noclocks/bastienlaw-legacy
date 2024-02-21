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

use Codexonics\PrimeMoverFramework\classes\PrimeMover;
use Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class for resetting Prime Mover settings
 */
class PrimeMoverReset
{
    private $prime_mover;
    private $system_authorization;
    private $settings;

    /**
     * Constructor
     * @param PrimeMover $PrimeMover
     * @param PrimeMoverSystemAuthorization $system_authorization
     * @param array $utilities
     * @param PrimeMoverSettings $settings
     */
    public function __construct(PrimeMover $PrimeMover, PrimeMoverSystemAuthorization $system_authorization, array $utilities, 
        PrimeMoverSettings $settings) 
    {
        $this->prime_mover = $PrimeMover;
        $this->system_authorization = $system_authorization;
        $this->settings = $settings;
    }
    
    /**
     * Get Prime Mover settings
     * @return \Codexonics\PrimeMoverFramework\app\PrimeMoverSettings
     */
    public function getPrimeMoverSettings() 
    {
        return $this->settings;
    }    

    /**
     * Init hooks
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverReset::itAddsInitHooks() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverReset::itChecksIfHooksAreOutdated() 
     */
    public function initHooks() 
    {
        add_action('prime_mover_control_panel_settings', [$this, 'showResetSection'], 50);
        add_action('wp_ajax_prime_mover_reset_settings', [$this,'resetSettings']);
    }
    
    /**
     * Show reset section
     */
    public function showResetSection()
    {
        ?>
       <h2><?php esc_html_e('Settings management', 'prime-mover')?></h2>
    <?php 
       $this->outputResetMarkup();        
    }
  
    /**
     * Output reset markup
     */
    public function outputResetMarkup()
    {
        ?>
        <table class="form-table">
        <tbody>
        <tr>
            <th scope="row">
                <label id="prime-mover-reset-settings-label" for="js-prime-mover-reset-settings"><?php esc_html_e('Reset settings', 'prime-mover')?></label>
            </th>
            <td>
                <p><button data-nonce="<?php echo $this->getPrimeMover()->getSystemFunctions()->primeMoverCreateNonce('prime_mover_reset_settings_nonce'); ?>" 
                id="js-prime-mover-reset-settings" class="button button-large prime-mover-deleteall-button" type="button">
                        <?php esc_html_e('Reset to defaults', 'prime-mover' ); ?></button></p>
                <div class="prime-mover-setting-description">
                    <p class="description prime-mover-settings-paragraph">
                    <?php printf( esc_html__('Using the above button, you can %s used by this plugin. Take note that this reset button applies to both basic and advance settings.',  'prime-mover'), 
                        '<strong>' . esc_html__('reset ALL settings', 'prime-mover'). '</strong>'
                        ); ?>
                    </p>
                    <p class="description prime-mover-settings-paragraph">
                    <?php esc_html_e('Careful, there is no way to restore these settings once deleted. Make a copy of these settings if it is important!',  'prime-mover');?>                    
                    </p>
                    <p class="p_wrapper_prime_mover_setting">
                        <span class="js-reset-back-to-defaults-migration-spinner prime_mover_settings_spinner"></span>
                    </p> 
                </div>                      
            </td>
        </tr>
        </tbody>
        </table>
    <?php
         echo $this->renderResetDialogMarkup();
    }
    
    /**
     *Render delete dialog markup
     */
    private function renderResetDialogMarkup()
    {
    ?>
        <div style="display:none;" id="js-prime-mover-panel-resettodefault-dialog" title="<?php esc_attr_e('Warning!', 'prime-mover')?>"> 
			<p><?php printf( esc_html__('Are you really sure you want to %s', 'prime-mover'), 
			    '<strong>' . esc_html__('reset all settings', 'prime-mover') . '</strong>'); ?> ? </p>
			<p><?php esc_html_e('This will delete both basic and advance settings used by this plugin.', 'prime-mover')?></p>
			<p><strong><?php esc_html_e('Once deleted, the process cannot be undone.')?></strong></p>		      	  	
        </div>
    <?php
    }
    
    /**
     * Reset settings ajax
     */
    public function resetSettings()
    {
        $response = [];
        $reset_confirmation = $this->getPrimeMoverSettings()->prepareSettings($response, 'reset_confirmation', 
            'prime_mover_reset_settings_nonce', true, $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverSanitizeStringFilter());
        
        $result = $this->processResetHandler($reset_confirmation);
        $this->getPrimeMoverSettings()->returnToAjaxResponse($response, $result);
    }
    
    /**
     * Process reset handler
     * @param string $delete_confirmation
     * @return array
     */
    public function processResetHandler($reset_confirmation = '')
    {
        if ( 'yes' !== $reset_confirmation ) {
            return ['status' => false, 'message' => esc_html__('Error ! Invalid request', 'prime-mover')];
        }
        $exist = $this->getPrimeMoverSettings()->getAllPrimeMoverSettings();
        if ($exist) {
            $status = $this->getPrimeMoverSettings()->deleteAllPrimeMoverSettings();
        } else {
            $status = true;    
        }
        if ($status) {
            return ['status' => true, 'message' => esc_html__('Success! Settings resetted.', 'prime-mover')];
        }
        
        return ['status' => false, 'message' => esc_html__('Error resetting settings. Please try again later.', 'prime-mover')];
    }
        
    /**
     * Get Prime Mover
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMover
     * @compatible 5.6
     */
    public function getPrimeMover()
    {
        return $this->prime_mover;
    }
}