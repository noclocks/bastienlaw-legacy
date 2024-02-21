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

use Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization;
use Codexonics\PrimeMoverFramework\build\WPConfigTransformer as WPConfigTransformer;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Config Utilities
 * Helper functionality for working with post-site configuration
 *
 */
class PrimeMoverConfigUtilities
{        
    private $import_utilities;
    private $doing_devpackage_migration;
    private $content_config_constants;
    
    const AUTOADJUSTMENT_ATTACHMENT_ENABLED = 'prime_mover_autoadjust_attachment';
    const PRIME_MOVER_OLD_ATTACHMENT = '_prime_mover_old_attachment';
    
    /**
     * Constructor
     * @param PrimeMoverSystemAuthorization $system_authorization
     * @param PrimeMoverImportUtilities $import_utilities
     */
    public function __construct(PrimeMoverImportUtilities $import_utilities)
    {
        $this->import_utilities = $import_utilities;
        $this->doing_devpackage_migration = false;
        $this->content_config_constants = [
            'WP_CONTENT_FOLDERNAME',
            'WP_CONTENT_DIR',
            'WP_CONTENT_URL',
            'UPLOADS'
        ];
    }
    
    /**
     * Get system utilities
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverSystemUtilities
     */
    public function getSystemUtilities()
    {
        return $this->getImportUtilities()->getSystemCheckUtilities()->getSystemUtilities();
    }
    
    /**
     * Get wp-content config constants
     * @return string[]
     */
    public function getContentConfigConstants()
    {
        return $this->content_config_constants;
    }
    
    /**
     * Get functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     */
    public function getSystemFunctions()
    {
        return $this->getImportUtilities()->getImporter()->getSystemFunctions();
    }
    
    /**
     * Get system initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     */
    public function getSystemInitialization()
    {
        return $this->getSystemFunctions()->getSystemInitialization();
    }
    
    /**
     * Initialize hooks
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverPrimeMoverConfigUtilities::itAddsInitHooks()
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverPrimeMoverConfigUtilities::itChecksIfHooksAreOutdated()
     */
    public function initHooks()
    {
        add_filter('prime_mover_skip_search_replace', [$this, 'maybeRestoringDevPackage'], 10, 3);
        add_filter('prime_mover_skip_search_replace', [$this, 'maybeRestoringCompletePackageWhichIsDev'], 15, 3);
        
        add_action('prime_mover_skipped_search_replace', [$this, 'updateHomeAndSiteURLConfig'], 10, 2); 
        add_action('prime_mover_skipped_search_replace', [$this, 'markedOldAttachments'], 15, 3);  
        
        add_filter('prime_mover_filter_other_information', [$this, 'setSiteAndHomeURL'], 10, 2);              
        add_filter('wp_get_attachment_url', [$this, 'autoAdjustOldAttachments'], 10, 2);
        add_filter('wp_get_attachment_thumb_url', [$this, 'autoAdjustOldAttachments'], 10, 2);
        
        add_filter('wp_get_attachment_image_attributes', [$this, 'autoAdjustmentOldImageAttributes'], 10, 2);
        add_filter('wp_calculate_image_srcset', [$this, 'autoAdjustmentOldSrcSets'], 10, 5);        
        
        add_action('prime_mover_before_doing_import', [$this, 'appendSiteURLToWPConfigSiteRestore'], 10, 2);
        add_action('prime_mover_append_other_config_constants', [$this, 'maybeAppendCustomWpContentUploadsConstants'], 10, 2);
    }    
    
    /**
     * Append custom wp-content CONFIG constants IF set.
     * @param WPConfigTransformer $config_transformer
     * @param array $custom_values
     */
    public function maybeAppendCustomWpContentUploadsConstants(WPConfigTransformer $config_transformer, $custom_values = [])
    {       
        foreach ($this->getContentConfigConstants() as $constant) {
            if ($config_transformer->exists('constant', $constant) && !empty($custom_values[$constant])) {
                $config_transformer->remove('constant', $constant);
                $config_transformer->add('constant', $constant, $custom_values[$constant], ["anchor" => '$table_prefix']);
            }
        }
    }
    
