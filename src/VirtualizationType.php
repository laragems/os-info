<?php

declare(strict_types=1);

namespace Laragems\OsInfo;

enum VirtualizationType: string
{
    case BareMetal = 'bare_metal';
    case Container = 'container';
    case VirtualMachine = 'virtual_machine';
    case Subsystem = 'subsystem';
    case Unknown = 'unknown';
}

