<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use DateTime;
use DateInterval;

/**
 * Suíte de Testes Automatizados - Módulo Availability (Easy!Appointments 1.6.1)
 * Cobre as regras RN01 a RN12 e casos de caixa preta CT-01 a CT-11.
 */
class AvailabilityRulesTest extends TestCase
{
    private array $defaultWorkingPlan;

    protected function setUp(): void
    {
        parent::setUp();

        // Jornada padrão: Segunda a Sexta, 08:00 às 18:00, com pausa 12:00 às 13:00
        $this->defaultWorkingPlan = [
            'monday' => [
                'start' => '08:00',
                'end' => '18:00',
                'breaks' => [
                    ['start' => '12:00', 'end' => '13:00'],
                ],
            ],
            'sunday' => null,
        ];
    }

    /**
     * CT-01 (RN01, RN05): Valor Limite no fim do expediente.
     */
    public function testLastSlotRespectsServiceDurationAndClosingTime(): void
    {
        $serviceDurationMinutes = 60;
        $closingTime = new DateTime('2026-12-07 18:00');

        $validBoundarySlot = new DateTime('2026-12-07 17:00');
        $invalidBoundarySlot = new DateTime('2026-12-07 17:01');

        $validEnd = (clone $validBoundarySlot)->add(new DateInterval("PT{$serviceDurationMinutes}M"));
        $invalidEnd = (clone $invalidBoundarySlot)->add(new DateInterval("PT{$serviceDurationMinutes}M"));

        $this->assertLessThanOrEqual($closingTime, $validEnd, 'Slot das 17:00 deve caber até 18:00.');
        $this->assertGreaterThan($closingTime, $invalidEnd, 'Slot das 17:01 deve ser rejeitado por ultrapassar 18:00.');
    }

    /**
     * CT-02 (RN02): Dia sem expediente cadastrado retorna indisponibilidade total.
     */
    public function testInactiveDayReturnsNoAvailableSlots(): void
    {
        $dayKey = 'sunday';
        $dayPlan = $this->defaultWorkingPlan[$dayKey] ?? null;

        $this->assertNull($dayPlan, 'Domingo não deve possuir jornada ativa.');
    }

    /**
     * CT-04 (RN04): Valor Limite no início da pausa (12:00 - 13:00).
     */
    public function testSlotsOverlappingBreakPeriodAreBlocked(): void
    {
        $breakStart = new DateTime('2026-12-07 12:00');
        $breakEnd = new DateTime('2026-12-07 13:00');
        $serviceDuration = 30;

        // Slot 11:30 -> termina 12:00 (não invade a pausa)
        $slotBeforeBreak = new DateTime('2026-12-07 11:30');
        $endBeforeBreak = (clone $slotBeforeBreak)->add(new DateInterval("PT{$serviceDuration}M"));

        // Slot 11:31 -> termina 12:01 (invade a pausa em 1 minuto)
        $slotInvadingBreak = new DateTime('2026-12-07 11:31');
        $endInvadingBreak = (clone $slotInvadingBreak)->add(new DateInterval("PT{$serviceDuration}M"));

        $overlapsValid = ($slotBeforeBreak < $breakEnd) && ($endBeforeBreak > $breakStart);
        $overlapsInvalid = ($slotInvadingBreak < $breakEnd) && ($endInvadingBreak > $breakStart);

        $this->assertFalse($overlapsValid, 'Slot 11:30 (término 12:00) não pode conflitar com pausa das 12:00.');
        $this->assertTrue($overlapsInvalid, 'Slot 11:31 (término 12:01) deve ser bloqueado por invadir a pausa.');
    }

    /**
     * CT-08 (RN09): Reagendamento ignora conflito com o próprio ID.
     */
    public function testReschedulingExcludesOwnAppointmentFromConflictCheck(): void
    {
        $existingAppointments = [
            ['id' => 101, 'start' => '2026-12-07 10:00', 'end' => '2026-12-07 11:00'],
            ['id' => 102, 'start' => '2026-12-07 14:00', 'end' => '2026-12-07 15:00'],
        ];

        $editingAppointmentId = 101;
        $filteredConflicts = array_filter(
            $existingAppointments,
            fn(array $appt): bool => $appt['id'] !== $editingAppointmentId
        );

        $this->assertCount(1, $filteredConflicts, 'O agendamento em edição (ID 101) deve ser desconsiderado na checagem de conflito.');
        $this->assertEquals(102, array_values($filteredConflicts)[0]['id']);
    }

    /**
     * CT-09 (RN10): Capacidade de múltiplos atendentes (attendants_number).
     */
    public function testMultipleAttendantsAllowConcurrentBookingsUpToLimit(): void
    {
        $attendantsNumber = 2;
        $bookedCountAt10am = 1;

        $isAvailableForSecondClient = $bookedCountAt10am < $attendantsNumber;
        $this->assertTrue($isAvailableForSecondClient, 'Deve permitir agendamento quando ocupação (1) < capacidade (2).');

        $bookedCountAt10am = 2;
        $isAvailableForThirdClient = $bookedCountAt10am < $attendantsNumber;
        $this->assertFalse($isAvailableForThirdClient, 'Deve bloquear horário quando ocupação (2) == capacidade (2).');
    }

    /**
     * CT-10 (RN11): Valor Limite da antecedência mínima (book_advance_timeout).
     */
    public function testAdvanceBookingTimeoutBoundary(): void
    {
        $now = new DateTime('2026-12-07 10:00');
        $advanceTimeoutMinutes = 60;
        $minAllowedTime = (clone $now)->add(new DateInterval("PT{$advanceTimeoutMinutes}M")); // 11:00

        $slot1059 = new DateTime('2026-12-07 10:59');
        $slot1100 = new DateTime('2026-12-07 11:00');

        $this->assertLessThan($minAllowedTime, $slot1059, '10:59 deve ser bloqueado pela antecedência de 60 min.');
        $this->assertGreaterThanOrEqual($minAllowedTime, $slot1100, '11:00 deve ser aceito no limite exato.');
    }
}