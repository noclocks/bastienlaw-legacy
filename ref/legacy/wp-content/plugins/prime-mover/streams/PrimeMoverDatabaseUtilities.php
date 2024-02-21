<?php
namespace Codexonics\PrimeMoverFramework\streams;

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
use PDO;
use PDOException;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Database utility class
 * Helper class on database stream processes
 * Responsible for efficient/fast database related processing.
 *
 */
class PrimeMoverDatabaseUtilities
{     
    private $prime_mover;
    private $getmayberandomizedbprefix;
    private $dbprefix_of_site;    
    private $random_prefix;
    private $openssl_utilities;
    private $db_encryption_key;
    private $maybe_enc;
    
    /**
     * Construct
     * @param PrimeMover $prime_mover
     * @param array $utilities
     */
    public function __construct(PrimeMover $prime_mover, $utilities = [])
    {
        $this->prime_mover = $prime_mover;
        $this->getmayberandomizedbprefix = false;
        $this->dbprefix_of_site = '';
        $this->random_prefix = '';
        $this->openssl_utilities = $utilities['openssl_utilities'];
        $this->db_encryption_key = '';
        $this->maybe_enc = false;
    }
    
    /**
     * Get System check utilities
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverSystemCheckUtilities
     */
    public function getSystemCheckUtilities()
    {
        return $this->getPrimeMover()->getSystemChecks()->getSystemCheckUtilities();
    }
    
    /**
     * Get openSSL utilities
     */
    public function getOpenSSLUtilities()
    {
        return $this->openssl_utilities;
    }
    
    /**
     * Get Prime Mover object
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMover
     */
    public function getPrimeMover()
    {
        return $this->prime_mover;
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
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDatabaseUtilities::itDoesNotAddInitHooksWhenNotAuthorized()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDatabaseUtilities::itChecksIfHooksAreOutdated()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDatabaseUtilities::itAddsInitHooksWhenAuthorized()
     */
    public function initHooks()
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
       
        add_filter('prime_mover_before_mysqldump_php', [$this, 'initializeGetMaybeRandomizeDbPrefix'], 10, 1);
        add_filter('prime_mover_before_mysqldump_php', [$this, 'initializeGetDbPrefixOfSite'], 15, 1);        
        add_filter('prime_mover_before_mysqldump_php', [$this, 'initializeRandomPrefix'], 20, 1); 
        
        
        add_filter('prime_mover_before_mysqldump_php', [$this, 'initializeEncKey'], 25, 1);
        add_filter('prime_mover_before_mysqldump_php', [$this, 'initializeMaybeEnc'], 30, 1);
        add_filter('prime_mover_before_mysqldump_php', [$this, 'computePrimaryKeys'], 35, 2);
       
        add_filter('prime_mover_db_primary_keys_dump', [$this, 'dBPrimaryKeysDump'], 10, 3);            
        add_filter('prime_mover_filter_export_db_data', [$this, 'randomizeDbPrefix'], 5, 1);        
        add_filter('prime_mover_filter_export_db_data', [$this, 'updateUserRoleToRandomPrefix'], 7, 1);
        
        add_filter('prime_mover_filter_export_db_data', [$this, 'encryptData'], 10, 1);        
        add_filter('prime_mover_filter_ret_after_db_dump', [$this, 'cleanUpDbRetArrayDump'], 10, 1);
        add_filter('prime_mover_filter_db_port', [$this, 'initializePortForPDO'], 10, 2);
        
        add_action('prime_mover_before_doing_export', [$this, 'maybeFixedCorruptedUserMetaTable'], 99, 2);
        add_action('prime_mover_before_doing_import', [$this, 'maybeFixedCorruptedUserMetaTable'], 99, 2);   
        add_filter('prime_mover_inject_db_parameters', [$this, 'checkdBUserPrivilege'], 10, 2);
        
        add_filter('prime_mover_inject_db_parameters', [$this, 'getMySQLDumpBatchSize'], 15, 2);
        add_filter('prime_mover_filter_sql_query', [$this, 'maybeAdjustConstraints'], 10, 1);
        add_filter('prime_mover_filter_sql_query', [$this, 'maybeSlowDownQueryForTestingPurposes'], 99, 1);
        
        add_filter('prime_mover_filter_sql_query', [$this, 'maybeRunDbRestoreHeaders'], 1, 5);
        add_filter('prime_mover_inject_db_parameters', [$this, 'getRequireSecureTransport'], 20, 2);
        add_filter('prime_mover_filter_pdo_dsn', [$this, 'maybeFilterPdoDsn'], 10, 5);
        
        add_filter('prime_mover_filter_sql_query', [$this, 'maybeRemovePageCompression'], 100, 1);
        add_filter('prime_mover_filter_sql_query', [$this, 'maybeRemovePageCheckSum'], 200, 1);
    }
 
