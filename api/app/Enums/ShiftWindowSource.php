<?php

namespace App\Enums;

enum ShiftWindowSource: string
{
    case Exception = 'exception';
    case EmployeeShift = 'employee_shift';
    case DefaultTemplate = 'default_template';
}
