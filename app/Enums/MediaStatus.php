<?php

namespace App\Enums;

/**
 * Photo lifecycle. Rejected photos keep their row (files deleted) so the Commons importer
 * remembers them and never downloads the same image again.
 */
enum MediaStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Rejected = 'rejected';
}