    /**
     * Maybe adjust page compressed parameter in CREATE TABLE call during site dB restore.
     * @param string $query
     * @return string
     */
    public function maybeRemovePageCompression($query = '')
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized() || !$query) {
            return $query;
        }
        
        if (0 !== strpos($query, "CREATE TABLE")) {
            return $query;
        }
        
        return str_replace("`PAGE_COMPRESSED`='ON'", '', $query);
    }
    
    /**
     * Maybe adjust page checksum parameter in CREATE TABLE call during site dB restore.
     * @param string $query
     * @return string
     */
    public function maybeRemovePageCheckSum($query = '')
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized() || !$query) {
            return $query;
        }
        
        if (0 !== strpos($query, "CREATE TABLE")) {
            return $query;
        }
        
        return str_replace("PAGE_CHECKSUM=1", '', $query);
    }
    
    /**
     * Filter PDO DSN depending on whether a non-port or port connection is supported
     * @param array $pdo_connection
     * @param array $pdo_connection_ported
     * @param number $port
     * @param array $ret
     * @param boolean $native_port_set
     * @return array
     */
    public function maybeFilterPdoDsn($pdo_connection = [], $pdo_connection_ported = [], $port = 3306, $ret = [], $native_port_set = false)
    {
        /**
         * @var string $no_port
         * @var string $ported
         * 
         * Parse into individual variables
         */
        list($mysql_server, $no_port) = $pdo_connection;
        list($mysql_server_ported, $ported) = $pdo_connection_ported;
       
        $hash_algo = $this->getSystemInitialization()->getFastHashingAlgo();
        $key_no_port = hash($hash_algo, $mysql_server);
        $key_port = hash($hash_algo, $mysql_server_ported);
        
        $require_secure_transport = $this->getSystemCheckUtilities()->maybeEnableRequireSecureTransportPdo([], $ret);
        $return = [
            $key_no_port => $pdo_connection,
            $key_port => $pdo_connection_ported
        ];
       
        $pdo_connection_mode = '';
        if (!empty($ret['pdo_connection_mode'])) {
            $pdo_connection_mode = $ret['pdo_connection_mode'];
        }
        
        if ('no_port_conn' === $pdo_connection_mode) {
            do_action('prime_mover_log_processed_events', "No port connection already established - skipping PDO instance check.", 0, 'export', __FUNCTION__, $this);
            return $pdo_connection;
        }
        
        if ('ported_conn' === $pdo_connection_mode) {
            do_action('prime_mover_log_processed_events', "Port connection already established - skipping PDO instance check.", 0, 'export', __FUNCTION__, $this);
            return $pdo_connection_ported;
        }
             
        $try = $mysql_server;
        $catch = $mysql_server_ported;
        
        if ($native_port_set) {
            $try = $mysql_server_ported;
            $catch = $mysql_server;
        }
        
        try {
            /**
             * PDO try first connection parameters.
             * @var \PDO $pdo_instance
             */
            if (empty($require_secure_transport)) {
                $pdo_instance = @new PDO($try, DB_USER, DB_PASSWORD);                
            } else {
                $pdo_instance = @new PDO($try, DB_USER, DB_PASSWORD, $require_secure_transport);
            }
            
            $pdo_instance = null;            
            
            $key = hash($hash_algo, $try);
            return $return[$key];
            
        } catch (PDOException $pe) {
            
            /**
             * PDO retry second connection parameters.
             * @var \PDO $pdo_instance
             */
            if (empty($require_secure_transport)) {
                $pdo_instance = @new PDO($catch, DB_USER, DB_PASSWORD);
            } else {
                $pdo_instance = @new PDO($catch, DB_USER, DB_PASSWORD, $require_secure_transport);
            }
            
            $pdo_instance = null;
            
            $key = hash($hash_algo, $catch);
            return $return[$key];
        }
        
        return $pdo_connection;
    }
    
    /**
     * Maybe restore db file headers
     * @param string $query
     * @param array $ret
     * @param number $q
     * @param wpdb $wpdb
     * @param boolean $is_retry
     * @return string
     */
    public function maybeRunDbRestoreHeaders($query = '', $ret = [], $q = 0,  wpdb $wpdb = null, $is_retry = false)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $query;
        }
        
        if (!$is_retry) {
            return $query;
        }
        
        if (0 !== $q) {
            return $query;            
        }
        
        if (!is_object($wpdb)) {
            return $query; 
        }
       
        foreach ($this->restoredBFileHeaders() as $header) {
            $wpdb->query($header);
        }
              
        return $query;
    }
    
    /**
     * Restore File headers on dB restore retry
     * @return string[]
     */
    protected function restoredBFileHeaders()
    {
        $headers = [
            "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;",
            "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;",
            "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;",
            "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;",
            "/*!40103 SET TIME_ZONE='+00:00' */;",
            "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;",
            "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;",
            "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;",
            "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;",
            "/*!40101 SET @saved_cs_client     = @@character_set_client */;",
            "/*!40101 SET character_set_client = utf8 */;"
        ];
        
        return $headers;
    }
   
    /**
     * Maybe slow query for testing purposes
     * @param string $query
     * @return string
     */
    public function maybeSlowDownQueryForTestingPurposes($query = '')
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $query;
        }
        if (!defined('PRIME_MOVER_TROUBLESHOOT_SQL_QUERY')) {
            return $query;
        }
        $latency = (int)PRIME_MOVER_TROUBLESHOOT_SQL_QUERY;
        if (!$latency) {
            return $query;
        }       
        
        $this->getSystemInitialization()->setProcessingDelay($latency, true);        
        return $query;
    }
    
    /**
     * Maybe adjust constraint during CREATE TABLE call during site dB restore.
     * @param string $query
     * @return string
     */
    public function maybeAdjustConstraints($query = '')
    {        
        if (!$this->getSystemAuthorization()->isUserAuthorized() || !$query) {
            return $query;
        }
        
        if (0 !== strpos($query, "CREATE TABLE")) {
            return $query;
        }
        
        if (false === strpos($query, "CONSTRAINT `")) {
            return $query;
        }
      
        $equivalence = $this->generateOriginalToUniqueConstraints($query);
        foreach ($equivalence as $search => $replace) {
            $query = str_replace($search, $replace, $query);
        }
             
        return $query;
    }

    /**
     * Generate original to new unique 
     * @param string $query
     * @return string[]
     */
    protected function generateOriginalToUniqueConstraints($query = '')
    {
        $array = explode(" ", $query);
        $prefix = "_primovr_";
        $keys = array_keys($array, "CONSTRAINT", true);
        $cons_k = [];
        $random = strtolower(wp_generate_password(5, false, false));
        
        foreach ($keys as $k) {
            $cons_k[] = $k + 1;
        }
        
        $constraints = [];
        foreach ($cons_k as $cons) {
            
            $constraint = trim($array[$cons], "`");
            $srch = "CONSTRAINT `{$constraint}`";
            if (false !== strpos($constraint, $prefix)) {
                $constraint = substr($constraint, 0, strpos($constraint, $prefix));
            }
            
            $identifier = $constraint . $prefix . $random;
            $char_len = strlen($identifier);
            if ($char_len >= 64) {
                $identifier = substr($identifier, 0, 63);
            }
            
            $rplc = "CONSTRAINT `{$identifier}`";
            $constraints[$srch] = $rplc;
        }
        
        return $constraints;
    }
    
    /**
     * Get MySQLDump batch size
     * @param array $ret
     * @param string $mode
     * @return array
     */
    public function getMySQLDumpBatchSize($ret = [], $mode = '')
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized() || !is_array($ret)) {
            return $ret;
        }
        
        $ret['prime_mover_db_dump_size'] = $this->getSystemInitialization()->getMySqlDumpPHPBatchSize();
        return $ret;
    }
    
    /**
     * Check if dB server requires secure transport
     * @param array $ret
     * @param string $mode
     * @return array
     */
    public function getRequireSecureTransport($ret = [], $mode = '')
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized() || !is_array($ret)) {
            return $ret;
        }
        
        $ret['prime_mover_req_secure_transport'] = $this->getSystemCheckUtilities()->getRequireSecureTransport();
        return $ret;
    }
    
    /**
     * Check dB user privileges
     * @param array $ret
     * @param string $mode
     * @return array
     */
    public function checkdBUserPrivilege($ret = [], $mode = '')
    {
        global $wpdb;
        if (!$this->getSystemAuthorization()->isUserAuthorized() || !is_array($ret)) {
            return $ret;
        }
        if (isset($ret['prime_mover_is_super_db_user'])) {
            return $ret;
        }
        
        $grants = $wpdb->get_results('SHOW GRANTS');
        if (!is_array($grants)) {
            $ret['prime_mover_is_super_db_user'] = false;
            return $ret;
        }
        
        $granted_all = false;        
        foreach ($grants as $grant) {
            if (!is_object($grant)) {
                continue;
            }
            $grant_array = get_object_vars($grant);
            if (!is_array($grant_array)) {
                continue;
            }
            foreach ($grant_array as $privileges) {
                if ($this->isUserPrivilegedToSetGlobal($privileges)) {
                    $granted_all = true;
                    break;
                }
            }
            if ($granted_all) {
                break;
            }               
        }
        $ret['prime_mover_is_super_db_user'] = $granted_all;       
        do_action('prime_mover_after_db_user_privilege_check', $granted_all, $mode, $ret);      
        
        return $ret;
    }

    /**
     * Check if current dB user has super privileges to set global variables
     * @param string $privileges
     * @return boolean
     */
    protected function isUserPrivilegedToSetGlobal($privileges = '')
    {
        $granted_all = false;
        $privileges = strtolower($privileges);
        if (false !== strpos($privileges, 'grant all privileges')) {
            $granted_all = true;
        }
        
        if(!$granted_all && false !== strpos($privileges, 'grant ') && false !== strpos($privileges, 'select,') && false !== strpos($privileges, 'super,')) {
            $granted_all = true;
        }
        return $granted_all;
    }
    /**
     * Maybe fix corrupted user meta table before any import/export process
     * @param number $blog_id
     * @param boolean $process_initiated
     */
    public function maybeFixedCorruptedUserMetaTable($blog_id = 0, $process_initiated = false)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if ($process_initiated) {
            return;
        }
        if (!$this->isUserMetaTableCorrupted()) {
            return;
        }
       
        $this->reEnableAutoIncrementUserMeta();       
    }
    
    /**
     * Re-enable auto-increment on user meta table if needed
     */
    protected function reEnableAutoIncrementUserMeta()
    {
        global $wpdb;
        $usermeta_table = $this->getSystemFunctions()->getUserMetaTableName();
        
        $umeta_id_max = $wpdb->get_var("SELECT max(umeta_id) FROM `{$usermeta_table}`");
        $umeta_id_max = (int)$umeta_id_max;       
        if (!$umeta_id_max) {
            return;
        }
        
        $new_max = $umeta_id_max + 20;        
        $res = $wpdb->query(
            $wpdb->prepare(
                "ALTER TABLE `{$usermeta_table}`
                 AUTO_INCREMENT = %d,
                 CHANGE COLUMN `umeta_id` `umeta_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT",
                 $new_max                
                )
        );
        
        if (false === $res) {
            $this->terminatedBProcess(sprintf(
                esc_html__("%s database table is corrupted. umeta_id field is not using AUTO_INCREMENT. Please check this with your WordPress administrator or hosting company.", 'prime-mover'), 
                $usermeta_table));
        }
    }
    
    /**
     * Terminate dB process
     * @param string $errors
     */
    protected function terminatedBProcess($errors = '')
    {
        do_action('prime_mover_shutdown_actions', [
            'type' => 1,
            'message' => $errors
        ]);    
    
        wp_die();
    }
    
    /**
     * Return TRUE when site user meta table is corrupted
     * @return boolean
     */
    protected function isUserMetaTableCorrupted()
    {
        global $wpdb;
        $usermeta_table = $this->getSystemFunctions()->getUserMetaTableName();
        $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$usermeta_table}` WHERE Extra = 'auto_increment'", ARRAY_A);
        
        return (is_array($columns) && empty($columns));        
    }
    
    /**
     * Initialize port for MySQLDump PHP PDO
     * @param number $port
     * @param array $ret
     * @return number
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDatabaseUtilities::itInitializesPortForPdo()
     */
    public function initializePortForPDO($port = 0, $ret = [])
    {        
        if (defined('PRIME_MOVER_FORCE_DB_PORT') && PRIME_MOVER_FORCE_DB_PORT) {
            $port = PRIME_MOVER_FORCE_DB_PORT;
            $port = (int)$port;
        }
        
        if ($port) {
            return $port;
        }
        
        if (!empty($ret['db_port'])) {
            return $ret['db_port'];
        }       
        
        global $wpdb;
        $result = $wpdb->get_results("SHOW VARIABLES WHERE Variable_name = 'port'", ARRAY_N);
        if (!is_array($result) ) {
            return $port;
        }
        if (empty($result[0])) {
            return $port;
        }
        $data = $result[0];
        if (!is_array($data)) {
            return $port;
        }
        if (empty($data[1]) ) {
            return $port;
        }        
        $computed_port = (int)$data[1];
        if ($computed_port) {
            return $computed_port;
        }
       
        return $port;
    }
    
    /**
     * Clean up large array after dB dump.
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDatabaseUtilities::itCleansUpDbRetArrayDump()
     * @param array $ret
     * @return array
     */
    public function cleanUpDbRetArrayDump($ret = [])
    {        
        if (!empty($ret['tbl_primary_keys'])) {
            unset($ret['tbl_primary_keys']);
        }        
        
        return $ret;
    }
    /**
     * Db primary keys
     * @param array $keys
     * @param array $ret
     * @param string $table
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDatabaseUtilities::itDumpsDbPrimaryKeys()
     */
    public function dBPrimaryKeysDump($keys = [], $ret = [], $table = '')
    {
        $primary_keys = [];
        $orderby_keys = [];
        if (!empty($ret['tbl_primary_keys'][$table]['primary_keys'])) {
            $primary_keys = $ret['tbl_primary_keys'][$table]['primary_keys'];
        } elseif (!empty($ret['tbl_primary_keys'][$table]['orderby'])) {
            $orderby_keys = $ret['tbl_primary_keys'][$table]['orderby'];
        }
        
        return [$primary_keys, $orderby_keys];        
    }
    
    /**
     * Compute primary keys for tables
     * Used for MySQLdump seek method implementation
     * @param array $ret
     * @param array $clean_tables
     * @return array
     */
    public function computePrimaryKeys($ret = [], $clean_tables = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        
        if (isset($ret['tbl_primary_keys'])) {
            return $ret;
        }        
        $primary_keys = [];
        foreach ($clean_tables as $table) {   
            $res = $this->queryPrimaryKeys($table);
            if (empty($res)) {
                $primary_keys = $this->processOrderByKeys($table, $primary_keys);                
            } else {
                $primary_keys = $this->processPrimaryKeys($res, $table, $primary_keys);
            }
        }
        if (!empty($primary_keys)) {
            $ret['tbl_primary_keys'] = $primary_keys;
        }        
        return $ret;
    }
    
    /**
     * Query primary keys given table
     * @param string $table
     * @return array|object|NULL
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDatabaseUtilities::itQueriesPrimaryKeys()
     */
    protected function queryPrimaryKeys($table = '')
    {
        global $wpdb;
        return $wpdb->get_results("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY' OR (Non_unique = 0 AND `Null` = '')", ARRAY_A);  
    }
    
    /**
     * Process order by keys
     * @param string $table
     * @param array $primary_keys
     */
    protected function processOrderByKeys($table = '', $primary_keys = [])
    {
        global $wpdb;
        $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table}`", ARRAY_A);  
        $orderbycolumn = '';
        foreach ($columns as $column) {
            if (!empty($column['Field']) && !empty($column['Null']) && 'NO' === $column['Null']) {                
                $orderbycolumn = $column['Field'];                
                $orderbycolumn = "`{$orderbycolumn}`";
                $primary_keys[$table] = ['orderby' => [$orderbycolumn]];
                
                break;
            }
        }
        
        return $primary_keys;
    }
    
    /**
     * Process primary keys
     * @param array $res
     * @param string $table
     * @param array $primary_keys
     * @return array
     */
    protected function processPrimaryKeys($res = [], $table = '', $primary_keys = [])
    {
        $keys = [];
        $uniquecol = [];        
        foreach ($res as $val) {
            
            $pri = false;
            $uniquecolumn = false;
            $column = '';
            
            if (!empty($val['Key_name']) && 'PRIMARY' === $val['Key_name']) {
                $pri = true;
            } else {
                $uniquecolumn = true;                
            }           
            if (isset($val['Column_name'])) {
                $column = $val['Column_name'];
            }
            if ($column && $pri) {               
                $keys[] = "`{$column}`";
                
            } elseif ($column && $uniquecolumn) {
                $uniquecol[] = "`{$column}`";
            }
        }
       
        if (empty($keys)) {
            $primary_keys[$table] = ['primary_keys' => $uniquecol];
        } else {
            $primary_keys[$table] = ['primary_keys' => $keys];
        }
        
        return $primary_keys;
    }
    
    /**
     * Initialize get maybe randomized db prefix
     * Hooked to `prime_mover_before_mysqldump_php` ACTION executed before PHP-MySQL dump process to set reusable object properties
     * @param array $ret
     * @return array
     */
    public function initializeGetMaybeRandomizeDbPrefix($ret = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        $this->getmayberandomizedbprefix = $this->getSystemInitialization()->getMaybeRandomizeDbPrefix($ret);
        return $ret;        
    }
 
    /**
     * Initialize get db prefix of site
     * Hooked to `prime_mover_before_mysqldump_php` ACTION executed before PHP-MySQL dump process to set reusable object properties
     * @param array $ret
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDatabaseUtilities::itInitializeGetDbPrefixOfSite()
     */
    public function initializeGetDbPrefixOfSite($ret = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        
        $export_blog_id = $this->getSystemInitialization()->getExportBlogID();        
        $this->dbprefix_of_site = $this->getSystemFunctions()->getDbPrefixOfSite($export_blog_id);        
        return $ret;
    }
    
    /**
     * Initialize random prefix
     * @param array $ret
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDatabaseUtilities::itInitializeRandomPrefix()
     * Hooked to `prime_mover_before_mysqldump_php` ACTION executed before PHP-MySQL dump process to set reusable object properties
     * @return array
     */
    public function initializeRandomPrefix($ret = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        $this->random_prefix = $this->getSystemInitialization()->getRandomPrefix($ret);        
        return $ret;
    }
    
    /**
     * Initialize enc key
     * @param array $ret
     * Hooked to `prime_mover_before_mysqldump_php` ACTION executed before PHP-MySQL dump process to set reusable object properties
     * @return array
     */
    public function initializeEncKey($ret = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        $this->db_encryption_key = $this->getSystemInitialization()->getDbEncryptionKey();        
        return $ret;
    }
    
    /**
     * 
     * Initialize maybe enc data
     * @param array $ret
     * Hooked to `prime_mover_before_mysqldump_php` ACTION executed before PHP-MySQL dump process to set reusable object properties
     * @return array
     */
    public function initializeMaybeEnc($ret = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $$ret;
        }
        
        $this->maybe_enc = $this->getSystemInitialization()->getMaybeEncryptExportData($ret);        
        return $ret;
    }
 
    /**
     * Randomize dB prefix on export
     * @param string $data
     * @return string
     * Hooked to `prime_mover_filter_export_db_data` filter
     */
    public function randomizeDbPrefix($data = '')
    {
        if (!$this->getMaybeRandomizedDbPrefix()) {
            return $data;
        }
        
        $current_prefix = $this->getDbPrefixOfSite();
        $updated_prefix = $this->getRandomPrefix();
        $random_prefix = "`$updated_prefix";
        
        return str_replace("`$current_prefix", $random_prefix, $data);
    }

    /**
     * Update user role to random prefix
     * @param string $data
     * @return string
     * @updated 1.0.6
     * Hooked to `prime_mover_filter_export_db_data` filter
     */
    public function updateUserRoleToRandomPrefix($data = '')
    {
        if (!$this->getMaybeRandomizedDbPrefix()) {
            return $data;
        }
        
        $current_prefix = $this->getDbPrefixOfSite();
        $updated_prefix = $this->getRandomPrefix();
        
        $current_role_option = $current_prefix . 'user_roles';
        $updated_role_option = $updated_prefix . 'user_roles';
        
        $search = "'$current_role_option',";
        $replace = "'$updated_role_option',";
        
        return str_replace($search, $replace, $data);
    }
    
    /**
     * Encrypt data
     * @param string $data
     * @return string
     * Hooked to `prime_mover_filter_export_db_data` filter
     */
    public function encryptData($data = '')
    {
        return $this->getOpenSSLUtilities()->encryptData($data, $this->getDbEncKey(), $this->getMaybeEncData());
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
     * Get maybe enc data
     * @return boolean
     */
    public function getMaybeEncData()
    {
        return $this->maybe_enc;
    }
    
    /**
     * Get db enc key
     * @return string
     */
    public function getDbEncKey()
    {
        return $this->db_encryption_key;
    }
    
    /**
     * Get db prefix of current site
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDatabaseUtilities::itInitializeGetDbPrefixOfSite()
     */
    public function getDbPrefixOfSite()
    {
        return $this->dbprefix_of_site;
    }
    
    /**
     * Get random prefix
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverDatabaseUtilities::itInitializeRandomPrefix()
     */
    public function getRandomPrefix()
    {
        return $this->random_prefix;
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
     * Get maybe randomized db prefix
     * @return boolean
     */
    public function getMaybeRandomizedDbPrefix()
    {
        return $this->getmayberandomizedbprefix;
    }    
}