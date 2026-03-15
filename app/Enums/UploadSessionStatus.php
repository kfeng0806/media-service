<?php

namespace App\Enums;

enum UploadSessionStatus: string
{
    case Pending = 'pending';
    case Uploaded = 'uploaded';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
