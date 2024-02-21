<?php
namespace Codexonics\PrimeMoverFramework\menus;

/*
 * This file is part of the Codexonics.PrimeMoverFramework package.
 *
 * (c) Codexonics Ltd
 */

use Codexonics\PrimeMoverFramework\classes\PrimeMover;
use Codexonics\PrimeMoverFramework\utilities\PrimeMoverComponentAuxiliary;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Screen option helper class
 *
 */
class PrimeMoverGearBoxScreenOptions
{
    private $prime_mover;
    private $component_utilities;
    
    const SCREEN_OPTION_SETTING = 'prime_mover_show_sites_with_backups';
    const SCREEN_OPTION_SETTING_MIGRATED = 'prime_mover_screen_settings_migrated';
    
    /**
     * 
     * @param PrimeMover $PrimeMover
     * @param PrimeMoverComponentAuxiliary $component_utilities
     */
    public function __construct(PrimeMover $PrimeMover, PrimeMoverComponentAuxiliary $component_utilities)
    {
        $this->prime_mover = $PrimeMover;
        $this->component_utilities = $component_utilities;
    }
    
    /**
     * Get backup utilities
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverComponentAuxiliary
     */
    public function getComponentUtilities()
    {
        return $this->component_utilities;
    }
    
    /**
     * Added init hooks
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itAddsInitHooks()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itChecksIfHooksAreOutdated() 
     */
    public function initHooks()
    {
        add_filter('screen_settings', [$this, 'screenOptions'], 99, 1 );
        add_action('wp_loaded', [$this, 'saveScreenOptionSetting']);        
        
        add_action('prime_mover_after_singlezipfile_delete', [$this, 'removeSiteWithNoBackupsRecords'], 10, 1);
        add_action('prime_mover_after_allzipfiles_delete', [$this, 'removeSiteWithNoBackupsRecords'], 10, 1);        
        add_action('load-sites.php', [$this, 'addSitesQueryFilter']);
        
        add_action('wp_loaded', [$this, 'maybeMigrateScreenOptionSettings'],5);
        add_action('prime_mover_after_generating_download_url', [$this, 'markSiteWithBackups'], 10, 5);
    }    
 
    /**
     * Mark site with backups
     * @param string $results
     * @param string $hash
     * @param number $blogid_to_export
     * @param boolean $export_directory_on
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itMarkSitesWithBackups()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itDoesNotMarkSitesWithBackupsIfExportDirDisabled()
     */
    public function markSiteWithBackups($results = '', $hash = '', $blogid_to_export = 0, $export_directory_on = false, $ret = [])
    {
        if ( ! $blogid_to_export || ! $results || ! $hash ) {
            return;
        }
        $blogid_to_export= (int)$blogid_to_export;
        $this->doScreenOptionSettings('update', $blogid_to_export);
    }
    
    /**
     * Maybe migrate new screen option settings
     */
    public function maybeMigrateScreenOptionSettings()
    {
        if ( ! $this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized() ) {
            return;
        } 
        if ( ! is_multisite() ) {
            return;
        }
        if ( ! is_network_admin()) {
            return;
        }    
        
        if ('yes' === $this->getPrimeMover()->getSystemFunctions()->getSiteOption(self::SCREEN_OPTION_SETTING_MIGRATED, false, true, true)) {
            return;
        }
        
        $setting = [];   
        $user_id = get_current_user_id();
        $show_sites = get_user_option('multisite_migration_show_sites_with_backups');
        
        if ('yes' === $show_sites) {
            $setting[self::SCREEN_OPTION_SETTING] = $show_sites;
            $this->setUserSetting($setting);           
            delete_user_meta($user_id, 'multisite_migration_show_sites_with_backups');
        }
        
        $main_site_id = $this->getPrimeMover()->getSystemInitialization()->getMainSiteBlogId();
        $option_name = $this->getPrimeMover()->getSystemInitialization()->getBackupSitesOptionName();
        if ( ! $main_site_id) {
            return;
        }
        
        $this->getPrimeMover()->getSystemFunctions()->switchToBlog($main_site_id);
        wp_cache_delete('alloptions', 'options');
        
        $site_ids = get_option('multisite_migration_backup_sites');             
        if ($site_ids) {
            $this->getPrimeMover()->getSystemFunctions()->updateSiteOption($option_name, $site_ids, true);
            
            delete_option('multisite_migration_backup_sites');
        }
        $this->getPrimeMover()->getSystemFunctions()->restoreCurrentBlog();        
        $this->getPrimeMover()->getSystemFunctions()->updateSiteOption(self::SCREEN_OPTION_SETTING_MIGRATED, 'yes', true);
    }
    
    /**
     * Add sites query filter
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itAddsQuerySitesFilterHook()
     */
    public function addSitesQueryFilter()
    {
        add_filter('ms_sites_list_table_query_args', [$this, 'showOnlySitesContainingBackups'], 10, 1);
    }
    
    /**
     * Query sites containing only backups
     * @param array $args
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itShowsOnlySitesContainingBackups()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itDoesNotShowSitesContainingBackupsNotSitesPage()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itDoesNotShowSitesContainingBackupsNotAuthorized() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itDoesNotShowSitesContainingBackupsNoSetting()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itReturnsNoSitesIfNoSitesContainingBackups() 
     */
    public function showOnlySitesContainingBackups($args = [])
    {        
        global $current_screen;
        if ( ! is_object($current_screen) || ! isset($current_screen->id) ) {
            return $args;
        }
        if ('sites-network' !== $current_screen->id || ! $this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized() ) {
            return $args;
        }
        $current_setting = get_user_option(self::SCREEN_OPTION_SETTING);
        if ('yes' !== $current_setting) {
            return $args;
        }
        $sites_with_backups = $this->doScreenOptionSettings('get');
        if (empty($sites_with_backups)) {
            $args['site__in'] = [0];
            return $args;
        }
        if ( ! isset($args['site__in']) ) {
            $args['site__in'] = [];
        }
        foreach ($sites_with_backups as $site) {
            $args['site__in'][] = $site;
        }
        return $args;
    }
    
