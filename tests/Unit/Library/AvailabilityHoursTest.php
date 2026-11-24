<?php

namespace Tests\Unit\Library;

require_once __DIR__ . '/AvailabilityTestCase.php';

use DateTime;
use DateTimeZone;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Availability::get_available_hours() and consider_multiple_attendants()
 *
 * Dates are always relative to "now" (a few days ahead) so the booking timeout and the future booking limit never
 * interfere.
 */
class AvailabilityHoursTest extends AvailabilityTestCase
{
    private function futureDate(int $days = 3): string
    {
        return (new DateTime('now', new DateTimeZone('UTC')))->modify("+{$days} days")->format('Y-m-d');
    }

    private function service(array $overrides = []): array
    {
        return array_merge(['id' => 3, 'duration' => 60, 'slot_interval' => 60, 'attendants_number' => 1], $overrides);
    }

    // ── get_available_hours: single attendant ────────────────────────────

    public function testSingleAttendantHoursFollowTheWorkingPlan(): void
    {
        $hours = $this->availability()->get_available_hours(
            $this->futureDate(),
            $this->service(),
            $this->provider('09:00', '12:00'),
        );

        $this->assertSame(['09:00', '10:00', '11:00'], $hours);
    }

    public function testSingleAttendantDefaultsToFifteenMinuteSlots(): void
    {
        $hours = $this->availability()->get_available_hours(
            $this->futureDate(),
            $this->service(['duration' => 30, 'slot_interval' => null]),
            $this->provider('09:00', '10:00'),
        );

        $this->assertSame(['09:00', '09:15', '09:30'], $hours);
    }

    public function testSingleAttendantHoursSkipBookedAppointments(): void
    {
        $date = $this->futureDate();
        $this->appointments = [$this->appointment("$date 10:00:00", "$date 11:00:00")];

        $hours = $this->availability()->get_available_hours(
            $date,
            $this->service(),
            $this->provider('09:00', '12:00'),
        );

        $this->assertSame(['09:00', '11:00'], $hours);
    }

    public function testEntireDayBlockedReturnsNoHoursWithoutLookingAtTheSchedule(): void
    {
        $this->entireDateBlocked = true;

        $hours = $this->availability()->get_available_hours(
            $this->futureDate(),
            $this->service(),
            $this->provider(),
        );

        $this->assertSame([], $hours);
        $this->assertSame([], $this->appointmentWheres, 'The schedule must not even be queried for a blocked day.');
    }

    public function testEntireDayBlockedAlsoShortCircuitsMultipleAttendants(): void
    {
        $this->entireDateBlocked = true;

        $hours = $this->availability()->get_available_hours(
            $this->futureDate(),
            $this->service(['attendants_number' => 3]),
            $this->provider(),
        );

        $this->assertSame([], $hours);
        $this->assertSame([], $this->otherServiceCalls);
    }

    public function testDayOffReturnsNoHours(): void
    {
        $date = $this->futureDate();
        $this->exceptions = [$date => null];

        $this->assertSame([], $this->availability()->get_available_hours($date, $this->service(), $this->provider()));
        $this->assertSame(
            [],
            $this->availability()->get_available_hours($date, $this->service(['attendants_number' => 2]), $this->provider()),
        );
    }

    public function testDateBeyondTheFutureBookingLimitReturnsNoHours(): void
    {
        $hours = $this->availability()->get_available_hours(
            $this->futureDate(100),
            $this->service(),
            $this->provider(),
        );

        $this->assertSame([], $hours);
    }

    // ── exclude_appointment_id normalisation ─────────────────────────────

    public static function excludeIdsForSingleAttendant(): array
    {
        return [
            'null' => [null, false, null],
            'empty string' => ['', false, null],
            'zero string' => ['0', false, null],
            'non numeric' => ['abc', false, null],
            'int zero' => [0, false, null],
            'numeric string' => ['5', true, 5],
            'int' => [8, true, 8],
        ];
    }

    #[DataProvider('excludeIdsForSingleAttendant')]
    public function testExcludeAppointmentIdIsNormalisedForSingleAttendant(
        int|string|null $input,
        bool $expectsClause,
        ?int $expectedId,
    ): void {
        $this->availability()->get_available_hours($this->futureDate(), $this->service(), $this->provider(), $input);

        if ($expectsClause) {
            $this->assertSame($expectedId, $this->appointmentWheres[0]['id !=']);
        } else {
            $this->assertArrayNotHasKey('id !=', $this->appointmentWheres[0]);
        }
    }

