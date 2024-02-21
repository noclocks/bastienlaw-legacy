<?php
namespace Codexonics\PrimeMoverFramework\classes;

/*
 * This file is part of the Codexonics.PrimeMoverFramework package.
 *
 * (c) Codexonics Ltd
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Hooked Methods
 *
 * The Prime Mover Hooked Method Class provides the hooked methods to WordPress core hooks
 *
 */
class PrimeMoverHookedMethods
{
    private $system_checks;
    private $progress_handlers;
    
    /**
     * Constructor
     * @param PrimeMoverSystemChecks $system_checks
     * @param PrimeMoverProgressHandlers $progress_handlers
     */
    public function __construct(
        PrimeMoverSystemChecks $system_checks,
        PrimeMoverProgressHandlers $progress_handlers
    ) 
    {
        $this->system_checks = $system_checks;
        $this->progress_handlers = $progress_handlers;
    }

    /**
     * Get progress handlers
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverProgressHandlers
     */
    public function getProgressHandlers()
    {
        return $this->progress_handlers;        
    }
    
    /**
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     * @compatible 5.6
     */
    public function getSystemFunctions()
    {
        return $this->getSystemChecks()->getSystemFunctions();
    }
    
    /**
     * Get System authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     * @compatible 5.6
     */
    public function getSystemAuthorization()
    {
        return $this->getSystemChecks()->getSystemAuthorization();
    }
    
    /**
     * Get System checks
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemChecks
     * @compatible 5.6
     */
    public function getSystemChecks()
    {
        return $this->system_checks;
    }
    
    /**
     * Get System Initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     * @compatible 5.6
     */
    public function getSystemInitialization()
    {
        return $this->getSystemChecks()->getSystemInitialization();
    }
    
    /**
     * Adds the custom export and import column to Network -> Sites list table.
     * @param array $column_headers
     * @return array $column_headers
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itAddsPrimeMoverNetworkColumn() 
     */
    public function primeMoverAddNetworkColumn($column_headers = [])
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        /**
         * Add custom export/import columns if export folder created
         */
        if (true === $this->getSystemChecks()->primeMoverEssentialRequisites()) {
            $column_headers['multisite_export_column']	=	esc_html__('Export Site', 'prime-mover');
            $column_headers['multisite_import_column'] 	=	esc_html__('Import Site', 'prime-mover');
        }
        
