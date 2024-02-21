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
use FS_Options;
use FS_Plugin;
use WP_Site;
use Freemius;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Freemius Compatibility Class
 * Helper class for interacting with Freemius
 *
 */
class PrimeMoverFreemiusCompat
{     
    private $prime_mover;
    private $freemius_options;
    private $freemius;
    private $core_modules;
    private $free_deactivation_option;
    private $action_links;
    
    const ON_UPGRADE_USER_VERIFIED = "prime_mover_upgrade_freemius_verified";
    
    /**
     * Construct
     * @param PrimeMover $prime_mover
     * @param array $utilities
     * @param Freemius $freemius
     */
    public function __construct(PrimeMover $prime_mover, $utilities = [], Freemius $freemius = null)
    {
        $this->prime_mover = $prime_mover;
        $this->freemius_options = [
            'fs_accounts',
            'fs_dbg_accounts',
            'fs_active_plugins',
            'fs_api_cache',
            'fs_dbg_api_cache',
            'fs_debug_mode'
        ];
        
        $this->action_links = [
            'upgrade',
            'activate-license prime-mover',
            'opt-in-or-opt-out prime-mover',            
        ];
        
        $this->freemius = $freemius;
        $this->core_modules = [PRIME_MOVER_DEFAULT_FREE_BASENAME, PRIME_MOVER_DEFAULT_PRO_BASENAME];
        $this->free_deactivation_option = '_prime_mover_free_autodeactivated';
    }   
 
    /**
     * Get action links
     * @return string[]
     */
    public function getActionLinks()
    {
        return $this->action_links;
    }
    
    /**
     * Get auto deactivation option
     * @return string
     */
    public function getAutoDeactivationOption()
    {
        return $this->free_deactivation_option;
    }
    
    /**
     * Get core modules
     * @return string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverFreemiusCompat::itGetsCoreModules()
     */
    public function getCoreModules()
    {
        return $this->core_modules;
    }
    
    /**
     * Get Freemius
     * @return Freemius
     */
    public function getFreemius()
    {
        return $this->freemius;
    }
    
    /**
     * Get Freemius options
     * @return string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverFreemiusCompat::itGetsFreemiusOptions() 
     */
    public function getFreemiusOptions()
    {
        return $this->freemius_options;
    }
    
    /**
     * Register hooks
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverFreemiusCompat::itRegisterDeactivationHook() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverFreemiusCompat::itRegistersHooks()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverFreemiusCompat::itChecksIfHooksAreOutdated()
     */
    public function registerHooks()
    {        
        register_deactivation_hook(PRIME_MOVER_MAINPLUGIN_FILE, [$this, 'deactivationHook']);
        add_action('admin_init', [$this, 'activationHook'], 0);
        
        add_filter('network_admin_plugin_action_links_' . PRIME_MOVER_DEFAULT_PRO_BASENAME , [$this, 'userFriendlyActionLinks'], PHP_INT_MAX, 1);
        add_filter('plugin_action_links_' . PRIME_MOVER_DEFAULT_PRO_BASENAME , [$this, 'userFriendlyActionLinks'], PHP_INT_MAX, 1);
        
        add_filter('network_admin_plugin_action_links_' . PRIME_MOVER_DEFAULT_FREE_BASENAME , [$this, 'userFriendlyActionLinks'], PHP_INT_MAX, 1);
        add_filter('plugin_action_links_' . PRIME_MOVER_DEFAULT_FREE_BASENAME , [$this, 'userFriendlyActionLinks'], PHP_INT_MAX, 1);
       
        add_action('network_admin_notices', [$this, 'maybeShowMainSiteOnlyMessage'] );
        add_action( 'init', [$this, 'maybeUpdateIfUserReadMessage']);
        add_action('prime_mover_load_module_apps', [$this, 'maybeResetFreemiusOnIssues'], -1); 
        
        add_action('admin_head', [$this, 'jSUpgrade'], 100);
        add_action('admin_head', [$this, 'filterPrimeMoverSlug'], 50);
        add_action('prime_mover_load_module_apps', [$this, 'removeVerifiedMeta'], 1000); 
        
        $this->injectFreemiusHooks();
    }
    
