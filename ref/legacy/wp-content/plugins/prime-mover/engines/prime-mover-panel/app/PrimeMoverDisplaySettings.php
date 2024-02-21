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
 * Display Prime Mover settings
 *
 */
class PrimeMoverDisplaySettings
{
    private $prime_mover_customdir_settings;
    private $prime_mover_excludedplugin_settings;
    private $prime_mover_excluded_uploads_settings;
    private $prime_mover_display_maintenance_settings;
    private $prime_mover_display_security_settings;
    private $prime_mover_display_dropbox_settings;
    private $prime_mover_display_encryption_settings;
    private $prime_mover_display_gdrive_settings;
    private $prime_mover_runtime_setings;
    
    /**
     * Constructor
     * @param PrimeMoverDisplayCustomDirSettings $prime_mover_customdir_settings
     * @param PrimeMoverDisplayExcludedPluginsSettings $prime_mover_excludedplugin_settings
     * @param PrimeMoverDisplayExcludedUploadSettings $prime_mover_excluded_uploads_settings
     * @param PrimeMoverDisplayMaintenanceSettings $prime_mover_display_maintenance_settings
     * @param PrimeMoverDisplaySecuritySettings $prime_mover_display_security_settings
     * @param PrimeMoverDisplayDropBoxSettings $prime_mover_display_dropbox_settings
     * @param PrimeMoverDisplayEncryptionSettings $prime_mover_display_encryption_settings
     * @param PrimeMoverDisplayGDriveSettings $prime_mover_display_gdrive_settings
     * @param PrimeMoverDisplayRunTimeSettings $prime_mover_runtime_setings
     */
    public function __construct(
        PrimeMoverDisplayCustomDirSettings $prime_mover_customdir_settings, 
        PrimeMoverDisplayExcludedPluginsSettings $prime_mover_excludedplugin_settings,
        PrimeMoverDisplayExcludedUploadSettings $prime_mover_excluded_uploads_settings,
        PrimeMoverDisplayMaintenanceSettings $prime_mover_display_maintenance_settings,
        PrimeMoverDisplaySecuritySettings $prime_mover_display_security_settings,
        PrimeMoverDisplayDropBoxSettings $prime_mover_display_dropbox_settings,
        PrimeMoverDisplayEncryptionSettings $prime_mover_display_encryption_settings,
        PrimeMoverDisplayGDriveSettings $prime_mover_display_gdrive_settings,
        PrimeMoverDisplayRunTimeSettings $prime_mover_runtime_setings
        ) 
    {
        $this->prime_mover_customdir_settings = $prime_mover_customdir_settings;
        $this->prime_mover_excludedplugin_settings = $prime_mover_excludedplugin_settings;
        $this->prime_mover_excluded_uploads_settings = $prime_mover_excluded_uploads_settings;
        $this->prime_mover_display_maintenance_settings = $prime_mover_display_maintenance_settings;
        $this->prime_mover_display_security_settings = $prime_mover_display_security_settings;
        $this->prime_mover_display_dropbox_settings = $prime_mover_display_dropbox_settings;
        $this->prime_mover_display_encryption_settings = $prime_mover_display_encryption_settings;     
        $this->prime_mover_display_gdrive_settings = $prime_mover_display_gdrive_settings;
        $this->prime_mover_runtime_setings = $prime_mover_runtime_setings;
    }
    
    /**
     * Get Prime Mover runtime settings
     * @return \Codexonics\PrimeMoverFramework\app\PrimeMoverDisplayRunTimeSettings
     */
    public function getPrimeMoverRunTimeSettings()
    {
        return $this->prime_mover_runtime_setings;
    }

    /**
     * Display Gdrive settings instance
     * @return \Codexonics\PrimeMoverFramework\app\PrimeMoverDisplayGDriveSettings
     */
    public function getPrimeMoverDisplayGdriveSettings()
    {
        return $this->prime_mover_display_gdrive_settings;
    }
    
    /**
     * Display encryption settings instance
     * @return \Codexonics\PrimeMoverFramework\app\PrimeMoverDisplayEncryptionSettings
     */
    public function getPrimeMoverDisplayEncryptionSettings()
    {
        return $this->prime_mover_display_encryption_settings;
    }
    
    /**
     * Custom dir settings instance
     * @return \Codexonics\PrimeMoverFramework\app\PrimeMoverDisplayCustomDirSettings
     */
    public function getPrimeMoverCustomDirSettings()
    {
        return $this->prime_mover_customdir_settings;
    }

    /**
     * Display exclude plugin settings
     * @return \Codexonics\PrimeMoverFramework\app\PrimeMoverDisplayExcludedPluginsSettings
     */
    public function getPrimeMoverExcludePluginSetings()
    {
        return $this->prime_mover_excludedplugin_settings;
    }

    /**
     * Get Prime Mover exclude uplaods
     * @return \Codexonics\PrimeMoverFramework\app\PrimeMoverDisplayExcludedUploadSettings
     */
    public function getPrimeMoverExcludedUploads()
    {
        return $this->prime_mover_excluded_uploads_settings;
    }  
 
    /**
     * Get display maintenace mode settings
     * @return \Codexonics\PrimeMoverFramework\app\PrimeMoverDisplayMaintenanceSettings
     */
    public function getPrimeMoverDisplayMaintenanceSettings()
    {
        return $this->prime_mover_display_maintenance_settings;
    } 
    
    /**
     * Get display security settings
     * @return \Codexonics\PrimeMoverFramework\app\PrimeMoverDisplaySecuritySettings
     */
    public function getDisplaySecuritySettings()
    {
        return $this->prime_mover_display_security_settings;
    } 
 
