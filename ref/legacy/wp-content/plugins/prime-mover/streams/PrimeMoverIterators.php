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

use SplHeap;
use SplFixedArray;
use Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Iterator primarily used
 * For sorting files during export as its added to the archive
 */
class PrimeMoverIterators extends SplHeap
{
    private $system_functions;
    
    /**
     * Constructor
     * @param PrimeMoverSystemFunctions $system_functions
     */
    public function __construct(PrimeMoverSystemFunctions $system_functions) {
        $this->system_functions = $system_functions;
    }

    /**
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     */
    public function getSystemFunctions()
    {
        return $this->system_functions;
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
     * Get system initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     */
    public function getSystemInitialization()
    {
        return $this->getSystemFunctions()->getSystemInitialization();
    }
    
     /**
     * Insert items to heap
     * @param SplFixedArray $iterator
     */
    public function insertItems(SplFixedArray $iterator) {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        foreach ($iterator as $item) {
            $this->insert($item);
        }
    }    
    
    /**
     * Compare
     * {@inheritDoc}
     * @see SplHeap::compare()
     */
    #[\ReturnTypeWillChange]
    public function compare($a, $b)
    {        
        if ($this->getSystemFunctions()->fileSize64($a) === $this->getSystemFunctions()->fileSize64($b)) {
            return 0;
        }
        
        if ($this->getSystemFunctions()->fileSize64($a) > $this->getSystemFunctions()->fileSize64($b)) {
            return -1;;
            
        } else {
            return 1;
        }
    }
 
    /**
     * Analyze if the file is a locale (for language folder exports)
     * @param array $locales
     * @param string $file
     * @return boolean
     */
    protected function isLocaleFile($locales = [], $file = '')
    {
        if (!$file || !is_array($locales)) {
            return false;
        }
        
        $valid = false;
        foreach ($locales as $locale) {
            
            $file = basename($file);
            $locale_suffix = "{$locale}-";
            $locale_prefix = "-{$locale}";
            $mo_match = "{$locale}.mo";
            $po_match = "{$locale}.po";
                     
            if (false !== strpos($file, $locale_suffix) || false !== strpos($file, $locale_prefix) ||
                false !== strpos($file, $mo_match) || false !== strpos($file, $po_match)) {
                $valid = true;
                break;
            }            
        }
        
        return $valid;
    }
    
    /**
     * Generate files list given a directory
     * Then write to a temporary file, directories first then files.
     * @param string $dir
     * @param array $ret
     * @param array $excluded_dirs
     * @param array $locale
     * @return array
     * @mainsitesupport_affected
     */
    public function generateFilesListGivenDir($dir = '', $ret = [], $excluded_dirs = [], $locale = [])
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = sprintf(esc_html__('Unauthorized to generate files list in directory: %s. Please check credentials.', 'prime-mover'), $dir);
            return $ret;
        }
        $mode = 'wb';
        if (empty($ret['copymedia_shell_tmp_list'])) {
            $tmpfname = $this->getSystemInitialization()->wpTempNam();
        } else {
            $tmpfname = $ret['copymedia_shell_tmp_list'];
            $mode = 'ab';
        }
        
        $processing_locale = false;
        if (is_array($locale) && !empty($locale)) {
            $processing_locale = true;
        }
        
        $this->getSystemFunctions()->temporarilyIncreaseMemoryLimits();       
        $dir_spl = new SplFixedArray(50);
        $files_spl = new SplFixedArray(50);
        
        /** @var Type $index_dir directory index*/
        /** @var Type $index_file file index*/
        list($dir_spl, $files_spl, $index_dir, $index_file) = $this->listFolderFiles($dir, $dir_spl, $files_spl, 0, 0, $excluded_dirs);
        