    /**
     * Checks if upgraded user already verified
     * @return boolean
     */
    protected function isUpgradedUserVerified()
    {
        return ('yes' === $this->getPrimeMover()->getSystemFunctions()->getSiteOption(self::ON_UPGRADE_USER_VERIFIED));
    }
    
    /**
     * Filter Prime Mover slug in menu
     */
    public function filterPrimeMoverSlug()
    {
        $verified = false;
        if ($this->isUpgradedUserVerified()) {
            $verified = true;
        }
        
        if (false === $verified && false === $this->maybeUpgradeComplete(false)) {
            return;
        }
        
        global $menu;
        foreach ($menu as $k => $v) {
            if (isset($v[2]) && 'migration-panel-settings' === $v[2]) {
                if ($verified) {
                    $menu[$k][2] = 'admin.php?page=migration-panel-settings&prime-mover-upgrade-complete=yes';
                } else {
                    $menu[$k][2] = 'admin.php?page=migration-panel-settings&prime-mover-upgrade-complete=yes&_wpnonce=' . $this->getSystemFunctions()->primeMoverCreateNonce('prime-mover-upgrade-complete'); 
                }                               
            }
        }
    }
    
    /**
     * Add Freemius customization hooks
     */
    protected function injectFreemiusHooks()
    {
        $freemius = $this->getFreemius();
        $freemius->add_filter('connect-header_on-update', [$this, 'filterHeader']);       
        $freemius->add_filter('connect_message_on_update', [$this, 'filterMessage']); 
        
        $freemius->add_action('connect/after_message', [$this, 'filterActionButtons']);
        $freemius->add_filter('connect-message_on-premium', [$this, 'filterUpgradeMessage']);
        $freemius->add_action('after_premium_version_activation', [$this, 'removeVerifiedMeta']);
    }
    
