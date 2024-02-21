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
 * 
 * ORIGINAL CREDITS: kosinix: https://gist.github.com/kosinix/4cf0d432638817888149
 */

use Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions;

if (! defined('ABSPATH')) {
    exit;
}

class PrimeMoverResumableDownloadStream 
{
    
    private $file;
    private $name;
    private $boundary;
    private $delay = 0;
    private $size = 0;
    private $system_functions;
    
    public function __construct(PrimeMoverSystemFunctions $system_functions) 
    {
        $this->system_functions = $system_functions;
    }
    
    public function getSystemFunctions()
    {
        return $this->system_functions;
    }
    
    public function initializeProperties($file = '', $delay = 0)
    {
        $this->setSize($file);
        $this->setFile($file);
        $this->setBoundary($file);
        $this->setDelay($delay);
        $this->setName($file);
    }
 
    public function getSystemAuthorization()
    {
        return $this->getSystemFunctions()->getSystemAuthorization();
    }
    
    private function canProcess()
    {
        return ( ! empty($this->file) && ! empty($this->size) && $this->getSystemAuthorization()->isUserAuthorized() );
    }
    
    private function setSize($file = '')
    {
        if ( ! $file ) {
            return;
        }
        $this->size = filesize($file);
    }
    
    private function setFile($file = '')
    {
        if ( ! $file ) {
            return;
        }
        $handle = fopen($file, "r");
        if ($handle) {
            $this->file = $handle;
        }
    }
    
    private function setBoundary($file = '')
    {
        if ( ! $file ) {
            return;
        }
        $this->boundary = md5($file);  
    }
    
    private function setDelay($delay = 0)
    {
        $this->delay = $delay;
    }
    
    private function setName($file = '')
    {
        if ( ! $file ) {
            return;
        }
        $this->name = basename($file);
    }
    
    public function process() 
    {
        if ( ! $this->canProcess() ) {
            return;
        }
        $ranges = NULL;
        $t = 0;
        if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_SERVER['HTTP_RANGE']) && $range = stristr(trim($_SERVER['HTTP_RANGE']), 'bytes=')) {
            $range = substr($range, 6);
            $ranges = explode(',', $range);
            $t = count($ranges);
        }
        header("Accept-Ranges: bytes");
        header("Content-Type: application/octet-stream");
        header("Content-Transfer-Encoding: binary");
        header(sprintf('Content-Disposition: attachment; filename="%s"', $this->name));
        if ($t > 0) {
            header("HTTP/1.1 206 Partial content");
            $t === 1 ? $this->pushSingle($range) : $this->pushMulti($ranges);            
        } else {
            header("Content-Length: " . $this->size);
            $this->getSystemFunctions()->flush();
            $this->readFile();
        }
    }
    
    private function pushSingle($range) 
    {
        $start = $end = 0;
        $this->getRange($range, $start, $end);
        header("Content-Length: " . ($end - $start + 1));
        header(sprintf("Content-Range: bytes %d-%d/%d", $start, $end, $this->size));
        fseek($this->file, $start);
        $this->getSystemFunctions()->flush();
        $this->readFile();
    }
    
    private function pushMulti($ranges) 
    {
        $length = $start = $end = 0;
        $tl = "Content-type: application/octet-stream\r\n";
        $formatRange = "Content-range: bytes %d-%d/%d\r\n\r\n";
        foreach ( $ranges as $range ) {
            $this->getRange($range, $start, $end);
            $length += strlen("\r\n--$this->boundary\r\n");
            $length += strlen($tl);
            $length += strlen(sprintf($formatRange, $start, $end, $this->size));
            $length += $end - $start + 1;
        }
        $length += strlen("\r\n--$this->boundary--\r\n");
        header("Content-Length: $length");
        header("Content-Type: multipart/x-byteranges; boundary=$this->boundary");
        $this->getSystemFunctions()->flush();
        foreach ( $ranges as $range ) {
            $this->getRange($range, $start, $end);
            echo "\r\n--$this->boundary\r\n";
            echo $tl;
            echo sprintf($formatRange, $start, $end, $this->size);
            fseek($this->file, $start);            
            $this->readBuffer($end - $start + 1);
        }
        echo "\r\n--$this->boundary--\r\n";
    }
    
    private function getRange($range, &$start, &$end) 
    {
        list($start, $end) = explode('-', $range);
        $fileSize = $this->size;
        if ($start == '') {
            $tmp = $end;
            $end = $fileSize - 1;
            $start = $fileSize - $tmp;
            if ($start < 0)
                $start = 0;
        } else {
            if ($end == '' || $end > $fileSize - 1)
                $end = $fileSize - 1;
        }
        if ($start > $end) {
            header("Status: 416 Requested range not satisfiable");
            header("Content-Range: */" . $fileSize);
            exit();
        }
        return array(
                $start,
                $end
        );
    }
    
    private function readFile() 
    {        
        while (!feof($this->file)) {
            $buffer = fread($this->file, 1024*1024);
            echo $buffer;
            flush();
            usleep($this->delay);
        }
    }
    
    private function readBuffer($bytes = 0, $size = 1024) 
    {
        $bytesLeft = $bytes;
        while ( $bytesLeft > 0 && ! feof($this->file) ) {
            $bytesLeft > $size ? $bytesRead = $size : $bytesRead = $bytesLeft;
            $bytesLeft -= $bytesRead;
            echo fread($this->file, $bytesRead);
            flush();
        }
    }
}