<?php

namespace Tests\Unit\Library;

require_once __DIR__ . '/AvailabilityTestCase.php';

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;

/**
 * Availability::get_available_periods()
 */
class AvailabilityPeriodsTest extends AvailabilityTestCase
{
    private const DATE = '2030-01-07'; // a Monday

    private function periods(array $provider, ?int $exclude = null): array
    {
        return $this->flatten($this->availability()->get_available_periods(self::DATE, $provider, $exclude));
    }

    private function at(string $time): string
    {
        return self::DATE . ' ' . $time . ':00';
    }

    // ── happy path / working plan ────────────────────────────────────────

    public function testFullWorkingPlanIsOnePeriodWhenNothingIsBooked(): void
    {
        $this->assertSame(['09:00-17:00'], $this->periods($this->provider()));
    }

    public function testDayWithoutWorkingPlanHasNoPeriods(): void
    {
        $provider = $this->providerWithPlan(['tuesday' => ['start' => '09:00', 'end' => '17:00', 'breaks' => []]]);

        $this->assertSame([], $this->periods($provider));
    }

    public function testNullWorkingDayHasNoPeriods(): void
    {
        $provider = $this->providerWithPlan(['monday' => null]);

        $this->assertSame([], $this->periods($provider));
    }

    public function testMiddleBreakSplitsThePlan(): void
    {
        $provider = $this->provider('09:00', '17:00', [['start' => '12:00', 'end' => '13:00']]);

        $this->assertSame(['09:00-12:00', '13:00-17:00'], $this->periods($provider));
    }

    public function testBreakAtTheStartAndAtTheEndOfThePlan(): void
    {
        $provider = $this->provider('09:00', '17:00', [
            ['start' => '09:00', 'end' => '10:00'],
            ['start' => '16:00', 'end' => '17:00'],
        ]);

        $this->assertSame(['10:00-16:00'], $this->periods($provider));
    }

    public function testBreakStartingBeforeThePlanIsClippedToThePlanStart(): void
    {
        $provider = $this->provider('09:00', '17:00', [['start' => '08:00', 'end' => '09:30']]);

        $this->assertSame(['09:30-17:00'], $this->periods($provider));
    }

    public function testBreakWithInvertedBoundsIsIgnored(): void
    {
        $provider = $this->provider('09:00', '17:00', [['start' => '13:00', 'end' => '12:00']]);

        $this->assertSame(['09:00-17:00'], $this->periods($provider));
    }

    public function testTwoNonOverlappingBreaksProduceThreePeriods(): void
    {
        $provider = $this->provider('09:00', '17:00', [
            ['start' => '10:00', 'end' => '10:30'],
            ['start' => '14:00', 'end' => '15:00'],
        ]);

        $this->assertSame(['09:00-10:00', '10:30-14:00', '15:00-17:00'], $this->periods($provider));
    }

    #[Group('known-bug')]
    public function testBug_OverlappingBreaksLeaveAPeriodCoveredByALargerBreak(): void
    {
        $this->skipKnownBug(
            'BUG confirmado: pausas 10:00-10:30 e 09:00-11:00 sobre 09:00-12:00 deixam 09:00-10:00 livre '
                . '(Availability.php:424-440: a condicao "break_start > period_start" e a de "break_end < period_end" '
                . 'nao cobrem uma pausa que comeca no inicio do periodo e termina alem dele, entao o periodo '
                . '09:00-10:00 nao e removido). Esperado: apenas 11:00-12:00.',
        );

        $provider = $this->provider('09:00', '12:00', [
            ['start' => '10:00', 'end' => '10:30'],
            ['start' => '09:00', 'end' => '11:00'],
        ]);

        $this->assertSame(['11:00-12:00'], $this->periods($provider));
    }

    public function testPlanWithoutBreaksKeyYieldsNoPeriodsCurrentBehaviour(): void
    {
        // Documents l.392: periods are only seeded when the "breaks" key exists. A plan with start/end but no
        // "breaks" key therefore has no availability at all (stored plans always carry the key).
        $provider = $this->providerWithPlan(['monday' => ['start' => '09:00', 'end' => '17:00']]);

        $this->assertSame([], $this->periods($provider));
    }