    /**
     * Append site URL and home URL to WP Config to avoid breaking ajax requests during restoration dB processing.
     * @param number $blog_id
     * @param boolean $import_initiated
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverPrimeMoverConfigUtilities::itAppendsSiteUrlToWpConfigSingleSite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverPrimeMoverConfigUtilities::itDoesNotAppendSiteURLWpConfigWhenMultisite() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverPrimeMoverConfigUtilities::itDoesNotAppendSiteUrlWpConfigWhenImportInitiated() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverPrimeMoverConfigUtilities::itAddsSiteUrlAndHomeUrlWhenConfigDoesNotExist() 
     */
    public function appendSiteURLToWPConfigSiteRestore($blog_id = 0, $import_initiated = false)
    {
        if (is_multisite()) {
            return;
        }
        if ($import_initiated) {
            return;
        }
        
        $site_url = get_site_url();
        $home_url = get_home_url();
        
        if ($this->maybeBailOutSiteAndHomeUrlOverride($site_url, $home_url)) {
            return;
        }
        
        $this->updateSiteAndHomeUrLWpConfigHelper($site_url, $home_url);
    }
    
    /**
     * Maybe bail out site and home URL override
     * @param string $site_url
     * @param string $home_url
     * @return boolean
     */
    protected function maybeBailOutSiteAndHomeUrlOverride($site_url = '', $home_url = '')
    {
        $site_url_cons = '';
        $home_url_cons = '';
        
        if (defined('WP_SITEURL') && WP_SITEURL) {
            $site_url_cons = WP_SITEURL;
        }
        
        if (defined('WP_HOME') && WP_HOME) {
            $home_url_cons = WP_HOME;
        }
                
        if (!$site_url_cons || !$home_url_cons) {
            return false;
        }
        
        if ($site_url_cons !== $site_url || $home_url_cons !== $home_url) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Update site and home URL in wp-config.php helper method
     * @param string $site_url
     * @param string $home_url
     * @tested Codexonics\PrimeMoverFramework\Tests\TesPrimeMoverPrimeMoverConfigUtilities::itAppendsSiteUrlToWpConfigSingleSite() 
     */
    protected function updateSiteAndHomeUrLWpConfigHelper($site_url = '', $home_url = '')
    {
        if (!$site_url || !$home_url) {
            return;
        }
        $config_transformer = $this->getConfigTransformer();
        if (!$config_transformer) {
            return;
        }
        $custom_values = $this->computeCustomConfigConstants();
        if ($config_transformer->exists('constant', 'WP_SITEURL')) {
            
            $config_transformer->remove('constant', 'WP_SITEURL' );
            $config_transformer->add('constant', 'WP_SITEURL', $site_url, ["anchor" => '$table_prefix']);
            
        } else {
            $config_transformer->add('constant', 'WP_SITEURL', $site_url, ["anchor" => '$table_prefix']);
        }
        
        if ($config_transformer->exists('constant', 'WP_HOME')) {
            
            $config_transformer->remove('constant', 'WP_HOME');
            $config_transformer->add('constant', 'WP_HOME', $home_url, ["anchor" => '$table_prefix']);
            
        } else {
            $config_transformer->add('constant', 'WP_HOME', $home_url, ["anchor" => '$table_prefix']);
        }
        
        $custom_values['site_url'] = $site_url;
        $custom_values['home_url'] = $home_url;
        
        do_action('prime_mover_append_other_config_constants', $config_transformer, $custom_values);                
    }
    
    /**
     * Compute custom wp-content config constants
     * @return NULL[]|string[]
     */
    protected function computeCustomConfigConstants()
    {
        $wpcontent_foldername = '';
        if (defined('WP_CONTENT_FOLDERNAME') && WP_CONTENT_FOLDERNAME) {
            $wpcontent_foldername = WP_CONTENT_FOLDERNAME;
        }
        
        $wpcontent_dir = '';
        if (defined('WP_CONTENT_DIR') && WP_CONTENT_DIR) {
            $wpcontent_dir = WP_CONTENT_DIR;
        }
        
        $wpcontent_url = '';
        if (defined('WP_CONTENT_URL') && WP_CONTENT_URL) {
            $wpcontent_url = WP_CONTENT_URL;
        }
        
        $uploads = '';
        if (defined('UPLOADS') && UPLOADS) {
            $$uploads = UPLOADS;
        }
        
        return [
            'WP_CONTENT_FOLDERNAME' => $wpcontent_foldername,
            'WP_CONTENT_DIR' => $wpcontent_dir,
            'WP_CONTENT_URL' => $wpcontent_url,
            'UPLOADS' => $uploads             
        ];
    }
    
    /**
     * Restoring a complete package with a dev package db
     * @param boolean $skipped
     * @param array $ret
     * @param number $blogid_to_import
     * @return string|boolean
     */
    public function maybeRestoringCompletePackageWhichIsDev($skipped = false, $ret = [], $blogid_to_import = 0)
    {
        if ($this->getDoingDevPackageMigration()) {
            return $skipped;
        }
        if (false === $this->getImportUtilities()->maybeImportPlugins($ret) || empty($ret['origin_site_url'])) {
            return $skipped;
        }
        $this->getSystemFunctions()->switchToBlog($blogid_to_import);
        
        global $wpdb;
        $db_site_url = $wpdb->get_var("SELECT option_value FROM $wpdb->options WHERE option_name = 'siteurl'");        
        
        $this->getSystemFunctions()->restoreCurrentBlog();                
        $db_site_domain = parse_url($db_site_url, PHP_URL_HOST);
        
        $origin_site_url = PRIME_MOVER_SECURE_PROTOCOL . $ret['origin_site_url'];
        $origin_site_domain = parse_url($origin_site_url, PHP_URL_HOST);
        
        if ($db_site_domain !== $origin_site_domain) {            
            if (defined('PRIME_MOVER_CAN_COMPLETE_PACKAGE_BE_DEV') && true === PRIME_MOVER_CAN_COMPLETE_PACKAGE_BE_DEV) {
                
                $this->doing_devpackage_migration = true;
                do_action('prime_mover_log_processed_events', "Restoring dev package.", $blogid_to_import , 'import', 'maybeRestoringCompletePackageWhichIsDev', $this);
                
                return true;
                
            } else {
                
                do_action('prime_mover_log_processed_events', "Dev package dB on complete package - FORCING SITE URL UPDATE LATER.", $blogid_to_import , 'import', 'maybeRestoringCompletePackageWhichIsDev', $this);
                $this->getSystemInitialization()->setForceAdjustSiteUrl(true);
                return $skipped;
            }            
        }        
        return $skipped;
    }
    
    /**
     * Maybe load dev attachment
     * @param mixed $attachment
     * @return boolean|[]
     */
    private function maybeLoadDevAttachment($attachment)
    {
        if ( ! $attachment ) {
            return false;
        }
        if (isset($attachment->ID)) {
            $attachment_id = (int)$attachment->ID;
        } else {
            $attachment_id = (int)$attachment;
        }
        if ( ! $attachment_id ) {
            return false;
        }
        $blog_id = 0;
        if (is_multisite()) {
            $blog_id = get_current_blog_id();
        }
        if ( ! get_post_meta($attachment_id, self::PRIME_MOVER_OLD_ATTACHMENT, true)) {
            return false;
        }
        $replaceable_attachments = $this->getSystemFunctions()->getBlogOption($blog_id, self::AUTOADJUSTMENT_ATTACHMENT_ENABLED);
        if ( ! $replaceable_attachments ) {
            return false;
        }
        if ( ! isset($replaceable_attachments['search']) || ! isset($replaceable_attachments['replace'] ) ) {
            return false;
        }
        
        $search = $replaceable_attachments['search'];
        $replace = $replaceable_attachments['replace'];
        $replace = str_replace('https://', 'http://', $replace);
        
        return ['search' => $search, 'replace' => $replace ];
    }
    
    /**
     * Auto-adjust old src sets for dev package
     * @param array $sources
     * @param array $size_array
     * @param string $image_src
     * @param array $image_meta
     * @param number $attachment_id
     * @return array
     */
    public function autoAdjustmentOldSrcSets($sources = [], $size_array = [], $image_src = '', $image_meta = [], $attachment_id = 0)
    {
        $dev_attachment = $this->maybeLoadDevAttachment($attachment_id);
        if ( ! $dev_attachment ) {
            return $sources;
        }
        $search = $dev_attachment['search'];
        $replace = $dev_attachment['replace'];
        
        foreach ($sources as $id => $details) {
            if ( ! isset($details['url'] ) ) {
                continue;
            }
            $url = $details['url']; 
            if ( ! is_string($url) || ! $url ) {
                continue;
            }
            $sources[$id]['url'] = $this->replaceWithCanonicalAttachment($search, $replace, $url);
        }

        return $sources;         
    }
    
    /**
     * Auto adjustment srcsets
     * @param array $attribute
     * @param object $attachment
     * @return array
     */
    public function autoAdjustmentOldImageAttributes($attribute = [], $attachment = null)
    {
        $dev_attachment = $this->maybeLoadDevAttachment($attachment);
        if ( ! $dev_attachment ) {
            return $attribute;
        }
        
        if ( ! isset($attribute['srcset']) ) {
            return $attribute;
        }
        
        $srcset = $attribute['srcset'];
        if ( ! $srcset ) {
            return $attribute;
        }
        
        $search = $dev_attachment['search'];
        $replace = $dev_attachment['replace'];
        
        $srcset = $this->replaceWithCanonicalAttachment($search, $replace, $srcset);
        $attribute['srcset'] = $srcset;
        
        return $attribute;        
    }
    
    /**
     * Auto adjust attachments
     * @param string $url
     * @param number $attachment_id
     */
    public function autoAdjustOldAttachments($url = '', $attachment_id = 0)
    {
        $dev_attachment = $this->maybeLoadDevAttachment($attachment_id);
        if ( ! $dev_attachment || ! $url ) {
            return $url;
        }
        
        $search = $dev_attachment['search'];
        $replace = $dev_attachment['replace'];

        return $this->replaceWithCanonicalAttachment($search, $replace, $url);
    }
    
    /**
     * Search replace on runtime for old attachments
     * @param string $search
     * @param string $replace
     * @param string $url
     * @return mixed
     */
    private function replaceWithCanonicalAttachment($search = '', $replace = '', $url = '')
    {        
        $uploads = $this->getSystemInitialization()->getWpUploadsDir(false, true);
        $result = str_replace($search, $replace, $url);
        if ($result === $url && ! empty($uploads['baseurl'])) {            
            $baseurl = $uploads['baseurl'];
            $result = str_replace($baseurl, $replace, $url);
        }
        return $result;        
    }
    
    /**
     * Mark old attachments helpers
     */
    private function markedOldAttachmentsHelper()
    {
        global $wpdb;
        $wpdb->query(  
            
            "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
             SELECT ID AS post_id, '_prime_mover_old_attachment' AS meta_key, 'yes' AS meta_value
             FROM {$wpdb->posts} WHERE ID IN
             (SELECT ID FROM {$wpdb->posts} where post_type = 'attachment')"
                 
         );       
    }
    
    /**
     * Mark old attachments after migration so they can be located
     * This is done after dB restore
     * @param array $ret
     * @param number $blogid_to_import
     * @param array $replaceables
     */
    public function markedOldAttachments($ret = [], $blogid_to_import = 0, $replaceables = [])
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized() ) {
            return;
        }
        
