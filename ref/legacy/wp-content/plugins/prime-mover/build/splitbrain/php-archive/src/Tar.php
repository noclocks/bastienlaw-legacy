<?php

namespace Codexonics\PrimeMoverFramework\build\splitbrain\PHPArchive;

/**
 * Class Tar
 *
 * Creates or extracts Tar archives. Supports gz and bzip compression
 *
 * Long pathnames (>100 chars) are supported in POSIX ustar and GNU longlink formats.
 *
 * @author  Andreas Gohr <andi@splitbrain.org>
 * @package splitbrain\PHPArchive
 * @license MIT
 * 
 * Prime Mover WordPress Plugin Integration
 * https://github.com/codex-m/php-archive
 * @author Emerson Maningo (emerson@codexonics.com)
 */
class Tar extends Archive
{
    protected $file = '';
    protected $comptype = Archive::COMPRESS_AUTO;
    protected $complevel = 9;
    protected $fh;
    protected $memory = '';
    protected $closed = \true;
    protected $writeaccess = \false;
    const FILE_ENCRYPTION_BLOCKS = 32;
    const CIPHER_METHOD = 'AES-256-CBC';
    /**
     * Sets the compression to use
     *
     * @param int $level Compression level (0 to 9)
     * @param int $type Type of compression to use (use COMPRESS_* constants)
     * @throws ArchiveIllegalCompressionException
     */
    public function setCompression($level = 9, $type = Archive::COMPRESS_AUTO)
    {
        $this->compressioncheck($type);
        if ($level < -1 || $level > 9) {
            throw new ArchiveIllegalCompressionException('Compression level should be between -1 and 9');
        }
        $this->comptype = $type;
        $this->complevel = $level;
        if ($level == 0) {
            $this->comptype = Archive::COMPRESS_NONE;
        }
        if ($type == Archive::COMPRESS_NONE) {
            $this->complevel = 0;
        }
    }
    /**
     * @param string $file
     * @param int $offset
     * @param string $source_is_url
     * @throws ArchiveIOException
     * @throws ArchiveIllegalCompressionException
     * {@inheritDoc}
     * @see \splitbrain\PHPArchive\Archive::open()
     */
    public function open($file, $offset = 0, $source_is_url = \false)
    {
        $this->file = $file;
        // update compression to mach file
        if ($this->comptype == Tar::COMPRESS_AUTO) {
            $this->setCompression($this->complevel, $this->filetype($file));
        }
        $opts = ["ssl" => ["verify_peer" => \false, "verify_peer_name" => \false]];
        // open file handles
        if ($this->comptype === Archive::COMPRESS_GZIP) {
            $this->fh = @\gzopen($this->file, 'rb');
        } elseif ($this->comptype === Archive::COMPRESS_BZIP) {
            $this->fh = @\bzopen($this->file, 'r');
        } else {
            if ($source_is_url) {
                $this->fh = @\fopen($this->file, 'rb', \false, \stream_context_create($opts));
            } else {
                $this->fh = @\fopen($this->file, 'rb');
            }
        }
        if (!$this->fh) {
            throw new ArchiveIOException('Could not open file for reading: ' . $this->file);
        }
        $this->closed = \false;
        if ($offset) {
            \fseek($this->fh, $offset);
        }
    }
    /**
     * Read the contents of a TAR archive
     *
     * This function lists the files stored in the archive
     *
     * The archive is closed afer reading the contents, because rewinding is not possible in bzip2 streams.
     * Reopen the file with open() again if you want to do additional operations
     *
     * @throws ArchiveIOException
     * @throws ArchiveCorruptedException
     * @returns FileInfo[]
     */
    public function contents()
    {
        if ($this->closed || !$this->file) {
            throw new ArchiveIOException('Can not read from a closed archive');
        }
        $result = array();
        while ($read = $this->readbytes(512)) {
            $header = $this->parseHeader($read);
            if (!\is_array($header)) {
                continue;
            }
            $this->skipbytes(\ceil($header['size'] / 512) * 512);
            $result[] = $this->header2fileinfo($header);
        }
        $this->close();
        return $result;
    }
    /**
     * Checks if a WPRIME archive is not corrupted
     * This should only be used after identifying the archive is WPRIME.
     * 
     * Returns TRUE if archive is clean (not corrupted) otherwise FALSE.
     * @return boolean
     */
    public function isArchiveCorrupted()
    {
        $buffer_size = 512;
        \fseek($this->fh, 0, \SEEK_END);
        $pos = \ftell($this->fh);
        $clean = \false;
        $bytes_read = 0;
        while (0 !== $pos) {
            $read_size = $pos >= $buffer_size ? $buffer_size : $pos;
            \fseek($this->fh, $pos - $read_size, \SEEK_SET);
            $read = \fread($this->fh, $read_size);
            $header = $this->parseHeader($read, \true);
            if (!\is_array($header)) {
                $pos -= $read_size;
                $bytes_read += $read_size;
                if ($bytes_read >= 65536) {
                    break;
                }
                if (!$pos) {
                    break;
                }
                continue;
            }
            $fileinfo = $this->header2fileinfo($header);
            $pathtolog = $fileinfo->getPath();
            $filename = \basename($pathtolog);
            if ($filename && PRIME_MOVER_WPRIME_CLOSED_IDENTIFIER === $filename) {
                $clean = \true;
            }
            if ($filename) {
                break;
            }
            $pos -= $read_size;
            if (!$pos) {
                break;
            }
        }
        \fclose($this->fh);
        return $clean;
    }
    /**
     * Decode the given tar file header
     *
     * @param string $block a 512 byte block containing the header data
     * @return array|false returns false when this was a null block
     * @throws ArchiveCorruptedException
     */
    protected function parseHeader($block, $ret_errors = \false)
    {
        if (!$block || \strlen($block) != 512) {
            if ($ret_errors) {
                return \false;
            } else {
                throw new ArchiveCorruptedException('Unexpected length of header');
            }
        }
        // null byte blocks are ignored
        if (\trim($block) === '') {
            return \false;
        }
        for ($i = 0, $chks = 0; $i < 148; $i++) {
            $chks += \ord($block[$i]);
        }
        for ($i = 156, $chks += 256; $i < 512; $i++) {
            $chks += \ord($block[$i]);
        }
        $header = @\unpack("a100filename/a8perm/a8uid/a8gid/a12size/a12mtime/a8checksum/a1typeflag/a100link/a6magic/a2version/a32uname/a32gname/a8devmajor/a8devminor/a155prefix", $block);
        if (!$header) {
            if ($ret_errors) {
                return \false;
            } else {
                throw new ArchiveCorruptedException('Failed to parse header');
            }
        }
        $return['checksum'] = @\OctDec(\trim($header['checksum']));
        if ($return['checksum'] != $chks) {
            if ($ret_errors) {
                return \false;
            } else {
                return \false;
            }
        }
        $return['filename'] = \trim($header['filename']);
        $return['perm'] = \OctDec(\trim($header['perm']));
        $return['uid'] = \OctDec(\trim($header['uid']));
        $return['gid'] = \OctDec(\trim($header['gid']));
        $return['size'] = \OctDec(\trim($header['size']));
        $return['mtime'] = \OctDec(\trim($header['mtime']));
        $return['typeflag'] = $header['typeflag'];
        $return['link'] = \trim($header['link']);
        $return['uname'] = \trim($header['uname']);
        $return['gname'] = \trim($header['gname']);
        // Handle ustar Posix compliant path prefixes
        if (\trim($header['prefix'])) {
            $return['filename'] = \trim($header['prefix']) . '/' . $return['filename'];
        }
        // Handle Long-Link entries from GNU Tar
        if ($return['typeflag'] == 'L') {
            // following data block(s) is the filename
            $filename = \trim($this->readbytes(\ceil($return['size'] / 512) * 512));
            // next block is the real header
            $block = $this->readbytes(512);
            $return = $this->parseHeader($block);
            // overwrite the filename
            $return['filename'] = $filename;
        }
        return $return;
    }
    /**
     * Checks if Prime Mover TarBall package
     * File extension should be checked first before using this function
     * Returns false if not a Prime Mover Tarball
     * Otherwise returns the Prime Mover tarball configuration
     * @return boolean|string
     */
    public function isPrimeMoverTarBall()
    {
        if ($this->closed || !$this->file) {
            return \false;
        }
        $bytes_read = 0;
        while ($read = $this->readbytes(512)) {
            $header = $this->parseHeader($read, \true);
            $bytes_read = \ftell($this->fh);
            if ($bytes_read >= 10240) {
                $this->close();
                return \false;
            }
            if (!\is_array($header)) {
                continue;
            }
            $fileinfo = $this->header2fileinfo($header);
            $pathtolog = $fileinfo->getPath();
            $filename = \basename($pathtolog);
            if (PRIME_MOVER_WPRIME_CONFIG === $filename) {
                $json = '';
                $size = \floor($header['size'] / 512);
                for ($i = 0; $i < $size; $i++) {
                    $json .= $this->readbytes(512);
                }
                if ($header['size'] % 512 != 0) {
                    $json .= $this->readbytes(512);
                }
                $this->close();
                return $json;
            }
        }
        $this->close();
        return \false;
    }
    /**
     * Extract an existing TAR archive 
     * If encryption key is passed, it will automatically decrypt encrypted files inside the archive.
     * 
     * The $strip parameter allows you to strip a certain number of path components from the filenames
     * found in the tar file, similar to the --strip-components feature of GNU tar. This is triggered when
     * an integer is passed as $strip.
     * Alternatively a fixed string prefix may be passed in $strip. If the filename matches this prefix,
     * the prefix will be stripped. It is recommended to give prefixes with a trailing slash.
     *
     * By default this will extract all files found in the archive. You can restrict the output using the $include
     * and $exclude parameter. Both expect a full regular expression (including delimiters and modifiers). If
     * $include is set only files that match this expression will be extracted. Files that match the $exclude
     * expression will never be extracted. Both parameters can be used in combination. Expressions are matched against
     * stripped filenames as described above.
     *
     * The archive is closed afer reading the contents, because rewinding is not possible in bzip2 streams.
     * Reopen the file with open() again if you want to do additional operations
     *
     * @param string $outdir the target directory for extracting
     * @param int|string $strip either the number of path components or a fixed prefix to strip
     * @param string $exclude a regular expression of files to exclude
     * @param string $include a regular expression of files to include
     * @param int $start Time start of extraction for retry purposes
     * @param int $file_offset Offset of the file being extracted
     * @param int $index Index loop for the file being extracted
     * @param int $base_read_offset Base read offset of the tar being extracted
     * @param int $blog_id Blog ID of WordPress site (always 1 in single-site)
     * @param string $key Encryption key $this
     * @param string $iv Initialization vector
     * @param int $blog_id Blog ID of WordPress site
     * @throws ArchiveIOException
     * @throws ArchiveCorruptedException
     * @return boolean
     */
    public function extract($outdir, $strip = '', $exclude = '', $include = '', $start = 0, $file_offset = 0, $index = 0, $base_read_offset = 0, $blog_id = 0, $key = '', $iv = '')
    {
        if ($this->closed || !$this->file) {
            throw new ArchiveIOException('Can not read from a closed archive');
        }
        if ($key) {
            $key = \substr(\sha1($key, \true), 0, 16);
        }
        $outdir = \rtrim($outdir, '/');
        if (!$file_offset) {
            if (!wp_mkdir_p($outdir)) {
                throw new ArchiveIOException("Could not create directory '{$outdir}'");
            }
        }
        $retry_timeout = apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, __FUNCTION__);
        while ($dat = $this->readbytes(512)) {
            $processing_retry = \false;
            $this->maybeTestExtractionFileDelay();
            $header = $this->parseHeader($dat);
            if (!\is_array($header)) {
                continue;
            }
            $fileinfo = $this->header2fileinfo($header);
            $fileinfo->strip($strip);
            if (!\strlen($fileinfo->getPath()) || !$fileinfo->match($include, $exclude)) {
                $this->skipbytes(\ceil($header['size'] / 512) * 512);
                continue;
            }
            $output = $outdir . '/' . $fileinfo->getPath();
            $directory = $fileinfo->getIsdir() ? $output : \dirname($output);
            if (!$file_offset) {
                wp_mkdir_p($directory);
            }
            $mode = 'wb';
            if ($file_offset) {
                $mode = 'ab';
            }
            if (!$fileinfo->getIsdir()) {
                $fp = @\fopen($output, $mode);
                if (!$fp) {
                    $error_msg = \error_get_last();
                    if (\is_array($error_msg) && !empty($error_msg['message'])) {
                        $extr_error_msg = $error_msg['message'];
                        do_action('prime_mover_log_processed_events', "File cannot be extracted: {$output} - ERROR: {$extr_error_msg}", $blog_id, 'export', __FUNCTION__, $this);
                        continue;
                    } else {
                        throw new ArchiveIOException('Could not open file for writing: ' . $output);
                    }
                }
                $size = \floor($header['size'] / 512);
                if ($file_offset) {
                    $processing_retry = \true;
                    \fseek($this->fh, $file_offset);
                    $file_offset = 0;
                }
                $decrypt = $this->maybeDecrypt($header, $key);
                if ($decrypt && $iv && $processing_retry) {
                    $iv = \base64_decode($iv);
                }
                if (!$file_offset && $decrypt && !$processing_retry) {
                    $iv = \fread($this->fh, 16);
                }
                for ($i = $index; $i < $size; $i++) {
                    $this->maybeTestExtractionFileDelay();
                    if ($decrypt) {
                        $block_size = self::FILE_ENCRYPTION_BLOCKS;
                        $ciphertext = \fread($this->fh, 16 * ($block_size + 1));
                        $plaintext = \openssl_decrypt($ciphertext, self::CIPHER_METHOD, $key, \OPENSSL_RAW_DATA, $iv);
                        $iv = \substr($ciphertext, 0, 16);
                        \fwrite($fp, $plaintext, 512);
                    } else {
                        \fwrite($fp, $this->readbytes(512), 512);
                    }
                    $offset = \ftell($this->fh);
                    if (\microtime(\true) - $start > $retry_timeout) {
                        $index = $i + 1;
                        \fclose($fp);
                        do_action('prime_mover_log_processed_events', "Time out reach, need to retry at offset {$offset}, base read offset {$base_read_offset} and index {$index} for file {$output} ", $blog_id, 'export', __FUNCTION__, $this);
                        return ['tar_read_offset' => $offset, 'base_read_offset' => $base_read_offset, 'index' => $index, 'iv' => \base64_encode($iv)];
                    }
                }
                if ($header['size'] % 512 != 0) {
                    if ($decrypt) {
                        $block_size = self::FILE_ENCRYPTION_BLOCKS;
                        $ciphertext = \fread($this->fh, 16 * ($block_size + 1));
                        $plaintext = \openssl_decrypt($ciphertext, self::CIPHER_METHOD, $key, \OPENSSL_RAW_DATA, $iv);
                        \fwrite($fp, $plaintext, $header['size'] % 512);
                    } else {
                        \fwrite($fp, $this->readbytes(512), $header['size'] % 512);
                    }
                }
                \fclose($fp);
                @\touch($output, $fileinfo->getMtime());
                @\chmod($output, $fileinfo->getMode());
            } else {
                $this->skipbytes(\ceil($header['size'] / 512) * 512);
            }
            if (\is_callable($this->callback)) {
                \call_user_func($this->callback, $fileinfo);
            }
            $index = 0;
            $iv = '';
            $base_read_offset = \ftell($this->fh);
        }
        do_action('prime_mover_log_processed_events', "Entire extraction is done.", $blog_id, 'export', __FUNCTION__, $this);
        $this->close();
        return \true;
    }
    /**
     * Checks if a file needs to be decrypted depending on its type flag header.
     * @param array $header
     * @param string $key
     * @return boolean
     */
    protected function maybeDecrypt($header = [], $key = '')
    {
        return !empty($header['typeflag']) && 'P' === $header['typeflag'] && $key;
    }
    /**
     * Maybe test extraction delay
     */
    private function maybeTestExtractionFileDelay()
    {
        $delay = 0;
        if (\defined('PRIME_MOVER_TEST_EXTRACTION_TAR_DELAY') && PRIME_MOVER_TEST_EXTRACTION_TAR_DELAY) {
            $delay = (int) PRIME_MOVER_TEST_EXTRACTION_TAR_DELAY;
            \usleep($delay);
        }
    }
    /**
     * Create a new TAR file
     *
     * If $file is empty, the tar file will be created in memory
     *
     * @param string $file
     * @throws ArchiveIOException
     * @throws ArchiveIllegalCompressionException
     */
    public function create($file = '', $mode = 'wb')
    {
        $this->file = $file;
        $this->memory = '';
        $this->fh = 0;
        if ($this->file) {
            // determine compression
            if ($this->comptype == Archive::COMPRESS_AUTO) {
                $this->setCompression($this->complevel, $this->filetype($file));
            }
            if ($this->comptype === Archive::COMPRESS_GZIP) {
                $this->fh = @\gzopen($this->file, $mode . $this->complevel);
            } elseif ($this->comptype === Archive::COMPRESS_BZIP) {
                $this->fh = @\bzopen($this->file, 'w');
            } else {
                $this->fh = @\fopen($this->file, $mode);
            }
            if (!$this->fh) {
                throw new ArchiveIOException('Could not open file for writing: ' . $this->file);
            }
        }
        $this->writeaccess = \true;
        $this->closed = \false;
    }
    /**
     * Add a file to the current TAR archive using an existing file in the filesystem
     *
     * @param string $file path to the original file
     * @param string|FileInfo $fileinfo either the name to us in archive (string) or a FileInfo oject with all meta data, empty to take from original
     * @param int $start Start time of adding file (for retry purposes)
     * @param int $file_position File position for subsequent reading.
     * @param int $blog_id Blog ID of WordPress site.
     * @param boolean $enable_retry Whether to enable retry
     * @param string $key Encryption key (optional, enables encryption when key is provided)
     * @param string $iv Initialization vector (used only when encryption package)
     * @param int $bytes_written Total bytes written
     * 
     * Returns:
     * String in case of error
     * Array in case of retries
     * Integer in case of success, indicating the number of bytes written.
     */
    public function addFile($file, $fileinfo = '', $start = 0, $file_position = 0, $blog_id = 0, $enable_retry = \false, $key = '', $iv = '', $bytes_written = 0)
    {
        $encrypt = \false;
        if ($key) {
            $encrypt = \true;
        }
        if ($iv && $encrypt) {
            $iv = \base64_decode($iv);
        }
        if (\is_string($fileinfo)) {
            $fileinfo = FileInfo::fromPath($file, $fileinfo);
        }
        $retried = \false;
        if ($this->closed) {
            return esc_html__('Archive has been closed, files can no longer be added', 'prime-mover');
        }
        if ($key) {
            $key = \substr(\sha1($key, \true), 0, 16);
        }
        $fp = null;
        if (\is_file($file)) {
            do_action('prime_mover_log_processed_events', "Opening {$file} for archiving", $blog_id, 'export', __FUNCTION__, $this, \true);
            $fp = @\fopen($file, 'rb');
            if (!$fp) {
                return \sprintf(esc_html__('Could not open file for reading: %s', 'prime-mover'), $file);
            }
        }
        if ($file_position && \is_resource($fp) && $enable_retry) {
            $retried = \true;
            do_action('prime_mover_log_processed_events', "Resuming reading {$file} on position {$file_position}", $blog_id, 'export', __FUNCTION__, $this);
            \fseek($fp, $file_position);
        } else {
            do_action('prime_mover_log_processed_events', "Writing header for file {$file}.", $blog_id, 'export', __FUNCTION__, $this, \true);
            $this->writeFileHeader($fileinfo, $encrypt);
        }
        if ($encrypt && !$iv) {
            $iv = \openssl_random_pseudo_bytes(16);
        }
        $retry_timeout = apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, __FUNCTION__);
        if (\is_resource($fp)) {
            if (!$retried && $encrypt) {
                $bytes_written += $this->writebytes($iv);
            }
            while (!\feof($fp)) {
                $this->maybeTestAddFileDelay();
                $data = \fread($fp, 512);
                $pos = \ftell($fp);
                if ($data === \false) {
                    break;
                }
                if ($data === '') {
                    break;
                }
                $packed = \pack("a512", $data);
                if ($encrypt) {
                    $ciphertext = \openssl_encrypt($packed, self::CIPHER_METHOD, $key, \OPENSSL_RAW_DATA, $iv);
                    $iv = \substr($ciphertext, 0, 16);
                    $bytes_written += $this->writebytes($ciphertext);
                } else {
                    $bytes_written += $this->writebytes($packed);
                }
                if ($enable_retry && \microtime(\true) - $start > $retry_timeout) {
                    do_action('prime_mover_log_processed_events', "{$retry_timeout} seconds Time out reach while archiving {$file} on position {$pos}", $blog_id, 'export', __FUNCTION__, $this);
                    \fclose($fp);
                    $pos = (int) $pos;
                    return ['tar_add_offset' => $pos, 'iv' => \base64_encode($iv), 'bytes_written' => $bytes_written];
                }
            }
            \fclose($fp);
        }
        do_action('prime_mover_log_processed_events', "Successfully closed reading archiving {$file}.", $blog_id, 'export', __FUNCTION__, $this, \true);
        return $bytes_written;
    }
    /**
     * Maybe add test delay
     */
    private function maybeTestAddFileDelay()
    {
        $delay = 0;
        if (\defined('PRIME_MOVER_TEST_ADDFILE_TAR_DELAY') && PRIME_MOVER_TEST_ADDFILE_TAR_DELAY) {
            $delay = (int) PRIME_MOVER_TEST_ADDFILE_TAR_DELAY;
            \usleep($delay);
        }
    }
    /**
     * Add a file to the current TAR archive using the given $data as content
     *
     * @param string|FileInfo $fileinfo either the name to us in archive (string) or a FileInfo oject with all meta data
     * @param string          $data     binary content of the file to add
     * @throws ArchiveIOException
     */
    public function addData($fileinfo, $data)
    {
        if (\is_string($fileinfo)) {
            $fileinfo = new FileInfo($fileinfo);
        }
        if ($this->closed) {
            throw new ArchiveIOException('Archive has been closed, files can no longer be added');
        }
        $len = \strlen($data);
        $fileinfo->setSize($len);
        $this->writeFileHeader($fileinfo);
        for ($s = 0; $s < $len; $s += 512) {
            $this->writebytes(\pack("a512", \substr($data, $s, 512)));
        }
        if (\is_callable($this->callback)) {
            \call_user_func($this->callback, $fileinfo);
        }
    }
    /**
     * Add the closing footer to the archive if in write mode, close all file handles
     *
     * After a call to this function no more data can be added to the archive, for
     * read access no reading is allowed anymore
     *
     * "Physically, an archive consists of a series of file entries terminated by an end-of-archive entry, which
     * consists of two 512 blocks of zero bytes"
     *
     * @link http://www.gnu.org/software/tar/manual/html_chapter/tar_8.html#SEC134
     * @throws ArchiveIOException
     */
    public function close()
    {
        if ($this->closed) {
            return;
        }
        // we did this already
        // write footer
        if ($this->writeaccess) {
            $this->writebytes(\pack("a512", ""));
            $this->writebytes(\pack("a512", ""));
        }
        // close file handles
        if ($this->file) {
            if ($this->comptype === Archive::COMPRESS_GZIP) {
                \gzclose($this->fh);
            } elseif ($this->comptype === Archive::COMPRESS_BZIP) {
                \bzclose($this->fh);
            } else {
                \fclose($this->fh);
            }
            $this->file = '';
            $this->fh = 0;
        }
        $this->writeaccess = \false;
        $this->closed = \true;
    }
    /**
     * Returns the created in-memory archive data
     *
     * This implicitly calls close() on the Archive
     * @throws ArchiveIOException
     */
    public function getArchive()
    {
        $this->close();
        if ($this->comptype === Archive::COMPRESS_AUTO) {
            $this->comptype = Archive::COMPRESS_NONE;
        }
        if ($this->comptype === Archive::COMPRESS_GZIP) {
            return \gzencode($this->memory, $this->complevel);
        }
        if ($this->comptype === Archive::COMPRESS_BZIP) {
            return \bzcompress($this->memory);
        }
        return $this->memory;
    }
    /**
     * Save the created in-memory archive data
     *
     * Note: It more memory effective to specify the filename in the create() function and
     * let the library work on the new file directly.
     *
     * @param string $file
     * @throws ArchiveIOException
     * @throws ArchiveIllegalCompressionException
     */
    public function save($file)
    {
        if ($this->comptype === Archive::COMPRESS_AUTO) {
            $this->setCompression($this->complevel, $this->filetype($file));
        }
        if (!@\file_put_contents($file, $this->getArchive())) {
            throw new ArchiveIOException('Could not write to file: ' . $file);
        }
    }
    /**
     * Read from the open file pointer
     *
     * @param int $length bytes to read
     * @return string
     */
    protected function readbytes($length)
    {
        if ($this->comptype === Archive::COMPRESS_GZIP) {
            return @\gzread($this->fh, $length);
        } elseif ($this->comptype === Archive::COMPRESS_BZIP) {
            return @\bzread($this->fh, $length);
        } else {
            return @\fread($this->fh, $length);
        }
    }
    /**
     * Write to the open filepointer or memory
     *
     * @param string $data
     * @throws ArchiveIOException
     * @return int number of bytes written
     */
    protected function writebytes($data)
    {
        if (!$this->file) {
            $this->memory .= $data;
            $written = \strlen($data);
        } elseif ($this->comptype === Archive::COMPRESS_GZIP) {
            $written = @\gzwrite($this->fh, $data);
        } elseif ($this->comptype === Archive::COMPRESS_BZIP) {
            $written = @\bzwrite($this->fh, $data);
        } else {
            $written = @\fwrite($this->fh, $data);
        }
        if ($written === \false) {
            throw new ArchiveIOException('Failed to write to archive stream');
        }
        return $written;
    }
    /**
     * Skip forward in the open file pointer
     *
     * This is basically a wrapper around seek() (and a workaround for bzip2)
     *
     * @param int $bytes seek to this position
     */
    protected function skipbytes($bytes)
    {
        if ($this->comptype === Archive::COMPRESS_GZIP) {
            @\gzseek($this->fh, $bytes, \SEEK_CUR);
        } elseif ($this->comptype === Archive::COMPRESS_BZIP) {
            // there is no seek in bzip2, we simply read on
            // bzread allows to read a max of 8kb at once
            while ($bytes) {
                $toread = \min(8192, $bytes);
                @\bzread($this->fh, $toread);
                $bytes -= $toread;
            }
        } else {
            @\fseek($this->fh, $bytes, \SEEK_CUR);
        }
    }
    /**
     * Write the given file meta data as header
     * @param FileInfo $fileinfo
     * @param boolean $encrypted
     */
    protected function writeFileHeader(FileInfo $fileinfo, $encrypted = \false)
    {
        $typeflag = $fileinfo->getIsdir() ? '5' : '0';
        if ($encrypted && '5' !== $typeflag) {
            $typeflag = "P";
        }
        $this->writeRawFileHeader($fileinfo->getPath(), $fileinfo->getUid(), $fileinfo->getGid(), $fileinfo->getMode(), $fileinfo->getSize(), $fileinfo->getMtime(), $typeflag);
    }
    /**
     * Write a file header to the stream
     *
     * @param string $name
     * @param int $uid
     * @param int $gid
     * @param int $perm
     * @param int $size
     * @param int $mtime
     * @param string $typeflag Set to '5' for directories
     * @throws ArchiveIOException
     */
    protected function writeRawFileHeader($name, $uid, $gid, $perm, $size, $mtime, $typeflag = '')
    {
        // handle filename length restrictions
        $prefix = '';
        $namelen = \strlen($name);
        if ($namelen > 100) {
            $file = \basename($name);
            $dir = \dirname($name);
            if (\strlen($file) > 100 || \strlen($dir) > 155) {
                // we're still too large, let's use GNU longlink
                $this->writeRawFileHeader('././@LongLink', 0, 0, 0, $namelen, 0, 'L');
                for ($s = 0; $s < $namelen; $s += 512) {
                    $this->writebytes(\pack("a512", \substr($name, $s, 512)));
                }
                $name = \substr($name, 0, 100);
                // cut off name
            } else {
                // we're fine when splitting, use POSIX ustar
                $prefix = $dir;
                $name = $file;
            }
        }
        // values are needed in octal
        $uid = \sprintf("%6s ", \decoct($uid));
        $gid = \sprintf("%6s ", \decoct($gid));
        $perm = \sprintf("%6s ", \decoct($perm));
        $size = \sprintf("%11s ", \decoct($size));
        $mtime = \sprintf("%11s", \decoct($mtime));
        $data_first = \pack("a100a8a8a8a12A12", $name, $perm, $uid, $gid, $size, $mtime);
        $data_last = \pack("a1a100a6a2a32a32a8a8a155a12", $typeflag, '', 'ustar', '', '', '', '', '', $prefix, "");
        for ($i = 0, $chks = 0; $i < 148; $i++) {
            $chks += \ord($data_first[$i]);
        }
        for ($i = 156, $chks += 256, $j = 0; $i < 512; $i++, $j++) {
            $chks += \ord($data_last[$j]);
        }
        $this->writebytes($data_first);
        $chks = \pack("a8", \sprintf("%6s ", \decoct($chks)));
        $this->writebytes($chks . $data_last);
    }
    /**
     * Creates a FileInfo object from the given parsed header
     *
     * @param $header
     * @return FileInfo
     */
    protected function header2fileinfo($header)
    {
        $fileinfo = new FileInfo();
        $fileinfo->setPath($header['filename']);
        $fileinfo->setMode($header['perm']);
        $fileinfo->setUid($header['uid']);
        $fileinfo->setGid($header['gid']);
        $fileinfo->setSize($header['size']);
        $fileinfo->setMtime($header['mtime']);
        $fileinfo->setOwner($header['uname']);
        $fileinfo->setGroup($header['gname']);
        $headerflag = $header['typeflag'];
        $typeflag = \true;
        if ("P" === $headerflag) {
            $typeflag = \false;
        } else {
            $typeflag = (bool) $header['typeflag'];
        }
        $fileinfo->setIsdir($typeflag);
        return $fileinfo;
    }
    /**
     * Checks if the given compression type is available and throws an exception if not
     *
     * @param $comptype
     * @throws ArchiveIllegalCompressionException
     */
    protected function compressioncheck($comptype)
    {
        if ($comptype === Archive::COMPRESS_GZIP && !\function_exists('gzopen')) {
            throw new ArchiveIllegalCompressionException('No gzip support available');
        }
        if ($comptype === Archive::COMPRESS_BZIP && !\function_exists('bzopen')) {
            throw new ArchiveIllegalCompressionException('No bzip2 support available');
        }
    }
    /**
     * Guesses the wanted compression from the given file
     *
     * Uses magic bytes for existing files, the file extension otherwise
     *
     * You don't need to call this yourself. It's used when you pass Archive::COMPRESS_AUTO somewhere
     *
     * @param string $file
     * @return int
     */
    public function filetype($file)
    {
        // for existing files, try to read the magic bytes
        if (\file_exists($file) && \is_readable($file) && \filesize($file) > 5) {
            $fh = @\fopen($file, 'rb');
            if (!$fh) {
                return \false;
            }
            $magic = \fread($fh, 5);
            \fclose($fh);
            if (\strpos($magic, "BZ") === 0) {
                return Archive::COMPRESS_BZIP;
            }
            if (\strpos($magic, "\x1f\x8b") === 0) {
                return Archive::COMPRESS_GZIP;
            }
        }
        // otherwise rely on file name
        $file = \strtolower($file);
        if (\substr($file, -3) == '.gz' || \substr($file, -4) == '.tgz') {
            return Archive::COMPRESS_GZIP;
        } elseif (\substr($file, -4) == '.bz2' || \substr($file, -4) == '.tbz') {
            return Archive::COMPRESS_BZIP;
        }
        return Archive::COMPRESS_NONE;
    }
}