    /**
     * Remove site with backup on records
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itRemovesSitesWithNoBackupRecords()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itDoesNotRemoveSitesWithBackupRecords()
     */
    public function removeSiteWithNoBackupsRecords($blog_id = 0)
    {
        if ( ! $blog_id ) {
            return;
        }    
        
        $backups = $this->getComponentUtilities()->getValidatedBackupsInExportDirectoryCached($blog_id);
        if (empty($backups)) {
            $this->doScreenOptionSettings('delete', $blog_id);
        }
    }

    /**
     * Handler for interacting with screen option setting.
     * @param string $mode
     * @param number $blog_id
     * @return void|number[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itShowsOnlySitesContainingBackups()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itReturnsNoSitesIfNoSitesContainingBackups()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itRemovesSitesWithNoBackupRecords()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itMarkSitesWithBackups()
     */
    private function doScreenOptionSettings($mode = 'get', $blog_id = 0)
    {
        return $this->getPrimeMover()->getSystemFunctions()->doScreenOptionSettings($mode, $blog_id);
    }
    
    /**
     * Save screen option settings
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itSavesScreenOptionSettings()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itDoesNotSaveSettingIfNotAuthorized() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itDoesNotSaveSettingIfNotNetworkAdmin()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itDoesNotSaveSettingIfNonceFailed() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itDoesNotSaveSettingIfNoSetting()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itDeleteUserSettingsIfNotSet()
     */
    public function saveScreenOptionSetting()
    {
        if ( ! $this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized() ) {
            return;
        } 
        $setting = $this->maybeSaveSetting();
        if (empty($setting)) {
            return;
        }
        if (empty($setting[self::SCREEN_OPTION_SETTING])) {
            $this->deleteUserSetting();
        } else {
            $this->setUserSetting($setting);
        }        
    }

    /**
     * Set user setting
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itDeleteUserSettingsIfNotSet()
     */
    private function deleteUserSetting()
    {
        if ( ! $this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized() ) {
            return;
        } 
        $user_id = get_current_user_id();
        delete_user_meta($user_id, self::SCREEN_OPTION_SETTING);
    }
    
    /**
     * Set user setting
     * @param array $setting
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itSavesScreenOptionSettings()
     */
    private function setUserSetting($setting = [])
    {
        if (empty($setting[self::SCREEN_OPTION_SETTING])) {
            return;
        }
        if ( ! $this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized() ) {
            return;
        } 
        $user_id = get_current_user_id();
        $value = $setting[self::SCREEN_OPTION_SETTING];
        if ('yes' === $value) {
            update_user_meta($user_id, self::SCREEN_OPTION_SETTING, $value);
        }        
    }    
    
    /**
     * Define settings args
     * @return string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itSavesScreenOptionSettings()
     */
    private function getSettingsArgs()
    {
        $setting_args = [];
        $option_setting = self::SCREEN_OPTION_SETTING;
        $nonce_key = 'prime_mover_screen_option_nonce';
        
        $setting_args[$option_setting] = $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverSanitizeStringFilter();
        $setting_args[$nonce_key] = $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverSanitizeStringFilter();
        
        return $setting_args;
    }
    
    /**
     * Validate and check for settings
     * @return array|mixed|array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itSavesScreenOptionSettings()
     */
    protected function maybeSaveSetting()
    {
        $setting = [];
        if ( ! $this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized() ) {
            return $setting;
        }    
        if ( ! is_network_admin() ) {
            return $setting;
        }
        $settings_args = $this->getSettingsArgs();
        $screen_options_posted = $this->getPrimeMover()->getSystemInitialization()->getUserInput('post', $settings_args, 'gearbox_screen_option', '', true, true);
        if ( ! is_array($screen_options_posted ) ) {
            return $setting;
        }
        
        if (empty($screen_options_posted['prime_mover_screen_option_nonce'])) {
            return $setting;
        }
        
        if  (!$this->getPrimeMover()->getSystemFunctions()->primeMoverVerifyNonce($screen_options_posted['prime_mover_screen_option_nonce'], 'prime_mover_screen_option_save' ) ) {
            return $setting; 
        }
        
        return $screen_options_posted;
    }
    
    /**
     * Display screen option settings
     * @param string $setting
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itDisplaysSettingsInScreenOptions()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itDoesNotDisplaySettingsIfNotNetworkSites()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverGearBoxScreenOptions::itDoesNotDisplaySettingsIfNotAuthorized()
     */
    public function screenOptions($setting = '')
    {
        if ( ! $this->getPrimeMover()->getSystemInitialization()->isNetworkSites() || ! $this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized()) {
            return $setting;
        }
        ob_start();
        $current_setting = get_user_option(self::SCREEN_OPTION_SETTING);
        ?>
       <fieldset class="prime-mover-screen-options">            
           <legend><?php esc_html_e('Prime Mover', 'prime-mover'); ?></legend>
           <label><input type="checkbox" name="<?php echo esc_attr(self::SCREEN_OPTION_SETTING);?>" <?php checked( $current_setting, 'yes' ); ?> value="yes"> 
           <?php esc_html_e('Show only sites containing backups', 'prime-mover' );?></label>
           <?php $this->getPrimeMover()->getSystemFunctions()->primeMoverNonceField('prime_mover_screen_option_save', 'prime_mover_screen_option_nonce'); ?>
       </fieldset>       
        <?php
        $output = $setting . ob_get_clean();
        return $output;
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