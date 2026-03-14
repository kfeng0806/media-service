<?php

namespace App\Support;

use Vinkla\Hashids\Facades\Hashids;

final class MediaPathGenerator
{
    public static function attachmentPath(int $id, string $extension): string
    {
        $base = config('paths.media.attachment');
        $dir = PathSharder::shard($id, 3);
        $file = Hashids::encode($id).'.'.$extension;

        return "{$base}/{$dir}/{$file}";
    }

    public static function imagePath(int $id, string $extension): string
    {
        $base = config('paths.media.image');
        $dir = PathSharder::shard($id, 3);
        $file = Hashids::encode($id).'.'.$extension;

        return "{$base}/{$dir}/{$file}";
    }

    public static function videoDir(int $id, string $path = ''): string
    {
        $base = config('paths.media.video');
        $dir = PathSharder::shard($id, 3);
        $hash = Hashids::encode($id);

        $full = "{$base}/{$dir}/{$hash}";

        return $path ? "{$full}/".ltrim($path, '/') : $full;
    }

    public static function audioPath(int $id): string
    {
        $base = config('paths.media.audio');
        $dir = PathSharder::shard($id, 3);
        $file = Hashids::encode($id).'.mp3';

        return "{$base}/{$dir}/{$file}";
    }

    public static function audioArtworkPath(int $id): string
    {
        $base = config('paths.media.audio');
        $dir = PathSharder::shard($id, 3);
        $file = Hashids::encode($id).'_artwork.jpg';

        return "{$base}/{$dir}/{$file}";
    }
}