    /**
     * Get display Dropbox settings
     * @return \Codexonics\PrimeMoverFramework\app\PrimeMoverDisplayDropBoxSettings
     */
    public function getDisplayDropBoxSettings()
    {
        return $this->prime_mover_display_dropbox_settings;
    }
    
    /**
     * Get Prime Mover instance
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMover
     */
    public function getPrimeMover()
    {
        return $this->getPrimeMoverCustomDirSettings()->getPrimeMover();
    }
    
    /**
     * Get Prime Mover settings
     * @return \Codexonics\PrimeMoverFramework\app\PrimeMoverSettings
     */
    public function getPrimeMoverSettings()
    {
        return $this->getPrimeMoverCustomDirSettings()->getPrimeMoverSettings();
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
     * Get authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     */
    public function getSystemAuthorization()
    {
        return $this->getPrimeMover()->getSystemAuthorization();
    }
    
    /**
     * Get component utilities
     */
    public function getComponentAuxiliary()
    {
        return $this->getPrimeMoverSettings()->getComponentUtilities();
    }
    
    /**
     * Initialize plugin settings display hooks
     */
    public function initHooks()
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        add_action('prime_mover_control_panel_settings', [$this, 'showCustomExportDirectorySetting'], 1); 
        add_action('prime_mover_control_panel_settings', [$this, 'showExcludedPluginsSetting'], 11);        
        add_action('prime_mover_control_panel_settings', [$this, 'showExcludedUploadsSetting'], 12); 
        
        add_action('prime_mover_control_panel_settings', [$this, 'showMaintenanceModeSetting'], 10);    
        add_action('prime_mover_control_panel_settings', [$this, 'showRunTimeParameterSettings'], 15); 
        add_action('prime_mover_control_panel_settings', [$this, 'showDownloadSecuritySetting'], 20);  
        
        add_action('prime_mover_control_panel_settings', [$this, 'showDownloadAuthenticationSetting'], 30); 
        
        add_action('prime_mover_control_panel_settings', [$this, 'showDropBoxSetting'], 30); 
        add_action('prime_mover_control_panel_settings', [$this, 'showEncryptionSetting'], 35); 
        add_action('prime_mover_control_panel_settings', [$this, 'showGdriveSettings'], 30); 
        
        add_action('prime_mover_last_table_heading_settings', [$this, 'maybeInformProSetting'], 10, 1);   
        add_action('prime_mover_panel_after_enqueue_assets', [$this, 'primeMoverEnqueueClipBoardJs'], 10);
    }
 
    public function showRunTimeParameterSettings()
    {
        $this->getPrimeMoverRunTimeSettings()->showRunTimeParameterSettings();
    }
    
    /**
     * Enqueue clipboard JS that is needed for settings
     */
    public function primeMoverEnqueueClipBoardJs()
    {
        $this->getComponentAuxiliary()->enqueueClipBoardJs();        
    }
    
    /**
     * Display Gdrive settings
     */
    public function showGdriveSettings()
    {
        $this->getPrimeMoverDisplayGdriveSettings()->showGdriveSettings();
    }
    
    /**
     * Display encryption settings
     */
    public function showEncryptionSetting()
    {
        $this->getPrimeMoverDisplayEncryptionSettings()->showEncryptionSetting();
    }
    
    /**
     * Display DropBox settings
     */
    public function showDropBoxSetting()
    {
        $this->getDisplayDropBoxSettings()->showDropBoxSetting();
    }
    
    /**
     * Show download authentication settings
     */
    public function showDownloadAuthenticationSetting()
    {
        $this->getDisplaySecuritySettings()->showDownloadAuthenticationSetting();
    }
    
    /**
     * Show security settings
     */
    public function showDownloadSecuritySetting()
    {
        $this->getDisplaySecuritySettings()->showDownloadSecuritySetting();
    }
    
    /**
     * Show maintenance mode setting
     */
    public function showMaintenanceModeSetting()
    {
        $this->getPrimeMoverDisplayMaintenanceSettings()->showMaintenanceModeSetting();
    }
    
    /**
     * Show excluded uploads settings
     */
    public function showExcludedUploadsSetting()
    {
        $this->getPrimeMoverExcludedUploads()->showExcludedUploadsSetting();
    }
    
    /**
     * Display exclude plugin settings
     */
    public function showExcludedPluginsSetting()
    {
        $this->getPrimeMoverExcludePluginSetings()->showExcludedPluginsSetting();
    }
    
    /**
     * Show custom export path directory setting
     */
    public function showCustomExportDirectorySetting()
    {
        $this->getPrimeMoverCustomDirSettings()->showCustomExportDirectorySetting();
    }
     
    /**
     * Maybe display documentation link for a setting
     * @param string $url
     */
    public function maybeInformProSetting($url = '')
    {
        if ( false === apply_filters('prime_mover_is_loggedin_customer', false)) { ?>
            <p class="prime_mover_pro_class_heading_table"><span class="dashicons dashicons-lock prime-mover-dashicons-lock"></span><?php esc_html_e('PRO FEATURE', 'prime-mover'); ?></p>        
    <?php 
        } else {            
            if ($url) {
    ?>    
            <p class="prime-mover-settings-doc-link">
                <a class="prime-mover-external-link" target="_blank" href="<?php echo esc_url($url); ?>"><?php esc_html_e('Documentation', 'prime-mover'); ?></a>
            </p>
    <?php 
            }
        }
    }
}