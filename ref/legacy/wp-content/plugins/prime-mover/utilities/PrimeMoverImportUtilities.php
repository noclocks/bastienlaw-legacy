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

use Codexonics\PrimeMoverFramework\classes\PrimeMoverImporter;
use WP_Error;
use ZipArchive;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Import Utilities
 * Helper functionality for Import.
 *
 */
class PrimeMoverImportUtilities
{
    private $importer;
    private $export_utilities;
    private $lock_utilities;
    
    /**
     * Constructor
     * @param PrimeMoverImporter $importer
     * @param PrimeMoverExportUtilities $export_utilities
     * @param PrimeMoverLockUtilities $lock_utilities
     */
    public function __construct(PrimeMoverImporter $importer, PrimeMoverExportUtilities $export_utilities, PrimeMoverLockUtilities $lock_utilities)
    {
        $this->importer = $importer;
        $this->export_utilities = $export_utilities;
        $this->lock_utilities = $lock_utilities;
    }

    /**
     * Get lock utilities
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverLockUtilities
     */
    public function getLockUtilities()
    {
        return $this->lock_utilities;
    }
    
    /**
     * Get importer
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverImporter
     * @compatibility 5.6
     */
    public function getImporter()
    {
        return $this->importer;
    }
    
    /**
     * Get export utilities
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverExportUtilities
     * @compatibility 5.6
     */
    public function getExportUtilities()
    {
        return $this->export_utilities;
    }
    
    /**
     * Gets System authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     * @compatibility 5.6
     */
    public function getSystemAuthorization()
    {
        return $this->getImporter()->getSystemAuthorization();
    }
    
    /**
     * Init hooks
     * @compatibility 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImportUtilities::itAddsInitHooks() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImportUtilities::itChecksIfHooksAreOutdated()
     */
    public function initHooks()
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        add_action('prime_mover_render_import_button', [$this, 'showCoreImportButton'], 10, 1);
        add_filter('prime_mover_import_plugins', [ $this, 'primeMoverImportPluginsFunc' ], 10, 4);
        add_filter('prime_mover_import_plugins_by_shell', [ $this, 'primeMoverImportPluginsFunc' ], 10, 5);
        add_filter('prime_mover_import_themes', [ $this, 'primeMoverImportThemesFunc' ], 10, 3);
        
        add_action('prime_mover_do_after_import_button', [ $this, 'importWarnMismatchSite'], 3, 1);
        add_action('prime_mover_do_after_import_button', [ $this, 'importWrongFileType'], 4, 1);        
        add_action('prime_mover_do_after_import_button', [ $this, 'importWarning'], 5, 1);
        add_action('prime_mover_do_after_import_button', [ $this, 'importDiffDialog'], 10, 1);
        
        add_action('prime_mover_do_after_import_button', [ $this, 'importCancelSuccess'], 15, 1);
        add_action('prime_mover_do_after_import_button', [ $this, 'importCancelFail'], 20, 1);
        add_action('prime_mover_do_after_import_button', [ $this, 'genericFailNotice'], 25, 1);
        add_action('prime_mover_do_after_import_button', [ $this, 'importDoneDialog'], 30, 1);

        add_filter('prime_mover_filter_config_after_diff_check', [ $this, 'verifyPluginsThemesIncluded'], 15, 1);
        add_filter('prime_mover_filter_config_after_diff_check', [ $this, 'filterDiff'], 10, 1);    
        
        add_action('prime_mover_after_actual_import', [ $this, 'markedUpdateTrackerCompleted'], PHP_INT_MAX );
        add_filter('prime_mover_ajax_rendered_js_object', [$this, 'setRestoreModeDialogTexts'], 100, 1 );
        add_filter('prime_mover_filter_upload_phrase', [$this, 'singleSiteUploadInfoCompat'], 10, 3);
        
