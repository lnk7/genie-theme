<?php

namespace Theme\Utils;

class File
{


    /**
     * Maybe make a directory
     *
     * @param $folder
     * @param int $permissions
     * @param bool $recursive
     */
    public static function maybeCreateFolder($folder, $permissions = 0755, $recursive = true)
    {

        if (!is_dir($folder)) {
            mkdir($folder, $permissions, $recursive);
        }
    }
}
