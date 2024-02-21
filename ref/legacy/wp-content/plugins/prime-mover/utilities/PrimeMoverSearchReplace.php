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
use SplFixedArray;
use Error;

if (! defined('ABSPATH')) {
    exit;
}
/**
 *
/**
 * Extended Duplicator Search Replace Class
 * Standalone and removed reporting, logging methods.
 *
 * @package PrimeMoverFramework\utilities
 * @link https://github.com/lifeinthegrid/duplicator Duplicator GitHub Project
 * @link http://www.lifeinthegrid.com/duplicator/
 * @link http://www.snapcreek.com/duplicator/
 * @author Snap Creek
 * @author Codexonics
 * @copyright 2011-2017  SnapCreek LLC
 * @license GPLv2 or later

 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

 * SOURCE CONTRIBUTORS:
 * David Coveney of Interconnect IT Ltd
 * https://github.com/interconnectit/Search-Replace-DB/
 */
final class PrimeMoverSearchReplace extends DupxUpdateEngine
{   
    private static $left_off;
    private static $num_types = [      
        'bit',
        'tinyint',
        'smallint',
        'mediumint',
        'int',
        'integer',
        'bigint',
        'real',
        'double',
        'float',
        'decimal',
        'numeric'       
    ];
    
    private static $binary_types = [
        'binary'
        ];
        
    private static $blog_id = 1;
    
    /**
     * Get num types
     * @return string[]
     */
    private static function getNumTypes()
    {
        return self::$num_types;
    }
    
    /**
     * Get binary types
     * @return string[]
     */
    private static function getBinaryTypes()
    {
        return self::$binary_types;
    }
    
    /**
     * Set blog ID being processed
     * @param number $blog_id
     */
    private static function setBlogId($blog_id = 1)
    {
        self::$blog_id = $blog_id;
    }
    
    /**
     * Get blog ID being processed
     * @return number
     */
    private static function getBlogId()
    {
        return self::$blog_id;
    }
    
    /**
     * Log search replace header
     * @param array $ret
     * @param PrimeMoverImporter $importer
     * @param array $tables
     * @param array $list
     */
    private static function logSearchReplaceHeaderCall($ret, PrimeMoverImporter $importer, $tables = [], $list = [])
    {
        if (isset($ret['srch_rplc_original_tables_count'])) {
            self::logSearchReplaceHeader($importer, $ret, $list, $tables, "Search replace doing another retry processing");
        } else {
            $ret['srch_rplc_original_tables_count'] = count($tables);
            self::logSearchReplaceHeader($importer, $ret, $list, $tables, "Search replace doing process the first time, ret: ");
        }
    }
    
    /**
     * Get excluded column
     * @param array $excluded_columns
     * @return boolean[]|string[]|mixed[]
     */
    private static function getExcludedColumn($excluded_columns = [])
    {
        $table_with_excluded_column = '';
        $excluded_column = '';
        if (is_array($excluded_columns) && !empty($excluded_columns)) {
            $table_with_excluded_column = key($excluded_columns);
            $excluded_column = reset($excluded_columns);
        }
        
        $is_already_timeout = false;        
        return [$excluded_column, $table_with_excluded_column, $is_already_timeout];
    }
    
    /**
     * Get total rows processed
     * @param array $ret
     * @return number
     */
    private static function getTotalRowsProcessed($ret = [])
    {
        $total_rows_processed = 0;
        if (isset($ret['ongoing_srch_rplc_rows_processed'])) {
            $total_rows_processed = $ret['ongoing_srch_rplc_rows_processed'];
        } 
        
        return $total_rows_processed;
    }
    
    /**
     * Get columns definition
     * @param string $table
     * @param resource $dbh
     * @param string $table_with_excluded_column
     * @param string $excluded_column
     * @param array $ret
     * @return boolean[][]
     */
    private static function getColumnsDefinition($table, $dbh, $table_with_excluded_column = '', $excluded_column = '', $ret = [])
    {
        do_action('prime_mover_log_processed_events', "Doing search replace on $table" , $ret['blog_id'], 'import', 'load', 'PrimeMoverSearchReplace');
        $columns = [];        
        $primary_keys = [];
        
        if (!empty($ret['srch_rplc_table_definition'][$table]) && !empty($ret['srch_rplc_table_definition'][$table]['columns']) && !empty($ret['srch_rplc_table_definition'][$table]['pk'])) {            
            return [$ret['srch_rplc_table_definition'][$table]['columns'], $ret['srch_rplc_table_definition'][$table]['pk'], $ret];
        }
        $fields = mysqli_query($dbh, 'DESCRIBE '.$table);
        while ($column = mysqli_fetch_array($fields)) {
            $primary_key = false;
            if ('PRI' === $column['Key']) {
                $primary_key = true;
            }
            $columns[$column['Field']] = $primary_key;
            if ($primary_key) {
                if (self::isBinaryPrimaryKey($column)) {
                    $primary_keys[$column['Field']] = 'bin';
                } else {
                    $primary_keys[$column['Field']] = self::isNumericPrimaryKey($column);
                }                
            }
        }
        
        if ($table_with_excluded_column === $table && array_key_exists($excluded_column, $columns)) {
            unset($columns[$excluded_column]);
        }        
       
        $ret['srch_rplc_table_definition'][$table]['columns'] = $columns;
        $ret['srch_rplc_table_definition'][$table]['pk'] = $primary_keys;
        
        return [$columns, $primary_keys, $ret];
    }
 