        add_filter('prime_mover_ajax_rendered_js_object', [$this, 'setImportProcessError'], 110, 1 );
        add_filter('prime_mover_ajax_rendered_js_object', [$this, 'setImportMethodLists'], 110, 1 );
    }    

    /**
     * Set import methods list
     * @param array $args
     * @return array
     */
    public function setImportMethodLists($args = [])
    {
        $args['prime_mover_import_method_lists'] = $this->getImporter()->getSystemInitialization()->getPrimeMoverImportMethods();        
        return $args;
    }
    
    /**
     * Setup ongoing import process error
     * @param array $args
     * @return array
     */
    public function setImportProcessError( array $args )
    {       
        $args['prime_mover_importprocess_error_message'] = esc_js(
            "<p>" . sprintf(__('Import process fails for site ID : {{BLOGID}}. Retry is attempted but still fails after %s seconds.', 'prime-mover'), '<strong>{{RETRYSECONDS}}</strong>') . "</p>" .
            "<p>" . '<strong>' . __('Server Error : {{PROGRESSSERVERERROR}}', 'prime-mover') . '</strong>' . "</p>" .
            "<p>" . __('Error occurs while processing', 'prime-mover') . ' ' . "<strong>{{IMPORTMETHODWITHERROR}}</strong>" . ' ' . __('method.', 'prime-mover') . "</p>" .
            "<p><strong>" . sprintf(__('Can you try increasing the web server timeout beyond %s', 'prime-mover'), '<strong>{{FIXEDSECONDS}}</strong>') . ' ' . __('seconds', 'prime-mover') ."?</strong></p>" .
            "<p>" . __('This might help resolve this issue when restoring/importing large sites.', 'prime-mover') . "</p>"
            );        
        
        $args['prime_mover_unknown_import_process_error'] = esc_js(__('unknown', 'prime-mover'));
        
        return $args;
    }
    
    /**
     * Single site alternative upload URL replace
     * @param array $upload_phrase
     * @param array $ret
     * @param array $replaceables
     * @return array
     */
    public function singleSiteUploadInfoCompat($upload_phrase = [], $ret = [], $replaceables = [])
    {
        if ( ! empty($ret['imported_package_footprint']['legacy_upload_information_url']) ) {            
            return $upload_phrase;
        }
        if (empty($upload_phrase['wpupload_url']['search']) || empty($upload_phrase['wpupload_url']['replace'])) {
            return $upload_phrase;
        }
        if (empty($ret['origin_site_url'])) {
            return $upload_phrase;
        }
        $origin_site_url = $ret['origin_site_url'];
        $current_upload_url = $upload_phrase['wpupload_url']['search'];
        $target_upload_url = $upload_phrase['wpupload_url']['replace'];
        
        if (false !== strpos($current_upload_url, $origin_site_url)) {            
            return $upload_phrase;
        }
        
        $origin_domain = parse_url($current_upload_url, PHP_URL_HOST);
        $alternative_upload_url = str_replace($origin_domain, $origin_site_url, $current_upload_url);

        $upload_phrase['wpupload_url_alternative']['search'] =  $alternative_upload_url;
        $upload_phrase['wpupload_url_alternative']['replace'] = $target_upload_url;        
        
        return $upload_phrase;
    }
    
    /**
     * Set restore mode dialog texts
     * @param array $args
     * @return array
     */
    public function setRestoreModeDialogTexts( array $args ) 
    {
        
        $args['prime_mover_upload_package_mode'] = esc_js(__('Upload package', 'prime-mover'));
        $args['prime_mover_restorewithinserver_mode'] = esc_js(__('Restore within server backup', 'prime-mover'));
        $args['prime_mover_encrypted_note'] = apply_filters('prime_mover_encryption_note_text', '');
        
        $args['prime_mover_restore_remote_url_mode'] = esc_js(__('Restore from a remote URL package', 'prime-mover'));
        $args['prime_mover_restore_encrypted_package_warning'] = esc_js(__('Yes', 'prime-mover'));
        $args['prime_mover_restore_noencryption_warning'] = esc_js(__('No', 'prime-mover'));
        
        return $args;
    }
    
    /**
     * Show core import button
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImportUtilities::itShowsCoreImportButton()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImportUtilities::itLoadsMobileClassOnCoreImportWhenMobile()
     */
    public function showCoreImportButton($blog_id = 0) 
    {
        if ( ! $blog_id ) {
            return;
        }
        $mobile_class = '';
        $target = apply_filters('prime_mover_filter_restore_button_text', esc_html__('RESTORE', 'prime-mover'), $blog_id);
        if (is_multisite()) {
            $button_text = sprintf(esc_html__('%s blog ID: %d', 'prime-mover'), $target, $blog_id);         
        } else {
            $button_text = $target;
        }        
        if (wp_is_mobile()) {
            $mobile_class = 'prime_mover_is_mobile';
            $button_text = $target;
        }
    ?>
       <label for="js-prime_mover_importing_blog_<?php echo esc_attr($blog_id) ; ?>" id="js-prime-mover-browseupload-label-<?php echo esc_attr($blog_id) ; ?>" 
       class="<?php echo apply_filters('prime_mover_filter_button_class', $this->getImporter()->getSystemInitialization()->defaultButtonClasses(), $blog_id); ?> prime-mover-fileupload-label">
           <?php echo $button_text; ?>
       </label>
	    	<input name ="prime_mover_importbrowsefile" 
	    			type ="file"
	    			class = "prime_mover_importbrowsefile js-prime_mover_importbrowsefile <?php echo esc_attr($mobile_class);?>"
	    			accept =".wprime, .zip"
	    			id ="js-prime_mover_importing_blog_<?php echo esc_attr($blog_id) ; ?>"
	    			data-multisiteblogid = "<?php echo esc_attr($blog_id); ?>" />    
    <?php       
    }

    /**
     * Officially mark update tracker completed
     * @compatibility 5.6
     */
    public function markedUpdateTrackerCompleted() 
    {        
        $this->getImporter()->getProgressHandlers()->updateTrackerProgress(esc_html__('Finalizing restore..', 'prime-mover'));         
        $this->getImporter()->getSystemInitialization()->setProcessingDelay(3);
    }
    
    /**
     * Filter diff if its empty
     * @param array $ret
     * @compatibility 5.6
     */
    public function filterDiff(array $ret)
    {
        if (! isset($ret['diff'])) {
            return $ret;
        }
        $is_empty_diff = array_filter($ret['diff']);
        if (empty($is_empty_diff)) {
            unset($ret['diff']);
        }
        return $ret;
    }
    
    /**
     * Get system utilities
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverSystemUtilities
     */
    public function getSystemUtilities()
    {
        return $this->getSystemCheckUtilities()->getSystemUtilities();
    }
    
    /**
     * Verify if themes and plugins are included in import package
     * @param array $import_data
     * @return array
     * @compatibility 5.6
     */
    public function verifyPluginsThemesIncluded(array $import_data)
    {
        $include_themes = $this->checkIfPackageIncludeThemes($import_data);
        $include_plugins = $this->checkIfPackageIncludePlugins($import_data);
        $unset_diff = false;
        
        $themeless = $this->getSystemUtilities()->isRestoringThemeLessPackage($import_data);        
        $package_with_plugins_themes = $this->getExportUtilities()->getExporter()->getCanExportPluginsThemes();
        $export_option = '';
        if (!empty($import_data['wprime_tar_config_set']['export_options'])) {
            $export_option = $import_data['wprime_tar_config_set']['export_options'];
        }
        
        if ($include_themes && $include_plugins && isset($import_data['diff'])) {
            $unset_diff = true;
        }       
        
        if ($themeless && isset($import_data['diff']['themes'])) {
            $import_data['diff']['themes'] = [];
        }
        
        if (!$unset_diff && empty($import_data['diff']['themes']) && empty($import_data['diff']['plugins'])) {   
            $unset_diff = true;
        }
             
        if (!$unset_diff && in_array($export_option, $package_with_plugins_themes) && $themeless) {
            $unset_diff = true;
        }
        
        if ($unset_diff && isset($import_data['diff'])) {
            unset($import_data['diff']);
        }        
        
        return $import_data;
    }
    
    /**
     *
     * @param int $blog_id
     * @compatibility 5.6
     */
    public function importCancelFail($blog_id = 0)
    {
        if ( ! $blog_id ) {
            return;
        }
        ?>
        <div style="display:none;" id="js-prime-mover-cancel-import-diff-fail-<?php echo esc_attr($blog_id); ?>" title="<?php esc_attr_e('Import Cancelled!', 'prime-mover')?>"> 
			<p><?php esc_html_e('You cancel the import. Nothing is changed on the server.', 'prime-mover'); ?></p>  	  	
        </div>
    <?php
    }

    /**
     *
     * @param int $blog_id
     * @compatibility 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImportUtilities::itDisplaysFailedUploadNotice() 
     */
    public function genericFailNotice($blog_id = 0)
    {
        if ( ! $blog_id ) {
            return;   
        }
        ?>
        <div style="display:none;" id="js-prime-mover-import-generic-fail-<?php echo esc_attr($blog_id); ?>" title="<?php esc_attr_e('Error!', 'prime-mover')?>"> 
			<p></p>  	  	
        </div>
    <?php
    }
    
    /**
     *
     * @param int $blog_id
     * @compatibility 5.6
     */
    public function importCancelSuccess($blog_id = 0)
    {
        if ( ! $blog_id ) {
            return;   
        }
        ?>
        <div style="display:none;" id="js-prime-mover-cancel-import-diff-<?php echo esc_attr($blog_id); ?>" title="<?php esc_attr_e('Import Cancelled!', 'prime-mover')?>"> 
			<p><?php esc_html_e('You cancel the import and temp file deleted. Nothing is changed on the server.', 'prime-mover')  ?></p>  	  	
        </div>
    <?php
    }

    /**
     * Warn user of a mismatch import
     * @param int $blog_id
     * @compatibility 5.6
     */
    public function importWarnMismatchSite($blog_id = 0)
    {
        if ( ! $blog_id ) {
            return;   
        }
        ?>
        <div style="display:none;" id="js-prime-mover-wrong-importedsite-<?php echo esc_attr($blog_id); ?>" title="<?php esc_attr_e('Error!', 'prime-mover')?>"> 
			<p><?php echo $this->getImporter()->getSystemInitialization()->returnCommonWrongTargetSiteError(); ?></p>  	  	
        </div>
    <?php
    }
    
    /**
     * Shows import done dialog
     * @param int $blog_id
     * @compatibility 5.6
     */
    public function importDoneDialog($blog_id = 0)
    {
        if ( ! $blog_id ) {
            return;   
        }
        $permalinks_page = $this->getSystemCheckUtilities()->getSystemUtilities()->generateUrlToPermalinksPage($blog_id);
        ?>
        <div style="display:none;" id="js-prime-mover-import-done-dialog-<?php echo esc_attr($blog_id); ?>" title="<?php esc_attr_e('Hooray!', 'prime-mover')?>"> 
            <h3 class="prime-mover-success-p-dialog"><?php esc_html_e('Your site has been restored successfully', 'prime-mover');?></h3>
			<ul class="prime-mover-ul-dialog">
                <li>
                <a target="_blank" class="prime-mover-external-link" href="<?php echo esc_url($permalinks_page); ?>">
                <?php esc_html_e('Re-save permalinks', 'prime-mover'); ?></a> <?php esc_html_e('to make sure site front end URL works', 'prime-mover');?>.
                <em><?php esc_html_e('Purge all cache if using caching plugin', 'prime-mover'); ?>.</em>
                </li>
  
                <li><?php esc_html_e('Optionally', 'prime-mover');?>, <a target="_blank" href="https://wordpress.org/support/view/plugin-reviews/prime-mover?rate=5#postform" class="prime-mover-external-link"><?php esc_html_e('review the Prime Mover plugin', 'prime-mover');?></a></li>
            </ul>	  	
        </div>
    <?php
    }
    
    /**
     * Shows wrong import filetype
     * @param int $blog_id
     * @compatibility 5.6
     */
    public function importWrongFileType($blog_id = 0)
    {
        if ( ! $blog_id ) {
            return;
        }
        ?>
        <div style="display:none;" id="js-prime-mover-wrong-filetype-<?php echo esc_attr($blog_id); ?>" title="<?php esc_attr_e('Error!', 'prime-mover')?>"> 
			<p><?php esc_html_e('Invalid file type or corrupted package.', 'prime-mover'); ?></p>  	  	
        </div>
    <?php
    }
    
    /**
     * Shows import warning to the user
     * @param int $blog_id
     * @compatibility 5.6
     */
    public function importWarning($blog_id = 0)
    {
        if (!$blog_id) {
            return;   
        }
        $upload_max_filesize = $this->getSystemCheckUtilities()->getSystemFunctions()->getUploadMaxFilesizeCorePhpValue();
        $post_max_size = $this->getSystemCheckUtilities()->getSystemFunctions()->getPostMaxSizeCorePhpValue();
        $server_upload_limits = "upload_max_filesize = $upload_max_filesize | post_max_size = $post_max_size";
        ?>
        <div style="display:none;" id="js-prime-mover-import-warning-confirm-<?php echo esc_attr($blog_id); ?>" title="<?php esc_attr_e('Heads Up!', 'prime-mover')?>"> 
          <p class="js-prime-mover-upload-option-selected prime-mover-upload-option-selected">
          <?php echo esc_html__('It will take around ', 'prime-mover') . ' ' . "<code id='js-prime-mover-computed-uploadtime' title='" . esc_attr($server_upload_limits) . "'></code>" . ' ' . 
              esc_html__('to upload this package', 'prime-mover'); ?>.              
          <?php
             $backups_menu_url = $this->getSystemCheckUtilities()->getSystemFunctions()->getBackupMenuUrl($blog_id);
             echo sprintf(esc_html__('You can also copy this to %s using FTP and restore in %s.', 'prime-mover'), 
                 '<a href="' . $backups_menu_url . '">' . esc_html__('Prime Mover package path', 'prime-mover') . '</a>' , '<em>' . esc_html__('Prime Mover -> Packages', 'prime-mover') . '</em>'); 
             ?>    
          </p>
          <?php if ( false === apply_filters('prime_mover_is_loggedin_customer', false)) { ?>
          <p class="js-prime-mover-upload-option-selected prime-mover-upload-option-selected">
              <a target="_blank" href="<?php echo esc_url($this->getImporter()->getSystemFunctions()->getUpgradeUrl()); ?>">
              <?php echo esc_html__('Upgrade to PRO', 'prime-mover');?></a> <?php echo esc_html__('to use all migration and backup options.', 'prime-mover');?></p>  
          <?php 
          }
          ?>        
    	  <p><?php esc_html_e('Import process deletes old site and will be replaced with the imported package.', 'prime-mover') ?> <?php esc_html_e('Please verify that below is correct:', 'prime-mover') ?></p>
    	  
    	  <ul>
    	      <li class="restoration-mode-prime-mover-li" id="js-restoration-mode-prime-mover"><strong><?php esc_html_e('Restoration mode', 'prime-mover');?></strong>: <span class="js-prime-mover-warning-restoration-mode"></span></li> 
    	      <li class="restoration-source-prime-mover-li" id="js-restoration-source-prime-mover"><strong><?php esc_html_e('Source', 'prime-mover');?></strong>: <span class="js-prime-mover-warning-source-of-origin"></span></li>
    	      <li class="restoration-package-size-prime-mover-li" id="js-restoration-package-size-prime-mover"><strong><?php esc_html_e('Package size', 'prime-mover');?></strong>: <span class="js-prime-mover-package-size-dialog"></span></li> 
    	      <?php if (is_multisite() ) { ?>   	      
    	      <li class="restoration-blog-id-prime-mover" id="js-restoration-blog-id-prime-mover"><strong><?php esc_html_e('Target blog ID', 'prime-mover');?></strong>: <span class="js-prime-mover-warning-target-blog-id"></span></li>
    	      <?php } ?>
    	      <li class="restoration-site-title-prime-mover-li" id="js-restoration-site-title-prime-mover"><strong><?php esc_html_e('Site title', 'prime-mover');?></strong>: <span class="js-prime-mover-warning-target-site-title"></span></li>
    	      <li class="restoration-encrypted-database-prime-mover-li" id="js-restoration-encrypted-database-prime-mover"><strong><?php esc_html_e('Encrypted database', 'prime-mover');?></strong>: <span class="js-prime-mover-warning-restoring-encrypted"></span></li>
    	      <li class="restoration-encrypted-mediafiles-prime-mover-li" id="js-restoration-encrypted-mediafiles-prime-mover"><strong><?php esc_html_e('Encrypted media files', 'prime-mover');?></strong>: <span class="js-prime-mover-encrypted-media"></span></li>    	      
    	      <li class="restoration-encrypted-wprime-prime-mover-li" id="js-restoration-encrypted-wprime"><strong><?php esc_html_e('Encrypted package', 'prime-mover');?></strong>: <span class="js-prime-mover-encrypted-wprime"></span></li>   	      
    	      <li class="restoration-description-prime-mover-li" id="js-restoration-description-prime-mover"><strong><?php esc_html_e('Description', 'prime-mover');?></strong>: <span class="js-prime-mover-warning-scope-mode"></span></li>
    	  </ul>    	  
    	  <p><span class="js-prime-mover-encrypted-package-note prime-mover-encrypted-package-note"></span> <?php esc_html_e('To be safe, make sure you have backups ready. For best results, please disable any active caching.', 'prime-mover'); ?> <strong><?php  esc_html_e('Are you sure you want to proceed?', 'prime-mover'); ?></strong></p>   	  	
        </div>
    <?php
    }
    
    /**
     * Markup for import diff dialog
     * @param int $blog_id
     * @compatibility 5.6
     */
    public function importDiffDialog($blog_id = 0)
    {
        if ( ! $blog_id ) {
            return;   
        }
        ?>
        <div style="display:none;" id="js-prime-mover-import-diff-confirm-<?php echo esc_attr($blog_id); ?>" title="<?php esc_attr_e('Warning', 'prime-mover')?>!"></div>
    <?php
    }
    
    /**
     * Import themes based on the package
     * @compatibility 5.6
     * @param array $import_data
     * @param number $blog_id
     * @param number $start
     * @return string|boolean|string|boolean
     */
    public function primeMoverImportThemesFunc($import_data = [], $blog_id = 0, $start = 0)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            $import_data['error'] = esc_html__('Unauthorize themes import', 'prime-mover');
            return $import_data;
        }
        
        $include_themes = $this->checkIfPackageIncludeThemes($import_data);
        if (!$include_themes || empty($import_data['imported_package_footprint']['stylesheet']) || empty($import_data['imported_package_footprint']['template']) || empty($import_data['imported_package_footprint']['using_child_theme'])) {            
             $import_data['copy_themes_done'] = true;
             return $import_data;
        }
        
        $using_child_theme = $import_data['imported_package_footprint']['using_child_theme'];
        $template = key($import_data['imported_package_footprint']['template']);        
        $template_path_package = $include_themes . $template . DIRECTORY_SEPARATOR;
        global $wp_filesystem;
        if (! $wp_filesystem->exists($template_path_package)) {           
            $import_data['copy_themes_done'] = true;
            return $import_data;
        }
        
        $template_path_wpcontent = $this->getExportUtilities()->getThemeFullPath($template, false);           
        $import_data = $this->maybeBailOutThemeRestore($import_data, $template_path_wpcontent, $template_path_package, 'parent', $blog_id);
        
        if (isset($import_data['error'])) {
            return $import_data;
        }
        
        $processed = $this->getProcessedThemeFiles($import_data);
        $parent_import_theme_done = false;
        if (!empty($import_data['parent_theme_copy_done'])) {
            $parent_import_theme_done = true;
        }
       
        if (!$parent_import_theme_done) {
            $import_data = $this->handleThemeImport($template_path_wpcontent, $template_path_package, $blog_id, $start, $processed, $import_data, 'parent');
        }       
        if (isset($import_data['error'])) {
            return $import_data;
            
        } elseif (isset($import_data['copychunked_offset']) && !$parent_import_theme_done) {
            return $import_data;
            
        } elseif ('no' === $using_child_theme) {    
            $import_data['copy_themes_done'] = true;
            return $import_data;            
        } 
        
        $processed = $this->getProcessedThemeFiles($import_data);        
        $child_theme_name = key($import_data['imported_package_footprint']['stylesheet']);
        $child_template_path_package = $include_themes . $child_theme_name . DIRECTORY_SEPARATOR;
        if (! $wp_filesystem->exists($child_template_path_package)) {
            $import_data['error'] = esc_html__('Unable to import child theme because it does not exist', 'prime-mover');
            return $import_data;
        }
        
        $child_theme_path_wpcontent = $this->getExportUtilities()->getThemeFullPath($child_theme_name, false); 
        $import_data = $this->maybeBailOutThemeRestore($import_data, $child_theme_path_wpcontent, $child_template_path_package, 'child', $blog_id);
        
        if (isset($import_data['error'])) {
            return $import_data;
        }
        
        if (!empty($import_data['child_theme_copy_done'])) {
            $import_data['copy_themes_done'] = true;
            return $import_data;
        }
        
        $import_data = $this->handleThemeImport($child_theme_path_wpcontent, $child_template_path_package, $blog_id, $start, $processed, $import_data, 'child');        
        if (!empty($import_data['child_theme_copy_done'])) {
            $import_data['copy_themes_done'] = true;
        }        
        
        return $import_data;
    }
    
    /**
     * Maybe bail out theme restore
     * @param array $import_data
     * @param string $template_path_wpcontent
     * @param string $template_path_package
     * @param string $copying_what
     * @param number $blog_id
     * @return array
     */
    protected function maybeBailOutThemeRestore($import_data = [], $template_path_wpcontent = '', $template_path_package = '', $copying_what = 'parent', $blog_id = 0)
    {
        if (!$template_path_wpcontent || !$template_path_package) {
            return $import_data;
        }
        
        $template_path_wpcontent = wp_normalize_path($template_path_wpcontent);
        $template_path_package = wp_normalize_path($template_path_package);
        
        if (!$this->getSystemCheckUtilities()->getSystemFunctions()->fileExists($template_path_wpcontent) || 
            !$this->getSystemCheckUtilities()->getSystemFunctions()->fileExists($template_path_package)) {
                
            return $import_data;
        }
        
        if (wp_is_writable($template_path_wpcontent)) {            
            return $import_data;
        }        
        
        $hash_algo = $this->getSystemCheckUtilities()->getSystemInitialization()->getFastHashingAlgo();
        $source_theme_hash = $this->getSystemCheckUtilities()->getSystemFunctions()->hashEntity($template_path_package, $hash_algo);
        $target_theme_hash = $this->getSystemCheckUtilities()->getSystemFunctions()->hashEntity($template_path_wpcontent, $hash_algo);
        
        do_action('prime_mover_log_processed_events', "Comparing non-permissive $copying_what theme folders using hash algo: $hash_algo", $blog_id, 'import', __FUNCTION__, $this);
        do_action('prime_mover_log_processed_events', "Source $copying_what theme hash: $source_theme_hash and target $copying_what theme hash: $target_theme_hash", $blog_id, 'import', __FUNCTION__, $this);        
        
        if ($source_theme_hash && $source_theme_hash === $target_theme_hash) {            
            $import_data = $this->closeThemeRestore($import_data, $copying_what);            
        } else {
            $import_data['error'] = sprintf(
                esc_html__('Unable to restore theme due to file permission issues. Please delete this path manually and try again: %s', 'prime-mover'), $template_path_wpcontent);     
                
        }
        
        return $import_data;        
    }
    
    /**
     * Get processed items
     * @param array $import_data
     * @return number
     */
    protected function getProcessedThemeFiles($import_data = [])
    {
        $processed = 0;
        if (!empty($import_data['copydir_processed'])) {
            $processed = (int)$import_data['copydir_processed'];
        }
        return $processed;
    }
    
    /**
     * Close theme restore
     * @param array $import_data
     * @param string $copying_what
     * @return array
     */
    protected function closeThemeRestore($import_data = [], $copying_what = 'parent')
    {
        if (isset($import_data['copychunked_under_copy'])) {
            unset($import_data['copychunked_under_copy']);
        }
        if (isset($import_data['copychunked_offset'])) {
            unset($import_data['copychunked_offset']);
        }
        if (isset($import_data['copydir_processed'])) {
            unset($import_data['copydir_processed']);
        }
        
        $key = $copying_what . '_theme_copy_done';
        $import_data[$key] = true;  
        
        return $import_data;
    }
    
    /**
     * @compatibility 5.6
     * @param string $template_path_wpcontent
     * @param string $template_path_package
     * @param number $blog_id
     * @param number $start
     * @param number $processed
     * @param array $import_data
     * @param string $copying_what
     * @return string|WP_Error|number|boolean
     */
    private function handleThemeImport($template_path_wpcontent = '', $template_path_package = '', $blog_id = 0, $start = 0, $processed = 0, $import_data  = [], $copying_what = 'parent')
    {        
        $this->getImporter()->getSystemFunctions()->enableMaintenanceDuringImport();        
        if (!$processed) {
            $this->getImporter()->getSystemFunctions()->primeMoverDoDelete($template_path_wpcontent);
        }      
        if (!$processed) {
            $theme_make_directory_result = wp_mkdir_p($template_path_wpcontent);
            if (false === $theme_make_directory_result) {
                
                $import_data['error'] = esc_html__('Cannot create theme directory, please check permissions', 'prime-mover');
                $this->getImporter()->getSystemFunctions()->disableMaintenanceDuringImport();
                return $import_data;
            }
        }      
        
        do_action('prime_mover_log_processed_events', "COPYING THEME: $template_path_package TO: $template_path_wpcontent", $blog_id, 'import', 'handleThemeImport', $this);        
        $processed = (int)$processed;
        $progress_text = esc_html__('starting..', 'prime-mover');
        if ($processed) {
            $progress_text = sprintf(esc_html__('%d files processed', 'prime-mover'), $processed);
        } 
        
        $this->getImporter()->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Importing %s theme..%s', 'prime-mover'), $copying_what, $progress_text));  
        $copy_directory_result	= $this->getSystemCheckUtilities()->copyDir($template_path_package, $template_path_wpcontent, [], [], true, true, $start, $blog_id, $processed, true, 'themes_copy', [], $import_data);        
        $this->getImporter()->getSystemFunctions()->disableMaintenanceDuringImport();
        
        if (is_wp_error($copy_directory_result)) {   
            $import_data['error'] = $copy_directory_result->get_error_message();            
            
        } elseif (is_array($copy_directory_result) && isset($copy_directory_result['copychunked_offset'])) {    
            $import_data = $copy_directory_result;                   
            
        } elseif (is_bool($copy_directory_result) && $copy_directory_result) {
            
            if (isset($import_data['copychunked_under_copy'])) {
                unset($import_data['copychunked_under_copy']);
            }
            if (isset($import_data['copychunked_offset'])) {
                unset($import_data['copychunked_offset']);
            }
            if (isset($import_data['copydir_processed'])) {
                unset($import_data['copydir_processed']);
            }            
            
            $key = $copying_what . '_theme_copy_done';
            $import_data[$key] = true;            
        }
        
        return $import_data;
    }
    
    /**
     * Get system check utilities
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverSystemCheckUtilities
     */
    public function getSystemCheckUtilities()
    {
        return $this->getImporter()->getSystemChecks()->getSystemCheckUtilities();        
    }
    
    /**
     * Checks if we need to restore plugins
     * @param array $import_data
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImportUtilities::itMaybeImportPlugins() 
     */
    public function maybeImportPlugins($import_data = [])
    {        
        $include_plugins = $this->checkIfPackageIncludePlugins($import_data);
        if (! $include_plugins || ! isset($import_data['imported_package_footprint']['plugins'])) {
            return false;
        }
        $plugins = $import_data['imported_package_footprint']['plugins'];
        if (empty($plugins) || ! is_array($plugins)) {
            return false;
        }
        return ['plugins' => $plugins, 'include_plugins' => $include_plugins];
    }
    
    /**
     * Is doing CLI retry
     * @param string $cli_tmpname
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImportUtilities::itChecksIfDoingCliPluginsImportRetry()
     */
    protected function isDoingCliRetry($cli_tmpname = '')
    {
        return ($cli_tmpname && $this->getImporter()->getSystemFunctions()->nonCachedFileExists($cli_tmpname));
    }
    
    /**
     * Define plugin import parameters
     * @param array $import_data
     * @param boolean $shell_mode
     * @param boolean $copying_plugins_started
     * @return []
     */
    protected function definePluginImportParameters($import_data = [], $shell_mode = false, $copying_plugins_started = false)
    {        
        $cli_tmpname = '';
        $cli_retry = false;
        if (true === $shell_mode) {
            $cli_tmpname = $this->getImporter()->getSystemInitialization()->generateCliReprocessingTmpName($import_data, $import_data['process_id'], $import_data['shell_progress_key']);
            $cli_retry = $this->isDoingCliRetry($cli_tmpname);
        }        
        if ( ! $copying_plugins_started ) {
            $plugin_details = $this->maybeImportPlugins($import_data);
            $plugins = $plugin_details['plugins'];
            $include_plugins = $plugin_details['include_plugins'];
            
            $import_data['plugins_to_copy'] = $plugins;
            $import_data['package_include_plugins'] = $include_plugins;
            
        } else {
            $plugins = $import_data['plugins_to_copy'];
            $include_plugins = $import_data['package_include_plugins'];
        }
        
        if ( ! $copying_plugins_started && is_array($plugins) ) {
            $plugins = array_keys($plugins);
            $import_data['total_plugins_to_copy'] = count($plugins);
        }
        
        $processed = 0;
        if (isset($import_data['single_plugin_import_processed'])) {
            $processed = $import_data['single_plugin_import_processed'];
        }
        
        return [$cli_tmpname, $include_plugins, $processed, $cli_retry, $plugins, $import_data];
    }
    
    /**
     * Do import plugins monitoring
     * @param number $blog_id
     * @param array $import_data
     * @param boolean $copying_plugins_started
     */
    protected function doImportPluginsMonitoring($blog_id = 0, $import_data = [], $copying_plugins_started = false)
    {
        do_action('prime_mover_log_processed_events', "Obtained plugin lock file. Starting copying plugins..", $blog_id, 'import', 'primeMoverImportPluginsFunc', $this);
        
        $counted = count($import_data['plugins_to_copy']);
        $percent = "0%";
        if ($copying_plugins_started && isset($import_data['total_plugins_to_copy'])) {
            $progress = $import_data['total_plugins_to_copy'] - $counted;
            $percent = round(($progress / $import_data['total_plugins_to_copy']) * 100, 0) . '%';
        }
        $text_files = esc_html__('plugin', 'prime-mover');
        if (isset($counted) && $counted > 1) {
            $text_files = esc_html__('plugins', 'prime-mover');
        }
        $this->getImporter()->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Importing %d remaining %s, %s done.', 'prime-mover'), $counted, $text_files, $percent), 'import' );            
    }
    
    /**
     * Import plugins
     * @param array $import_data
     * @param number $blog_id
     * @param boolean $copying_plugins_started
     * @param number $start
     * @param boolean $shell_mode
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImportUtilities::itImportPlugins()
     */
    public function primeMoverImportPluginsFunc($import_data = [], $blog_id = 0, $copying_plugins_started = false, $start = 0, $shell_mode = false)
    {          
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            $import_data['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $import_data;
        }
        if (false === $this->maybeImportPlugins($import_data)) {
            if ($shell_mode) {
                return true;
            }            
            return $import_data;
        }
        $loop = [];
        list($cli_tmpname, $include_plugins, $processed, $cli_retry, $plugins, $import_data) = $this->definePluginImportParameters($import_data, $shell_mode, $copying_plugins_started);
        $lock_file = $this->getImporter()->getSystemInitialization()->getProcessingPluginPath();        
        $fp = $this->getLockUtilities()->openLockFile($lock_file, false);
        if ( ! $fp ) {
            do_action('prime_mover_log_processed_events', "ERROR: Failed to open plugins copy lock file.", $blog_id, 'import', 'primeMoverImportPluginsFunc', $this);
            $import_data['error'] = esc_html__('Failed to open plugins copy lock file.', 'prime-mover');
            return $import_data;
        }
      
        if ($this->getLockUtilities()->createProcessLockFile($fp)) {           
            $count = 0;
            if (true === $shell_mode && $cli_tmpname) {                 
                $plugins = $this->getExportUtilities()->getPluginListForReprocessing($plugins, $cli_tmpname);
            } else {
                $this->doImportPluginsMonitoring($blog_id, $import_data, $copying_plugins_started);
            }
            $loop = $this->loopPluginsToImport($plugins, $count, $shell_mode, $include_plugins, $processed, $cli_retry, $blog_id, $import_data, $start, $cli_tmpname);
            if (isset($loop['result'])) {
                return $loop['result'];    
            }
            $this->unLockFilePlugin($fp, $blog_id);
            
        } else {
            do_action('prime_mover_log_processed_events', "ERROR: Failed to obtain plugin lock file.", $blog_id, 'import', 'primeMoverImportPluginsFunc', $this);  
            $import_data['error'] = esc_html__('Failed to obtain plugin lock file.', 'prime-mover');
            return $import_data;
        }       
        if (isset($loop['non_shell_plugins'])) {
            $plugins = $loop['non_shell_plugins'];
        }
        if (isset($loop['non_shell_import_data'])) {
            $import_data = $loop['non_shell_import_data'];
        }
        $import_data['plugins_to_copy'] = $plugins;    
        $this->getLockUtilities()->closeLock($fp);
        
        if (true === $shell_mode) {
            return true;    
        } else {
            return $import_data;
        }        
    }
    
    /**
     * Start log of plugins restore
     * @param number $blog_id
     */
    protected function startLog($blog_id = 0, $plugins = [])
    {
        do_action('prime_mover_log_processed_events', "List of plugins to be imported", $blog_id, 'import', 'loopPluginsToImport', $this);
        do_action('prime_mover_log_processed_events', print_r($plugins, true), $blog_id, 'import', 'loopPluginsToImport', $this);
    }
    
    /**
     * Plugin exists and writable
     * @param string $plugin_path_target
     * @return boolean
     */
    protected function pluginExistsWritable($plugin_path_target = '')
    {
        global $wp_filesystem; 
        $plugin_path_target = wp_normalize_path($plugin_path_target);
        
        return ($wp_filesystem->is_dir($plugin_path_target) && wp_is_writable($plugin_path_target));         
    }
    
    /**
     * Loop plugins to import
     * @param array $plugins
     * @param number $count
     * @param boolean $shell_mode
     * @param string $include_plugins
     * @param number $processed
     * @param boolean $cli_retry
     * @param number $blog_id
     * @param array $import_data
     * @param number $start
     * @param string $cli_tmpname
     * @return []
     */
    protected function loopPluginsToImport($plugins = [], $count = 0, $shell_mode = false, $include_plugins = '', $processed = 0,
        $cli_retry = false, $blog_id = 0, $import_data = [], $start = 0, $cli_tmpname = '')
    {
        global $wp_filesystem;    
        $this->startLog($blog_id, $plugins);
        foreach ($plugins as $k => $plugin) {
            
            if (1 === $count && false === $shell_mode) {
                break;
            }            
            list($plugins_imported_package_path, $plugin_path_target, $ds) = $this->getPluginsImportedPackagePath($plugin, $include_plugins);            
            if ( ! $wp_filesystem->exists($plugins_imported_package_path)) {
                list($plugins, $count) = $this->countAndUnsetPlugins($plugins, $count, $k);
                continue;
            }            
            $this->getImporter()->getSystemFunctions()->enableMaintenanceDuringImport();            
            if ( ! $processed && ! $cli_retry ) {               
                $import_data = apply_filters('prime_mover_before_copying_plugin', $import_data, $plugin, $blog_id, $plugins, $plugin_path_target);
                
                if ($this->pluginExistsWritable($plugin_path_target)) {
                    $this->getImporter()->getSystemFunctions()->primeMoverDoDelete($plugin_path_target);
                }                
            }
            
            $plugin_make_directory_result = true;
            if ($ds && !$processed && !$wp_filesystem->is_dir(wp_normalize_path($plugin_path_target))) {
                $plugin_make_directory_result = $wp_filesystem->mkdir($plugin_path_target);
            }
            
            if (false === $plugin_make_directory_result && false === $shell_mode) {
                list($plugins, $count) = $this->countAndUnsetPlugins($plugins, $count, $k);
                continue;
            }
            
            $copy_directory_result = $this->copyDirectoryForPluginsImport($plugins_imported_package_path, $plugin_path_target, $shell_mode, $ds, $blog_id, $import_data, $plugins, $start, $processed);
            if (is_wp_error($copy_directory_result)) {                
                return $this->returnRetryCopyDirectoryResult($import_data, $copy_directory_result, $plugins, $plugins_imported_package_path, $blog_id, true);
                
            } elseif (is_array($copy_directory_result) && isset($copy_directory_result['copychunked_offset'])) {  
                return $this->returnRetryCopyDirectoryResult($import_data, $copy_directory_result, $plugins, $plugins_imported_package_path, $blog_id);
                
            } elseif ($this->getImporter()->getSystemFunctions()->nonCachedFileExists($cli_tmpname) ) {
                $this->endLogAndDisableMaintenance($copy_directory_result, $blog_id);
                return ['result' => 'restart'];
                
            } elseif (true === $copy_directory_result || true === $shell_mode) {                
                if (isset($import_data['single_plugin_import_processed'])) {
                    unset($import_data['single_plugin_import_processed']);
                }
                list($plugins, $count) = $this->countAndUnsetPlugins($plugins, $count, $k);                
            }
            
            $this->endLogAndDisableMaintenance($copy_directory_result, $blog_id);  
            $import_data = apply_filters('prime_mover_after_copying_plugin', $import_data, $plugin, $blog_id, $plugins, $plugin_path_target);
        }  
        if (false === $shell_mode) {
            return ['non_shell_plugins' => $plugins, 'non_shell_import_data' => $import_data];
        }
    }
    
    /**
     * Retry timeout and WP_Error plugin restore handler.
     * @param array $import_data
     * @param array $copy_directory_result
     * @param array $plugins
     * @param string $plugins_imported_package_path
     * @param number $blog_id
     * @param boolean $error
     * @return []
     */
    protected function returnRetryCopyDirectoryResult($import_data = [], $copy_directory_result = [], $plugins = [], $plugins_imported_package_path = '', $blog_id = 0, $error = false)
    {
        if ($error) {
            $import_data['error'] = $copy_directory_result->get_error_message();
        } else {
            if (isset($copy_directory_result['copydir_processed'])) {
                $import_data['single_plugin_import_processed'] = (int)$copy_directory_result['copydir_processed'];
            }
            if (isset($copy_directory_result['copychunked_under_copy'])) {
                $import_data['copychunked_under_copy'] = $copy_directory_result['copychunked_under_copy'];
            }
            if (isset($copy_directory_result['copychunked_offset'])) {
                $import_data['copychunked_offset'] = $copy_directory_result['copychunked_offset'];
            }
        }
        
        $import_data['plugins_to_copy'] = $plugins;
        if ($error) {
            do_action('prime_mover_log_processed_events', "$plugins_imported_package_path WP Error detected informing user.", $blog_id, 'import', 'primeMoverImportPluginsFunc', $this);
        } else {
            do_action('prime_mover_log_processed_events', "$plugins_imported_package_path copying timeouts, retrying..", $blog_id, 'import', 'primeMoverImportPluginsFunc', $this);
        }
        $this->getImporter()->getSystemFunctions()->disableMaintenanceDuringImport();
        
        return ['result' => $import_data];
    }
    
    /**
     * End log and disable maintenance
     * @param mixed $copy_directory_result
     * @param number $blog_id
     */
    protected function endLogAndDisableMaintenance($copy_directory_result, $blog_id = 0)
    {
        do_action('prime_mover_log_processed_events', "Copy directory result:", $blog_id, 'import', 'primeMoverImportPluginsFunc', $this);
        do_action('prime_mover_log_processed_events', $copy_directory_result, $blog_id, 'import', 'primeMoverImportPluginsFunc', $this);
        $this->getImporter()->getSystemFunctions()->disableMaintenanceDuringImport();
    }
  
    /**
     * Get plugins imported package path
     * @param string $plugin
     * @param string $include_plugins
     * @return string
     */
    protected function getPluginsImportedPackagePath($plugin = '', $include_plugins = '')
    {        
        $plugin_path_target = $this->getExportUtilities()->getPluginFullPath($plugin, false);
        $ds = DIRECTORY_SEPARATOR;
        if ($this->getExportUtilities()->isPluginFile($plugin_path_target)) {
            $ds = '';
        }
        
        $plugin_dirname = basename($plugin_path_target);
        if ($ds) {
            $plugin_dirname = dirname($plugin) . $ds;
        }
        $plugins_imported_package_path = $include_plugins . $plugin_dirname;
        return [$plugins_imported_package_path, $plugin_path_target, $ds];        
    }
    
    /**
     * Count and unset
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImportUtilities::itCountAndUnsetPlugins()
     * @param array $plugins
     * @param number $count
     * @param number $k
     * @return []
     */
    protected function countAndUnsetPlugins($plugins = [], $count = 0, $k = 0)
    {
        unset($plugins[$k]);
        $count++;
        
        return [$plugins, $count];        
    }
    
    /**
     * 
     * @param string $plugins_imported_package_path
     * @param string $plugin_path_target
     * @param boolean $shell_mode
     * @param string $ds
     * @param number $blog_id
     * @param array $import_data
     * @param array $plugins
     * @param number $start
     * @param number $processed
     * @return string|WP_Error|number|boolean|void
     */
    protected function copyDirectoryForPluginsImport($plugins_imported_package_path = '', $plugin_path_target = '', $shell_mode = false, $ds = '', $blog_id = 0, $import_data = [], 
        $plugins = [], $start = 0, $processed = 0)
    {
        global $wp_filesystem;
        $copy_directory_result = '';
        do_action('prime_mover_log_processed_events', "COPYING: $plugins_imported_package_path TO: $plugin_path_target", $blog_id, 'import', 'primeMoverImportPluginsFunc', $this);
        if ($ds && true === $shell_mode) {
            $copy_directory_result = $this->getImporter()->getSystemFunctions()->recurseCopy($plugins_imported_package_path, $plugin_path_target, $import_data, $plugins, 
                ['source' => __METHOD__, 'mode' => 'import', 'blog_id' => $blog_id]);
            
        } elseif ($ds && false === $shell_mode) {
            $permission_check = $this->maybeBailOutPluginsRestore($plugin_path_target, $plugins_imported_package_path, $blog_id);
            if (is_wp_error($permission_check)) {
                return $permission_check;
            }
            
            $copy_directory_result	= $this->getSystemCheckUtilities()->copyDir(
                $plugins_imported_package_path, 
                $plugin_path_target, 
                [], 
                [], 
                true, 
                true, 
                $start, 
                $blog_id, 
                $processed, 
                true, 
                'default', 
                [], 
                $import_data);
        } else {
            $copy_directory_result	= $wp_filesystem->copy($plugins_imported_package_path, $plugin_path_target, true);
        }                
        
        return $copy_directory_result;
    }
 
    /**
     * Maybe bail out plugin restore
     * Should return WP_Error if permission issues exists so user should know.
     * Otherwise return false if we want to let copyDir() handle the rest
     * @param string $plugin_path_target
     * @param string $plugins_imported_package_path
     * @param number $blog_id
     * @return array
     */
    protected function maybeBailOutPluginsRestore($plugin_path_target = '', $plugins_imported_package_path = '', $blog_id = 0)
    {
        if (!$plugin_path_target || !$plugins_imported_package_path) {
            return false;
        }
        
        $plugin_path_target = wp_normalize_path($plugin_path_target);
        $plugins_imported_package_path = wp_normalize_path($plugins_imported_package_path);
        
        if (!$this->getSystemCheckUtilities()->getSystemFunctions()->fileExists($plugin_path_target) ||
            !$this->getSystemCheckUtilities()->getSystemFunctions()->fileExists($plugins_imported_package_path)) {
                
                return false;
            }
            
        if (wp_is_writable($plugin_path_target)) {
            return false;
        }
        
        $hash_algo = $this->getSystemCheckUtilities()->getSystemInitialization()->getFastHashingAlgo();
        $source_plugin_hash = $this->getSystemCheckUtilities()->getSystemFunctions()->hashEntity($plugins_imported_package_path, $hash_algo);
        $target_plugin_hash = $this->getSystemCheckUtilities()->getSystemFunctions()->hashEntity($plugin_path_target, $hash_algo);
        
        do_action('prime_mover_log_processed_events', "Comparing non-permissive plugin folders using hash algo: $hash_algo", $blog_id, 'import', __FUNCTION__, $this);
        do_action('prime_mover_log_processed_events', "Source plugin hash: $source_plugin_hash and target plugin hash: $target_plugin_hash", $blog_id, 'import', __FUNCTION__, $this);
        
        if ($source_plugin_hash && $source_plugin_hash === $target_plugin_hash) {
            return false;
        } else {
            return new WP_Error( 'permission_issue_plugin_folder', sprintf(__( 'Could not copy plugin due to permission issue - please manually delete this directory and try again: %s' ), $plugin_path_target), $plugin_path_target);
            
        }
        
        return false;
    }
    
    /**
     * Unlock File 
     * @param resource $fp
     */
    protected function unLockFilePlugin($fp, $blog_id = 0)
    {
        $unlock = $this->getLockUtilities()->unLockFile($fp);
        if ($unlock) {
            do_action('prime_mover_log_processed_events', "Plugin processing completed, lock successfully released.", $blog_id, 'import', 'primeMoverImportPluginsFunc', $this);
        } 
    }
    
    /**
     * Checks if package includes themes to import
     * @param array $import_data
     * @return string
     * @compatibility 5.6
     */
    protected function checkIfPackageIncludeThemes($import_data = [])
    {
        $ret = '';
        global $wp_filesystem;
        $themes_package_path = $this->getThemespackagepath($import_data);
        if ($wp_filesystem->exists($themes_package_path)) {
            $ret = $themes_package_path;
        }
        return $ret;
    }
    
    /**
     * Checks if package includes plugins to import
     * @param array $import_data
     * @return string
     * @compatibility 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverImportUtilities::itChecksIfPackageHasPluginsIncluded()
     */
    protected function checkIfPackageIncludePlugins($import_data = [])
    {
        $ret = '';
        global $wp_filesystem;
        $plugins_package_path = $this->getPluginspackagepath($import_data);
        if ($wp_filesystem->exists($plugins_package_path)) {
            $ret = $plugins_package_path;
        }
        return $ret;
    }
 
    /**
     * Get plugins path
     * @param array $import_data
     * @return string
     * @compatibility 5.6
     */
    protected function getPluginspackagepath($import_data = [])
    {
        $ret = '';
        if (empty($import_data['unzipped_directory'])) {
            return $ret;
        }
        $unzipped_directory = $import_data['unzipped_directory'];
        $plugins_foldername = $this->getExportUtilities()->getPluginFoldername();
        $plugins_package_path = $unzipped_directory . $plugins_foldername . '/';
        
        return $plugins_package_path;
    }
    
    /**
     * Get themes path
     * @param array $import_data
     * @return string
     * @compatibility 5.6
     */
    private function getThemespackagepath($import_data = [])
    {
        $ret = '';
        if (empty($import_data['unzipped_directory'])) {
            return $ret;
        }
        $unzipped_directory = $import_data['unzipped_directory'];
        $themes_foldername = $this->getExportUtilities()->getThemeFoldername();
        $themes_package_path = $unzipped_directory . $themes_foldername . DIRECTORY_SEPARATOR;
        
        return $themes_package_path;
    }
    
    /**
     * Analyze zip package description
     * @param string $tmp_path
     * @param number $blog_id
     * @param boolean $encrypted
     * @param \ZipArchive $zip
     * @param string $package_type
     * @param boolean $blog_id_check
     * @param boolean $array_type
     * @return string[]|string[]|mixed[]
     */
    private function analyzeZipPackageDescription($tmp_path = '', $blog_id = 0, $encrypted = false, ZipArchive $zip = null, $package_type = '', $blog_id_check = true, $array_type = false)
    {
        $dir = trim($zip->getNameIndex(0), '/');
        $plugin_dir = $dir . "/plugins/";
        $package_type_data = [];
        
        $blog_id = (int)$blog_id;
        $blog_id_package = $this->getRealBlogIDFromZipPackage($zip, $blog_id);
        $blog_id_package = (int)$blog_id_package;
        
        if ( ! $blog_id_package ) {
            $package_type_data[$blog_id_package] = '';
            return $package_type_data;
        }
        if ($blog_id_check && $blog_id_package !== $blog_id) {
            $package_type_data[$blog_id_package] = '';
            return $package_type_data;
        }
        $blog_id = $blog_id_package;
        $media_dir = $dir . "/media.zip";       
        
        $database_file = $blog_id . '.sql';
        if ($encrypted) {
            $database_file = $database_file . '.enc';
        }
        
        $plugins_exist = $zip->locateName($plugin_dir);
        $media_exist = $zip->locateName($media_dir);
        $database_exist = $zip->locateName($database_file, \ZIPARCHIVE::FL_NODIR);
        
        $footprint_exist = $zip->locateName('footprint.json', \ZIPARCHIVE::FL_NODIR);
        $blogidtext_exist = $zip->locateName('blogid.txt', \ZIPARCHIVE::FL_NODIR);
        
        if (false !== $plugins_exist) {
            if (false !== $media_exist) {
                $package_type_slug = 'complete_export_mode';
            } else {
                $package_type_slug = 'development_package';
            }            
        } elseif (false !== $media_exist) {
            $package_type_slug = 'db_and_media_export';
        } elseif (false !== $database_exist) {
            $package_type_slug = 'dbonly_export';
        }
        
        if (false === $database_exist || false === $footprint_exist || false === $blogidtext_exist) {
            $package_type_text = '';
        }
        
        $export_modes = $this->getExportUtilities()->getExportModes();        
        if ( ! empty($export_modes[$package_type_slug]) ) {
            $package_type_text = $export_modes[$package_type_slug];
        }       
        if ($array_type) {
            $package_type = [];
            $package_type[$package_type_slug] = $package_type_text;
        } else {
            $package_type = $package_type_text;
        }
        $package_type_data[$blog_id] = $package_type;
        return $package_type_data;        
    }
    
    /**
     * Get real blog ID from zip package
     * @param mixed $zip
     * @param number $passed_blog_id
     * @param string $zip_path
     * @return number
     */
    public function getRealBlogIDFromZipPackage($zip = null, $passed_blog_id = 0, $zip_path = '')
    {        
        if ( ! is_a($zip, 'ZipArchive') && $zip_path && file_exists($zip_path)) {
            $zip = new \ZipArchive();
            if (true !== $zip->open($zip_path)) {
                return 0;
            }
        }
        
        $blogidtext_exist = $zip->locateName('blogid.txt', \ZIPARCHIVE::FL_NODIR);
        if ( ! $blogidtext_exist) {
            return 0;    
        }
        $read_blog_id = $zip->getFromIndex($blogidtext_exist);
        $read_blog_id = (int)$read_blog_id;
        
        if ($read_blog_id) {
            $passed_blog_id = $read_blog_id;
        }
        
        if ($zip_path) {
            $zip->close();
        }
        
        return $passed_blog_id;
    }
    
    /**
     * Get zip package description
     * @param string $tmp_path
     * @param number $blog_id
     * @param boolean $encrypted
     * @param boolean $delete_if_invalid
     * @param boolean $blog_id_check
     * @param boolean $array_type
     * @return boolean|mixed
     */
    public function getZipPackageDescription($tmp_path = '', $blog_id = 0, $encrypted = false, $delete_if_invalid = true, $blog_id_check = true, $array_type = false)
    {
        if ( ! $tmp_path ) {
            return false;
        }
        $zip = new \ZipArchive();
        $package_type = '';
        $blog_id = (int)$blog_id;
        $result = [];
        $package_type_data = [];
        $package_type_data[$blog_id] = $package_type;       
        
        if (true === $zip->open($tmp_path)) {            
            $package_type_data = $this->analyzeZipPackageDescription($tmp_path, $blog_id, $encrypted, $zip, $package_type, $blog_id_check, $array_type);
        }          
        if ($array_type && ! empty($package_type_data[$blog_id])) {
            $result = $package_type_data[$blog_id];
            $package_type = reset($result);
        } else {
            $package_type = reset($package_type_data); 
        }
        if ( ! $package_type && $delete_if_invalid) {
            $this->getImporter()->getSystemFunctions()->primeMoverDoDelete($tmp_path);
        }
        if ($array_type && is_array($result) && ! empty($result)) {
            return $result;
        } else {
            return $package_type; 
        }        
    }
    
    /**
     * Checks if zip package dB is encrypted
     * @param string $tmp_path
     * @return boolean
     */
    public function isZipPackageDbEncrypted($tmp_path = '')
    {
        return $this->getImporter()->getSystemFunctions()->isZipPackageHasEntityEncrypted($tmp_path, $this->getImporter()->getSystemInitialization()->getSignatureFile());
    }

    /**
     * Checks if zip package media files is encrypted
     * @param string $tmp_path
     * @return boolean
     */
    public function isZipPackageMediaEncrypted($tmp_path = '')
    {
        return $this->getImporter()->getSystemFunctions()->isZipPackageHasEntityEncrypted($tmp_path, $this->getImporter()->getSystemInitialization()->getMediaEncryptedSignature());
    }
    
    /**
     * Get real blog ID from zip package
     * @param \ZipArchive $zip
     * @return number
     */
    public function getSiteTitleFromZipPackage($tmp_path = '')
    {
        if ( ! $tmp_path ) {
            return '';
        }
        $za = new \ZipArchive();
        $zip = $za->open($tmp_path);
        if (true !== $zip) {
            return '';
        }        
        $footprint = $za->locateName('footprint.json', \ZIPARCHIVE::FL_NODIR);
        if ( ! $footprint) {
            return '';
        }
        $footprint_json = $za->getFromIndex($footprint);
        $system_footprint_package_array	= json_decode($footprint_json, true);
        if ( ! empty($system_footprint_package_array['site_title'] ) ) {
            return $system_footprint_package_array['site_title'];
        }
        return '';
    }
    
    /**
     * Analyze if zip should be disabled
     * @param array $zipmeta
     * @param boolean $multisite (Returns TRUE if this site is a MULTISITE otherwise FALSE)
     * @param number $blog_id (Server site blog ID, Returns 1 if single-site OTHERWISE, its a number greater than 1 in multisite)
     * @return string
     * @mainsitesupport_affected
     */
    public function isZipShouldBeDisabled($zipmeta = [], $multisite = true, $blog_id = 0)
    {
        $disabled = '';
        $zippath = '';
        if ( ! empty($zipmeta['filepath'] ) ) {
            $zippath = $zipmeta['filepath'];
        }
        $is_tar = false;
        if ($zippath && $this->getSystemCheckUtilities()->getSystemFunctions()->hasTarExtension($zippath)) {
            $is_tar = true;
        }
        $tar_config = [];
        $encrypted = false;
        if ($is_tar) {
            $tar_config = apply_filters('prime_mover_get_tar_package_config_from_file', [], $zippath);
        } else {
            $encrypted = $this->isZipPackageDbEncrypted($zippath);
            $package_description = $this->getZipPackageDescription($zippath, $blog_id, $encrypted, false);
        }
        $export_modes = [];
        $export_options = '';
        if (!empty($tar_config)) {
            $export_options = $tar_config['export_options'];
            $export_modes = $this->getExportUtilities()->getExportModes();            
        }
        if (isset($export_modes[$export_options])) {
            $package_description = $export_modes[$export_options];
        }
        if (!$package_description ) {
            $disabled = 'disabled';
        }
        
        if ($is_tar && $this->isWprimeShouldBeDisabled($tar_config, $multisite, $blog_id)) {
            $disabled = 'disabled';            
        }

        if ($is_tar) {            
            return [$disabled, $tar_config['prime_mover_export_targetid'], $tar_config, $is_tar, $tar_config['encrypted']];
        } else {
            return [$disabled, $this->getRealBlogIDFromZipPackage(null, 0, $zippath), $tar_config, $is_tar, $encrypted];
        }        
    }
 
    /**
     * Check if WPRIME package should be disabled in gearbox and menus.
     * @param array $tar_config
     * @param boolean $multisite
     * @param number $blog_id
     * @return boolean
     * @mainsitesupport_affected
     * Since 1.2.0, its possible to have target BLog ID 1 in multisite configuration
     * It is because of the main site export/import support.
     */
    protected function isWprimeShouldBeDisabled($tar_config = [], $multisite = true, $blog_id = 0)
    {        
        $blog_id = (int)$blog_id;
        $blog_package_id = (int)$tar_config['prime_mover_export_targetid'];
        $disabled = false;
        $single_site = false;
        
        if (!empty($tar_config['prime_mover_export_type']) && 'single-site' === $tar_config['prime_mover_export_type']) {
            $single_site = true;
        } 
        
        if (!empty($tar_config['prime_mover_export_type']) && 'multisite' === $tar_config['prime_mover_export_type']) {
            $single_site = false;
        }
        
        if (!isset($tar_config['prime_mover_export_type']) && 1 === $blog_package_id) {
            $single_site = true;
        }       
        
        if ($single_site && $multisite) {   
            $disabled = true;
        }
        
        if (!$single_site && !$multisite) {
            $disabled =  true;    
        }       
        
        if ($multisite && !$single_site && $blog_id !== $blog_package_id) {    
            $disabled =  true;            
        }
        
        return $disabled;       
    }
    
    /**
     * @mainsitesupport_affected
     * Analyze package type
     * @param string $disabled
     * @param number $package_blog_id (the blog ID coming from the WPRIME or zip package)
     * @param string $disabled
     * @param number $package_blog_id
     * @param array $tar_config
     * @return string
     * Since 1.2.0, its possible to have target BLog ID 1 in multisite configuration
     * It is because of the main site export/import support.
     */
    public function getPackageType($disabled = '', $package_blog_id = 0, $tar_config = [])
    {
        if (!empty($tar_config['prime_mover_export_type'])) {
            return $tar_config['prime_mover_export_type'];
        }
        
        $package_blog_id = (int)$package_blog_id;
        $package_type = '';
        if ('disabled' === $disabled) {
            $package_type = esc_html__('multisite', 'prime-mover');
            if (is_multisite() ) {
                $package_type = esc_html__('single-site', 'prime-mover');
            }
        } else {
            $package_type = esc_html__('single-site', 'prime-mover');
            if (is_multisite() ) {
                $package_type = esc_html__('multisite', 'prime-mover');
            }
        }
       
        if ($package_blog_id && $package_blog_id > 1) {
            $package_type = esc_html__('multisite', 'prime-mover');
        }
        
        if ($package_blog_id && 1 === $package_blog_id) {
            $package_type = esc_html__('single-site', 'prime-mover');
        }
        
        return $package_type;
    }
}
