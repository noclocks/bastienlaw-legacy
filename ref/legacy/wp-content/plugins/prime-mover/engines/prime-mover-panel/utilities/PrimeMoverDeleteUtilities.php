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
 * Delete utilities class
 *
 */
class PrimeMoverDeleteUtilities
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
     * Get system utilities
     */
    public function getSystemUtilities() 
    {
        return $this->system_utilities;
    }
    
    /**
     * Save settings ajax
     * @tested TestPrimeMoverDeleteUtilities::itDeleteAllBackups()
     */
    public function deleteAllBackups()
    {
        $response = [];
        $delete_confirmation = $this->getPrimeMoverSettings()->prepareSettings($response, 'delete_confirmation', 
            'prime_mover_delete_all_backup_zips_network_nonce', true, $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverSanitizeStringFilter());

        $result = $this->processDeleteHandler($delete_confirmation);
        $this->getPrimeMoverSettings()->returnToAjaxResponse($response, $result);
    }
    
    /**
     * Process delete handler
     * @tested TestPrimeMoverDeleteUtilities::itDeleteAllBackups()
     * @param string $delete_confirmation
     * @return array
     */
    public function processDeleteHandler($delete_confirmation = '')
    {
        if ( 'yes' !== $delete_confirmation ) {
            return ['status' => false, 'message' => esc_html__('Error ! Invalid request', 'prime-mover')];
        }
        $status = $this->deleteAllBackupsHelper();
        if ($status) {
            return ['status' => true, 'message' => esc_html__('Success! All backups deleted', 'prime-mover')];
        }
        
        return ['status' => false, 'message' => esc_html__('Error deleting files. Please check for permission issues in your backup directory.', 'prime-mover')];
    }
 
    /**
     * Output delete all backups markup
     */
    public function outputDeleteAllBackupsMarkup()
    {
        ?>
        <table class="form-table">
        <tbody>
        <tr>
            <th scope="row">
                <label id="prime-mover-deletebackups-settings-label" for="delete_all_backup_zips_network"><?php esc_html_e('Delete ALL backups', 'prime-mover')?></label>
            </th>
            <td>
                <p><button data-nonce="<?php echo $this->getPrimeMover()->getSystemFunctions()->primeMoverCreateNonce('prime_mover_delete_all_backup_zips_network_nonce'); ?>" id="js-delete_all_backup_zips_network" class="button button-large prime-mover-deleteall-button" type="button">
                        <?php esc_html_e('Delete ALL Backups', 'prime-mover' ); ?></button></p>
                <div class="prime-mover-setting-description">
                    <p class="description prime-mover-settings-paragraph">
                    <?php printf( esc_html__('Using the above button, you can %s created by the plugin in %s. 
                   Careful, there is no way to restore these files once deleted!',  'prime-mover'), 
                        '<strong>' . esc_html__('delete all backup packages including logs', 'prime-mover'). '</strong>',
                        '<strong>' . esc_html__('in a single-site or all sites in this network if multisite', 'prime-mover' ) . '</strong>'
                        ); ?>
                    </p>
                    <p class="p_wrapper_prime_mover_setting">
                        <span class="js-delete_all_backup_zips_network-spinner prime_mover_settings_spinner"></span>
                    </p> 
                </div>                      
            </td>
        </tr>
        </tbody>
        </table>
    <?php
         echo $this->renderDeleteDialogMarkup();
    }
    
    /**
     *Render delete dialog markup
     */
    private function renderDeleteDialogMarkup()
    {
    ?>
        <div style="display:none;" id="js-prime-mover-panel-deleteall-dialog" title="<?php esc_attr_e('Warning!', 'prime-mover')?>"> 
			<p><?php printf( esc_html__('Are you really sure you want to %s', 'prime-mover'), 
			    '<strong>' . esc_html__('DELETE ALL BACKUPS', 'prime-mover') . '</strong>'); ?> ? </p>
			<p><?php esc_html_e('This will delete ALL backup zips and log files in your backup directory.', 'prime-mover')?></p>
			<p><?php esc_html_e('This will also delete all the troubleshooting logs.', 'prime-mover')?></p>
			<p><strong><?php esc_html_e('Once deleted, the process cannot be undone.')?></strong></p>		      	  	
        </div>
    <?php
    }
    
    /**
     * Delete all backups
     * @return boolean
     * @tested TestPrimeMoverDeleteUtilities::itDeleteAllBackups()
     */
    private function deleteAllBackupsHelper()
    {
        $backup_path = $this->getPrimeMover()->getSystemInitialization()->getMultisiteExportFolderPath();
        global $wp_filesystem;
        $dir = $wp_filesystem->dirlist($backup_path, false, false);
        if (empty($dir) && ! is_array($dir)) {
            return true;   
        }
        $blog_ids = array_keys($dir);
        $multisite = false;
        if (is_multisite()) {
            $multisite = true;
        }
        foreach ($blog_ids as $blog_id) {
            if (!filter_var($blog_id, FILTER_VALIDATE_INT, ["options" => ["min_range"=> 1]])) {
                continue;
            }
            $blog_id = (int) $blog_id;
            if (!$blog_id ) {
                continue;    
            }
            if ($multisite && !get_blogaddress_by_id($blog_id)) {
                continue;
            }
            $this->getSystemUtilities()->cleanBackupDirectoryUponRequest($blog_id);
        }
        
        $this->getPrimeMover()->getSystemFunctions()->primeMoverDoDelete($backup_path);
        
        return true;
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