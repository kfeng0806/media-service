<?php

namespace App\Support;

final class PathSharder
{
    public static function shard(
        int $id,
        int $segmentLength,
        int $depth = 2
    ): string {
        $totalLength = $segmentLength * $depth;

        $padded = str_pad(
            (string) $id,
            $totalLength + 3,
            '0',
            STR_PAD_LEFT
        );

        return implode(
            '/',
            str_split(substr($padded, 0, $totalLength), $segmentLength)
        );
    }
}
