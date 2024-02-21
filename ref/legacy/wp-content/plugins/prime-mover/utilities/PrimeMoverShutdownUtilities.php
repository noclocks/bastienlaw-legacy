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

use Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Shutdown Utilities
 * Helper functionality for anything that needs to be done on WordPress shutdown action related to error handling.
 *
 */
class PrimeMoverShutdownUtilities
{
    private $system_functions;
    
    /**
     * Constructor
     * @param PrimeMoverSystemFunctions $system_functions
     */
    public function __construct(
        PrimeMoverSystemFunctions $system_functions
    ) 
    {
        $this->system_functions = $system_functions;
    }

    /**
     * Get system authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     */
    public function getSystemAuthorization()
    {
        return $this->getSystemFunctions()->getSystemAuthorization();
    }
    
    /**
     * Delete error log before starting any processes
     * @param number $blog_id
     * @param boolean $process_initiated
     * @compatible 5.6
     */
    public function primeMoverDeleteErrorLog($blog_id = 0, $process_initiated = true)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if ( ! $blog_id ) {
            return;
        }
        if ($process_initiated) {
            return;
        }
        global $wp_filesystem;
        $error_log_file = $this->getSystemInitialization()->getErrorLogFile($blog_id);
        $error_log_path = $this->getPrimeMoverErrorPath($blog_id, $error_log_file);
        if ($wp_filesystem->exists($error_log_path)) {
            $this->getSystemFunctions()->primeMoverDoDelete($error_log_path);
        }
        $error_hash_option = $this->getSystemInitialization()->getErrorHashOption();
        $log_option = $error_hash_option . $blog_id;
        
