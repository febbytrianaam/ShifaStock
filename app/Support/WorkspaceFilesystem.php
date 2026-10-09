<?php

namespace App\Support;

use Illuminate\Filesystem\Filesystem;

/** Keep literal workspace brackets from becoming glob character classes. */
class WorkspaceFilesystem extends Filesystem
{
    public function glob($pattern, $flags = 0)
    {
        $base = str_replace('\\', '/', base_path());
        $pattern = str_replace('\\', '/', $pattern);
        if (str_starts_with($pattern, $base.'/')) {
            $pattern = strtr($base, ['[' => '[[]', ']' => '[]]']).substr($pattern, strlen($base));
        }

        return parent::glob($pattern, $flags);
    }
}