    /**
     * Checks if primary key is binary in nature
     * @param array $colType
     * @return boolean
     */
    private static function isBinaryPrimaryKey($colType = [])
    {
        $datatype = self::parseDataType($colType);          
        return (in_array($datatype, self::getBinaryTypes()));
    }
    
    /**
     * Parse data type
     * @param array $colType
     * @return string
     */
    private static function parseDataType($colType = [])
    {
        $colParts = explode(" ", $colType['Type']);
        if ($fparen = strpos($colParts[0], "(")) {
            $datatype = substr($colParts[0], 0, $fparen);
        } else {
            $datatype = $colParts[0];
        }
        return $datatype;
    }
    
    /**
     * Checks if primary key is numeric in nature
     * @param array $colType
     * @return boolean
     */
    private static function isNumericPrimaryKey($colType = [])
    {        
        $datatype = self::parseDataType($colType);        
        return (in_array($datatype, self::getNumTypes()));
    }
  
    /**
     * Get total rows count for the specific table
     * @param array $ret
     * @param string $table
     * @param resource $dbh
     * @return int
     */
    private static function getRowsCount($ret, $table, $dbh)
    {
        if (isset($ret['main_search_replace_tables_rows_count'][$table])) {
            
            $rows = (int)$ret['main_search_replace_tables_rows_count'][$table];
            return $rows;
            
        } else {
            $row_count = mysqli_query($dbh, "SELECT COUNT(*) FROM `{$table}`");
            $rows_result = mysqli_fetch_array($row_count);
            @mysqli_free_result($row_count);
            
            return $rows_result[0]; 
        }       
    }
    
    /**
     * Get init page
     * @param array $ret
     * @param string $table
     * @param number $init_page
     * @return array
     */
    private static function getInitPage($ret = [], $table = '', $init_page = 0)
    {
        if (!empty($ret['ongoing_srch_rplc_page_to_resume']) ) {
            $init_page = (int)$ret['ongoing_srch_rplc_page_to_resume'];
            
            do_action('prime_mover_log_processed_events', "Resuming search replace on table $table at page $init_page" , $ret['blog_id'], 'import', 'load', 'PrimeMoverSearchReplace');
            unset($ret['ongoing_srch_rplc_page_to_resume']);
        }  
        
        return [$init_page, $ret];
    }
    
    /**
     * Get resume mode
     * @param array $ret
     * @param number $page
     * @param number $page_size
     * @param string $table
     * @return array
     */
    private static function getResumeMode($ret = [], $page = 0, $page_size = 0, $table = '')
    {
        do_action('prime_mover_log_processed_events', "Doing page transaction on table $table at page $page" , $ret['blog_id'], 'import', 'load', 'PrimeMoverSearchReplace');
        $start = $page * $page_size;  
        
        $resume_mode = false;
        if (!empty($ret['ongoing_srch_rplc_row_to_resume']) ) {            
            $start = $ret['ongoing_srch_rplc_row_to_resume'];
            unset($ret['ongoing_srch_rplc_row_to_resume']);
            $resume_mode = true;
        }
        
        return [$resume_mode, $ret, $start];
    }
    