    // ── appointment position relative to the period ──────────────────────

    public function testAppointmentInsideThePeriodSplitsIt(): void
    {
        $this->appointments = [$this->appointment($this->at('12:00'), $this->at('13:00'))];

        $this->assertSame(['09:00-12:00', '13:00-17:00'], $this->periods($this->provider()));
    }

    public function testAppointmentOverlappingTheLeftEdgeTrimsTheStart(): void
    {
        $this->appointments = [$this->appointment($this->at('08:00'), $this->at('10:00'))];

        $this->assertSame(['10:00-17:00'], $this->periods($this->provider()));
    }

    public function testAppointmentStartingAtThePeriodStartTrimsTheStart(): void
    {
        $this->appointments = [$this->appointment($this->at('09:00'), $this->at('10:00'))];

        $this->assertSame(['10:00-17:00'], $this->periods($this->provider()));
    }

    public function testAppointmentOverlappingTheRightEdgeTrimsTheEnd(): void
    {
        $this->appointments = [$this->appointment($this->at('16:00'), $this->at('18:00'))];

        $this->assertSame(['09:00-16:00'], $this->periods($this->provider()));
    }

    public function testAppointmentEndingAtThePeriodEndTrimsTheEnd(): void
    {
        $this->appointments = [$this->appointment($this->at('16:00'), $this->at('17:00'))];

        $this->assertSame(['09:00-16:00'], $this->periods($this->provider()));
    }

    public function testAppointmentSwallowingThePeriodRemovesIt(): void
    {
        $this->appointments = [$this->appointment($this->at('08:00'), $this->at('18:00'))];

        $this->assertSame([], $this->periods($this->provider()));
    }

    public function testMultiDayAppointmentCoveringTheDateRemovesThePeriod(): void
    {
        $this->appointments = [$this->appointment('2030-01-06 15:00:00', '2030-01-08 10:00:00')];

        $this->assertSame([], $this->periods($this->provider()));
    }

    #[Group('known-bug')]
    public function testBug_AppointmentCoincidingWithThePeriodRemovesItCompletely(): void
    {
        $this->skipKnownBug(
            'BUG confirmado: agendamento 09:00-17:00 sobre o periodo 09:00-17:00 deixa o periodo degenerado '
                . '17:00-17:00 em vez de remove-lo (Availability.php:474-481: o ramo "comeca antes e termina dentro" '
                . 'captura o caso coincidente, entao o ramo de igualdade em :497 e inalcancavel).',
        );

        $this->appointments = [$this->appointment($this->at('09:00'), $this->at('17:00'))];

        $this->assertSame([], $this->periods($this->provider()));
    }

    public function testAppointmentBeforeThePeriodDoesNotChangeIt(): void
    {
        $this->appointments = [$this->appointment($this->at('07:00'), $this->at('08:00'))];

        $this->assertSame(['09:00-17:00'], $this->periods($this->provider()));
    }

    public function testAppointmentAfterThePeriodDoesNotChangeIt(): void
    {
        $this->appointments = [$this->appointment($this->at('18:00'), $this->at('19:00'))];

        $this->assertSame(['09:00-17:00'], $this->periods($this->provider()));
    }

    public function testAppointmentWithZeroOrNegativeDurationIsIgnored(): void
    {
        $this->appointments = [
            $this->appointment($this->at('12:00'), $this->at('12:00')),
            $this->appointment($this->at('14:00'), $this->at('13:00')),
        ];

        $this->assertSame(['09:00-17:00'], $this->periods($this->provider()));
    }

    public function testSeveralAppointmentsCarveSeveralGaps(): void
    {
        $this->appointments = [
            $this->appointment($this->at('10:00'), $this->at('11:00')),
            $this->appointment($this->at('14:00'), $this->at('15:00')),
        ];

        $periods = $this->periods($this->provider());
        sort($periods);

        $this->assertSame(['09:00-10:00', '11:00-14:00', '15:00-17:00'], $periods);
    }

    public function testUnavailabilitiesAndBlockedPeriodsAlsoReducePeriods(): void
    {
        $this->unavailabilities = [$this->appointment($this->at('09:00'), $this->at('10:00'))];
        $this->blockedPeriods = [$this->appointment($this->at('16:00'), $this->at('17:00'))];

        $this->assertSame(['10:00-16:00'], $this->periods($this->provider()));
    }