        return $column_headers ;
    }
    
    /**
     * Get blog address
     * @param number $blog_id
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itGetsBlogAddress()
     */
    protected function getBlogAddress($blog_id = 0)
    {
        if (is_multisite()) {
            return get_blogaddress_by_id($blog_id);
        } else {
            return network_site_url();
        }        
    }
    
    /**
     * Checks if columns matched
     * @param string $mode
     * @param string $column_name
     * @param number $blog_id
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itChecksIfColumnMatched()
     */
    protected function isColumnsMatched($mode = 'export', $column_name = '', $blog_id = 0)
    {
        $columns = ['export' => 'multisite_export_column', 'import' => 'multisite_import_column'];
        if ( ! isset($columns[$mode] ) ) {
            return false;
        }
        $column_check = $columns[$mode];
        if ($column_check !== $column_name || !$blog_id) {
            return false;
        }
        return true;
    }
    
    /**
     * Maybe load export section check
     * @param string $column_name
     * @param number $blog_id
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itFiltersLoadMigrationSection()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itReturnsTrueIfSingleSiteLoadMigrationSection()
     */
    protected function maybeLoadMigrationSection($column_name = '', $blog_id = 0, $mode = 'export')
    {
        if ( ! is_multisite() ) {
            return true;
        }
        
        if (!$this->isColumnsMatched($mode, $column_name, $blog_id)) {
            return false;
        }
        
        if (!$this->getSystemFunctions()->isMultisiteMainSite($blog_id)) {
            return true;
        }
        
        return apply_filters('prime_mover_maybe_load_migration_section', false, $blog_id);
    }
    
    /**
     * Renders the export section of the plugin in Network -> Sites
     * @param string $column_name
     * @param number $blog_id
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itOutputsPrimeMoverExportSection()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itDoesNotOutputExportSectionNotOnExportColumn()
     * @mainsitesupport_affected
     */
    public function primeMoverExportSection($column_name = '', $blog_id = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if ( ! is_multisite() && ! $blog_id ) {
            $blog_id = 1;
        }
        $rendered = false;        
        if (true === $this->getSystemChecks()->primeMoverEssentialRequisites()) {
            $button_text = $this->generateExportButtonText($blog_id);
            $blog_id	= intval($blog_id);
            if ($this->maybeLoadMigrationSection($column_name, $blog_id)) {
                $rendered = true;
                $blogaddress_by_id	= $this->getBlogAddress($blog_id);
                $tooltip_button = sprintf(esc_html__('Export %s with blog ID: %d', 'prime-mover'), $blogaddress_by_id, $blog_id);
                if ( ! is_multisite() ) {
                    $tooltip_button = esc_html__('Export site', 'prime-mover');
                }
                $button_class = apply_filters('prime_mover_filter_button_class', $this->getSystemInitialization()->defaultButtonClasses(), $blog_id);
                ?>
			<input	name="prime_mover_exportbutton" 
					value="<?php echo $button_text; ?>" 
					data-primemover-button-class="<?php echo esc_attr($button_class);?>"
					class="<?php echo $button_class; ?> prime_mover_exportbutton js-prime_mover_exportbutton"
					title="<?php echo esc_attr($tooltip_button); ?>"
					id ="js-prime_mover_exporting_blog_<?php echo esc_attr($blog_id) ; ?>"
					data-multisiteblogid = "<?php echo esc_attr($blog_id); ?>" 				
					type="button" >				
			<p class="prime_mover_export_progress_span_p" id="js-prime_mover_export_progress_span_p_<?php echo esc_attr($blog_id); ?>">
			   <span id="js-multisite_export_span_<?php echo esc_attr($blog_id); ?>"></span>
			   <span class="prime_mover_progress_span" id="js-multisite_export_progress_span_<?php echo esc_attr($blog_id); ?>"></span>
			</p>
			<?php do_action('prime_mover_do_after_export_button', $blog_id); ?>
			<?php
            }
            
            if (!$rendered && $this->getSystemFunctions()->isMultisiteMainSite($blog_id) && $this->isColumnsMatched('export', $column_name, $blog_id)) {
                $upgrade_url = network_admin_url( 'admin.php?page=migration-panel-settings-pricing');
            ?>	
		    <a title="<?php echo esc_attr__('Upgrade or activate license to create backup/export the main site.', 'prime-mover'); ?>" href="<?php echo esc_url($upgrade_url);?>" 
		    class="js-prime-mover-upgrade-button-simple prime-mover-upgrade-button-simple prime_mover_exportbutton button">
		    <?php echo apply_filters('prime_mover_filter_upgrade_pro_text', esc_html__( 'Upgrade to PRO', 'prime-mover' ), $blog_id); ?></a>			
			<?php                          
            }
        }
    }
    
    /**
     * Generate export button text
     * @param number $blog_id
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itGeneratesExportButtonText()
     */
    protected function generateExportButtonText($blog_id = 0)
    {
        $target = apply_filters('prime_mover_filter_export_button_text', esc_html__('EXPORT', 'prime-mover'), $blog_id);   
        if (is_multisite()) {
            $text = sprintf(('%s %s : %d'), $target, esc_html__('blog ID', 'prime-mover'), esc_attr($blog_id));
        } else {
            $text = $target;
        }
        
        if (is_multisite() && wp_is_mobile()) {
            $text = $target;
        }        
        
        return $text;
    }
    
    /**
     * Renders the export section of the plugin in Network -> Sites
     * @param string $column_name
     * @param number $blog_id
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itOutputsPrimeMoverImportSection()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itDoesNotOutputImportSectionIfNotImportColumn()
     * @compatible 5.6
     * @mainsitesupport_affected
     */
    public function primeMoverImportSection($column_name = '', $blog_id = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if ( ! is_multisite() && ! $blog_id ) {
            $blog_id = 1;
        }
        $rendered = false;
        if (true === $this->getSystemChecks()->primeMoverEssentialRequisites()) {
            $blog_id	= intval($blog_id);
            if ($this->maybeLoadMigrationSection($column_name, $blog_id, 'import')) {
                $rendered = true;
            ?>
            <?php do_action('prime_mover_do_before_import_button', $blog_id); ?>
	    	<?php do_action('prime_mover_render_import_button', $blog_id ); ?> 
	    	<p class="prime_mover_import_progress_span_p" id="js-prime_mover_import_progress_span_p_<?php echo esc_attr($blog_id); ?>">
	    		<span class="prime_mover_import_span" id="js-multisite_import_span_<?php echo esc_attr($blog_id); ?>"></span>
	    		<span class="prime_mover_progress_span" id="js-multisite_import_progress_span_<?php echo esc_attr($blog_id); ?>"></span>
	    	</p>
	    	<?php do_action('prime_mover_do_after_import_button', $blog_id); ?>		    
	<?php
            }
            
            if (!$rendered && $this->getSystemFunctions()->isMultisiteMainSite($blog_id) && $this->isColumnsMatched('import', $column_name, $blog_id)) {
                $upgrade_url = network_admin_url( 'admin.php?page=migration-panel-settings-pricing');
                ?>
		        <a title="<?php echo esc_attr__('Upgrade or activate license to migrate/restore the main site.', 'prime-mover'); ?>" href="<?php echo esc_url($upgrade_url);?>" 
		        class="js-prime-mover-upgrade-button-simple prime-mover-upgrade-button-simple prime-mover-fileupload-label button">
		        <?php echo apply_filters('prime_mover_filter_upgrade_pro_text', esc_html__( 'Upgrade to PRO', 'prime-mover' ), $blog_id); ?></a>			
			<?php                          
            }
        }
    }

    /**
     * Add js body class
     * @param string $classes
     * @return string
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itAddsJsBodyClassOnNetworkSitesPage()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itDoesNotAddBodyClassNotOnNetworkSites()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itAddsJsBodyClassOnExistingBodyClass() 
     */
    public function addJsBodyClassOnNetworkSitesPage($classes = '')
    {       
        if ( ! $this->getSystemInitialization()->isNetworkSites()) {
            return $classes;
        }
        if (empty($classes)) {
            $classes_array = [];
        } else {
            $classes_array = explode(' ', $classes);
        }        
        $js_body_class = $this->getSystemInitialization()->getJsBodyClass();
        $css_body_class = $this->getSystemInitialization()->getCssBodyClass();
        
        if ( ! in_array($js_body_class, $classes_array)) {
            $classes_array[] = $js_body_class;            
        }
        if ( ! in_array($css_body_class, $classes_array)) {
            $classes_array[] = $css_body_class;
        }
        
        $classes = implode(' ', $classes_array);
        return $classes;
    }
    
    /**
     * Checks if we are not on network sites page
     * @param string $hook
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itChecksIfNotSitesPageOnMultisite() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itChecksIfNotSitesPageOnSingleSite()
     */
    public function maybeWeAreNotOnSitesPage($hook = '')
    {
        global $current_screen;
        if (is_multisite()) {
            return ('sites.php' != $hook || ! $current_screen->in_admin('network') || false === $this->getSystemChecks()->primeMoverEssentialRequisites());
        }
        if ( ! is_multisite() ) {
            $load_hook = 'tools_page_migration-tools';            
            return ($load_hook != $hook || $current_screen->id !== $load_hook || false === $this->getSystemChecks()->primeMoverEssentialRequisites());
        }
    }
    
    /**
     * Enqueue plugin JS on Network -> Sites.
     * @param string $hook
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itEnqueuesStandardPrimeMoverScripts() 
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itEnqueuesMinifiedJsOnNonDebugMode() 
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itDoesNotEnqueueScriptNotOnSitesPage()
     */
    public function primeMoverEnqueueScripts($hook = '')
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        if ($this->getSystemFunctions()->maybeLoadMenuAssets()) {
            wp_enqueue_style(
                'prime_mover_panel_dashicons',
                esc_url_raw(plugins_url('res/css/prime-mover-panel-icon.css', dirname(__FILE__))),
                [],
                PRIME_MOVER_VERSION
                );
        }
        
        if ($this->maybeWeAreNotOnSitesPage($hook)) {
            return;
        }
        $this->getSystemInitialization()->setIsNetworkSites(true);
        $min = '.min';
        if ( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ) {
            $min = '';
        }
        $core_js = "prime-mover-js-network-admin$min.js";
        $jszip = "zip.js";
        
        $uncompress_js = "uncompress$min.js"; 
        $untar_js = "libuntar$min.js"; 
        if ( ! is_multisite() ) {
            remove_all_actions( 'admin_notices' );
        }
        
        wp_enqueue_script(
            'prime_mover_uncompress_js',
            esc_url_raw(plugins_url('res/js/' . $uncompress_js, dirname(__FILE__))),
            ['jquery'],
            PRIME_MOVER_VERSION
            );
        
        wp_enqueue_script(
            'prime_mover_js_network_admin',
            esc_url_raw(plugins_url('res/js/' . $core_js, dirname(__FILE__))),
            ['jquery', 'jquery-ui-core', 'jquery-ui-dialog', 'prime_mover_uncompress_js'],
                PRIME_MOVER_VERSION
        );
        
        wp_enqueue_script(
            'prime_mover_jszip',
            esc_url_raw(plugins_url('res/js/' . $jszip, dirname(__FILE__))),
            ['jquery', 'jquery-ui-core', 'jquery-ui-dialog', 'prime_mover_js_network_admin'],
            PRIME_MOVER_VERSION
            );
        
        wp_enqueue_script(
            'prime_mover_untar_js',
            esc_url_raw(plugins_url('res/js/' . $untar_js, dirname(__FILE__))),
            ['jquery'],
            PRIME_MOVER_VERSION
            );
        
        wp_enqueue_style(
            'prime_mover_css_network_admin',
                esc_url_raw(plugins_url('res/css/prime_mover_css_network_admin.css', dirname(__FILE__))),
                ['wp-jquery-ui-dialog'],
                PRIME_MOVER_VERSION
                );
        
        $debug_uploads = false;
        if (defined('PRIME_MOVER_DEBUG_UPLOAD') && PRIME_MOVER_DEBUG_UPLOAD) {
            $debug_uploads = true;
        }
        wp_localize_script(
            'prime_mover_js_network_admin',
            'prime_mover_js_ajax_renderer',
                apply_filters( 'prime_mover_ajax_rendered_js_object', [
                        'ajaxurl' => esc_url_raw(admin_url('admin-ajax.php')),
                        'prime_mover_slice_size' => $this->getSystemFunctions()->getSliceSize(),
                        'prime_mover_export_nonce' => $this->getSystemFunctions()->primeMoverCreateNonce('prime_mover_export_nonce'),
                        'prime_mover_import_nonce' => $this->getSystemFunctions()->primeMoverCreateNonce('prime_mover_import_nonce'),
                        'prime_mover_uploads_nonce' => $this->getSystemFunctions()->primeMoverCreateNonce('prime_mover_uploads_nonce'),
                        'prime_mover_import_progress_nonce' => $this->getSystemFunctions()->primeMoverCreateNonce('prime_mover_import_progress_nonce'),
                        'prime_mover_export_progress_nonce' => $this->getSystemFunctions()->primeMoverCreateNonce('prime_mover_export_progress_nonce'),
                        'prime_mover_decryption_check_nonce' => $this->getSystemFunctions()->primeMoverCreateNonce('prime_mover_decryption_check_nonce'),
                        'prime_mover_errorlog_nonce' => $this->getSystemFunctions()->primeMoverCreateNonce('prime_mover_errorlog_nonce'),
                        'prime_mover_deletetmpfile_nonce' => $this->getSystemFunctions()->primeMoverCreateNonce('prime_mover_deletetmpfile_nonce'),
                        'prime_mover_upload_progress' => esc_js(__('Uploading in progress.', 'prime-mover')),
                        'prime_mover_ajax_ajax_loader_gif' => esc_url_raw(plugins_url('res/img/ajax-loader.gif', dirname(__FILE__))),
                        'prime_mover_import_success' => esc_js(__('Site successfully imported!', 'prime-mover')),
                        'prime_mover_import_error' => esc_js(__('Import error!', 'prime-mover')),
                        'export_now_button' => esc_js(__('Export now', 'prime-mover')),
                        'cancel_button' => esc_js(__('Cancel', 'prime-mover')),
                        'yes_button' => esc_js(__('Yes', 'prime-mover')),
                        'no_button' => esc_js(__('No', 'prime-mover')),
                        'ok_button' => esc_js(__('OK', 'prime-mover')),
                        'prime_mover_post_max_size' => $this->getSystemFunctions()->getPostMaxSizeInPhpini(),
                        'prime_mover_upload_max_size' => $this->getSystemFunctions()->getUploadmaxFilesizeInPhpini(),
                        'prime_mover_debug_uploads' => apply_filters('prime_mover_enable_upload_js_debug', $debug_uploads),                        
                        'prime_mover_spinner_upload_text' => esc_js(__('Processing', 'prime-mover')),
                        'prime_mover_spinner_download_text' => esc_js(__('Initializing download', 'prime-mover')),
                        'prime_mover_downloading_progress_text' => esc_js(__('Downloading package', 'prime-mover')),
                        'prime_mover_dropbox_upload_progress_text' => esc_js(__('Uploading to Dropbox', 'prime-mover')),
                        'prime_mover_unknown_js_error' => esc_js(__('Unknown error occurred, please try again.', 'prime-mover')),
                        'prime_mover_spinner_zipanalysis_text' => esc_js(__('Analyzing resources..', 'prime-mover')),                        
                        'prime_mover_zipjs_workers' => esc_url_raw(plugins_url('res/js/zip-library/', dirname(__FILE__))),
                        'prime_mover_media_decryption_error' => $this->getSystemInitialization()->returnCommonMediaDecryptionError(),
                        'prime_mover_phpuploads_misconfigured' => $this->getSystemFunctions()->maybeUploadParametersMisconfigured(),
                        'prime_mover_upload_misconfiguration_error' => sprintf(esc_html__("Upload package restore is not possible due to PHP upload misconfiguration. Please %s .", 'prime-mover'), 
            '<a class="prime-mover-external-link" target="_blank" href="' .
            esc_url(CODEXONICS_PACKAGE_MANAGER_RESTORE_GUIDE . "#packagemanager") . '">' . esc_html__('restore using package manager', 'prime-mover') . '</a>'),
                        'prime_mover_invalid_package' => esc_js(__('Invalid file type or corrupted package.', 'prime-mover')),
                        'prime_mover_exceeded_browser_limit' => esc_js(__('Restoring package beyond 4GB is not supported by browser uploads. Please upgrade to premium version and use remote URL restore feature.', 'prime-mover')),
                ])
        );
        
        do_action( 'prime_mover_after_enqueue_assets');
    }
    
    /**
     * Show an update nag to network administrator
     * that the plugin was not been able to initialize due to unmeet dependencies
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itShowsAdminNoticeErrorWhenNoOpenSSLSupport()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itDoesNotOutputAnyErrorWhenAllRequisitesMeet()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itReturnsErrorWhenFolderIsNotCreated()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itReturnsErrorWhenFileSystemIsNotInitialized()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itReturnsErrorWhenZipDisabled()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itReturnsErrorWhenNotCompliedWithMinimumRequirement()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itReturnsErrorWhenNotAllSystemsFunctionsEnabled() 
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itReturnsErrorWhenNoMbStringEnabled()
     */
    public function multisiteShowNetworkAdminNotice()
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if (false === $this->getSystemChecks()->primeMoverEssentialRequisites()) {
            //Essential requirements not meet ?>
			<div class="error">
		<?php 
            
        if (false === $this->getSystemInitialization()->getMultisiteExportFolderCreated()) {
            ?>				
			<p><?php echo esc_html__('Prime Mover plugin is not yet ready to use. Error: Unable to create its own export folder. 
					This is required to export sites. Please make sure WordPress has permission to create folder in your uploads directory.', 'prime-mover'); ?></p>
				
			<?php
        }
            if (false === $this->getSystemInitialization()->getMultisiteWpFilesystemInitialized()) {
                ?>			
			<p><?php echo sprintf ( esc_html__('Prime Mover plugin is activated but not yet ready to use. %s.', 
			    'prime-mover'), 
			    '<strong>' . esc_html__('Error: WordPress FileSystem API not set since it requires DIRECT FILE PERMISSIONS', 'prime-mover') . '</strong>'); ?></p>
			<p><?php echo esc_html__('Make sure WordPress is creating files as the same owner as the WordPress files.', 'prime-mover'); ?></p>
			
		<?php
            }
            if (false === $this->getSystemFunctions()->primeMoverCheckIfZipEnabled()) {
                ?>
			<p><?php echo esc_html__('Prime Mover plugin is not yet ready to use. Error: Requires PHP Zip extension to be enabled. Please check with you web host.', 'prime-mover'); ?></p>			
		<?php
            }
            if (false === $this->getSystemFunctions()->compliedMinimumRequirement()) {
                //Essential PHP version requirements not meet ?>
			<p><?php echo esc_html__('Prime Mover plugin is not yet ready to use. Error: Requires at least PHP 5.6.0.', 'prime-mover'); ?></p>
		<?php
            }
            if (false === $this->getSystemChecks()->primeMoverCheckIfMbstringEnabled()) {
                //No mbstring extension ?>
			<p><?php echo esc_html__('Prime Mover plugin is not yet ready to use. Error: Requires mbstring PHP extension. Please enable it.', 'prime-mover'); ?></p>
		<?php
            }
        ?>
		</div>
		<?php
        }
    }
    
    /**
     * Single site migration callback
     */
    public function singleSiteMigrationCallBack()
    {
        $backups_menu_url = $this->getSystemFunctions()->getBackupMenuUrl();
    ?>
        <div class="wrap">
           <h1 class="wp-heading-inline"><?php esc_html_e( 'Migration Tools', 'prime-mover' ); ?></h1>
           <a title="<?php esc_attr_e('Go to package manager', 'prime-mover');?>" href="<?php echo esc_url($backups_menu_url);?>" class="page-title-action"><?php esc_html_e('Go to Package Manager', 'prime-mover');?></a>
           <div class="card">              
                 <div class="prime_mover_exporter_block">  
                 <h2><?php esc_html_e( 'Export site', 'prime-mover' ); ?></h2>             
                     <div class="notice-large highlight prime-mover-helper-export-text">
                        <p><em><?php echo esc_html__( 'Export this site to any format. Please do not refresh or navigate away from this page while export is ongoing.', 
                             'prime-mover' ); 
                         ?></em></p>  
                     </div>                                      
                     <?php                        
                         do_action('prime_mover_exporter_block');
                     ?>
                 </div>
          </div>    
          <div class="card">             
                 <div class="prime_mover_importer_block">    
                 <h2><?php esc_html_e( 'Import package', 'prime-mover' ); ?></h2> 
                     <div class="notice-large highlight prime-mover-helper-export-text">
                        <p><em><?php echo esc_html__( 'Migrate a site package. Please do not refresh or navigate away from this page while import is ongoing.', 
                             'prime-mover' ); 
                         ?></em></p>                                               
                     </div>                                                                        
                      <?php 
                         do_action('prime_mover_importer_block');
                      ?>
                </div>       
	       </div>
          <div class="card">             
                 <div class="prime_mover_contact_block">    
                 <h2><?php esc_html_e( 'Contact Developers', 'prime-mover' ); ?></h2> 
                     <div class="notice-large highlight prime-mover-helper-export-text">
                        <p><em><?php echo esc_html__( 'Any suggestions for improvement? Found a bug?', 
                             'prime-mover' ); 
                         ?></em></p>                                               
                     </div>                                                                        
                     <a href="<?php echo esc_url($this->getSystemInitialization()->getContactUsPage()); ?>" class="button button-primary js-prime-mover-contact-dev"><?php esc_html_e('Contact us', 'prime-mover')?></a>
                </div>       
	       </div>	        
	   </div>
   <?php      
    }
    
    /**
     * Callback for single site Tools sub-menu
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itAddsMigrationToolsToSingleSiteMenu()
     */
    public function addExportImportOptionsSingleSite()
    {
        add_submenu_page( 'tools.php', esc_html__('Migration Tools', 'prime-mover'), 
            esc_html__('Migration Tools', 'prime-mover'),
            'manage_options', 'migration-tools', [$this, 'singleSiteMigrationCallBack']);
    }
    
    /**
     * Add menu page
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itAddsMenuPage()
     */
    public function addMenuPage()
    {
        if ( ! $this->getSystemFunctions()->maybeLoadMenuAssets() ) {
            return;
        }
        $required_cap = 'manage_network_options';
        if ( ! is_multisite() ) {
            $required_cap = 'manage_options';
        }
        
        add_menu_page(
            sprintf(esc_html__('%s Control Panel', 'prime-mover'), $this->getSystemInitialization()->getPrimeMoverPluginTitle()), 
            sprintf(esc_html__('%s', 'prime-mover'), $this->getSystemInitialization()->getPrimeMoverPluginTitle()),
            $required_cap, 'migration-panel-settings', [$this, 'addMenuPageCallBack'], 'dashicons-multisitemigrationpaneldashicons');
        
        do_action('prime_mover_run_menus');
    }
    
    /**
     * Add menu page callback
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itAddsMenuPageCallBack()
     */
    public function addMenuPageCallBack()
    {
        ?>
      <div class="wrap">
         <h1><?php echo sprintf(esc_html__('%s Control Panel', 'prime-mover'), $this->getSystemInitialization()->getPrimeMoverPluginTitle()); ?></h1>
           <?php do_action('prime_mover_dashboard_content');?> 
      </div>       
    <?php     
    }
    
    /**
     * Remove distractions on Getting Started / Settings page
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itRemovesDistractionsOnSettingsPage()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itDoesNotRemoveDistractionsAnywhere()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itDoesNotRemoveDistractionsNotAuthorized() 
     */
    public function removeDistractionsOnSettingsPage()
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $current_screen = get_current_screen();
        $load = false;
        if ( $this->getSystemFunctions()->maybeLoadAssetsOnDashboard($current_screen ) ) {
            remove_all_actions( 'admin_notices' );
            $load = true;
        }
        if ($load) {
            if (is_multisite()) {
                do_action("prime_mover_network_admin_notices");  
            } else {
                do_action("prime_mover_admin_notices");
            }
        }
    }
    
    /**
     * Load plugin text domain
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itLoadsPluginTextDomain()
     */
    public function loadPluginTextdomain()
    {
        load_plugin_textdomain( 'prime-mover', false, basename(PRIME_MOVER_PLUGIN_PATH) . '/languages/' );
    }
}