        $dev_attachment_protocol = [];
        if ( ! $this->getDoingDevPackageMigration() ) {   
            delete_option(self::AUTOADJUSTMENT_ATTACHMENT_ENABLED);
            return;
        }
        if ( ! is_multisite() ) {
            $blogid_to_import = 0;
        }
        $this->getSystemFunctions()->switchToBlog($blogid_to_import);
        
        if (empty($replaceables['wpupload_url'])) {
            $this->getSystemFunctions()->restoreCurrentBlog();
            return;
        }
        
        $time_start = microtime(true);
        $this->markedOldAttachmentsHelper();        
        $time_end = microtime(true);
        $execution_time = $time_end - $time_start;

        do_action('prime_mover_log_processed_events', "It took $execution_time seconds to mark all attachments", 0, 'import', 'markedOldAttachments', $this);
        
        $replaceable_attachments = $replaceables['wpupload_url'];        
        wp_cache_delete('alloptions', 'options');
        $devattachment_current_setting = get_option(self::AUTOADJUSTMENT_ATTACHMENT_ENABLED);
        
        $dev_attachment_protocol['search'] = $replaceable_attachments['replace'];
        $dev_attachment_protocol['replace'] = $replaceable_attachments['search'];
        
        if ($devattachment_current_setting) {
            $dev_attachment_protocol['replace'] = $devattachment_current_setting['replace'];
        }
        delete_option(self::AUTOADJUSTMENT_ATTACHMENT_ENABLED);         
        $this->getSystemFunctions()->updateBlogOption($blogid_to_import, self::AUTOADJUSTMENT_ATTACHMENT_ENABLED, $dev_attachment_protocol);        
        $this->getSystemFunctions()->restoreCurrentBlog(); 
    }
    
    /**
     * Set site and home URL
     * Used for both single site and multisite
     * @param array $ret
     * @param number $blogid_to_import
     * @return array
     */
    public function setSiteAndHomeURL($ret = [], $blogid_to_import = 0)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized() ) {
            return;
        }
        
        if (isset($ret['dev_site_url'])) {
            unset($ret['dev_site_url']);
        }
        if (isset($ret['dev_home_url'])) {
            unset($ret['dev_home_url']);
        }
        if (!is_multisite() ) {
            $blogid_to_import = null;
        }
        
        $ret['dev_site_url'] = get_site_url($blogid_to_import);
        $ret['dev_home_url'] = get_home_url($blogid_to_import);        
        
        return $ret;
    }
 
    /**
     * Checks if site is restoring dev or debugging package
     * This applicable for single-site and multisite
     * @param boolean $skipped
     * @param array $ret
     * @param number $blogid_to_import
     * @return string|boolean
     */
    public function maybeRestoringDevPackage($skipped = false, $ret = [], $blogid_to_import = 0)
    {
        if ( ! isset($ret['skipped_media'])) {
            return $skipped;
        }
        
        if (false === $this->getImportUtilities()->maybeImportPlugins($ret)) {
            return $skipped;
        }
        
        $this->doing_devpackage_migration = true;
        do_action('prime_mover_log_processed_events', "Restoring dev package.", $blogid_to_import , 'import', 'maybeRestoringDevPackage', $this);
        return true;
    }
    
    /**
     * Update site and home url for single sites.
     * Single-site specific method
     * @param array $ret
     */
    private function updateHomeAndSiteURLConfigSingleSite($ret = [])
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized() ) {
            return;
        }
        
        $site_url = $ret['dev_site_url'];
        $home_url = $ret['dev_home_url']; 
        $this->updateSiteAndHomeUrLWpConfigHelper($site_url, $home_url);
    }

    /**
     * Update site and home url for restored multisite sub-site
     * @param array $ret
     * @param number $blogid_to_import
     */
    private function updateHomeAndSiteURLConfigSubSite($ret = [], $blogid_to_import = 0)
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized() || ! is_multisite() || ! $blogid_to_import ) {
            return;
        }
        
        $site_url = $ret['dev_site_url'];
        $home_url = $ret['dev_home_url']; 
        
        delete_blog_option($blogid_to_import, 'siteurl');
        delete_blog_option($blogid_to_import, 'home');        
        
        $this->getSystemFunctions()->updateBlogOption($blogid_to_import, 'siteurl', $site_url);
        $this->getSystemFunctions()->updateBlogOption($blogid_to_import, 'home', $home_url);     
    }
    
    /**
     * Update home and site URL in wp-config.php after debug package migration
     * For both single-site and multisite
     * @param array $ret
     * @param number $blogid_to_import
     */
    public function updateHomeAndSiteURLConfig($ret = [], $blogid_to_import = 0)
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized() || ! $this->getDoingDevPackageMigration() ) {
            return $ret;
        }
        if ( ! isset($ret['dev_site_url'])) {
            return $ret;
        }
        if ( ! isset($ret['dev_home_url'])) {
            return $ret;
        }
        do_action('prime_mover_log_processed_events', "Updating site and home URL after skipping search replace, required for dev package restore.", $blogid_to_import , 'import', 'updateHomeAndSiteURLConfig', $this);
        
        if (is_multisite()) {
            $this->updateHomeAndSiteURLConfigSubSite($ret, $blogid_to_import);
        } else {
            $this->updateHomeAndSiteURLConfigSingleSite($ret);
        }
    }
    
    /**
     * Checks if the site is doing dev package migration
     * @return boolean
     */
    public function getDoingDevPackageMigration()
    {
        return $this->doing_devpackage_migration;
    }
    
    /**
     * Get import utilities
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverImportUtilities
     */
    public function getImportUtilities()
    {
        return $this->import_utilities;
    }
    
    /**
     * Get system authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     */
    public function getSystemAuthorization()
    {
        return $this->getImportUtilities()->getSystemAuthorization();
    }
    
    /**
     * Get config transformer instance
     * @return NULL|\Codexonics\PrimeMoverFramework\build\WPConfigTransformer
     * Note that this returns NULL if wp-config.php path is NOT writable.
     */
    public function getConfigTransformer()
    {
        $instance = null;
        if (!$this->getSystemFunctions()->isConfigFileWritable()) {
            return $instance;
        }
        
        if ( ! $this->getSystemAuthorization()->isUserAuthorized() ) {
            return $instance;    
        }
        
        if ( ! primeMoverGetConfigurationPath() ) {
            return $instance; 
        }
        
        $configuration_path = primeMoverGetConfigurationPath();
        return new WPConfigTransformer($configuration_path);
    }
    
    /**
     * Attempt to determine where the enc key is added
     * @return string
     */
    public function whereEncKeyAdded()
    {        
        if (!$this->getSystemUtilities()->isEncKeyValid()) {  
            return '';
        }
        
        global $wp_filesystem;
        if (!$this->getSystemFunctions()->isWpFileSystemUsable($wp_filesystem)) {
            return '';
        }
        
        $configuration_path = primeMoverGetConfigurationPath();
        if (!$configuration_path) {
            return '';
        }
       
        $config_transformer = $this->getConfigTransformer();
        if ($config_transformer && $config_transformer->exists('constant', 'PRIME_MOVER_DB_ENCRYPTION_KEY')) {            
            return $configuration_path;            
        } 
             
        $mu_constant_script = $this->getSystemInitialization()->getCliMustUseConstantScriptPath();
        $string = '';
        if ($this->getSystemFunctions()->nonCachedFileExists($mu_constant_script) && false === apply_filters('prime_mover_is_config_usable', false)) {
            $string = $this->getSystemFunctions()->fileGetContents($mu_constant_script);
        }
        
        if (is_string($string) && $string && false !== strpos($string, PRIME_MOVER_DB_ENCRYPTION_KEY)) {
            return $mu_constant_script;
        }            

       return '';       
    }
}