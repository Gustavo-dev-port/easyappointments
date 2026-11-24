<?php

namespace Tests\Unit\Library;

use BookingPolicyValidator;
use DateTimeImmutable;
use Tests\TestCase;

class BookingPolicyValidatorTest extends TestCase
{

    private function validator(): BookingPolicyValidator
    {
        require_once APPPATH . 'libraries/BookingPolicyValidator.php';

        return new BookingPolicyValidator(120);

    }

    public function testRejectsBookingWithLessThanTwoHoursAdvance(): void
    {
        $now = new DateTimeImmutable('2026-12-01 08:00:00');
        $start = $now->modify('+119 minutes');

        $violations = $this->validator()->validate($start, $now);

        $this->assertSame(['RN11'], $violations);
    }

    public function testAcceptsBookingWithExactlyTwoHoursAdvance(): void
    {
        $now = new DateTimeImmutable('2026-12-01 08:00:00');
        $start = $now->modify('+120 minutes');

        $violations = $this->validator()->validate($start, $now);

        $this->assertSame([], $violations);
    }
}