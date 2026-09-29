<?php

namespace App\Enums;

enum MediaSource: string
{
    case Commons = 'commons';
    case Upload = 'upload';
    case Url = 'url';
}
