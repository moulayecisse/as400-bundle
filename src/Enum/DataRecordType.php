<?php

namespace Cisse\Bundle\As400\Enum;

enum DataRecordType: string
{
    case INSERT = 'insert';
    case UPDATE = 'update';
    case DELETE = 'delete';
}

