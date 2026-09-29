<?php

namespace App\Enums;

/**
 * Imported and new content starts as a draft; only published rows appear on the public site.
 */
enum PublishStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
