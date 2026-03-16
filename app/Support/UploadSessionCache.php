<?php

namespace App\Support;

final class UploadSessionCache
{
    public static function metadataKey(int $uploadSessionId): string
    {
        return "upload_session:{$uploadSessionId}:metadata";
    }
}