    public static function excludeIdsForMultipleAttendants(): array
    {
        return [
            'null' => [null, null],
            'empty string' => ['', null],
            'zero string' => ['0', 0],
            'non numeric' => ['abc', 0],
            'numeric string' => ['12', 12],
            'int' => [9, 9],
        ];
    }

    #[DataProvider('excludeIdsForMultipleAttendants')]
    public function testExcludeAppointmentIdIsNormalisedForMultipleAttendants(
        int|string|null $input,
        ?int $expected,
    ): void {
        $this->availability()->get_available_hours(
            $this->futureDate(),
            $this->service(['attendants_number' => 2]),
            $this->provider('09:00', '10:00'),
            $input,
        );

        $this->assertNotEmpty($this->otherServiceCalls);
        $this->assertSame($expected, $this->otherServiceCalls[0][1]);
    }

    // ── multiple attendants ──────────────────────────────────────────────

    private function multi(array $serviceOverrides = [], ?array $provider = null): array
    {
        return $this->availability()->get_available_hours(
            $this->futureDate(),
            $this->service(array_merge(['attendants_number' => 3], $serviceOverrides)),
            $provider ?? $this->provider('09:00', '12:00'),
        );
    }

    public function testMultipleAttendantsWithNoBookingsOffersEverySlot(): void
    {
        $this->assertSame(['09:00', '10:00', '11:00'], $this->multi());
    }

    public function testMultipleAttendantsSlotWithFreeSeatsIsStillOffered(): void
    {
        $this->attendantsBySlot = ['09:00' => 2];

        $this->assertSame(['09:00', '10:00', '11:00'], $this->multi());
    }

    public function testMultipleAttendantsFullSlotIsHidden(): void
    {
        $this->attendantsBySlot = ['09:00' => 3, '10:00' => 4];

        $this->assertSame(['11:00'], $this->multi());
    }

    public function testMultipleAttendantsSlotTakenByAnotherServiceIsHidden(): void
    {
        $this->otherServiceBySlot = ['10:00' => 1];

        $this->assertSame(['09:00', '11:00'], $this->multi());
    }

    public function testMultipleAttendantsRespectsBreaks(): void
    {
        $provider = $this->provider('09:00', '12:00', [['start' => '10:00', 'end' => '11:00']]);

        $this->assertSame(['09:00', '11:00'], $this->multi([], $provider));
    }

    public function testMultipleAttendantsRespectsUnavailabilityEvents(): void
    {
        $date = $this->futureDate();
        $this->unavailabilities = [$this->appointment("$date 09:00:00", "$date 10:00:00")];

        $this->assertSame(['10:00', '11:00'], $this->multi());
    }

    public function testMultipleAttendantsRespectsBlockedPeriods(): void
    {
        $date = $this->futureDate();
        $this->blockedPeriods = [$this->appointment("$date 11:00:00", "$date 13:00:00")];

        $this->assertSame(['09:00', '10:00'], $this->multi());
    }

    public function testMultipleAttendantsUsesTheExceptionHours(): void
    {
        $this->exceptions = [
            $this->futureDate() => ['start' => '14:00', 'end' => '16:00', 'breaks' => []],
        ];

        $this->assertSame(['14:00', '15:00'], $this->multi());
    }

    public function testMultipleAttendantsDefaultsToFifteenMinuteInterval(): void
    {
        $hours = $this->multi(
            ['duration' => 30, 'slot_interval' => ''],
            $this->provider('09:00', '10:00'),
        );

        $this->assertSame(['09:00', '09:15', '09:30'], $hours);
    }

    public function testMultipleAttendantsServiceLongerThanThePlanOffersNothing(): void
    {
        $this->assertSame([], $this->multi(['duration' => 240]));
    }

    public function testExactlyTwoAttendantsServiceIsTreatedAsMultiple(): void
    {
        $this->attendantsBySlot = ['09:00' => 2];

        $hours = $this->multi(['attendants_number' => 2], $this->provider('09:00', '11:00'));

        $this->assertSame(['10:00'], $hours);
    }
}
