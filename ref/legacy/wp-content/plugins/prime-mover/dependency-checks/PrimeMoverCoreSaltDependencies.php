<?php
/**
 *
 * This is the requirement for the site to have a complete list of core authentication constants as described here:
 * https://api.wordpress.org/secret-key/1.1/salt/
 * Otherwise it cannot be activated.
 *
 */
class PrimeMoverCoreSaltDependencies
{      
    /**
     * The only set constants for this site
     * @var array
     */
    private $set_constants = array();
    
    /**
     * Checks if all authentication salt constants are set in wp-config.php
     * Returns TRUE if it passes otherwise FALSE
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverRequirements::itChecksCorrectPluginRequirementsSingleSite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverRequirements::itChecksCorrectPluginRequirementsMultisite()
     */
    public function saltPasses()
    {              
        if (is_multisite()) {
            return true;
        }
        $set_constants = array();
        
        if (defined('AUTH_KEY') && AUTH_KEY) {
            $set_constants[] = 'AUTH_KEY';
        }
            
        if (defined('SECURE_AUTH_KEY') && SECURE_AUTH_KEY) {
            $set_constants[] = 'SECURE_AUTH_KEY';
        }
       
        if (defined('LOGGED_IN_KEY') && LOGGED_IN_KEY) {
            $set_constants[] = 'LOGGED_IN_KEY';
        }
        
        if (defined('NONCE_KEY') && NONCE_KEY) {
            $set_constants[] = 'NONCE_KEY';
        }
 
        if (defined('AUTH_SALT') && AUTH_SALT) {
            $set_constants[] = 'AUTH_SALT';
        }
        
        if (defined('SECURE_AUTH_SALT') && SECURE_AUTH_SALT) {
            $set_constants[] = 'SECURE_AUTH_SALT';
        }
        
        if (defined('LOGGED_IN_SALT') && LOGGED_IN_SALT) {
            $set_constants[] = 'LOGGED_IN_SALT';
        }
        
        if (defined('NONCE_SALT') && NONCE_SALT) {
            $set_constants[] = 'NONCE_SALT';
        }        
        
        $required_constants = $this->requiredConstants();
        $this->set_constants = array_diff($required_constants, $set_constants);        
        
        $notice_hook = 'admin_notices';
        if (is_multisite()) {
            $notice_hook = 'network_admin_notices';
        }
        
        if ( ! empty($this->set_constants) ) {
            add_action($notice_hook, array($this, 'incompleteSaltConstants'));
            return false;  
        }
        
        $valid_key = $this->validateKeys();
        
        if ( ! $valid_key) {
            add_action($notice_hook, array($this, 'duplicatedSaltConstants'));            
            return false;
        }

        return true;              
    }
    
    /**
     * Validate keys
     * @return boolean
     */
    protected function validateKeys()
    {        
        $constants = $this->requiredConstants();
        $values = array();
        $count = count($constants);
        
        foreach ($constants as $constant) {
            $values[] = constant($constant);
        }
        
        $unique = array_unique($values);
        $unique_count = count($unique);
        
        return ($unique_count === $count);
    }
    
    /**
     * Required constants
     * @return string[]
     */
    protected function requiredConstants()
    {
        return array(
            'AUTH_KEY',
            'SECURE_AUTH_KEY',
            'LOGGED_IN_KEY',
            'NONCE_KEY',
            'AUTH_SALT',
            'SECURE_AUTH_SALT',
            'LOGGED_IN_SALT',
            'NONCE_SALT');       
    }
 
    /**
     * Report error
     */
    public function duplicatedSaltConstants()
    {
        ?>
        <div class="error">
            <p>
            <?php 
            printf( esc_html__( 'The %s plugin cannot be activated because it requires %s defined in wp-config.php. Some of your security constants are duplicated which is not advisable for best security.', 
                'prime-mover'), '<strong>' . esc_html(PRIME_MOVER_PLUGIN_CODENAME) . '</strong>', 
                '<a href="https://wordpress.org/support/article/editing-wp-config-php/#security-keys">' . esc_html__('unique security keys', 'prime-mover') . '</a>'); 
            ?>
            </p>
            <p>
            <?php printf(esc_html__( 'Please use this tool to re-generate all keys : %s . Clear your browser cache and re-activate the plugin again.', 'prime-mover'), 
                '<a href="https://api.wordpress.org/secret-key/1.1/salt/">https://api.wordpress.org/secret-key/1.1/salt/</a>'); ?>
            </p>
        </div>
        <?php 
    }
    
    /**
     * Report error
     */
    public function incompleteSaltConstants()
    {
        ?>
        <div class="error">
            <p>
            <?php 
            printf( esc_html__( 'The %s plugin cannot be activated because it requires %s defined in wp-config.php. The following constants are not set or does not have constant values :', 
                'prime-mover'), '<strong>' . esc_html(PRIME_MOVER_PLUGIN_CODENAME) . '</strong>', 
                '<a href="https://wordpress.org/support/article/editing-wp-config-php/#security-keys">' . esc_html__('complete security keys', 'prime-mover') . '</a>'); 
            ?>
            </p>
            <ul>
            <?php 
            foreach ($this->set_constants as $constant) {
            ?>
                <li><code><?php echo $constant; ?></code></li>                  
            <?php 
            } 
            ?>
            </ul>
            <p>
            <?php printf(esc_html__( 'Please use this tool to re-generate all keys : %s . Clear your browser cache and re-activate the plugin again.', 'prime-mover'), 
                '<a href="https://api.wordpress.org/secret-key/1.1/salt/">https://api.wordpress.org/secret-key/1.1/salt/</a>'); ?>
            </p>
        </div>
        <?php 
    }
}
