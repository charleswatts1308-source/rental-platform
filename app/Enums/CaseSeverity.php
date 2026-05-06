<?php

namespace App\Enums;

enum CaseSeverity: string
{
    case Routine = 'routine';
    case Serious = 'serious';
    case Emergency = 'emergency';
}
