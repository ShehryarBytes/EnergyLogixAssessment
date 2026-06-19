<?php

namespace App\Enums;

enum FormulaStatus: string
{
    case Draft    = 'draft';
    case Active   = 'active';
    case Archived = 'archived';
}
