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
use wpdb;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Custom Multisite Class
 * Compatibiity class for customized multisite main site implementations
 *
 */
class PrimeMoverCustomMultisite
{     
    private $prime_mover;
    
    /**
     * Construct
     * @param PrimeMover $prime_mover
     * @param array $utilities
     */
    public function __construct(PrimeMover $prime_mover, $utilities = [])
    {
        $this->prime_mover = $prime_mover;
    }
   
    
    /**
     * Get Prime Mover instance
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMover
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
     */
    public function getSystemAuthorization()
    {
        return $this->getPrimeMover()->getSystemAuthorization();
    }
        
    /**
     * Initialize hooks
     */
    public function initHooks()
    {
        add_filter('prime_mover_computed_updated_prefix', [$this, 'computeMainSiteSubsiteCompatPrefix'], 10, 3);
        add_filter('prime_mover_filter_match_prefix', [$this, 'setWpdBPrefixToGetUsersTableCorrectly'], 10, 3);
        add_filter('prime_mover_tables_for_replacement', [$this, 'excludeGlobalLevelTablesOnMainSite'], 15, 2);
        
        add_filter('prime_mover_filter_export_footprint', [$this, 'addCompatibleUploadInfoToSystemFootprint'], 9999, 1);
        add_filter('prime_mover_filter_replaceables', [$this, 'maybeRemoveAlternativeUploadsUrl'], 9999, 2);
        add_filter('prime_mover_filter_replaceables', [$this, 'maybeFixedCorrectDomain'], 12, 2);
        
        add_filter('prime_mover_tables_to_export', [$this, 'filterCoreMultisiteTables'], 10, 2);
    }

    /**
     * Remove core multisite tables since these are network specific
     * @param array $tables
     * @param number $blogid_to_export
     * @return array
     */
    public function filterCoreMultisiteTables($tables = [], $blogid_to_export = 0)
    {
        if (!$blogid_to_export) {
            return $tables;
        }
        
        if (!$this->getSystemFunctions()->isMultisiteMainSite($blogid_to_export, true)) {
            return $tables;
        }
        
        global $wpdb;
        $multisite_core_tables = array_values($wpdb->tables('ms_global', true, $blogid_to_export));
        
        return array_diff($tables, $multisite_core_tables);
    }
    
    /**
     * Exclude global level tables when restoring on main site
     * @param array $all_tables
     * @param number $blog_id
     */
    public function excludeGlobalLevelTablesOnMainSite($all_tables = [], $blog_id = 0)
    {
        if (!is_array($all_tables) || empty($all_tables) || !$blog_id) {
            return $all_tables;
        }
        
        if (!$this->getSystemFunctions()->isMultisiteMainSite($blog_id, true)) {
            return $all_tables;
        }
        
        global $wpdb;
        
        $global_tables = array_values($wpdb->tables('ms_global', true, $blog_id));
        $global_tables[] = $wpdb->users;
        $global_tables[] = $wpdb->usermeta;
        
        $all_tables = array_diff($all_tables, $global_tables);
        
        return $all_tables;
    }
    
    /**
     * Checks if domain replace problem scenario
     * If problem scenario is TRUE should skip adjustment
     * @param string $given
     * @param string $replace
     * @param string $search
     * @return boolean
     */
    protected function isProblemScenario($given = '', $replace = '', $search = '')
    {
        $problem_scenario = false;
        if (false !== strpos($replace, $search) && false !== strpos($given, $replace)) {
            $problem_scenario = true;
        }
        return $problem_scenario;
    }
    
    /**
     * Maybe fixed correct domain in the replace parameter 
     * to make sure it uses the domain itself and not DOMAIN_CURRENT_SITE
     * @param array $replaceables
     * @param array $ret
     */
    public function maybeFixedCorrectDomain($replaceables = [], $ret = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized() || !is_multisite() || !$this->isUsingCustomMainSite()) {
            return $replaceables;
        }        
        if (empty($replaceables['domain_replace_uri_http']['replace'])) {
            return $replaceables;
        }
        
        if (empty($replaceables['domain_replace']['search'])) {
            return $replaceables;
        }        
               
        $replace = $this->getSystemFunctions()->removeSchemeFromUrl($replaceables['domain_replace_uri_http']['replace']);
        
        $wp_upload_adjust = false;
        $wp_content_adjust = false;
        $generic_upload_adjust = false;
        $generic_content_adjust = false;
        
        if (!empty($replaceables['wpupload_url']['replace'])) {
            $wp_upload_adjust = true;
        }
        
        if (!empty($replaceables['wpcontent_urls']['replace'])) {
            $wp_content_adjust = true;
        }
        
        if (!empty($replaceables['generic_upload_scheme']['replace'])) {
            $generic_upload_adjust = true;
        }
        
        if (!empty($replaceables['generic_content_scheme']['replace'])) {
            $generic_content_adjust = true;
        }
        
