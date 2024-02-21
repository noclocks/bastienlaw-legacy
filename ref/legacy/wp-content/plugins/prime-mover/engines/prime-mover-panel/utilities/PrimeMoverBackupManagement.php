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
use Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization;
use Codexonics\PrimeMoverFramework\app\PrimeMoverSettings;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class for managing backup directory
 *
 */
class PrimeMoverBackupManagement
{
    private $prime_mover;
    private $system_authorization;
    private $settings;
    private $delete_utilities;
    private $backupdir_size;
    private $component_aux;
    
    const COPYBACKUP_DIR = 'dont_copydir_when_deactivated';
    
    /**
     * Constructor
     * @param PrimeMover $PrimeMover
     * @param PrimeMoverSystemAuthorization $system_authorization
     * @param array $utilities
     * @param PrimeMoverSettings $settings
     */
    public function __construct(PrimeMover $PrimeMover, PrimeMoverSystemAuthorization $system_authorization, array $utilities, 
        PrimeMoverSettings $settings, PrimeMoverDeleteUtilities $delete_utilities, PrimeMoverBackupDirectorySize $backupdir_size) 
    {
        $this->prime_mover = $PrimeMover;
        $this->system_authorization = $system_authorization;
        $this->settings = $settings;
        $this->delete_utilities = $delete_utilities;
        $this->backupdir_size = $backupdir_size;
        $this->component_aux = $utilities['component_utilities'];
    }

    /**
     * Get component auxiliary
     * @return array
     */
    public function getComponentAux()
    {
        return $this->component_aux;
    }
    
    /**
     * Get backup dir size instance
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverBackupDirectorySize
     */
    public function getBackupDirSize()
    {
        return $this->backupdir_size;
    }
    
    /**
     * Get Delete utilities
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverDeleteUtilities
     */
    public function getDeleteUtilities()
    {
        return $this->delete_utilities;        
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
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupManagement::itAddsInitHooks()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupManagement::itChecksIfHooksAreOutdated()
     */
    public function initHooks() 
    {
        /**
         * Ajax handler
         */
        add_action('wp_ajax_prime_mover_copydir_preference', [$this,'saveSettings']);
        add_action('wp_ajax_prime_mover_delete_all_backups_request', [$this,'deleteAllBackups']);
        add_action('wp_ajax_prime_mover_computedir_size', [$this,'computeBackupDirSize']);
        
        add_action('prime_mover_control_panel_settings', [$this, 'showBackupManagementSetting'], 40);   
        add_filter('prime_mover_filter_migratesites_column_markup', [$this, 'maybeFilterMigrateColumnMarkup'], 10, 3); 
    }
 
    /**
     * Maybe filter migrate column markup
     * @param array $markup
     * @param array $item
     * @param number $blog_id
     * @return array
     */
    public function maybeFilterMigrateColumnMarkup($markup = [], $item = [], $blog_id = 0)
    {
        if (!$blog_id) {
            return $markup;
        }
        
        if (true === $this->getComponentAux()->canSupportRestoreUrlInFreeMode()) {
            $markup = [
                sprintf(esc_attr__('Copy restore URL to clipboard. This requires PRO version at target %s to migrate this package.', 'prime-mover'), $item['package_type']),
                $item['download_url'],
                "button prime-mover-menu-button js-prime-mover-clipboard-button-responsive js-prime-mover-copy-clipboard-menu prime-mover-copy-clipboard-menu-button",
                esc_html__('Copy restore URL', 'prime-mover'),
                '<span id="' . esc_attr($item['sanitized_package_name']) . '" class="prime-mover-clipboard-key-confirmation-menu js-prime-mover-copy-clipboard-menu">' . esc_html__('Copied', 'prime-mover') . ' !' . '</span>',
                esc_attr($item['sanitized_package_name'])
            ];
        }
        
        return $markup;
    }
    
    /**
     * Compute backup dir size ajax
     */
    public function computeBackupDirSize()
    {
        $this->getBackupDirSize()->computeBackupDirSize();
    }
    
    /**
     * Save settings ajax
     */
    public function deleteAllBackups()
    {
        $this->getDeleteUtilities()->deleteAllBackups();
    }
    
    /**
     * Save settings ajax
     */
    public function saveSettings()
    {
        $response = [];       
        $copydir_preference = $this->getPrimeMoverSettings()->prepareSettings($response, 'copydir_preference', 'prime_mover_copydir_preference_nonce', 
            true, $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverSanitizeStringFilter());        
        
        $result = $this->saveSetting($copydir_preference);
        $this->getPrimeMoverSettings()->returnToAjaxResponse($response, $result);
    }
    
    /**
     * Save setting handler
     * @param string $copydir_preference
     * @return boolean[]|string[]
     */
    private function saveSetting($copydir_preference = '')
    {
        $this->getPrimeMoverSettings()->saveSetting(self::COPYBACKUP_DIR, $copydir_preference);
        $status = true;
        $message =  sprintf( esc_html__('Success! Custom backup directory will be %s back to default uploads backup directory when Control Panel plugin is %s.', 'prime-mover'), 
            '<strong>' . esc_html__('COPIED', 'prime-mover') . '</strong>', '<strong>' . esc_html__('DEACTIVATED', 'prime-mover') . '</strong>' );
        if ( 'true' === $copydir_preference) {
            $message =  sprintf( esc_html__('Success! Custom backup directory will %s back to default uploads backup directory when Control Panel plugin is %s.', 'prime-mover'), 
                '<strong>' . esc_html__('NOT BE COPIED', 'prime-mover') . '</strong>', '<strong>' . esc_html__('DEACTIVATED', 'prime-mover') . '</strong>' );
        }        
        return ['status' => $status, 'message' => $message];
    }
    
    /**
     * Check if user wants to copy custom backup dir to default when this plugin is deactivated
     * If setting is not set, returns TRUE by default.
     * @return boolean
     */
    public function maybeCopyCustomBackupDirToDefaultDir() 
    {     
        $current_setting = $this->getPrimeMoverSettings()->getSetting(self::COPYBACKUP_DIR);
        if ( ! $current_setting) {
            return true;
        }
        if ('true' === $current_setting) {
            return false;
        }
        return true;
    }
    
    /**
     * Show backup management setting
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupManagement::itShowsBackupManagementSetting();
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupManagement::itDoesNotShowBackupManagementWhenGearBoxDeactivated()
     */
    public function showBackupManagementSetting()
    {  
    ?>
       <h2><?php esc_html_e('Backup management', 'prime-mover')?></h2>
    <?php 
       $this->getDeleteUtilities()->outputDeleteAllBackupsMarkup();
       $this->getBackupDirSize()->outputBackupsDirSizeMarkup();
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