    /**
     * Process columns
     * @param array $columns
     * @param array $row
     * @param boolean $is_unkeyed
     * @param resource $dbh
     * @param array $list
     * @param number $serial_err
     * @param array $upd_sql
     * @param array $where_sql
     * @param boolean $upd
     * @return string[]
     */
    private static function processColumns($columns, $row, $is_unkeyed, $dbh, $list = [], $serial_err = 0, $upd_sql = [], $where_sql = [], $upd = false)
    {
        try {
            foreach ($columns as $column => $primary_key) {
                $edited_data = $data_to_fix = $row[$column];
                $base64converted = false;
                $txt_found = false;
                
                if ($is_unkeyed && ! empty($data_to_fix)) {
                    $where_sql[] = $column.' = "'.mysqli_real_escape_string($dbh, $data_to_fix).'"';
                }
                
                if (!empty($row[$column]) && !is_numeric($row[$column]) && $primary_key != 1) {
                    if (base64_decode($row[$column], true)) {
                        $decoded = base64_decode($row[$column], true);
                        if (self::isSerialized($decoded)) {
                            $edited_data = $decoded;
                            $base64converted = true;
                        }
                    }
                    
                    foreach ($list as $item) {
                        if (strpos($edited_data, $item['search']) !== false) {
                            $txt_found = true;
                            break;
                        }
                    }
                    if (!$txt_found) {
                        continue;
                    }
                    
                    foreach ($list as $item) {
                        $edited_data = self::recursiveUnserializeReplace($item['search'], $item['replace'], $edited_data);
                    }
                    
                    $serial_check = self::fixSerialString($edited_data);
                    if ($serial_check['fixed']) {
                        $edited_data = $serial_check['data'];
                    } elseif ($serial_check['tried'] && !$serial_check['fixed']) {
                        $serial_err++;
                    }
                }
                
                if ($edited_data != $data_to_fix || $serial_err > 0) {
                    if ($base64converted) {
                        $edited_data = base64_encode($edited_data);
                    }
                    $upd_sql[] = $column.' = "'.mysqli_real_escape_string($dbh, $edited_data).'"';
                    $upd = true;
                }
                
                if ($primary_key) {
                    $where_sql[] = $column.' = "'.mysqli_real_escape_string($dbh, $data_to_fix).'"';
                }
            }   
            
            return [$upd, $where_sql, $upd_sql, $column];
            
        } catch (Error $error) {
            
            $error_msg = $error->getMessage();
            $blog_id = self::getBlogId();
            do_action('prime_mover_log_processed_events', "CAUGHT ERROR: {$error_msg}" , $blog_id, 'import', 'processColumns', 'PrimeMoverSearchReplace', true);
            
        }       
    }  
    
    /**
     * Update query
     * @param boolean $upd
     * @param array $where_sql
     * @param string $column
     * @param array $ret
     * @param string $table
     * @param array $upd_sql
     * @param resource $dbh
     */
    private static function runDbUpdate($upd, $where_sql, $column, $ret, $table, $upd_sql, $dbh)
    {
        $run_update_query = false;
        if ($upd && !empty($where_sql)) {
            do_action('prime_mover_log_processed_events', "Running this UPDATE query for column $column transaction: " , $ret['blog_id'], 'import', 'load', 'PrimeMoverSearchReplace', true);
            $sql = "UPDATE `{$table}` SET ".implode(', ', $upd_sql).' WHERE '.implode(' AND ', array_filter($where_sql));
            do_action('prime_mover_log_processed_events', "DONE QUERY: $sql" , $ret['blog_id'], 'import', 'load', 'PrimeMoverSearchReplace', true);  
            $run_update_query = true;
        }
        if ($run_update_query && apply_filters('prime_mover_process_srchrplc_query_update', true, $ret, $table, $where_sql)) {
            mysqli_query($dbh, $sql);
        } elseif (isset($sql)) {  
            do_action('prime_mover_log_processed_events', "EXCLUDED SRCH REPLACE UPDATE QUERY: $sql" , $ret['blog_id'], 'import', 'load', 'PrimeMoverSearchReplace'); 
        }
        
        self::testSearchDelay();
    }
    
    /**
     * Get paging parameters
     * @param number $row_count
     * @param array $tbl_primary_keys
     * @param array $ret
     * @param PrimeMoverImporter $importer
     * @param number $key
     * @return number[]
     */
    private static function getPagingParams($row_count, $tbl_primary_keys, $ret, PrimeMoverImporter $importer, $key = 0)
    {
        $batch_size = apply_filters('prime_mover_get_runtime_setting', $importer->getSystemInitialization()->getSearchReplaceBatchSizeSetting(), PRIME_MOVER_SRCH_RLC_BATCH_SIZE);
        if (!empty($tbl_primary_keys)) {
            $page_size = $offset = $batch_size;
        } else {
            $page_size = $batch_size;
            $offset = ($page_size + 1);
        }        
        
        $pages = ceil($row_count / $page_size);
        
        $init_page = 0;
        $current_row = 0;   
        $key = (int)$key;
        if (0 === $key) {
            do_action('prime_mover_log_processed_events', "Search replace batch size used: $batch_size" , $ret['blog_id'], 'import', 'getPagingParams', 'PrimeMoverSearchReplace');            
        }
        return [$page_size, $offset, $pages, $init_page, $current_row];
    }
    