        if (empty($ret['total_media_files'])) {
            $files_count = 0;
        } else {
            $files_count = $ret['total_media_files'];
        }
        $handle = fopen($tmpfname, $mode);
        if (false === $handle) {
            $ret['error'] = sprintf(esc_html__('Unable to generate files list in directory: %s. Please check permissions.', 'prime-mover'), $dir);
        }
        foreach ($dir_spl as $entity) {
            if (!$entity) {
                continue;
            }
            $res = fwrite($handle, $entity . PHP_EOL);
            if ($res) {
                $files_count++;
            }
        }
        foreach ($files_spl as $entity) {
            if (!$entity) {
                continue;
            }
            
            if ($processing_locale && false === $this->isLocaleFile($locale, $entity)) {
                continue;
            }
            
            $res = fwrite($handle, $entity . PHP_EOL);
            if ($res) {
                $files_count++;
            }
        }
        
        fclose($handle);
        if ($locale && $files_count > 0) {
            $ret['lang_folder_exported'] = true;
        }
        
        $ret['copymedia_shell_tmp_list'] = $tmpfname;
        $ret['total_media_files'] = $files_count;
      
        return $ret;
    }
        
    /**
     * Returns TRUE if the directory should be excluded otherwise FALSE
     * @param array $excluded_dirs
     * @param string $dir
     * @return boolean
     */
    private function maybeExcludeDir($excluded_dirs = [], $dir = '')
    {
        $dir = untrailingslashit(wp_normalize_path($dir));
        return in_array($dir, $excluded_dirs, true);
    }
    
    /**
     * List folder files using recursive scandir and SPLFixedArrays for best performance.
     * @param string $dir
     * @param SplFixedArray $dir_spl
     * @param SplFixedArray $files_spl
     * @param number $index_dir
     * @param number $index_file
     * @param array $excluded_dirs
     * @mainsitesupport_affected
     * @return SplFixedArray[]
     */
    protected function listFolderFiles($dir = '', SplFixedArray $dir_spl = null, SplFixedArray $files_spl = null, $index_dir = 0, $index_file = 0, $excluded_dirs = [])
    {
        if ($this->isDirectory($dir) && !$this->maybeExcludeDir($excluded_dirs, $dir)) {
            $ffs = scandir($dir);
            
            unset($ffs[array_search('.', $ffs, true)]);
            unset($ffs[array_search('..', $ffs, true)]);
            
            if (count($ffs) < 1) {
                return [$dir_spl, $files_spl, $index_dir, $index_file];
            }
            foreach($ffs as $ff){
                $filepath = $this->getSystemInitialization()->realPath($dir . DIRECTORY_SEPARATOR . $ff);
                if($this->isDirectory($filepath) && !$this->maybeExcludeDir($excluded_dirs, $filepath)) {
                    
                    $index_dir++;
                    $normalized_dir = wp_normalize_path($filepath);
                    $dir_spl = $this->getSystemFunctions()->addNewElement($dir_spl, $index_dir, $normalized_dir);
                    list($dir_spl, $files_spl, $index_dir, $index_file) = $this->listFolderFiles($filepath, $dir_spl, $files_spl, $index_dir, $index_file, $excluded_dirs);
                    
                } elseif ($this->isFile($filepath)) {
                    $index_file++;
                    $normalized_file = wp_normalize_path($filepath);
                    $files_spl = $this->getSystemFunctions()->addNewElement($files_spl, $index_file, $normalized_file);
                }
            }
            return [$dir_spl, $files_spl, $index_dir, $index_file];
            
        } elseif ($this->isFile($dir)) {
            $index_file++;
            $normalized_file = wp_normalize_path($dir);
            $files_spl = $this->getSystemFunctions()->addNewElement($files_spl, $index_file, $normalized_file);
            
            return [$dir_spl, $files_spl, $index_dir, $index_file];
        }
        
        return [$dir_spl, $files_spl, $index_dir, $index_file];        
    }
    
    /**
     * Check if it's a readable directory
     * @param string $dir
     * @return boolean
     */
    private function isDirectory($dir = '')
    {
        return (is_dir($dir) && is_readable($dir));
    }
    
    /**
     * Check if it's a readable file
     * @param string $file
     * @return boolean
     */
    private function isFile($file = '')
    {
        return (is_file($file) && is_readable($file));
    }
}