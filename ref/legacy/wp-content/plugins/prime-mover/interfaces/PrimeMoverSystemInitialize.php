<?php
namespace Codexonics\PrimeMoverFramework\interfaces;

/*
 * This file is part of the Codexonics.PrimeMoverFramework package.
 *
 * (c) Codexonics Ltd
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

if (! defined('ABSPATH')) {
    exit;
}

interface PrimeMoverSystemInitialize
{
    public function multisiteInitializeWpFilesystemApi();
    public function primeMoverCreateFolder();
}
