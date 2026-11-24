<?php

namespace Tests\Unit\Library;

require_once __DIR__ . '/AvailabilityTestCase.php';

use DateTime;
use DateTimeZone;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Availability::consider_book_advance_timeout() and consider_future_booking_limit().
 *
 * "Now" is not injectable, so every case builds its slots from the current instant (formatted with the date and the
 * H:i of that same instant) and keeps at least two minutes of margin from the threshold.
 */
class AvailabilityLimitsTest extends AvailabilityTestCase
{
    private function provider_with_timezone(string $timezone = 'UTC'): array
    {
        return ['timezone' => $timezone];
    }

    /** Instant "now + $minutes" in the given timezone. */
    private function instant(int $minutes, string $timezone = 'UTC'): DateTime
    {
        return (new DateTime('now', new DateTimeZone($timezone)))->modify(sprintf('%+d minutes', $minutes));
    }

    private function timeout(string $date, array $hours, string $timezone = 'UTC'): array
    {
        return $this->callProtected(
            $this->availability(),
            'consider_book_advance_timeout',
            $date,
            $hours,
            $this->provider_with_timezone($timezone),
        );
    }

    private function limit(string $date, array $hours = ['10:00'], string $timezone = 'UTC'): array
    {
        return $this->callProtected(
            $this->availability(),
            'consider_future_booking_limit',
            $date,
            $hours,
            $this->provider_with_timezone($timezone),
        );
    }

    // ── consider_book_advance_timeout ────────────────────────────────────

    public function testTimeoutRemovesPastSlotsAndKeepsFutureOnes(): void
    {
        $past = $this->instant(-120);
        $future = $this->instant(+180);

        $this->assertSame([], $this->timeout($past->format('Y-m-d'), [$past->format('H:i')]));
        $this->assertSame(
            [$future->format('H:i')],
            $this->timeout($future->format('Y-m-d'), [$future->format('H:i')]),
        );
    }

    public function testTimeoutSlotExactlyAtTheThresholdIsRemoved(): void
    {
        $GLOBALS['ea_test_settings']['book_advance_timeout'] = '60';

        // The slot is the threshold truncated to the minute, so it is never after the real threshold (<=).
        $slot = $this->instant(60);
        $beyond = $this->instant(63);

        $this->assertSame([], $this->timeout($slot->format('Y-m-d'), [$slot->format('H:i')]));
        $this->assertSame(
            [$beyond->format('H:i')],
            $this->timeout($beyond->format('Y-m-d'), [$beyond->format('H:i')]),
        );
    }

    public function testTimeoutHidesEverythingInsideTheWindow(): void
    {
        $GLOBALS['ea_test_settings']['book_advance_timeout'] = 120;

        $inside = $this->instant(90);

        $this->assertSame([], $this->timeout($inside->format('Y-m-d'), [$inside->format('H:i')]));
    }

    public function testTimeoutOfZeroOnlyRemovesTheCurrentMinuteAndThePast(): void
    {
        $GLOBALS['ea_test_settings']['book_advance_timeout'] = '0';

        $now = $this->instant(0);
        $soon = $this->instant(3);

        $this->assertSame([], $this->timeout($now->format('Y-m-d'), [$now->format('H:i')]));
        $this->assertSame([$soon->format('H:i')], $this->timeout($soon->format('Y-m-d'), [$soon->format('H:i')]));
    }

    public static function invalidTimeouts(): array
    {
        return [
            'missing' => [null],
            'text' => ['abc'],
            'empty string' => [''],
            'negative' => ['-500'],
            'array' => [[]],
        ];
    }

    #[DataProvider('invalidTimeouts')]
    public function testMissingOrInvalidTimeoutBehavesLikeZero(mixed $value): void
    {
        if ($value !== null) {
            $GLOBALS['ea_test_settings']['book_advance_timeout'] = $value;
        }

        $soon = $this->instant(3);
        $now = $this->instant(0);

        $this->assertSame([$soon->format('H:i')], $this->timeout($soon->format('Y-m-d'), [$soon->format('H:i')]));
        $this->assertSame([], $this->timeout($now->format('Y-m-d'), [$now->format('H:i')]));
    }

    public function testNumericStringTimeoutIsAccepted(): void
    {
        $GLOBALS['ea_test_settings']['book_advance_timeout'] = '30';

        $inside = $this->instant(20);
        $outside = $this->instant(35);

        $this->assertSame([], $this->timeout($inside->format('Y-m-d'), [$inside->format('H:i')]));
        $this->assertSame(
            [$outside->format('H:i')],
            $this->timeout($outside->format('Y-m-d'), [$outside->format('H:i')]),
        );
    }

    public function testTimeoutReturnsSortedReindexedHours(): void
    {
        $date = $this->instant(60 * 24 * 5)->format('Y-m-d');

        $this->assertSame(['09:00', '12:00', '15:00'], $this->timeout($date, ['15:00', '09:00', '12:00']));
    }

