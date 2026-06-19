<?php

namespace App\Enums;

enum AnalysisStatus: string
{
    case Pending  = 'pending';
    case Complete = 'complete';
    case Failed   = 'failed';
}