    public function testAppointmentsAreRemovedFromThePeriodsAroundABreak(): void
    {
        $provider = $this->provider('09:00', '17:00', [['start' => '12:00', 'end' => '13:00']]);
        $this->appointments = [$this->appointment($this->at('10:00'), $this->at('11:00'))];

        $periods = $this->periods($provider);
        sort($periods);

        $this->assertSame(['09:00-10:00', '11:00-12:00', '13:00-17:00'], $periods);
    }

    // ── working plan exceptions ──────────────────────────────────────────

    public function testExceptionWithNullValueMakesTheDayADayOff(): void
    {
        $this->exceptions = [self::DATE => null];

        $this->assertSame([], $this->periods($this->provider()));
    }

    public function testExceptionWithOwnHoursReplacesTheWeeklyPlan(): void
    {
        $this->exceptions = [self::DATE => ['start' => '10:00', 'end' => '14:00', 'breaks' => []]];

        $this->assertSame(['10:00-14:00'], $this->periods($this->provider()));
    }

    public function testExceptionWithBreaksIsSplitByThem(): void
    {
        $this->exceptions = [
            self::DATE => ['start' => '10:00', 'end' => '14:00', 'breaks' => [['start' => '11:00', 'end' => '12:00']]],
        ];

        $this->assertSame(['10:00-11:00', '12:00-14:00'], $this->periods($this->provider()));
    }

    public function testExceptionOnAnotherDateDoesNotAffectTheSelectedDate(): void
    {
        $this->exceptions = ['2030-01-08' => null];

        $this->assertSame(['09:00-17:00'], $this->periods($this->provider()));
    }

    public function testExceptionCanOpenADayOutsideTheWeeklyPlan(): void
    {
        $provider = $this->providerWithPlan(['tuesday' => ['start' => '09:00', 'end' => '17:00', 'breaks' => []]]);
        $this->exceptions = [self::DATE => ['start' => '08:00', 'end' => '10:00', 'breaks' => []]];

        $this->assertSame(['08:00-10:00'], $this->periods($provider));
    }

    // ── input validation and exclude_appointment_id ──────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidDates')]
    public function testInvalidDateFormatThrows(string $date): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->availability()->get_available_periods($date, $this->provider());
    }

    public static function invalidDates(): array
    {
        return [
            'empty' => [''],
            'text' => ['abc'],
            'day first' => ['07-01-2030'],
            'no zero padding' => ['2030-1-7'],
            'with time' => ['2030-01-07 10:00'],
            'sql injection' => ["2030-01-07' OR '1'='1"],
            'leading space' => [' 2030-01-07'],
        ];
    }

    #[Group('known-bug')]
    public function testBug_DateWithTrailingNewlineIsRejected(): void
    {
        $this->skipKnownBug(
            'BUG confirmado: "2030-01-07\n" passa na validacao de data porque "$" aceita uma quebra de linha final '
                . '(Availability.php:350, regex sem \\z nem modificador D).',
        );

        $this->expectException(InvalidArgumentException::class);

        $this->availability()->get_available_periods("2030-01-07\n", $this->provider());
    }

    public function testExcludedAppointmentIdIsAddedToTheQuery(): void
    {
        $this->periods($this->provider(), 42);

        $this->assertSame(42, $this->appointmentWheres[0]['id !=']);
        $this->assertSame(42, $this->unavailabilityWheres[0]['id !=']);
        $this->assertSame(7, $this->appointmentWheres[0]['id_users_provider']);
        $this->assertSame(self::DATE, $this->appointmentWheres[0]['DATE(start_datetime) <=']);
        $this->assertSame(self::DATE, $this->appointmentWheres[0]['DATE(end_datetime) >=']);
    }

    public function testNoExclusionClauseWhenIdIsNullOrZero(): void
    {
        $this->periods($this->provider(), null);
        $this->periods($this->provider(), 0);

        $this->assertArrayNotHasKey('id !=', $this->appointmentWheres[0]);
        $this->assertArrayNotHasKey('id !=', $this->appointmentWheres[1]);
    }
}
