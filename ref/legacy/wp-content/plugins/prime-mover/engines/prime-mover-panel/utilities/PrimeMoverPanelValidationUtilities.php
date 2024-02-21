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
use Codexonics\PrimeMoverFramework\advance\PrimeMoverTroubleshooting;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Panel Validation Utilities
 * Helper functionality for data validation processes
 *
 */
class PrimeMoverPanelValidationUtilities
{     
    private $prime_mover;
    private $import_utilities;
    private $troubleshooting;
    /**
     * Construct
     * @param PrimeMover $prime_mover
     * @param array $utilities
     */
    public function __construct(PrimeMover $prime_mover, $utilities = [], PrimeMoverTroubleshooting $troubleshooting = null)
    {
        $this->prime_mover = $prime_mover;
        $this->import_utilities = $utilities['import_utilities'];
        $this->troubleshooting = $troubleshooting;
    }
    
    /**
     * Get troubleshooting
     * @return \Codexonics\PrimeMoverFramework\advance\PrimeMoverTroubleshooting
     */
    public function getTroubleShooting()
    {
        return $this->troubleshooting;
    }
    
    /**
     * Get checkbox settinsg in advance panel
     * @return NULL[]
     */
    private function getCheckBoxSettings()
    {
        $troubleshooting = $this->getTroubleShooting();
        return [ 
            $troubleshooting::TROUBLESHOOTING_KEY, 
            $troubleshooting::PERSIST_TROUBLESHOOTING_KEY,
            $troubleshooting::ENABLE_JS_LOG,
            $troubleshooting::ENABLE_UPLOAD_JS_LOG
        ];        
    }
    
    /**
     * 
     * Return export utilities
     */
    public function getExportUtilities()
    {
        return $this->getImportUtilities()->getExportUtilities();
    }
    
    /**
     * Get import utilities
     */
    public function getImportUtilities()
    {
        return $this->import_utilities;
    }
       
    /**
    * Initialize hooks
    * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverPanelValidationUtilities::itChecksIfHooksAreOutdated() 
    * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverPanelValidationUtilities::itAddsInitHooks()
    */
    public function initHooks()
    {
        add_filter("prime_mover_get_validation_id_mysqldump_cnf", [$this, 'returnValidationMySQLCnfPath']);
        add_filter("prime_mover_get_validation_id_maintenance_mode", [$this, 'returnValidationMaintenanceMode']);
        
        add_filter("prime_mover_get_validation_id_settings_checkbox_validation", [$this, 'returnValidationCheckBoxSettings'], 10, 2);
        add_filter("prime_mover_get_validation_id_custombackdir", [$this, 'returnValidationCustomBackupDir']);              
    }  
 
    /**
     * Custom backup dir protocol validation
     * @return string[]
     */    
    public function returnValidationCustomBackupDir()
    {
        return ['custom_path' => 'custom_backup_dir'];
    }
    /**
     * Return validation protocol of checkbox settings
     * @param array $default
     * @param mixed $sanitized
     * @return array
     */
    public function returnValidationCheckBoxSettings($default = [], $sanitized = null)
    {
        if ( ! is_array($sanitized) ) {
            return $default;
        }
        $checkbox_settings = $this->getCheckBoxSettings();        
        foreach($checkbox_settings as $checkbox_setting) {
            if (array_key_exists($checkbox_setting, $sanitized)) {
                return [
                    $checkbox_setting => ['true', 'false'],
                    'savenonce' => 'nonce'
                ];
            }
        }
        return $default;        
    }
    
    /**
     * Return validation maintenance mode
     * @reviewed
     */
    public function returnValidationMaintenanceMode()
    {
        return [
            'turn_off_maintenance' => ['true', 'false'],
            'savenonce' => 'nonce'
        ];
    }
    
    /**
     * Return validation settings for MySQL config
     * @reviewed
     */
    public function returnValidationMySQLCnfPath()
    {
        return [
            'mysqldump_cnf_path' => 'mysql_config',
            'savenonce' => 'nonce'
        ];
    }
}
