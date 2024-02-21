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

use SplFixedArray;
use Exception;

if (! defined('ABSPATH')) {
    exit;
}
/**
 * Search and Replace Class - from Duplicator Plugin
 *
 * @package Duplicator
 * @link https://github.com/lifeinthegrid/duplicator Duplicator GitHub Project
 * @link http://www.lifeinthegrid.com/duplicator/
 * @link http://www.snapcreek.com/duplicator/
 * @author Snap Creek
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
class DupxUpdateEngine
{
	/**
	 * Returns only the text type columns of a table ignoring all numeric types
	 * @codeCoverageIgnore
	 * @param mixed    $dbh       A valid database link handle
	 * @param string $table     A valid table name
	 *
	 * @return array All the column names of a table
	 */
	public static function getTextColumns($dbh, $table)
	{
		$type_where = "type NOT LIKE 'tinyint%' AND ";
		$type_where .= "type NOT LIKE 'smallint%' AND ";
		$type_where .= "type NOT LIKE 'mediumint%' AND ";
		$type_where .= "type NOT LIKE 'int%' AND ";
		$type_where .= "type NOT LIKE 'bigint%' AND ";
		$type_where .= "type NOT LIKE 'float%' AND ";
		$type_where .= "type NOT LIKE 'double%' AND ";
		$type_where .= "type NOT LIKE 'decimal%' AND ";
		$type_where .= "type NOT LIKE 'numeric%' AND ";
		$type_where .= "type NOT LIKE 'date%' AND ";
		$type_where .= "type NOT LIKE 'time%' AND ";
		$type_where .= "type NOT LIKE 'year%' ";

		$result = mysqli_query($dbh, "SHOW COLUMNS FROM `{$table}` WHERE {$type_where}");
		if (!$result) {
			return null;
		}
		$fields = array();
		if (mysqli_num_rows($result) > 0) {
			while ($row = mysqli_fetch_assoc($result)) {
				$fields[] = $row['Field'];
			}
		}

		//Return Primary which is needed for index lookup
		//$result = mysqli_query($dbh, "SHOW INDEX FROM `{$table}` WHERE KEY_NAME LIKE '%PRIMARY%'"); 1.1.15 updated
		$result = mysqli_query($dbh, "SHOW INDEX FROM `{$table}`");
		if (mysqli_num_rows($result) > 0) {
			while ($row = mysqli_fetch_assoc($result)) {
				$fields[] = $row['Column_name'];
			}
		}

		return (count($fields) > 0) ? $fields : null;
	}

	/**
	 * Begins the processing for replace logic
	 * @codeCoverageIgnore
	 * @param mixed  $dbh			The db connection object
	 * @param array  $list			Key value pair of 'search' and 'replace' arrays
	 * @param array  $tables		The tables we want to look at
	 * @param array  $fullsearch    Search every column regardless of its data type
	 *
	 * @return array Collection of information gathered during the run.
	 */
	public static function load($dbh, $list = array(), $tables = array(), $fullsearch = false)
	{
		$report = array(
			'scan_tables' => 0,
			'scan_rows' => 0,
			'scan_cells' => 0,
			'updt_tables' => 0,
			'updt_rows' => 0,
			'updt_cells' => 0,
			'errsql' => array(),
			'errser' => array(),
			'errkey' => array(),
			'errsql_sum' => 0,
			'errser_sum' => 0,
			'errkey_sum' => 0,
			'time' => '',
			'err_all' => 0
		);

		function set_sql_column_safe(&$str) {
			$str = "`$str`";
		}
        
		$profile_start = microtime(true);
		if (is_array($tables) && !empty($tables)) {

			foreach ($tables as $table) {
				$report['scan_tables'] ++;
				$columns = array();

				// Get a list of columns in this table
				$fields	 = mysqli_query($dbh, 'DESCRIBE '.$table);
				while ($column	 = mysqli_fetch_array($fields)) {
					$columns[$column['Field']] = $column['Key'] == 'PRI' ? true : false;
				}

				// Count the number of rows we have in the table if large we'll split into blocks
				$row_count	 = mysqli_query($dbh, "SELECT COUNT(*) FROM `{$table}`");
				$rows_result = mysqli_fetch_array($row_count);
				@mysqli_free_result($row_count);
				$row_count	 = $rows_result[0];
				if ($row_count == 0) {
					continue;
				}

				$page_size	 = 25000;
				$offset		 = ($page_size + 1);
				$pages		 = ceil($row_count / $page_size);

				// Grab the columns of the table.  Only grab text based columns because
				// they are the only data types that should allow any type of search/replace logic
				$colList = '*';				
				if (!$fullsearch) {
					$colList = self::getTextColumns($dbh, $table);
					if ($colList != null && is_array($colList)) {
						array_walk($colList, set_sql_column_safe);
						$colList = implode(',', $colList);
					}					
				}

				if (empty($colList)) {
					continue;
				}

				//Paged Records
				for ($page = 0; $page < $pages; $page++) {
					$current_row = 0;
					$start		 = $page * $page_size;					

					if (self::hostCanSupportMySQLiPrepareFunc()) {
					    $stmt = mysqli_prepare($dbh, "SELECT {$colList} FROM `{$table}` LIMIT ?, ?");
					    mysqli_stmt_bind_param($stmt, 'ii', $start, $offset);
					    mysqli_stmt_execute($stmt);
					    $data = mysqli_stmt_get_result($stmt);	
					} else {
					    $sql = sprintf("SELECT {$colList} FROM `%s` LIMIT %d, %d", $table, $start, $offset);
					    $data = mysqli_query($dbh, $sql);
					}				
					
					if (!$data) $report['errsql'][] = mysqli_error($dbh);		

					//Loops every row
					while ($row = mysqli_fetch_array($data)) {
						$report['scan_rows'] ++;
						$current_row++;
						$upd_col	 = array();
						$upd_sql	 = array();
						$where_sql	 = array();
						$upd		 = false;
						$serial_err	 = 0;
                        $is_unkeyed = !in_array(true,$columns);

						//Loops every cell
						foreach ($columns as $column => $primary_key) {
							$report['scan_cells'] ++;
							$edited_data		= $data_to_fix = $row[$column];
							$base64converted	= false;
							$txt_found			= false;

                            //Unkeyed table code
                            //Added this here to add all columns to $where_sql
                            //The if statement with $txt_found would skip additional columns
                            if($is_unkeyed && ! empty($data_to_fix)) {
                                $where_sql[] = $column.' = "'.mysqli_real_escape_string($dbh, $data_to_fix).'"';
                            }

							//Only replacing string values
							if (!empty($row[$column]) && !is_numeric($row[$column]) && $primary_key != 1) {
								//Base 64 detection
								if (base64_decode($row[$column], true)) {
									$decoded = base64_decode($row[$column], true);
									if (self::isSerialized($decoded)) {
										$edited_data	 = $decoded;
										$base64converted	 = true;
									}
								}

								//Skip table cell if match not found
								foreach ($list as $item) {
									if (strpos($edited_data, $item['search']) !== false) {
										$txt_found = true;
										break;
									}
								}
								if (!$txt_found) {
									continue;
								}

								//Replace logic - level 1: simple check on any string or serialized strings
								foreach ($list as $item) {
									$edited_data = self::recursiveUnserializeReplace($item['search'], $item['replace'], $edited_data);
								}

								//Replace logic - level 2: repair serialized strings that have become broken
								$serial_check = self::fixSerialString($edited_data);
								if ($serial_check['fixed']) {
									$edited_data = $serial_check['data'];
								} elseif ($serial_check['tried'] && !$serial_check['fixed']) {
									$serial_err++;
								}
							}

							//Change was made
							if ($edited_data != $data_to_fix || $serial_err > 0) {
								$report['updt_cells'] ++;
								//Base 64 encode
								if ($base64converted) {
									$edited_data = base64_encode($edited_data);
								}
								$upd_col[]	 = $column;
								$upd_sql[]	 = $column.' = "'.mysqli_real_escape_string($dbh, $edited_data).'"';
								$upd		 = true;
							}

							if ($primary_key) {
								$where_sql[] = $column.' = "'.mysqli_real_escape_string($dbh, $data_to_fix).'"';
							}
						}

						//PERFORM ROW UPDATE
						if ($upd && !empty($where_sql)) {
							$sql	= "UPDATE `{$table}` SET ".implode(', ', $upd_sql).' WHERE '.implode(' AND ', array_filter($where_sql));
							$result	= mysqli_query($dbh, $sql);
							if ($result) {
								if ($serial_err > 0) {
									$report['errser'][] = "SELECT " . implode(', ', $upd_col) . " FROM `{$table}`  WHERE " . implode(' AND ', array_filter($where_sql)) . ';';
								}
								$report['updt_rows']++;
							} else  {
								$report['errsql'][]	 = ($GLOBALS["LOGGING"] == 1)
									? 'DB ERROR: ' . mysqli_error($dbh)
									: 'DB ERROR: ' . mysqli_error($dbh) . "\nSQL: [{$sql}]\n";
							}
						} elseif ($upd) {
							$report['errkey'][] = sprintf("Row [%s] on Table [%s] requires a manual update.", $current_row, $table);
						}
					}
					//DUPX_U::fcgiFlush();
					@mysqli_free_result($data);
				}

				if ($upd) {
					$report['updt_tables'] ++;
				}
			}
		}
		$profile_end			 = microtime(true);
		$report['time']			 = $profile_end - $profile_start;
		$report['errsql_sum']	 = empty($report['errsql']) ? 0 : count($report['errsql']);
		$report['errser_sum']	 = empty($report['errser']) ? 0 : count($report['errser']);
		$report['errkey_sum']	 = empty($report['errkey']) ? 0 : count($report['errkey']);
		$report['err_all']		 = $report['errsql_sum'] + $report['errser_sum'] + $report['errkey_sum'];
		return $report;
	}
	
	/**
	 * Checks if host can support MySQLi prepared functions
	 * @return boolean
	 */
	private static function hostCanSupportMySQLiPrepareFunc()
	{
	    return (function_exists('mysqli_prepare') && function_exists('mysqli_stmt_bind_param') && function_exists('mysqli_stmt_execute') && function_exists('mysqli_stmt_get_result'));
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
		} catch (Exception $error) {
		    return $data;
		} finally {
		    return $data;
		}		
	}

	/**
     * Test if a string in properly serialized
     *
     * @param string $data  Any string type
     * @codeCoverageIgnore
     * @return bool Is the string a serialized string
     */
    public static function isSerialized($data)
    {        
        $test = @unserialize(($data));
        return ($test !== false || $test === 'b:0;') ? true : false;
    }

	/**
	 *  Fixes the string length of a string object that has been serialized but the length is broken
	 *
	 *  @param string $data	The string ojbect to recalculate the size on.
	 *  @codeCoverageIgnore
	 *  @return string  A serialized string that fixes and string length types
	 */
	public static function fixSerialString($data)
	{
	    $result = array('data' => $data, 'fixed' => false, 'tried' => false);
	    	        
        if (preg_match("/s:[0-9]+:/", $data)) {
            if (!self::isSerialized($data)) {
                $regex			 = '!(?<=^|;)s:(\d+)(?=:"(.*?)";(?:}|a:|s:|b:|d:|i:|o:|N;))!s';
                /** @var Type $matches Matches*/
                $serial_string	 = preg_match('/^s:[0-9]+:"(.*$)/s', trim($data), $matches);
                //Nested serial string
                if ($serial_string) {
                    $inner				 = preg_replace_callback($regex, 'Codexonics\PrimeMoverFramework\utilities\DupxUpdateEngine::fixStringCallback', rtrim($matches[1], '";'));
                    $serialized_fixed	 = 's:'.strlen($inner).':"'.$inner.'";';
                } else {
                    $serialized_fixed = preg_replace_callback($regex, 'Codexonics\PrimeMoverFramework\utilities\DupxUpdateEngine::fixStringCallback', $data);
                }
                
                if (self::isSerialized($serialized_fixed)) {
                    $result['data']	 = $serialized_fixed;
                    $result['fixed'] = true;
                }
                $result['tried'] = true;
            }
        }
		
		return $result;
	}

	/**
	 *  @codeCoverageIgnore
	 *  The call back method call from via fixSerialString
	 */
	private static function fixStringCallback($matches)
	{
		return 's:'.strlen(($matches[2]));
	}
}
