<?php

namespace App\Support;

use FFMpeg\Format\Video\DefaultVideo;

class RawVideoFormat extends DefaultVideo
{
    public function getAvailableAudioCodecs(): array
    {
        return ['copy', 'aac', 'libfdk_aac', 'libmp3lame'];
    }

    public function getAvailableVideoCodecs(): array
    {
        return ['copy', 'libx264', 'h264_nvenc'];
    }

    public function supportBFrames(): bool
    {
        return false;
    }

    public function getPasses(): int
    {
        return 1;
    }
}