    /**
     * Remove verified meta upon premium activation
     */
    public function removeVerifiedMeta()
    {
        if (!$this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        if (true === apply_filters('prime_mover_is_loggedin_customer', false)) {
            $this->getSystemFunctions()->deleteSiteOption(self::ON_UPGRADE_USER_VERIFIED); 
        }               
    }
    
    /**
     * Filter message
     * @param string $message
     * @return string
     */
    public function filterUpgradeMessage($message = '')
    {
        if (false === $this->maybeUpgradeComplete(false)) {
            return $message;
        }
        
        $message = '';
        $message .= sprintf(esc_html__('Thank you for upgrading to %s! To get started - please check your email to get details of your Freemius account and license key:', 'prime-mover'),
            '<b>' . PRIME_MOVER_PRO_PLUGIN_CODENAME . '</b>');
        $message .= '<ol>';
        $message .= '<li>' . sprintf(esc_html__('Login to your %s', 'prime-mover'),
            '<a target="_blank" class="prime-mover-external-link" href="https://users.freemius.com/login">' . esc_html__('Freemius account', 'prime-mover') . '</a>.</li>');
        
        $message .= '<li>' . sprintf(esc_html__('Inside your Freemius account - %s', 'prime-mover'),
            '<a target="_blank" class="prime-mover-external-link" href="https://codexonics.com/prime_mover/prime-mover/prime-mover-pro-how-to-deactivate-licenses-inside-your-freemius-account/">' . esc_html__('deactivate license', 'prime-mover') . '</a>.</li>');
        
        $message .= '<li>' . esc_html__('Click the "License" tab - copy your license key.', 'prime-mover') . '</li>';
        
        $message .= '<li>' . esc_html__('Please enter your license key in the box below.', 'prime-mover') . '</li>';
        $message .= '<li>' . esc_html__('Optionally - please confirm your email if you have received confirmation link.', 'prime-mover') . '</li>';
        $message .= '</ol>';
        
        return $message;
    }
    
    /**
     * Filter action buttons
     */
    public function filterActionButtons()
    {
        if (false === $this->maybeUpgradeComplete()) {
            return;
        }
    ?>
        <p><a target="_blank" href="https://users.freemius.com/login" class="button button-hero"><?php esc_html_e('Download Prime Mover PRO', 'prime-mover'); ?></a></p>    
    <?php      
    }
    
    /**
     * Filter message
     * @param string $message
     * @return string
     */
    public function filterMessage($message = '')
    {
        if (false === $this->maybeUpgradeComplete()) {
            return $message;
        }
        
        $message = '';
        $message .= esc_html__('To get started - please check your email to get details of your Freemius account and license key:', 'prime-mover');
        $message .= '<ol>';
        $message .= '<li>' . sprintf(esc_html__('Login to your %s', 'prime-mover'), 
            '<a target="_blank" class="prime-mover-external-link" href="https://users.freemius.com/login">' . esc_html__('Freemius account', 'prime-mover') . '</a>.</li>');
        $message .= '<li>' . esc_html__('Download latest Prime Mover PRO.', 'prime-mover') . '</li>';
        $message .= '<li>' . sprintf(esc_html__('Inside your Freemius account - %s', 'prime-mover'),
            '<a target="_blank" class="prime-mover-external-link" href="https://codexonics.com/prime_mover/prime-mover/prime-mover-pro-how-to-deactivate-licenses-inside-your-freemius-account/">' . esc_html__('deactivate license', 'prime-mover') . '</a>.</li>');
        $message .= '<li>' . esc_html__('Upload and install Prime Mover PRO to this site.', 'prime-mover') . '</li>';
        $message .= '<li>' . esc_html__('Deactivate Prime Mover FREE version.', 'prime-mover') . '</li>';
        $message .= '<li>' . esc_html__('Activate Prime Mover PRO and enter license key.', 'prime-mover') . '</li>';
        $message .= '<li>' . esc_html__('Optionally - please confirm your email if you have received confirmation link.', 'prime-mover') . '</li>';
        $message .= '</ol>';
        
        return $message;
    }
    
    /**
     * Filter upgrade header
     * @param string $header
     * @return string
     */
    public function filterHeader($header = '')
    {
        if (false === $this->maybeUpgradeComplete()) {
            return $header;
        } 
        
        return '<h2>' . esc_html__('Thank you for upgrading to Prime Mover PRO!', 'prime-mover') . '</h2>';        
    }
    
    /**
     * Maybe upgrade complete
     * @param boolean $procheck
     * @return boolean
     */
    protected function maybeUpgradeComplete($procheck = true)
    {
        $current =basename(PRIME_MOVER_PLUGIN_PATH) . '/' . PRIME_MOVER_PLUGIN_FILE;
        if ($procheck && PRIME_MOVER_DEFAULT_PRO_BASENAME === $current) {
            return false;
        }
        
        if (!$this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized()) {
            return false;
        }
        
        $args = [
            'prime-mover-upgrade-complete' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            '_wpnonce' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
        ];
        
        $get = $this->getPrimeMover()->getSystemInitialization()->getUserInput('get', $args, '', '', 0, true, true);
        
        if (!isset($get['prime-mover-upgrade-complete'])) {
            return false;
        }
        
        if ('yes' !== $get['prime-mover-upgrade-complete']) {
            return false;
        }
        
        if (!$this->isUpgradedUserVerified() && !$this->getSystemFunctions()->primeMoverVerifyNonce($get['_wpnonce'], 'prime-mover-upgrade-complete')) {
            return false;
        }    
        
        return true;
    }
    
    /**
     * On multisite network admin interface
     * No need to show the Freemius delegate link
     * Since the plugin is for network administrators only.
     */
    public function jSUpgrade()
    {
        if (false === $this->maybeUpgradeComplete()) {
            return;
        }        
        ?>
        <script>
        window.onload = function() {
        	if (jQuery('#fs_connect .fs-actions').length) {
        		jQuery('#fs_connect .fs-actions').remove();
        	}
        	if (jQuery('#fs_connect .fs-multisite-options-container').length) {
        		jQuery('#fs_connect .fs-multisite-options-container').remove();
        	}
        	if (jQuery('#fs_connect .fs-permissions').length) {
        		jQuery('#fs_connect .fs-permissions').remove();
        	}
        }
        </script>
    <?php
    }
    
    /**
     * Maybe reset Freemius on issues
     */
    public function maybeResetFreemiusOnIssues()
    {
        if (wp_doing_ajax() || wp_doing_cron()) {            
            return;
        }
        
        $freemius = $this->getFreemius();
        if (!is_object($freemius)) {            
            return;
        }
        
        if (!$this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized()) {            
            return;
        }
        
        if (!$freemius->is_anonymous()) {            
            return;
        }
        
        $args = [
            'plugin_id' => FILTER_SANITIZE_NUMBER_INT,
            'fs_action' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'page' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            '_wpnonce' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
        ];
        
        $get = $this->getPrimeMover()->getSystemInitialization()->getUserInput('get', $args, '', '', 0, true, true);
        $keys = array_keys($args);
        foreach($keys as $key) {
            if (empty($get[$key])) {                
                return;
            }
        }
        
        if ($get['plugin_id'] !== $freemius->get_id()) {            
            return;
        }
        
        $freemius_action = $freemius->get_unique_affix() . '_sync_license';
        if ($get['fs_action'] !== $freemius_action) {            
            return;
        }
        
        if ('migration-panel-settings-account' !== $get['page']) {            
            return;
        }        
        
        if (!wp_verify_nonce($get['_wpnonce'], $get['fs_action'])) {            
            return;
        }      
        
        $freemius->add_filter('connect_url', [$this, 'setActivationUrl']);
        $this->freemiusAllCleanedUp(true); 
        fs_redirect($freemius->get_activation_url());
    }
   
    /**
     * Set activation URL
     * @param string $url
     * @return string
     */
    public function setActivationUrl($url = '')
    {       
        $url = add_query_arg(
            [
                'prime-mover-upgrade-complete' => 'yes',
                '_wpnonce' => $this->getSystemFunctions()->primeMoverCreateNonce('prime-mover-upgrade-complete')
            ], $url);
        
        return esc_url_raw($url);
    }
    
    /**
     * Update if user read message
     */
    public function maybeUpdateIfUserReadMessage() {
        if (!$this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        $args = [
            'prime_mover_networksites_nonce' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'prime_mover_networksites_action' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
        ];
        
        $settings_get = $this->getPrimeMover()->getSystemInitialization()->getUserInput('get', $args, '', '', 0, true, true);
        if (empty($settings_get['prime_mover_networksites_action']) || empty($settings_get['prime_mover_networksites_nonce'])) {
            return;
        }
        
        $action = $settings_get['prime_mover_networksites_action'];
        $nonce = $settings_get['prime_mover_networksites_nonce'];
        
        if ('prime_mover_mark_user_read' === $action && $this->getSystemFunctions()->primeMoverVerifyNonce($nonce, 'prime_mover_user_read_mainsiteonly_notice')) {
            $this->getPrimeMover()->getSystemFunctions()->updateSiteOption($this->getPrimeMover()->getSystemInitialization()->getUserUnderstandMainSiteOnly(), 'yes', true);
            $this->redirectAndExit();
        }
    }
 
    /**
     * Redirect and exit helper
     */
    protected function redirectAndExit()
    {
        wp_safe_redirect(network_admin_url('sites.php') );
        exit;
    }
  
    /**
     * Generate import notice success URL
     * @return string
     */
    protected function generateNoticeSuccessUrl() {
        
        return add_query_arg(
            [
                'prime_mover_networksites_action' => 'prime_mover_mark_user_read',
                'prime_mover_networksites_nonce'  => $this->getSystemFunctions()->primeMoverCreateNonce('prime_mover_user_read_mainsiteonly_notice'),
            ], network_admin_url('sites.php')            
            );
    }
    
    /**
     * Show main site only message to user
     */
    public function maybeShowMainSiteOnlyMessage()
    {
        if (!$this->isOnNetworkSitesAuthorized()) {
            return;
        }
        
        if (!$this->isNetworkUsingOnlyMainSite()) {
            return;
        }        
        
        if (!$this->isUserNeedsToCreateSubSite()) {
            return;
        }    
        
        $upgrade_url = network_admin_url( 'admin.php?page=migration-panel-settings-pricing');
        $addsites_url = network_admin_url('site-new.php');        
        ?>
	    <div class="notice notice-info">  
	        <h2><?php esc_html_e('Important notice', 'prime-mover'); ?></h2>
	        <p><?php echo sprintf(esc_html__('Thank you for using %s. 
        To get started using the free version, you need to %s. Free version works on any number of multisite subsites. 
        If you want to export and restore the multisite main site, you need to %s. Thanks!', 'prime-mover'), 
	            '<strong>' . PRIME_MOVER_PLUGIN_CODENAME . '</strong>', 
	            '<a href="' . esc_url($addsites_url) . '">' . esc_html__('add a subsite for testing', 'prime-mover') . '</a>',
	            '<a href="' . esc_url($upgrade_url) . '">' . esc_html__('upgrade to the PRO version', 'prime-mover') . '</a>'
	            );
                ?>
	        </p>	
       
		    <p><a class="button" href="<?php echo esc_url($this->generateNoticeSuccessUrl()); ?>"><?php esc_html_e('Yes, I understand', 'prime-mover'); ?></a>
		</div>
		<?php        
    }

    /**
     * Checks if only using main site
     * @return boolean
     */
    protected function isNetworkUsingOnlyMainSite()
    {
        $count = (int)get_blog_count();
        if ($count === 0) {
            return false;
        }
        if ($count > 1) {
            return false;
        }
        
        $mainsite_blogid = $this->getPrimeMover()->getSystemInitialization()->getMainSiteBlogId();
        if (apply_filters('prime_mover_maybe_load_migration_section', false, $mainsite_blogid)) {
            return false;
        }    
        
        return true;
    }
    
    /**
     * Is on network sites and authorized
     * @return boolean
     */
    protected function isOnNetworkSitesAuthorized()
    {        
        return (is_multisite() && $this->getPrimeMover()->getSystemInitialization()->isNetworkSites() && $this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized());        
    }
    
    /**
     * Returns TRUE if user needs to create subsite
     * Otherwise FALSE
     * @return void|boolean
     */
    protected function isUserNeedsToCreateSubSite()
    {
        $shouldread = false;
        $importantreadmsg_setting = $this->getPrimeMover()->getSystemInitialization()->getUserUnderstandMainSiteOnly();
                
        if ('yes' !== $this->getPrimeMover()->getSystemFunctions()->getSiteOption($importantreadmsg_setting)) {   
            $shouldread = true;
        }
        
        return $shouldread;
    }   
    
    /**
     * User friendly action links.
     * @param array $actions
     * @return array
     */
    public function userFriendlyActionLinks($actions = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized() ) {
            return $actions;
        }        
        if (!is_array($actions)) {
            return $actions;
        }
        if (empty($actions)) {
            return $actions;
        }
        $freemius = [];
        $core = [];
        $prime_mover = [];
        
        foreach ($actions as $k => $v) {
            if (in_array($k, $this->getActionLinks())) {
                $freemius[$k] = $v;                
            } elseif ($k === $this->getSystemInitialization()->getPrimeMoverActionLink()) {
                $prime_mover[$k] = $v;
            } else {
                $core[$k] = $v;
            }
        }
        
        return array_merge($core, $freemius, $prime_mover);
    }
    
    /**
     * Deactivation hook
     */
    public function activationHook()
    {
        if (wp_doing_ajax()) {
            return;
        }
        $current =basename(PRIME_MOVER_PLUGIN_PATH) . '/' . PRIME_MOVER_PLUGIN_FILE;
        if (PRIME_MOVER_DEFAULT_FREE_BASENAME === $current) {
            return;
        }
        if (!$this->getSystemAuthorization()->isUserAuthorized() ) {
            return;
        }
        
        $activation_params = $this->getSystemInitialization()->getUserInput('get',
            [
                'activate' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter()
            ], 'prime_mover_activate_validate');
        
        if ($this->getSystemFunctions()->getSiteOption($this->getAutoDeactivationOption()) && isset($activation_params['activate']) && 'true' === $activation_params['activate']) {
            $this->getSystemFunctions()->deleteSiteOption($this->getAutoDeactivationOption());
            $this->freemiusAllCleanedUp();
            $this->setRedirectTransient(false);
        }
    }
    
    /**
     * Deactivation hook
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverFreemiusCompat::itRunsDeactivationHooks()
     */
    public function deactivationHook()
    {      
        do_action('prime_mover_deactivated');
        $current =basename(PRIME_MOVER_PLUGIN_PATH) . '/' . PRIME_MOVER_PLUGIN_FILE;
        if (PRIME_MOVER_DEFAULT_PRO_BASENAME === $current) {
            return;
        }
       
        if (!$this->getSystemAuthorization()->isUserAuthorized() ) {
            return;
        }
          
        if ($this->deactivationUserInitiated()) {
            return;
        }
        
        if ($this->deactivationNotProVersionInitiated()) {
            return;
        }
       
        if ($this->moreThanOneFreemiusModules()) {
            return;
        }
      
        $this->freemiusAllCleanedUp(); 
        $this->setRedirectTransient(true);
    }
    
    /**
     * Set redirect transient
     */
    protected function setRedirectTransient($update_option = false)
    {
        $transient = "fs_{$this->getFreemius()->get_module_type()}_{$this->getFreemius()->get_slug()}_activated";
        delete_transient($transient);
        set_transient($transient, true, 60);
        if ($update_option) {
            $this->getSystemFunctions()->updateSiteOption($this->getAutoDeactivationOption(), true);
        }        
    }
    
    /**
     * Clean up Freemius option
     * @param boolean $network
     */
    protected function freemiusCleanup($network = false)
    {
        foreach ($this->getFreemiusOptions() as $option) {
            if ($network) {
                delete_site_option($option);
            } else {
                delete_option($option);
            }            
        }
    }
    
    /**
     * Clean up all Freemius options
     */
    protected function freemiusAllCleanedUp($nonce_verified = false) 
    {
        if (is_multisite()) {            
            if (wp_is_large_network()) {
                return;
            }
            
            $sites = get_sites();            
            foreach ( $sites as $site ) {
                $blog_id = ($site instanceof WP_Site) ?
                $site->blog_id :
                $site['blog_id'];
                
                switch_to_blog($blog_id );                
                $this->freemiusCleanup(false);
                restore_current_blog();
            }            
            $this->freemiusCleanup(true);            
        } else {          
            $this->freemiusCleanup(false);
        }
        
        if ($nonce_verified && $this->getSystemAuthorization()->isUserAuthorized()) {
            $this->getPrimeMover()->getSystemFunctions()->updateSiteOption(self::ON_UPGRADE_USER_VERIFIED, 'yes', true);
        }
        
        do_action('prime_mover_log_processed_events', "Prime Mover successfully executes Freemius Fixer", 0, 'common', __FUNCTION__ , $this);
    }
    
    /**
     * Checks if more than one Freemius modules
     * @return boolean
     */
    protected function moreThanOneFreemiusModules()
    {
        if (!$this->isFreemiusLoaded()) {
            return true;
        }
        
        $fs_options = FS_Options::instance(WP_FS__ACCOUNTS_OPTION_NAME, true);
        if (!is_object($fs_options)) {
            return true;
        }
        
        $modules = fs_get_entities($fs_options->get_option('plugins'), FS_Plugin::get_class_name());
        if (!is_array($modules)) {
            return true;
        }
        
        $active = $this->getActiveModules($modules);
        $counted = count($active);
        
        return ($counted > 0);
    }
    
    /**
     * Get active Freemius modules
     * @param array $modules
     * @return []
     */
    protected function getActiveModules($modules = [])
    {
        $active = [];
        foreach ($modules as $module) {
            if (!isset($module->file)) {
                continue;
            }
            
            $file = $module->file;
            if (!$file) {
                continue;
            }
            
            $file = strtolower($file);
            if (in_array($file, $this->getCoreModules())) {
                continue;
            }
            
            if ($this->isPluginActive($file)) {
                $active[] = $file;
            }
        }
               
        return $active;
    }
    
    /**
     * Checks if plugin is active
     * Multisite or single site compatible
     * @param string $file
     * @return boolean
     */
    protected function isPluginActive($file = '')
    {
        if (!$file) {
            return false;
        }
        
        if (is_multisite() && $this->getSystemFunctions()->isPluginActive($file, true)) {
            return true;
        } elseif ($this->getSystemFunctions()->isPluginActive($file)) {
            return true;
        }
        return false;
    }
    
    /**
     * Checks if Freemius classes loaded
     * @return boolean
     */
    protected function isFreemiusLoaded()
    {
        return (class_exists('FS_Options') && defined('WP_FS__ACCOUNTS_OPTION_NAME') && function_exists('fs_get_entities') && class_exists('FS_Plugin'));       
    }
    
    /**
     * Returns FALSE if deactivation is pro version initiated
     * @return boolean
     */
    protected function deactivationNotProVersionInitiated()
    {
        $action = '';
        $plugin = '';
        $nonce = '';
        list($action, $plugin, $nonce) = $this->getDeactivationParams(); 
        
        if (!$action || !$plugin || !$nonce) {
            return true;
        }
        
        if ('activate' === $action && PRIME_MOVER_DEFAULT_PRO_BASENAME === $plugin && $this->getSystemFunctions()->primeMoverVerifyNonce( $nonce, 'activate-plugin_' . PRIME_MOVER_DEFAULT_PRO_BASENAME, true)) {
            return false;
        }
        
        return true;        
    }

    /**
     * Returns TRUE if deactivation is user initiated
     * @return boolean
     */
    protected function deactivationUserInitiated()
    {        
        $action = '';
        $plugin = '';
        $nonce = '';
        
        list($action, $plugin, $nonce) = $this->getDeactivationParams();        
        return ('deactivate' === $action && PRIME_MOVER_DEFAULT_FREE_BASENAME === $plugin && $this->getSystemFunctions()->primeMoverVerifyNonce($nonce, 'deactivate-plugin_' . PRIME_MOVER_DEFAULT_FREE_BASENAME, true));
    }
    
    /**
     * Get deactivation parameters
     * @return string[]|
     */
    protected function getDeactivationParams()
    {
        $queried = $this->getSystemInitialization()->getUserInput('get',
            [
                'action' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
                'plugin' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
                '_wpnonce' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter()
            ], 'prime_mover_free_deactivation');
        
        $action = '';
        $plugin = '';
        $nonce = '';
        
        if (!empty($queried['action'])) {
            $action = $queried['action'];
        }
        
        if (!empty($queried['plugin'])) {
            $plugin = $queried['plugin'];
        }
        
        if (!empty($queried['_wpnonce'])) {
            $nonce = $queried['_wpnonce'];
        }
        
        return [$action, $plugin, $nonce];
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
     * Get system authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverFreemiusCompat::itRunsDeactivationHooks()
     */
    public function getSystemAuthorization()
    {
        return $this->getPrimeMover()->getSystemAuthorization();
    }
    
    /**
     * Get Prime Mover instance
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMover
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverFreemiusCompat::itRunsDeactivationHooks()
     */
    public function getPrimeMover()
    {
        return $this->prime_mover;
    }
    
    /**
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     */
    public function getSystemFunctions()
    {
        return $this->getPrimeMover()->getSystemFunctions();
    }
}
