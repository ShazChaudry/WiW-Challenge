<?php

namespace App\Controller;

use DateTime;
use DateTimeZone;
use Symfony\Component\HttpFoundation\JsonResponse;

class ShiftController
{
    public const EMPLOYEE_ID = 'EmployeeID';
    public const SHIFT_ID = 'ShiftID';
    public const START_TIME = 'StartTime';
    public const END_TIME = 'EndTime';
    public const START_OF_WEEK = 'StartOfWeek';
    public const REGULAR_HOURS = 'RegularHours';
    public const OVERTIME_HOURS = 'OvertimeHours';
    public const INVALID_SHIFTS = 'InvalidShifts';

    public function processShifts()
    {
        $rawDataFilePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'dataset.json';
        if (!file_exists($rawDataFilePath)) {
            return new JsonResponse(['error' => "The dataset file was not found"], JsonResponse::HTTP_NOT_FOUND);
        }

        $fileContent = file_get_contents($rawDataFilePath);
        if ($fileContent === false) {
            return new JsonResponse(['error' => "Unable to read the dataset file"], JsonResponse::HTTP_BAD_REQUEST);
        }

        $rawShifts = json_decode($fileContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => "Unable to decode JSON file: " . json_last_error_msg()], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (empty($rawShifts)) {
            return new JsonResponse(['error' => "The JSON file was empty."], JsonResponse::HTTP_BAD_REQUEST);
        }

        usort($rawShifts, function ($a, $b) {
            $startTimeA = DateTime::createFromFormat(DateTime::RFC3339, $a[self::START_TIME]);
            $startTimeB = DateTime::createFromFormat(DateTime::RFC3339, $b[self::START_TIME]);
            return $startTimeA <=> $startTimeB;
        });

        $summaryByEmployeeByWeek = [];
        $centralTimeZone = new DateTimeZone('America/Chicago');
        for ($i = 0; $i < count($rawShifts) - 1; $i++) {
            $currentShift = $rawShifts[$i];
            $currentStartTime = new DateTime($currentShift[self::START_TIME]);
            $currentStartTime->setTimezone($centralTimeZone);
            $currentEndTime = new DateTime($currentShift[self::END_TIME]);
            $currentEndTime->setTimezone($centralTimeZone);
            $nextShift = $rawShifts[$i + 1];
            $currentWeekStartDate = (clone $currentStartTime)->modify('last Sunday')->format('Y-m-d');
            $nextWeekStartDate = (clone $currentStartTime)->modify('this Sunday midnight');

            if (!isset($summaryByEmployeeByWeek[$currentShift[self::EMPLOYEE_ID]][$currentWeekStartDate])) {
                $summaryByEmployeeByWeek[$currentShift[self::EMPLOYEE_ID]][$currentWeekStartDate] = [
                    self::START_OF_WEEK => $currentWeekStartDate,
                    self::REGULAR_HOURS => 0,
                    self::OVERTIME_HOURS => 0,
                    self::INVALID_SHIFTS => [],
                ];
            }

            $nextShiftStartTime = new DateTime($nextShift[self::START_TIME]);
            $nextShiftStartTime->setTimezone($centralTimeZone);
            $nextShiftWeekStartDate = (clone $currentStartTime)->modify('last Sunday')->format('Y-m-d');
            if ($currentShift[self::EMPLOYEE_ID] == $nextShift[self::EMPLOYEE_ID] && $currentEndTime > $nextShiftStartTime) {
                $summaryByEmployeeByWeek[$currentShift[self::EMPLOYEE_ID]][$currentWeekStartDate][self::INVALID_SHIFTS][] = $currentShift[self::SHIFT_ID];

                if (!isset($summaryByEmployeeByWeek[$currentShift[self::EMPLOYEE_ID]][$nextShiftWeekStartDate])) {
                    $summaryByEmployeeByWeek[$currentShift[self::EMPLOYEE_ID]][$nextShiftWeekStartDate] = [
                        self::START_OF_WEEK => $nextShiftWeekStartDate,
                        self::REGULAR_HOURS => 0,
                        self::OVERTIME_HOURS => 0,
                        self::INVALID_SHIFTS => [],
                    ];
                }
                $summaryByEmployeeByWeek[$nextShift[self::EMPLOYEE_ID]][$nextShiftWeekStartDate][self::INVALID_SHIFTS][] = $nextShift[self::SHIFT_ID];

                continue;
            }

            if ($currentEndTime > $nextWeekStartDate) {
                $hoursBeforeMidnight = $nextWeekStartDate->diff($currentStartTime)->h;
                $hoursAfterMidnight = $currentEndTime->diff($nextWeekStartDate)->h;

                $dstOccurredForBeforeMidnight = $currentStartTime->format('I') != $nextWeekStartDate->format('I');
                if ($dstOccurredForBeforeMidnight) {
                    $hoursBeforeMidnight = ($currentStartTime->format('I') > $nextWeekStartDate->format('I')) ? 1 : -1;
                }

                $dstOccurredForAfterMidnight = $nextWeekStartDate->format('I') != $currentEndTime->format('I');
                if ($dstOccurredForAfterMidnight) {
                    $hoursAfterMidnight = ($nextWeekStartDate->format('I') > $currentEndTime->format('I')) ? 1 : -1;
                }

                $totalRegularHoursThisWeek = $summaryByEmployeeByWeek[$currentShift[self::EMPLOYEE_ID]][$currentWeekStartDate][self::REGULAR_HOURS] ?? 0;
                $regularHoursToAdd = min($hoursBeforeMidnight, max(40 - $totalRegularHoursThisWeek, 0));
                $overtimeHoursToAdd = max($hoursBeforeMidnight - $regularHoursToAdd, 0);
                $summaryByEmployeeByWeek[$currentShift[self::EMPLOYEE_ID]][$currentWeekStartDate][self::REGULAR_HOURS] += $regularHoursToAdd;
                $summaryByEmployeeByWeek[$currentShift[self::EMPLOYEE_ID]][$currentWeekStartDate][self::OVERTIME_HOURS] += $overtimeHoursToAdd;

                if (!isset($summaryByEmployeeByWeek[$currentShift[self::EMPLOYEE_ID]][$nextWeekStartDate->format('Y-m-d')])) {
                    $summaryByEmployeeByWeek[$currentShift[self::EMPLOYEE_ID]][$nextWeekStartDate->format('Y-m-d')] = [
                        self::START_OF_WEEK => $nextWeekStartDate->format('Y-m-d'),
                        self::REGULAR_HOURS => 0,
                        self::OVERTIME_HOURS => 0,
                        self::INVALID_SHIFTS => [],
                    ];
                }

                $totalRegularHoursNextWeek = $summaryByEmployeeByWeek[$currentShift[self::EMPLOYEE_ID]][$nextWeekStartDate->format('Y-m-d')][self::REGULAR_HOURS] ?? 0;
                $regularHoursToAdd = min($hoursAfterMidnight, max(40 - $totalRegularHoursNextWeek, 0));
                $overtimeHoursToAdd = max($hoursAfterMidnight - $regularHoursToAdd, 0);
                $summaryByEmployeeByWeek[$currentShift[self::EMPLOYEE_ID]][$nextWeekStartDate->format('Y-m-d')][self::REGULAR_HOURS] += $regularHoursToAdd;
                $summaryByEmployeeByWeek[$currentShift[self::EMPLOYEE_ID]][$nextWeekStartDate->format('Y-m-d')][self::OVERTIME_HOURS] += $overtimeHoursToAdd;
            } else {
                $dstOccurred = $currentStartTime->format('I') != $currentEndTime->format('I');
                $hoursAdjustmentForDST = 0;
                if ($dstOccurred) {
                    $hoursAdjustmentForDST = ($currentStartTime->format('I') > $currentEndTime->format('I')) ? 1 : -1;
                }

                $totalRegularHoursThisWeek = $summaryByEmployeeByWeek[$currentShift[self::EMPLOYEE_ID]][$currentWeekStartDate][self::REGULAR_HOURS] ?? 0;
                $hoursWorked = $currentEndTime->diff($currentStartTime)->h + $hoursAdjustmentForDST;;
                $regularHoursToAdd = min($hoursWorked, max(40 - $totalRegularHoursThisWeek, 0));
                $overtimeHoursToAdd = max($hoursWorked - $regularHoursToAdd, 0);

                $summaryByEmployeeByWeek[$currentShift[self::EMPLOYEE_ID]][$currentWeekStartDate][self::REGULAR_HOURS] += $regularHoursToAdd;
                $summaryByEmployeeByWeek[$currentShift[self::EMPLOYEE_ID]][$currentWeekStartDate][self::OVERTIME_HOURS] += $overtimeHoursToAdd;
            }
        }

        $output = [];
        foreach ($summaryByEmployeeByWeek as $employeeId => $weeks) {
            foreach ($weeks as $weekStartDate => $summary) {
                $employeeData = [
                    "EmployeeID" => $employeeId,
                    "StartOfWeek" => $weekStartDate,
                    "RegularHours" => $summary[self::REGULAR_HOURS],
                    "OvertimeHours" => $summary[self::OVERTIME_HOURS],
                    "InvalidShifts" => $summary[self::INVALID_SHIFTS],
                ];
                $output[] = $employeeData;
            }
        }

        return new JsonResponse($output);
    }
}