    /**
     * Check if timeout is due.
     * @param number $start_time
     * @param array $ret
     * @return boolean
     */
    private static function isTimeOut($start_time = 0, $ret = [])
    {
        $elapsed = microtime(true) - $start_time;        
        $retry_timeout = apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, 'searchAndReplace');        
        if ($elapsed > $retry_timeout) {
            do_action('prime_mover_log_processed_events', "Retry search replace after $elapsed seconds elapsed time." , $ret['blog_id'], 'import', 'isTimeOut', 'PrimeMoverSearchReplace');
            do_action('prime_mover_log_processed_events', $ret , $ret['blog_id'], 'import', 'isTimeOut', 'PrimeMoverSearchReplace', true);
            return true;
        }        
        return false;
    }
    
    /**
     * Initialize row params
     * @param array $columns
     * @return array[]|boolean[]|number[]
     */
    private static function initializeRowParams($columns = [])
    {
        $upd_sql = [];
        $where_sql = [];
        $upd = false;
        
        $serial_err = 0;
        $is_unkeyed = !in_array(true, $columns);  
        
        return [$upd_sql, $where_sql, $upd, $serial_err, $is_unkeyed];
    }
    
    /**
     * Generate SELECT Sql
     * @param string $colList
     * @param string $table
     * @param number $start
     * @param number $offset
     * @param array $ret
     * @param array $left_off
     * @param array $tbl_primary_keys
     * @param array $primary_keys
     * @param resource $dbh
     * @return string
     */
    private static function generateSelectSql($colList, $table, $start, $offset, $ret, $left_off, $tbl_primary_keys, $primary_keys, $dbh)
    {               
        if (empty($tbl_primary_keys)) {  
            $sql = sprintf("SELECT {$colList} FROM `%s` LIMIT %d, %d", $table, $start, $offset);            
            return $sql;
        } 
        
        $where = self::generateWhereToSeekCondition($left_off, $table, $primary_keys, $dbh);
        $orderby = self::generateOrderByClause($tbl_primary_keys);        
        $sql = @sprintf("SELECT {$colList} FROM `%s` {$where} {$orderby} LIMIT %d", $table, $offset);    
        
        return $sql;
    }

    /**
     * Safe column names
     * @param string $pri_key
     * @return string
     */
    private static function safeColumnNames($pri_key = '')
    {        
        return "`{$pri_key}`";
    }
    
    /**
     * Generate where to seek condition
     * @param array $left_off
     * @param string $table
     * @param array $primary_key_definiton
     * @param resource $dbh
     * @return string
     */
    private static function generateWhereToSeekCondition($left_off, $table, $primary_key_definiton, $dbh)
    {
        if (!isset($left_off[$table])) {
            return '';
        }
        
        $pk_array = $left_off[$table];      
        $callable = self::class . '::safeColumnNames';
        $primary_keys = array_map($callable, array_keys($pk_array));
        $left_off_values = self::escapeLeftOffValues($pk_array, $primary_key_definiton, $dbh);  
        
        $condition = "(" . implode(",", $primary_keys) . ")" . " > " . "(" . implode(",", $left_off_values) . ")";
        return " WHERE {$condition}";
    }
    
    /**
     * Escape left off values
     * @param array $pk_array
     * @param array $primary_key_definiton
     * @param resource $dbh
     * @return number[]|string[]
     */
    private static function escapeLeftOffValues($pk_array, $primary_key_definiton, $dbh)
    {
        $escaped_leftoff = [];
        foreach ($pk_array as $pk => $pk_val) {
            $int = false;
            if (isset($primary_key_definiton[$pk]) && true === $primary_key_definiton[$pk]) {
                $int = true;
            }
            
            if ($int) {
                $escaped_leftoff[] = (int)$pk_val;
            } else {
                $escaped_leftoff[] = self::quoteAndEscapeLeftOff($dbh, $pk_val);
            }
        }
        
        return $escaped_leftoff;
    }
    
    /**
     * Quote and escape left off string
     * @param resource $dbh
     * @param string $value
     * @return string
     */
    private static function quoteAndEscapeLeftOff($dbh, $value = '')
    {
        if ($value) {
            $value = str_replace('%', '%%', $value);
        }
        
        $value = mysqli_real_escape_string($dbh, $value);
        return "'" . $value . "'";
    }
    
    /**
     * Order by clause
     * @param array $tbl_primary_keys
     * @return string
     */
    private static function generateOrderByClause($tbl_primary_keys = [])
    {
        if (empty($tbl_primary_keys) || !is_array($tbl_primary_keys)) {
            return '';
        }
        $callable = self::class . '::safeColumnNames';
        $tbl_primary_keys = array_map($callable, $tbl_primary_keys);
        $orderby = implode(",", $tbl_primary_keys) . " ASC";
        
        return " ORDER BY {$orderby}";
    }
    
    /**
     * Get left off to resume
     * @param boolean $resume_mode
     * @param array $ret
     * @param array $tbl_primary_keys
     * @param string $table
     * @param array $primary_keys
     * @return array[]
     */
    private static function getLeftOffToResume($resume_mode = false, $ret = [], $tbl_primary_keys = [], $table = '', $primary_keys = [])
    {
        $left_off = [];
        if (empty($tbl_primary_keys)) {
            return [$left_off, $ret];
        }
        
        if ($resume_mode && isset($ret['ongoing_src_rplc_leftoff'])) {
            $left_off = $ret['ongoing_src_rplc_leftoff'];
            $left_off = self::maybeDecodeBinaryLeftOff($left_off, $table, $primary_keys);
            unset($ret['ongoing_src_rplc_leftoff']);            
            
        } else {
            $left_off = self::getLeftOff();            
        }
        
        if (!isset($left_off[$table])) {
            return [[], $ret];
        }
        
        self::setLeftOff(null);          
        return [$left_off, $ret];
    }  
    
    /**
     * Maybe decode binary left off data
     * @param array $left_off
     * @param string $table
     * @param array $primary_keys
     * @return array
     */
    private static function maybeDecodeBinaryLeftOff($left_off = [], $table = '', $primary_keys = [])
    {
        if (!isset($left_off[$table])) {
            return $left_off;
        }
        
        $left_off_data = $left_off[$table];        
        foreach ($left_off_data as $field => $value) {
            if (empty($primary_keys[$field])) {
               continue;
            }
            if ('bin' !== $primary_keys[$field]) {
                continue;
            }
            
            $left_off[$table][$field] = base64_decode($value);
        }
        
        return $left_off;        
    }
    
    /**
     * Do search and replace
     * @param resource $dbh
     * @param array $list
     * @param array $tables
     * @param boolean $fullsearch
     * @param PrimeMoverImporter $importer
     * @param array $excluded_columns
     * @param number $start_time
     * @param array $ret
     * @return void|string|array
     * @codeCoverageIgnore
     */
    public static function load($dbh, $list = [], $tables = [], $fullsearch = false, PrimeMoverImporter $importer = null, $excluded_columns = [], $start_time = 0, $ret = [])
    {
        if (!$importer->getSystemAuthorization()->isUserAuthorized() ) {
            return;
        }      
               
        self::logSearchReplaceHeaderCall($ret, $importer, $tables, $list);
        list($excluded_column, $table_with_excluded_column, $is_already_timeout) = self::getExcludedColumn($excluded_columns);
        $total_rows_processed = self::getTotalRowsProcessed($ret);        
        
        if (is_array($tables) && !empty($tables)) {
            foreach ($tables as $key => $table) {  
                list($columns, $primary_keys, $ret) = self::getColumnsDefinition($table, $dbh, $table_with_excluded_column, $excluded_column, $ret);
                $tbl_primary_keys = array_keys($primary_keys);
                $row_count = self::getRowsCount($ret, $table, $dbh);                
                if (0 === $row_count) {
                    unset($tables[$key]);                      
                    do_action('prime_mover_log_processed_events', "Table $table is skipped" , $ret['blog_id'], 'import', 'load', 'PrimeMoverSearchReplace');
                    continue;
                }
                
                list($page_size, $offset, $pages, $init_page, $current_row) = self::getPagingParams($row_count, $tbl_primary_keys, $ret, $importer, $key);   
                list($init_page, $ret) = self::getInitPage($ret, $table, $init_page);                    
                
                for ($page = $init_page; $page < $pages; $page++) {                                      
                    list($resume_mode, $ret, $start) = self::getResumeMode($ret, $page, $page_size, $table);                    
                    $current_row = self::getAdjustedCurrentRow($resume_mode, $start, $current_row);
                    
                    list($left_off, $ret) = self::getLeftOffToResume($resume_mode, $ret, $tbl_primary_keys, $table, $primary_keys);
                    $sql = self::generateSelectSql('*', $table, $start, $offset, $ret, $left_off, $tbl_primary_keys, $primary_keys, $dbh); 
                    if (empty($sql)) {
                        continue;
                    }
                    $data = self::getRowsDataFromDb($ret, $sql, $dbh);
                                      
                    list($datacount, $monitornumrows) = self::initializeTotalDataCount($data);
                    while ($row = mysqli_fetch_array($data)) { 
                        
                        list($upd_sql, $where_sql, $upd, $serial_err, $is_unkeyed) = self::initializeRowParams($columns);                  
                        list($upd, $where_sql, $upd_sql, $column) = self::processColumns($columns, $row, $is_unkeyed, $dbh, $list, $serial_err, $upd_sql, $where_sql, $upd);                        
                        self::runDbUpdate($upd, $where_sql, $column, $ret, $table, $upd_sql, $dbh);                         
                        list($current_row, $total_rows_processed, $monitornumrows) = self::monitorRowsProgress($current_row, $total_rows_processed, $monitornumrows);   
                        
                        if (self::isTimeOut($start_time, $ret)) {
                            self::initializeLeftOff($row, $table, $tbl_primary_keys);
                            $is_already_timeout = true;
                            break;
                        } elseif ($monitornumrows === $datacount) {
                            self::initializeLeftOff($row, $table, $tbl_primary_keys);
                        }
                    }
                    
                    @mysqli_free_result($data);                     
                    if ($is_already_timeout) {
                        return self::doRetrySearchReplace($page, $pages, $tables, $key, $importer, $ret, $total_rows_processed, $current_row, $primary_keys, $table);       
                    }              
                }                 
                list($tables, $ret) = self::unSetTables($tables, $key, $table, $ret);
            }            
        }        
                
        return self::markSearchReplaceComplete($ret, $importer);
    }
    
    /**
     * Initialize total data count
     * @param mixed $data
     * @return number[]
     */
    private static function initializeTotalDataCount($data = null)
    {
        $datacount = mysqli_num_rows($data);
        return [$datacount, 0];
    }
    
    /**
     * Get adjusted current row
     * @param boolean $resume_mode
     * @param number $start
     * @param number $current_row
     * @return number
     */
    private static function getAdjustedCurrentRow($resume_mode = false, $start = 0, $current_row = 0)
    {
        if ($resume_mode && $start) {
            $current_row = $start - 1;
        } 
        return $current_row;
    }
    
    /**
     * Monitor rows progress
     * @param number $current_row
     * @param number $total_rows_processed
     * @param number $monitornumrows
     * @return number[]
     */
    private static function monitorRowsProgress($current_row = 0, $total_rows_processed = 0, $monitornumrows = 0)
    {
        $current_row++;
        $total_rows_processed++;
        $monitornumrows++;
        
        return [$current_row, $total_rows_processed, $monitornumrows];
    }
    
    /**
     * Unset complete table in tables list
     * @param array $tables
     * @param string $key
     * @param string $table
     * @param array $ret
     * @return []
     */
    private static function unSetTables($tables = [], $key = '', $table = '', $ret = [])
    {
        $unset = false;
        if (isset($tables[$key])) {
            unset($tables[$key]);
            self::setLeftOff(null);
            $unset = true;
        }  
        
        if ($unset && isset($ret['srch_rplc_table_definition'][$table]['columns'])) {
            unset($ret['srch_rplc_table_definition'][$table]['columns']);
        }
        
        if ($unset && isset($ret['srch_rplc_table_definition'][$table]['pk'])) {
            unset($ret['srch_rplc_table_definition'][$table]['pk']);
        }   
        
        return [$tables, $ret];
    }
    
    /**
     * Get rows data from database using optimized SELECT (seek method) for best performances
     * @param array $ret
     * @param string $sql
     * @param resource $dbh
     * @return mixed
     */
    private static function getRowsDataFromDb($ret, $sql, $dbh)
    {
        do_action('prime_mover_log_processed_events', "Running this select query for page transaction: " , $ret['blog_id'], 'import', 'load', 'PrimeMoverSearchReplace');
        do_action('prime_mover_log_processed_events', $sql , $ret['blog_id'], 'import', 'load', 'PrimeMoverSearchReplace');
        
        return mysqli_query($dbh, $sql);  
    }
    
    /**
     * Initialize left off
     * @param array $row
     * @param string $table
     * @param array $tbl_primary_keys
     */
    private static function initializeLeftOff($row = [], $table = '', $tbl_primary_keys = [])
    {
        if (empty($tbl_primary_keys)) {
            return;
        }
        if (!$row) {
            return;
        }
        
        $left_off = [];
        $primary_key_values = [];       
        foreach ($tbl_primary_keys as $key) {
            if (isset($row[$key])) {
                $primary_key_values[$key] = $row[$key];
            }
        }       
        
        $left_off[$table] = $primary_key_values;        
        self::setLeftOff($left_off);        
    }
    
    /**
     * Set left off
     * @param $left_off
     */
    private static function setLeftOff($left_off = null)
    {
        self::$left_off = $left_off;
    }
    
    /**
     * Get left off
     * @return string
     */
    private static function getLeftOff()
    {
        return self::$left_off;
    }
    
    /**
     * Do retry search replace helper
     * @param number $page
     * @param number $pages
     * @param array $tables
     * @param number $key
     * @param PrimeMoverImporter $importer
     * @param array $ret
     * @param number $total_rows_processed
     * @param number $current_row
     * @param array $primary_keys
     * @param string $table
     * @return string
     * @codeCoverageIgnore
     */
    private static function doRetrySearchReplace($page, $pages, $tables, $key, PrimeMoverImporter $importer, $ret = [], $total_rows_processed = 0, $current_row = 0, $primary_keys = [], $table = '')
    {        
        $ret['ongoing_srch_rplc_page_to_resume'] = $page;        
        if ($current_row) {
            $ret['ongoing_srch_rplc_row_to_resume'] = $current_row;
        } 
       
        $ret['ongoing_srch_rplc_remaining_tables'] = $tables;
        $percent_string = esc_html__('Starting...', 'prime-mover');
       
        $total_rows_database = $ret['main_search_replace_total_rows_count'];        
        $percent = 0;
        
        if ($total_rows_processed < $total_rows_database) {
            $percent = round(($total_rows_processed /$total_rows_database) * 100, 2);
        }
       
        if ($total_rows_processed > $total_rows_database) {
            $percent = 99.5;
        }
       
        if ($percent) {
            $percent_string = $percent . '%' . ' '. esc_html__('done', 'prime-mover');
        }
        
        $ret['ongoing_srch_rplc_percent'] = $percent_string;
        $ret['ongoing_srch_rplc_rows_processed'] = $total_rows_processed;       
        
        $left_off = self::getLeftOff();
        $left_off = self::maybeEncodeBinaryLeftOff($left_off, $primary_keys, $table);
        if (is_array($left_off) && !empty($left_off)) {
            $ret['ongoing_src_rplc_leftoff'] = $left_off; 
        }
        
        return $ret;
    }
    
    /**
     * Maybe encode binary left off data
     * @param array $left_off
     * @param array $primary_keys
     * @param string $table
     * @return array
     */
    private static function maybeEncodeBinaryLeftOff($left_off = [], $primary_keys = [] , $table = '')
    {
        if (!is_array($left_off)) {
            return $left_off;
        }
        
        if (empty($left_off)) {
            return $left_off;
        }
        
        if (!isset($left_off[$table])) {
            return $left_off;
        }
        
        $left_off_val = $left_off[$table];
        if (!is_array($left_off_val)) {
            return $left_off;
        }
        if (empty($left_off_val)) {
            return $left_off;
        }
        
        foreach ($left_off_val as $field => $value) {
            if (empty($primary_keys[$field])) {
                continue;
            }
            
            $field_type = $primary_keys[$field];
            if ('bin' !== $field_type) {
                continue;
            }
            
            $encoded = base64_encode($value);
            $left_off[$table][$field] = $encoded;
        }
        
        return $left_off;
    }
    
    /**
     * Test search delay
     * @codeCoverageIgnore
     */
    private static function testSearchDelay()
    {
        if (defined('PRIME_MOVER_DELAY_SRCH_REPLACE') && PRIME_MOVER_DELAY_SRCH_REPLACE) {
            usleep(PRIME_MOVER_DELAY_SRCH_REPLACE);
        }
    }
    
    /**
     * Log First time search and replace
     * @param PrimeMoverImporter $importer
     * @param array $ret
     * @param array $list
     * @param array $tables
     * @param string $text
     */
    private static function logSearchReplaceHeader(PrimeMoverImporter $importer, $ret = [], $list = [], $tables = [], $text = '')
    {        
        self::setBlogId($ret['blog_id']);
        do_action('prime_mover_log_processed_events', $text , $ret['blog_id'], 'import', 'logSearchReplaceHeader', 'PrimeMoverSearchReplace', true);
        do_action('prime_mover_log_processed_events', $ret , $ret['blog_id'], 'import', 'logSearchReplaceHeader', 'PrimeMoverSearchReplace', true);
        do_action('prime_mover_log_processed_events', "Search and replace master list : " , $ret['blog_id'], 'import', 'logSearchReplaceHeader', 'PrimeMoverSearchReplace', true);
        do_action('prime_mover_log_processed_events', $list , $ret['blog_id'], 'import', 'logSearchReplaceHeader', 'PrimeMoverSearchReplace', true);
        do_action('prime_mover_log_processed_events', "Search replace tables: " , $ret['blog_id'], 'import', 'logSearchReplaceHeader', 'PrimeMoverSearchReplace', true);
        do_action('prime_mover_log_processed_events',$tables, $ret['blog_id'], 'import', 'logSearchReplaceHeader', 'PrimeMoverSearchReplace', true);
    }
    
    /**
     * Marked search replace complete and cleanup
     * @codeCoverageIgnore
     * @param array $ret
     * @param PrimeMoverImporter $importer
     * @return array
     */
    protected static function markSearchReplaceComplete($ret, PrimeMoverImporter $importer) {
        $ret['srch_rplc_completed'] = true;        
        do_action('prime_mover_log_processed_events',"All search replace done !", $ret['blog_id'], 'import', 'markSearchReplaceComplete', 'PrimeMoverSearchReplace');
        
        if (isset($ret['srch_rplc_original_tables_count'])) {
            unset($ret['srch_rplc_original_tables_count']);
        }
        if (isset($ret['ongoing_srch_rplc_page_to_resume'])) {
            unset($ret['ongoing_srch_rplc_page_to_resume']);
        }
        if (isset($ret['ongoing_srch_rplc_remaining_tables'])) {
            unset($ret['ongoing_srch_rplc_remaining_tables']);   
        }
        if (isset($ret['ongoing_srch_rplc_percent'])) {
            unset($ret['ongoing_srch_rplc_percent']);
        }
        if (isset($ret['ongoing_src_rplc_leftoff'])) {
            unset($ret['ongoing_src_rplc_leftoff']);
        }
        if (isset($ret['srch_rplc_table_definition'])) {
            unset($ret['srch_rplc_table_definition']);
        }
        if (isset($ret['main_search_replace_tables_rows_count'])) {
            unset($ret['srch_rplc_table_definition']);
        }
        return $ret;
    } 
        
    /**
     * Take a serialized array and unserialized it replacing elements and
     * unserializing any subordinate arrays and performing the replace.
     * @codeCoverageIgnore
     * @param string $from       String we're looking to replace.
     * @param string $to         What we want it to be replaced with
     * @param array  $data       Used to pass any subordinate arrays back to in.
     * @param bool   $serialised Does the array passed via $data need serializing.
     *
     * @return array	The original array with all elements replaced as needed.
     */
    public static function recursiveUnserializeReplace($from = '', $to = '', $data = '', $serialised = false)
    {
        try {
            if (is_string($data) && ($unserialized = @unserialize($data)) !== false) {
                $data = self::recursiveUnserializeReplace($from, $to, $unserialized, true);
            } elseif (is_array($data)) {
                $_tmp = array();
                foreach ($data as $key => $value) {
                    $_tmp[$key] = self::recursiveUnserializeReplace($from, $to, $value, false);
                }
                $data = $_tmp;
                unset($_tmp);
               
            } elseif (is_object($data) && ! is_a($data, '__PHP_Incomplete_Class') ) {
                $_tmp	 = $data;
                $props	 = get_object_vars($data);
                foreach ($props as $key => $value) {
                    if (is_string($key)) {
                        $key = trim($key);
                    }
                    if ($_tmp instanceof SplFixedArray) {
                        $_tmp[$key] = self::recursiveUnserializeReplace($from, $to, $value, false);
                    } else {
                        $_tmp->$key = self::recursiveUnserializeReplace($from, $to, $value, false);
                    }
                }
                $data = $_tmp;
                unset($_tmp);
            } else {
                if (is_string($data)) {
                    $data = str_replace($from, $to, $data);
                }
            }
            
            if ($serialised) return serialize($data);
            
        } catch (Error $error) {
            $error_msg = $error->getMessage();  
            $blog_id = self::getBlogId();
            do_action('prime_mover_log_processed_events', "CAUGHT ERROR: {$error_msg} on data" , $blog_id, 'import', 'recursiveUnserializeReplace', 'PrimeMoverSearchReplace', true);
            
        }
        
        return $data;               
    }    
}