    public function testTimeoutOfEmptyListIsEmpty(): void
    {
        $this->assertSame([], $this->timeout('2099-01-01', []));
    }

    public function testTimeoutInterpretsSlotsInTheProviderTimezone(): void
    {
        // 30 minutes ago on the wall clock of Kiritimati (UTC+14).
        $slot = $this->instant(-30, 'Pacific/Kiritimati');
        $date = $slot->format('Y-m-d');
        $hour = $slot->format('H:i');

        // Same wall clock read in Kiritimati is in the past; read as UTC it is ~13.5h in the future.
        $this->assertSame([], $this->timeout($date, [$hour], 'Pacific/Kiritimati'));
        $this->assertSame([$hour], $this->timeout($date, [$hour], 'UTC'));
    }

    public function testTimeoutWithAnInvalidProviderTimezoneThrows(): void
    {
        $this->expectException(Exception::class);

        $this->timeout('2099-01-01', ['10:00'], 'Not/AZone');
    }

    // ── consider_future_booking_limit ────────────────────────────────────

    private function day(int $offsetDays): string
    {
        return $this->instant(0)->modify(sprintf('%+d days', $offsetDays))->format('Y-m-d');
    }

    public function testDefaultLimitIsNinetyDays(): void
    {
        $this->assertSame(['10:00'], $this->limit($this->day(89)));
        $this->assertSame(['10:00'], $this->limit($this->day(90)));
        $this->assertSame([], $this->limit($this->day(92)));
    }

    public function testConfiguredLimitDayItselfIsStillBookable(): void
    {
        $GLOBALS['ea_test_settings']['future_booking_limit'] = '30';

        $this->assertSame(['10:00'], $this->limit($this->day(29)));
        $this->assertSame(['10:00'], $this->limit($this->day(30)));
    }

    public function testDayAfterTheConfiguredLimitIsNotBookable(): void
    {
        $GLOBALS['ea_test_settings']['future_booking_limit'] = '30';

        $this->assertSame([], $this->limit($this->day(32)));
        $this->assertSame([], $this->limit($this->day(400)));
    }

    public function testLimitOfZeroAllowsTodayButNotTomorrow(): void
    {
        $GLOBALS['ea_test_settings']['future_booking_limit'] = '0';

        $this->assertSame(['10:00'], $this->limit($this->day(0)));
        $this->assertSame([], $this->limit($this->day(1)));
    }

    public static function invalidLimits(): array
    {
        return ['text' => ['abc'], 'empty string' => [''], 'array' => [[]]];
    }

    #[DataProvider('invalidLimits')]
    public function testNonNumericLimitFallsBackToNinetyDays(mixed $value): void
    {
        $GLOBALS['ea_test_settings']['future_booking_limit'] = $value;

        $this->assertSame(['10:00'], $this->limit($this->day(80)));
        $this->assertSame([], $this->limit($this->day(95)));
    }

    public function testNegativeLimitIsTreatedAsZero(): void
    {
        $GLOBALS['ea_test_settings']['future_booking_limit'] = '-10';

        $this->assertSame(['10:00'], $this->limit($this->day(0)));
        $this->assertSame([], $this->limit($this->day(2)));
    }

    public function testPastDatesAreWithinTheLimit(): void
    {
        $this->assertSame(['10:00', '11:00'], $this->limit($this->day(-5), ['10:00', '11:00']));
    }

    public function testLimitKeepsTheHoursUntouched(): void
    {
        $hours = ['15:00', '09:00'];

        $this->assertSame($hours, $this->limit($this->day(1), $hours));
    }

    public function testLimitWithAnInvalidProviderTimezoneThrows(): void
    {
        $this->expectException(Exception::class);

        $this->limit($this->day(1), ['10:00'], 'Not/AZone');
    }

    #[Group('known-bug')]
    public function testBug_FutureLimitOfZeroKeepsTodayInTheProviderTimezone(): void
    {
        $this->skipKnownBug(
            'BUG hipotese em teste: consider_future_booking_limit interpreta a data selecionada no fuso padrao do PHP '
                . 'e nao no do provider (Availability.php:643, new DateTime($selected_date) sem $provider_timezone). '
                . 'Com fuso padrao diferente do do provider, "hoje" do provider pode ser tratado como futuro.',
        );

        // Default zone UTC-11 and provider zone UTC+14: the provider's "today" is always a later calendar day than
        // the default zone's, so its midnight (in the default zone) is still in the future.
        date_default_timezone_set('Pacific/Pago_Pago');
        $GLOBALS['ea_test_settings']['future_booking_limit'] = '0';

        $providerToday = (new DateTime('now', new DateTimeZone('Pacific/Kiritimati')))->format('Y-m-d');

        $this->assertSame(['10:00'], $this->limit($providerToday, ['10:00'], 'Pacific/Kiritimati'));
    }
}
