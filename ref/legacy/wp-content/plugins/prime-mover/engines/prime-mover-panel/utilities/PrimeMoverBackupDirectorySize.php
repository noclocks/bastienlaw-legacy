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
 * Backup directory size utilities class
 *
 */
class PrimeMoverBackupDirectorySize
{
    private $prime_mover;
    private $system_authorization;
    private $settings;
    private $system_utilities;

    /**
     * Constructor
     * @param PrimeMover $PrimeMover
     * @param PrimeMoverSystemAuthorization $system_authorization
     * @param array $utilities
     * @param PrimeMoverSettings $settings
     */
    public function __construct(PrimeMover $PrimeMover, PrimeMoverSystemAuthorization $system_authorization, array $utilities, PrimeMoverSettings $settings) 
    {
        $this->prime_mover = $PrimeMover;
        $this->system_authorization = $system_authorization;
        $this->settings = $settings;
        $this->system_utilities = $utilities['sys_utilities'];        
    }

    /**
     * Compute backup dir size
     */
    public function computeBackupDirSize()
    {
        $response = [];        
        $computebackup_dirsize = $this->getPrimeMoverSettings()->prepareSettings($response, 'compute_dir_size', 
            'prime_mover_compute_backup_directory_size_nonce', true, $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverSanitizeStringFilter());

        $result = $this->computeBackupDirSizeHelper($computebackup_dirsize);        
        $this->getPrimeMoverSettings()->returnToAjaxResponse($response, $result);
    }
    
    /**
     * Compute backup dir size helper
     * @param string $computebackup_dirsiz
     */
    private function computeBackupDirSizeHelper($computebackup_dirsize = '')
    {
        if ('yes' !== $computebackup_dirsize) {
            return ['status' => false, 'message' => esc_html__('Error ! Invalid request', 'prime-mover')];
        }
        $backup_path = $this->getPrimeMover()->getSystemInitialization()->getMultisiteExportFolderPath();
        $total_size = $this->getDirectorySize($backup_path);
        if ($total_size <= 768) {
            $total_size = 0;
        }
        if ( false === $total_size ) {
            return ['status' => false, 'message' => esc_html__('Error ! There is a problem computing backup dir size.', 'prime-mover')];
        }
        return ['status' => true, 'message' => sprintf( esc_html__('Total backup size is %s', 'prime-mover'), 
            '<strong>' . $this->getPrimeMover()->getSystemFunctions()->humanFileSize($total_size, 2) . '</strong>' )];
    }

    /**
     * Get directory size given a path
     * Improved form of this version:
     * https://stackoverflow.com/questions/478121/how-to-get-directory-size-in-php
     * @param string $path
     * @return boolean|number
     */
    private function getDirectorySize($path = '')
    {
        if ( ! $path ) {
            return false;
        }
        $bytestotal = 0;
        $path = realpath($path);
        
        if(false !== $path && file_exists($path)){
            foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $object){
                if ( '.htaccess' === $object->getFilename()) {
                    continue;
                }
                $path = $object->getPathname();
                $bytestotal += $this->getPrimeMover()->getSystemFunctions()->fileSize64($path);
            }
        }
        
        return $bytestotal;
    }
    
    /**
     * Get system utilities
     */
    public function getSystemUtilities() 
    {
        return $this->system_utilities;
    }
 
    /**
     * Output backups dir size markup
     */
    public function outputBackupsDirSizeMarkup()
    {
        ?>
        <table class="form-table">
        <tbody>
        <tr>
            <th scope="row">
                <label id="prime-mover-backupstats-settings-label"><?php esc_html_e('Backup stats', 'prime-mover')?></label>
            </th>
            <td>                 
                <p><button data-nonce="<?php echo $this->getPrimeMover()->getSystemFunctions()->primeMoverCreateNonce('prime_mover_compute_backup_directory_size_nonce'); ?>" id="js-prime-mover-backup-directory-size-button" class="button button-primary" type="button">
                        <?php esc_html_e('Compute Backup Directory Size', 'prime-mover' ); ?></button></p>
                <div class="prime-mover-setting-description">
                    <p class="description prime-mover-settings-paragraph">
                    <?php esc_html_e('Using the above button, you can compute the total backup directory size. You can use this information to decide whether its time to clean up or delete old backups.', 'prime-mover');?>
                    </p>
                    <p class="p_wrapper_prime_mover_setting">
                        <span class="js-prime-mover-backup-directory-size-spinner prime_mover_settings_spinner"></span>
                    </p> 
                </div>                      
            </td>
        </tr>
        </tbody>
        </table>
    <?php
    }
    
    /**
     * Get multisite migration settings
     * @return \Codexonics\PrimeMoverFramework\app\PrimeMoverSettings
     */
    public function getPrimeMoverSettings() 
    {
        return $this->settings;
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
}