        $mainsite_blog_id = $this->getSystemInitialization()->getMainSiteBlogId(true);
        $this->getSystemFunctions()->switchToBlog($mainsite_blog_id);
        delete_option($log_option);
        $this->getSystemFunctions()->restoreCurrentBlog();        
    }
    
    /**
     * Get parameters
     * @compatibility 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itStreamsErrorLog() 
     */
    public function getParameters()
    {
        $args = [
            'prime_mover_errornonce' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),            
            'prime_mover_blogid' => FILTER_SANITIZE_NUMBER_INT,
        ];
        return $this->getSystemInitialization()->getUserInput('get', $args, 'shutdown_error_log', '', 0, true);        
    }
    
    /**
     * get Error hash
     * @param int $blog_id
     * @param string $hash_option
     * @return boolean|mixed|boolean|NULL|array
     * @compatibility 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itStreamsErrorLog() 
     */
    public function getErrorHash( $blog_id = 0, $hash_option = '') 
    {
        if ( ! $blog_id ) {
            return false;
        }

        $option = $hash_option . $blog_id;
        return get_option($option); 
    }
    
    /**
     * Get error log URL
     * @param int $blogid_having_error
     * @return string
     * @compatibility 5.6
     */
    public function getDownloadErrorLogURL( $blogid_having_error = 0 )
    {      
        if ( ! $blogid_having_error ) {
            return '';
        }
        $error_nonce = $this->getSystemFunctions()->primeMoverCreateNonce('prime_mover_errornonce' . $blogid_having_error);      
        
        $errorlog_url = add_query_arg([
            'prime_mover_errornonce' => $error_nonce,            
            'prime_mover_blogid' => $blogid_having_error
        ], network_site_url());

        return $errorlog_url;
    }
    
    /**
     * 
     * @param bool $ret
     * @param int $blogid
     * @return boolean
     * @compatibility 5.6
     */
    public function primeMoverErrorLogExist( $ret = false, $blogid = 0, $errorfilename = '' ) 
    {
        global $wp_filesystem;        
        if ( ! $blogid ) {
            $blogid = $this->primeMoverGetProcessedID();
        }        
        $error_log_file = $this->getPrimeMoverErrorPath($blogid, $errorfilename);          
        if ( $wp_filesystem->exists($error_log_file)) {
            return true;
        }
        return $ret;
    }
    
    /**
     * Get Processed blog ID
     * @return number
     * @compatibility 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverShutdownUtilities::itGetsProcessedBlogId()
     */
    public function primeMoverGetProcessedID() 
    {
        $import_blogid = $this->getSystemInitialization()->getImportBlogID();
        $export_blogid = $this->getSystemInitialization()->getExportBlogID();
        
        if ($import_blogid) {
            return $import_blogid;
        }
        
        if ($export_blogid) {
            return $export_blogid;
        }

        return 0;
    }
    
    /**
     * Log error hash
     * @param int $blog_id
     * @param string $error_hash
     * @param string $hash_option
     * @compatibility 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverShutdownUtilities::itLogsErrorHash()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverShutdownUtilities::itDoesNotLogErrorHashIfBlogIdIsNotSet()
     */
    public function logErrorHash( $blog_id = 0, $error_hash = '', $hash_option = '' )
    {
        if ( ! $blog_id ) {
            return;
        }
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        $mainsite_blog_id = $this->getSystemInitialization()->getMainSiteBlogId(true);
        $this->getSystemFunctions()->switchToBlog($mainsite_blog_id);
        
        update_option( $hash_option . $blog_id, $error_hash );
        $this->getSystemFunctions()->restoreCurrentBlog();
    }
    
    /**
     * Get Processed path
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itDeletesPackageOnFatalError() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itDoesNotDeletePackageUserCreatedPackage()
     * @param number $blog_id
     * @return string[]|mixed[]
     * @compatibility 5.6
     */
    public function primeMoverGetProcessedPath($blog_id = 0) 
    {        
        $processed_paths = [];
        
        $processed_paths[] = $this->getSystemInitialization()->getTemporaryImportPackagePath($blog_id);
        $processed_paths[] = $this->getSystemInitialization()->getImportZipPath();
        $processed_paths[] = $this->getSystemInitialization()->getTemporaryExportPackagePath($blog_id);
        $processed_paths[] = $this->getSystemInitialization()->getExportZipPath($blog_id);
        
        return $processed_paths;
    }
    
    /**
     * Maybe delete maintenance mode
     * @param int $error_type
     * @return boolean
     * @compatibility 5.6
     */
    public function maybeDeleteMaintenanceFile( $error_type = 0 )
    {
        $ret = false;
        if( E_ERROR === $error_type && $this->getSystemFunctions()->isMaintenanceModeEnabled() ) {
            $ret = true;
        }
        return $ret;
    }

    /**
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     * @compatibility 5.6
     */
    public function getSystemFunctions()
    {
        return $this->system_functions;
    }
    
    /**
     * Get System Initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     */
    public function getSystemInitialization()
    {
        return $this->getSystemFunctions()->getSystemInitialization();
    }
    
    /**
     * Get Prime Mover error log path
     * @param int $blog_id
     * @param string $errorfilename
     * @return string
     * @compatibility 5.6
     */
    public function getPrimeMoverErrorPath( $blog_id = 0, $errorfilename = '' ) 
    {
        $blogexport_path = $this->getSystemFunctions()->getExportPathOfThisSubsite($blog_id);
        
        global $wp_filesystem;
        $created = false;
        if ( ! $wp_filesystem->exists($blogexport_path)) {
            $created = $wp_filesystem->mkdir($blogexport_path);            
        }      
        if ($created) {
            $this->getSystemInitialization()->camouflageFolders($blogexport_path);
        }        
        
        return $blogexport_path . $errorfilename;          
    }
    
    /**
     * Credits: http://php.net/manual/en/function.phpinfo.php
     * @return array
     * @compatibility 5.6
     */
    public function phpinfo2array() 
    {
        if (!function_exists('phpinfo')) {
            return [];
        }
        
        $entitiesToUtf8 = function($input) {
            // http://php.net/manual/en/function.html-entity-decode.php#104617
            return preg_replace_callback("/(&#[0-9]+;)/", function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $input);
        };
        $plainText = function($input) use ($entitiesToUtf8) {
            return trim(html_entity_decode($entitiesToUtf8(strip_tags($input))));
        };
        $titlePlainText = function($input) use ($plainText) {
            return '# '.$plainText($input);
        };
        
        ob_start();
        phpinfo(-1);
        
        $phpinfo = array('phpinfo' => array());
        
        // Strip everything after the <h1>Configuration</h1> tag (other h1's)
        if (!preg_match('#(.*<h1[^>]*>\s*Configuration.*)<h1#s', ob_get_clean(), $matches)) {
            return array();
        }
        
        $input = $matches[1];
        $matches = array();
        
        if(preg_match_all(
            '#(?:<h2.*?>(?:<a.*?>)?(.*?)(?:<\/a>)?<\/h2>)|'.
            '(?:<tr.*?><t[hd].*?>(.*?)\s*</t[hd]>(?:<t[hd].*?>(.*?)\s*</t[hd]>(?:<t[hd].*?>(.*?)\s*</t[hd]>)?)?</tr>)#s',
            $input,
            $matches,
            PREG_SET_ORDER
            )) {
                foreach ($matches as $match) {
                    $fn = strpos($match[0], '<th') === false ? $plainText : $titlePlainText;
                    if (strlen($match[1])) {
                        $phpinfo[$match[1]] = array();
                    } elseif (isset($match[3])) {
                        $keys1 = array_keys($phpinfo);
                        $phpinfo[end($keys1)][$fn($match[2])] = isset($match[4]) ? array($fn($match[3]), $fn($match[4])) : $fn($match[3]);
                    } else {
                        $keys1 = array_keys($phpinfo);
                        $phpinfo[end($keys1)][] = $fn($match[2]);
                    }                    
                }
            }
            
            return $phpinfo;
    }
}