        $orig_upload = $replaceables['wpupload_url']['replace'];
        $orig_content = $replaceables['wpcontent_urls']['replace'];
        $orig_generic_upload = $replaceables['generic_upload_scheme']['replace'];
        $orig_generic_content = $replaceables['generic_content_scheme']['replace'];        
        
        $search_upload = parse_url($orig_upload, PHP_URL_HOST);
        $search_content = $this->getSiteUrlFromWpContent($orig_content);     
        $search_generic_upload = parse_url($orig_generic_upload, PHP_URL_HOST);
        $search_generic_content = $this->getSiteUrlFromWpContent($orig_generic_content);        
        
        if ($wp_upload_adjust && !$this->isProblemScenario($orig_upload, $replace, $search_upload)) {
            $replaceables['wpupload_url']['replace'] = str_replace($search_upload, $replace, $orig_upload);
        }
        
        if ($wp_content_adjust && !$this->isProblemScenario($orig_content, $replace, $search_content)) {
            $replaceables['wpcontent_urls']['replace'] = str_replace($search_content, $replace, $orig_content);
        }        
        
        if ($generic_upload_adjust && !$this->isProblemScenario($orig_generic_upload, $replace, $search_generic_upload)) {
            $replaceables['generic_upload_scheme']['replace'] = str_replace($search_generic_upload, $replace, $orig_generic_upload);
        }
        
        if ($generic_content_adjust && !$this->isProblemScenario($orig_generic_content, $replace, $search_generic_content)) {
            $replaceables['generic_content_scheme']['replace'] = str_replace($search_generic_content, $replace, $orig_generic_content);
        }
        
