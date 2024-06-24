<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Controller\ShiftController;

$shiftController = new ShiftController();
echo $shiftController->processShifts();