<?php
namespace Codexonics\PrimeMoverFramework\utilities;

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
 * Prime Mover common settings markup
 *
 */
class PrimeMoverSettingsMarkups
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
     * Get multisite migration
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMover
     * @compatible 5.6
     */
    public function getPrimeMover()
    {
        return $this->prime_mover;
    }
    
    /**
     * Render submit button
     * @param string $nonce_key
     * @param string $button_id
     * @param string $spinner_class
     * @param string $wrapper
     * @param string $button_classes
     * @param string $button_text
     * @param string $disabled
     * @param string $title
     * @param boolean $pro
     */
    public function renderSubmitButton($nonce_key = '', $button_id = '', $spinner_class = '', $wrapper = 'div', $button_classes = 'button-primary', $button_text = '', $disabled = '', $title = '', $pro = true)
    {
        $spinner_class = "$spinner_class prime_mover_settings_spinner";
        $main_opening_tag = '<div class="p_wrapper_prime_mover_setting">';
        $main_closing_tag = '</div>';
        $spinner_tag = '<div class="' . esc_attr($spinner_class) . '"></div>';
        if ('p' === $wrapper) {
            $main_opening_tag = '<p class="p_wrapper_prime_mover_setting">';
            $main_closing_tag = '</p>';
            $spinner_tag = '<span class="' . esc_attr($spinner_class) . '"></span>';
        }
        if ( ! $button_text ) {
            $button_text =  esc_html__('Save', 'prime-mover');
        }
        echo $main_opening_tag;
        
        if ( false === apply_filters('prime_mover_is_loggedin_customer', false) && $pro) {            
    ?>        
            <a title="<?php esc_attr_e('This is PRO feature setting. Please upgrade to use this setting.', 'prime-mover'); ?>" 
            class="prime-mover-upgrade-button-simple button" href="<?php echo esc_url($this->getPrimeMover()->getSystemInitialization()->getUpgradeUrl()); ?>">
            <i class="dashicons dashicons-cart prime-mover-cart-dashicon"></i><?php esc_html_e('Upgrade to PRO', 'prime-mover'); ?></a>
     <?php        
        } else {            
       ?>
            <button title="<?php echo $title;?>" <?php echo $disabled; ?> data-nonce="<?php echo $this->getPrimeMover()->getSystemFunctions()->primeMoverCreateNonce($nonce_key); ?>" 
            id="<?php echo esc_attr($button_id);?>" class="<?php echo esc_attr($button_classes);?>" type="button">
            <?php echo $button_text;?></button>
            <?php echo $spinner_tag; ?>   
    <?php    
        }          
        echo $main_closing_tag;
    }
    
    /**
     * Start markup
     * @param string $heading
     */
    public function startMarkup($heading = '')
    {
        ?>
        <table class="form-table">
        <tbody>
        <tr>
            <th scope="row">
                <label><?php echo $heading; ?></label>
            </th>
            <td>                      
                <div class="prime-mover-setting-description">
    <?php    
    }
    
    /**
     * End markup
     */
    public function endMarkup()
    {
        ?>
                </div>                      
            </td>
        </tr>
        </tbody>
        </table>
    <?php        
    }
}