        return $replaceables;
    }
    
    /**
     * Get site URL from wp-content
     * @param string $content_url
     * @return string
     */
    protected function getSiteUrlFromWpContent($content_url = '')
    {
        $nocontent = untrailingslashit(dirname($content_url));
        return $this->getSystemFunctions()->removeSchemeFromUrl($nocontent);        
    }
    
    /**
     * Maybe remove incompatible alternative uploads URL
     * @param array $replaceables
     * @param array $ret
     * @return array
     */
    public function maybeRemoveAlternativeUploadsUrl($replaceables = [], $ret = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized() || !is_multisite() || !$this->isUsingCustomMainSite()) {
            return $replaceables;
        }
        
        if (empty($replaceables['wpupload_url']) || empty($replaceables['alternative_wpupload_url'])) {
            return $replaceables;
        }
        
        $alt_wpupload_url_srch = '';
        if (isset($replaceables['alternative_wpupload_url']['search'])) {
            $alt_wpupload_url_srch = $replaceables['alternative_wpupload_url']['search'];
        }
        $alt_wpupload_url_rplc = '';
        if (isset($replaceables['alternative_wpupload_url']['replace'])) {
            $alt_wpupload_url_rplc = $replaceables['alternative_wpupload_url']['replace'];
        }
        $wpupload_rplc = '';
        if (isset($replaceables['wpupload_url']['replace'])) {
            $wpupload_rplc = $replaceables['wpupload_url']['replace'];
        }
        
        if (!$alt_wpupload_url_srch || !$alt_wpupload_url_rplc || !$wpupload_rplc) {
            return $replaceables;
        }
        
        $alt_wpupload_url_srch = $this->getSystemFunctions()->removeSchemeFromUrl($alt_wpupload_url_srch);
        $alt_wpupload_url_rplc = $this->getSystemFunctions()->removeSchemeFromUrl($alt_wpupload_url_rplc);
        $wpupload_rplc = $this->getSystemFunctions()->removeSchemeFromUrl($wpupload_rplc);
        $unset = false;
        if (false !== strpos($wpupload_rplc, $alt_wpupload_url_srch) && false !== strpos($alt_wpupload_url_rplc, $alt_wpupload_url_srch)) {
            $unset = true;
            unset($replaceables['alternative_wpupload_url']);
            
        } 
        if ($unset && isset($replaceables['wpupload_url_alt_mixed_content'])) {
            unset($replaceables['wpupload_url_alt_mixed_content']);
        }        
        
        return $replaceables;        
    }
    
    /**
     * Fixed upload information URLs for custom multisites
     * @param array $footprint
     * @return array
     */
    public function addCompatibleUploadInfoToSystemFootprint($footprint = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized() || !is_multisite()) {
            return $footprint;
        }
        
        if (empty($footprint['upload_information_url']) || empty($footprint['site_url']) || empty($footprint['scheme'])) {
            return $footprint;
        }
        
        if (!$this->isUsingCustomMainSite()) {
            return $footprint;
        }
        
        $scheme = $footprint['scheme'];         
        $orig_upload_info_url = $footprint['upload_information_url'];
        $site_url = $footprint['site_url'];
        $orig_alt_upload_info_url = '';        
        
        if (empty($footprint['alternative_upload_information_url'])) {
            $orig_alt_upload_info_url = $this->computeAltUploadInformationUrl($orig_upload_info_url, $site_url);
        } else {
            $orig_alt_upload_info_url = $footprint['alternative_upload_information_url'];
        }
        
        if (!$orig_alt_upload_info_url) {
            return $footprint;
        }
       
        if (PRIME_MOVER_SECURE_PROTOCOL === $scheme) {
            $orig_alt_upload_info_url = str_replace(PRIME_MOVER_NON_SECURE_PROTOCOL, PRIME_MOVER_SECURE_PROTOCOL, $orig_alt_upload_info_url);
            $orig_upload_info_url = str_replace(PRIME_MOVER_NON_SECURE_PROTOCOL, PRIME_MOVER_SECURE_PROTOCOL, $orig_upload_info_url);
        }        
        
        $footprint['upload_information_url'] = $orig_alt_upload_info_url;
        $footprint['alternative_upload_information_url'] = $orig_upload_info_url;
       
        return $footprint;
    }
    
    /**
     * Compute alt upload information URL
     * @param string $orig_upload_info_url
     * @param string $site_url
     * @return string|mixed
     */
    protected function computeAltUploadInformationUrl($orig_upload_info_url = '', $site_url = '')
    {
        if (!$orig_upload_info_url || !$site_url) {
            return '';
        }
        $domain_current_site_host = parse_url($orig_upload_info_url, PHP_URL_HOST);
        if (!$domain_current_site_host) {
            return '';
        }
        return str_replace($domain_current_site_host, $site_url, $orig_upload_info_url);        
    }
    
    /**
     * Checks if using custom main site
     * @return boolean
     */
    protected function isUsingCustomMainSite()
    {
        $mainsite_blog_id = $this->getSystemInitialization()->getMainSiteBlogId();
        $mainsite_blog_id = (int)$mainsite_blog_id;
        $custom_main_site = false;
        
        if ($mainsite_blog_id > 1) {
            $custom_main_site = true;        
        }
        $domain_current_site_id = 0;
        if (!$custom_main_site && defined('DOMAIN_CURRENT_SITE') && DOMAIN_CURRENT_SITE) {
            $domain_current_site_id = (int)get_blog_id_from_url(DOMAIN_CURRENT_SITE);
        }
        if (!$custom_main_site && $domain_current_site_id !== $mainsite_blog_id) {
            $custom_main_site = true;            
        }
        return $custom_main_site;
    }
    
    /**
     * Use base prefix to get users correctly in custom main site implementation
     * @param string $wpdb_prefix
     * @param wpdb $wpdb
     * @param number $blog_id
     * @return string|wpdb
     */
    public function setWpdBPrefixToGetUsersTableCorrectly($wpdb_prefix = '', wpdb $wpdb = null, $blog_id = 0)
    {
        if (!is_multisite()) {
            return $wpdb_prefix;
        }
        
        $custom_main_site = $this->isUsingCustomMainSite();       
        if ($custom_main_site && $this->getSystemFunctions()->isMultisiteMainSite($blog_id, true)) {
            return $wpdb->base_prefix;
        }
        
        if ($custom_main_site && !$this->getSystemFunctions()->isMultisiteMainSite($blog_id, true)) {           
            return $wpdb->base_prefix . $blog_id;
        }
        
        if (!$custom_main_site && $this->getSystemFunctions()->isMultisiteMainSite($blog_id)) {
            return $wpdb->base_prefix;
        }        
        return $wpdb_prefix;
    }
    
    /**
     * Compute main site & subsite prefix - custom multisite compatible
     * @param string $updated_prefix
     * @param number $blogid_to_export
     * @param string $original_prefix
     * @return string
     */
    public function computeMainSiteSubsiteCompatPrefix($updated_prefix = '', $blogid_to_export = 0, $original_prefix = '')
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $updated_prefix;
        }
        
        if (!is_multisite()) {
            return $updated_prefix;
        }
        
        $main_site_blog_id = $this->getPrimeMover()->getSystemInitialization()->getMainSiteBlogId();
        $main_site_blog_id = (int)$main_site_blog_id;
        $blogid_to_export = (int)$blogid_to_export;
              
        if ($main_site_blog_id > 1 && $this->getSystemFunctions()->isMultisiteMainSite($blogid_to_export)) {
            return $updated_prefix . $blogid_to_export . '_';   
            
        } elseif (1 === $blogid_to_export && !$this->getSystemFunctions()->isMultisiteMainSite($blogid_to_export)) {
            return $original_prefix;
            
        } elseif (!$this->getSystemFunctions()->isMultisiteMainSite($blogid_to_export)) {
            return $updated_prefix . $blogid_to_export . '_'; 
        }
        
        return $updated_prefix;
    }        
}