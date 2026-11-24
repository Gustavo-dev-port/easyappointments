<?php

namespace Tests\Unit\Library;

use Availability;
use DateTime;
use DateTimeZone;
use InvalidArgumentException;
use ReflectionClass;
use Tests\TestCase;

/**
 * Suíte de Testes Unitários das Regras de Negócio - Módulo Availability (Easy!Appointments 1.6.1)
 * Exercita os métodos reais de Availability.php sem depender de banco de dados.
 */
class AvailabilityRulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once APPPATH . 'libraries/Availability.php';
    }

    /**
     * Instancia Availability injetando stubs nos modelos do CodeIgniter para testar
     * get_available_hours() e get_available_periods() de forma isolada e determinística.
     */
    private function createAvailabilityWithStubs(array $overrides = []): Availability
    {
        $reflection = new ReflectionClass(Availability::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        $blockedPeriodsModel = $overrides['blocked_periods_model'] ?? new class {
            public function is_entire_date_blocked(string $date): bool
            {
                return false;
            }

            public function get_for_period(string $start, string $end): array
            {
                return [];
            }
        };

        $workingPlanExceptionsModel = $overrides['working_plan_exceptions_model'] ?? new class {
            public function get_by_provider(int $providerId): array
            {
                return [];
            }
        };

        $appointmentsModel = $overrides['appointments_model'] ?? new class {
            public function get(array $where): array
            {
                return [];
            }
        };

        $unavailabilitiesModel = $overrides['unavailabilities_model'] ?? new class {
            public function get(array $where): array
            {
                return [];
            }
        };

        $ciStub = (object) [
            'blocked_periods_model' => $blockedPeriodsModel,
            'working_plan_exceptions_model' => $workingPlanExceptionsModel,
            'appointments_model' => $appointmentsModel,
            'unavailabilities_model' => $unavailabilitiesModel,
        ];

        $ciProperty = $reflection->getProperty('CI');
        $ciProperty->setAccessible(true);
        $ciProperty->setValue($instance, $ciStub);

        return $instance;
    }

    private function invokeProtectedMethod(Availability $instance, string $methodName, array $args): mixed
    {
        $method = (new ReflectionClass(Availability::class))->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($instance, $args);
    }

    private function defaultProvider(array $workingPlanOverrides = []): array
    {
        $defaultPlan = [
            'monday' => [
                'start' => '08:00',
                'end' => '18:00',
                'breaks' => [
                    ['start' => '12:00', 'end' => '13:00'],
                ],
            ],
            'tuesday' => [
                'start' => '08:00',
                'end' => '18:00',
                'breaks' => [
                    ['start' => '12:00', 'end' => '13:00'],
                ],
            ],
            'sunday' => null,
        ];

        return [
            'id' => 1,
            'timezone' => 'UTC',
            'settings' => [
                'working_plan' => json_encode(array_merge($defaultPlan, $workingPlanOverrides)),
            ],
        ];
    }

    private function defaultService(int $duration = 30, int $slotInterval = 30, int $attendants = 1): array
    {
        return [
            'id' => 1,
            'duration' => $duration,
            'slot_interval' => $slotInterval,
            'attendants_number' => $attendants,
        ];
    }

    // ── RN01 & RN05: Horário de trabalho e duração do serviço ─────────────

    public function testGetAvailableHoursRespectsWorkingPlanAndServiceDuration(): void
    {
        $availability = $this->createAvailabilityWithStubs();
        $provider = $this->defaultProvider();
        $service = $this->defaultService(duration: 30, slotInterval: 30);

        // Segunda-feira futura dentro do limite de 90 dias a partir de agora
        $targetDate = (new DateTime('+7 days next monday'))->format('Y-m-d');

        $hours = $availability->get_available_hours($targetDate, $service, $provider);

        $this->assertNotEmpty($hours);
        $this->assertSame('08:00', $hours[0]);
        // Último slot de 30 min que cabe até 18:00 é 17:30 (18:00 não pode ser ofertado)
        $this->assertSame('17:30', $hours[array_key_last($hours)]);
        $this->assertNotContains('18:00', $hours);
    }

    // ── RN02: Dias sem atendimento retornam lista vazia ───────────────────

    public function testGetAvailableHoursReturnsEmptyArrayOnNonWorkingDay(): void
    {
        $availability = $this->createAvailabilityWithStubs();
        $provider = $this->defaultProvider();
        $service = $this->defaultService(duration: 30, slotInterval: 30);

        $sundayDate = (new DateTime('+7 days next sunday'))->format('Y-m-d');

        $hours = $availability->get_available_hours($sundayDate, $service, $provider);

        $this->assertSame([], $hours);
    }

    // ── RN03: Exceções de plano de trabalho (working_plan_exceptions) ──────

    public function testGetAvailableHoursAppliesWorkingPlanExceptionForSpecificDate(): void
    {
        $targetDate = (new DateTime('+7 days next monday'))->format('Y-m-d');

        $exceptionsStub = new class ($targetDate) {
            public function __construct(private string $date) {}

            public function get_by_provider(int $providerId): array
            {
                return [
                    $this->date => [
                        'start' => '09:00',
                        'end' => '11:00',
                        'breaks' => [],
                    ],
                ];
            }
        };

        $availability = $this->createAvailabilityWithStubs([
            'working_plan_exceptions_model' => $exceptionsStub,
        ]);

        $hours = $availability->get_available_hours(
            $targetDate,
            $this->defaultService(duration: 30, slotInterval: 30),
            $this->defaultProvider(),
        );

        $this->assertSame(['09:00', '09:30', '10:00', '10:30'], $hours);
    }

    // ── RN04: Intervalos e pausas cadastradas ─────────────────────────────

    public function testGetAvailableHoursExcludesConfiguredBreakPeriod(): void
    {
        $availability = $this->createAvailabilityWithStubs();
        $targetDate = (new DateTime('+7 days next monday'))->format('Y-m-d');

        $hours = $availability->get_available_hours(
            $targetDate,
            $this->defaultService(duration: 30, slotInterval: 30),
            $this->defaultProvider(),
        );

        $this->assertContains('11:30', $hours);
        $this->assertNotContains('12:00', $hours);
        $this->assertNotContains('12:30', $hours);
        $this->assertContains('13:00', $hours);
    }

    // ── RN06: Intervalo entre slots (slot_interval) ───────────────────────

    public function testGetAvailableHoursRespectsCustomSlotInterval(): void
    {
        $availability = $this->createAvailabilityWithStubs();
        $targetDate = (new DateTime('+7 days next monday'))->format('Y-m-d');

        $hours = $availability->get_available_hours(
            $targetDate,
            $this->defaultService(duration: 30, slotInterval: 60),
            $this->defaultProvider(),
        );

        $this->assertContains('08:00', $hours);
        $this->assertContains('09:00', $hours);
        $this->assertNotContains('08:30', $hours);
    }

    // ── RN07 & RN08: Agendamentos existentes e bloqueios (is_unavailability)

    public function testGetAvailableHoursBlocksExistingAppointmentsAndEntireBlockedDates(): void
    {
        $targetDate = (new DateTime('+7 days next monday'))->format('Y-m-d');

        $appointmentsStub = new class ($targetDate) {
            public function __construct(private string $date) {}

            public function get(array $where): array
            {
                return [
                    [
                        'id' => 50,
                        'start_datetime' => $this->date . ' 14:00:00',
                        'end_datetime' => $this->date . ' 15:00:00',
                    ],
                ];
            }
        };

        $availability = $this->createAvailabilityWithStubs([
            'appointments_model' => $appointmentsStub,
        ]);

        $hours = $availability->get_available_hours(
            $targetDate,
            $this->defaultService(duration: 30, slotInterval: 30),
            $this->defaultProvider(),
        );

        $this->assertContains('13:30', $hours);
        $this->assertNotContains('14:00', $hours);
        $this->assertNotContains('14:30', $hours);
        $this->assertContains('15:00', $hours);

        // Dia totalmente bloqueado em blocked_periods_model
        $blockedDayStub = new class {
            public function is_entire_date_blocked(string $date): bool
            {
                return true;
            }
        };

        $blockedAvailability = $this->createAvailabilityWithStubs([
            'blocked_periods_model' => $blockedDayStub,
        ]);

        $this->assertSame(
            [],
            $blockedAvailability->get_available_hours(
                $targetDate,
                $this->defaultService(),
                $this->defaultProvider(),
            ),
        );
    }

    // ── Validação defensiva de formato de data em get_available_periods() ──

    public function testGetAvailablePeriodsThrowsExceptionOnInvalidDateFormat(): void
    {
        $availability = $this->createAvailabilityWithStubs();

        $this->expectException(InvalidArgumentException::class);
        $availability->get_available_periods('26/08/2026', $this->defaultProvider());
    }

    // ── RN11: Antecedência mínima (consider_book_advance_timeout) ─────────

    public function testConsiderBookAdvanceTimeoutFiltersPastAndImmediateSlots(): void
    {
        $availability = $this->createAvailabilityWithStubs();
        $provider = $this->defaultProvider();
        $tz = new DateTimeZone('UTC');

        $today = (new DateTime('now', $tz))->format('Y-m-d');
        $pastSlot = (new DateTime('-30 minutes', $tz))->format('H:i');
        $farFutureDate = (new DateTime('+10 days', $tz))->format('Y-m-d');

        // Em data futura, nenhum horário de hoje deve ser removido pelo timeout
        $futureResult = $this->invokeProtectedMethod(
            $availability,
            'consider_book_advance_timeout',
            [$farFutureDate, ['09:00', '10:00'], $provider],
        );
        $this->assertSame(['09:00', '10:00'], $futureResult);

        // Na data de hoje, horário no passado é removido pelo método real
        $todayResult = $this->invokeProtectedMethod(
            $availability,
            'consider_book_advance_timeout',
            [$today, [$pastSlot], $provider],
        );
        $this->assertNotContains($pastSlot, $todayResult);
    }

    // ── RN12: Limite de agendamento futuro (consider_future_booking_limit) ─

    public function testConsiderFutureBookingLimitBlocksDatesBeyondLimit(): void
    {
        $availability = $this->createAvailabilityWithStubs();
        $provider = $this->defaultProvider();
        $tz = new DateTimeZone('UTC');

        // Limite padrão é 90 dias: +95 dias deve retornar vazio; +10 dias mantém os horários
        $beyondLimitDate = (new DateTime('+95 days', $tz))->format('Y-m-d');
        $withinLimitDate = (new DateTime('+10 days', $tz))->format('Y-m-d');

        $blockedResult = $this->invokeProtectedMethod(
            $availability,
            'consider_future_booking_limit',
            [$beyondLimitDate, ['09:00', '10:00'], $provider],
        );
        $allowedResult = $this->invokeProtectedMethod(
            $availability,
            'consider_future_booking_limit',
            [$withinLimitDate, ['09:00', '10:00'], $provider],
        );

        $this->assertSame([], $blockedResult);
        $this->assertSame(['09:00', '10:00'], $allowedResult);
    }
}