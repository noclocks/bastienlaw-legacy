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

use php_user_filter;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Stream filters for data encryption
 *
 */
class PrimeMoverStreamFilters extends php_user_filter 
{
    /**
     * @var string
     */
    const FILTER_NAMESPACE = 'prime.mover.encrypt';
 
    /**
     * @var bool
     */
    private static $hasBeenRegistered = false;
    
    /**
     * @param string $in
     * @param string $out
     * @param string $consumed
     * @param $closing
     * @return int
     * @codeCoverageIgnore
     */
    #[\ReturnTypeWillChange]
    public function filter($in, $out, &$consumed, $closing)
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $data = $bucket->data;
            $pieces = explode(PHP_EOL, $data);
            $new = [];
            foreach ($pieces as $piece) {
                $new[] = apply_filters('prime_mover_filter_export_db_data', $piece);
            }
            $bucket->data = implode(PHP_EOL, $new);
            $bucket->data = $bucket->data . PHP_EOL;
            
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }
        return PSFS_PASS_ON;
    }
    
    /**
     * Return filter name
     * @return string
     * @codeCoverageIgnore
     */
    public static function getFilterName()
    {
        return self::FILTER_NAMESPACE;
    }
    
    /**
     * Register this class as a stream filter
     * @return boolean
     * @codeCoverageIgnore
     */
    public function register()
    {
        if (true === self::$hasBeenRegistered) {
            return true;
        }
        if ( false === stream_filter_register(self::getFilterName(), __CLASS__) ) {
            return false;
        }
        self::$hasBeenRegistered = true;
        
        return true;
    }
    
    /**
     * Return filter URL
     * @param string $filename
     * @param string $fromCharset
     * @param string $toCharset
     * @return string
     * @codeCoverageIgnore
     */
    public function getStreamFilter($path)
    {
        return sprintf('php://filter/write=prime.mover.encrypt/resource=%s', $path);        
    }    
}