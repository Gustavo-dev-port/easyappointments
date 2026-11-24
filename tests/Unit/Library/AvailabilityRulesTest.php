<?php

namespace Tests\Unit\Library;

use Availability;
use CI_Controller;
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

        if (!class_exists('CI_Controller', false)) {
            if (defined('BASEPATH') && file_exists(BASEPATH . 'core/Controller.php')) {
                require_once BASEPATH . 'core/Controller.php';
            } else {
                eval('class CI_Controller {}');
            }
        }

        if (!function_exists('setting')) {
            eval('function setting(string $key, mixed $default = null): mixed { return $default; }');
        }

        require_once APPPATH . 'libraries/Availability.php';
    }

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

            public function get_other_service_attendants_number($start, $end, $serviceId, $providerId): int
            {
                return 0;
            }
        };

        $unavailabilitiesModel = $overrides['unavailabilities_model'] ?? new class {
            public function get(array $where): array
            {
                return [];
            }
        };

        $ciStub = new class (
            $blockedPeriodsModel,
            $workingPlanExceptionsModel,
            $appointmentsModel,
            $unavailabilitiesModel,
        ) extends CI_Controller {
            public object $blocked_periods_model;
            public object $working_plan_exceptions_model;
            public object $appointments_model;
            public object $unavailabilities_model;

            public function __construct(
                object $blockedPeriodsModel,
                object $workingPlanExceptionsModel,
                object $appointmentsModel,
                object $unavailabilitiesModel,
            ) {
                $this->blocked_periods_model = $blockedPeriodsModel;
                $this->working_plan_exceptions_model = $workingPlanExceptionsModel;
                $this->appointments_model = $appointmentsModel;
                $this->unavailabilities_model = $unavailabilitiesModel;
            }
        };

        $ciProperty = $reflection->getProperty('CI');
        $ciProperty->setValue($instance, $ciStub);

        return $instance;
    }

    private function invokeProtectedMethod(Availability $instance, string $methodName, array $args): mixed
    {
        $method = (new ReflectionClass(Availability::class))->getMethod($methodName);

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

    // ── RN01 & RN05 (CT01): Horário de trabalho e duração do serviço ──────

    public function testGetAvailableHoursRespectsWorkingPlanAndServiceDuration(): void
    {
        $availability = $this->createAvailabilityWithStubs();
        $targetDate = (new DateTime('+7 days next monday'))->format('Y-m-d');

        $hours = $availability->get_available_hours(
            $targetDate,
            $this->defaultService(duration: 30, slotInterval: 30),
            $this->defaultProvider(),
        );

        $this->assertNotEmpty($hours);
        $this->assertSame('08:00', $hours[0]);
        $this->assertSame('17:30', $hours[array_key_last($hours)]);
        $this->assertNotContains('18:00', $hours);
    }

    // ── RN02 (CT02): Dias sem atendimento retornam lista vazia ────────────

    public function testGetAvailableHoursReturnsEmptyArrayOnNonWorkingDay(): void
    {
        $availability = $this->createAvailabilityWithStubs();
        $sundayDate = (new DateTime('+7 days next sunday'))->format('Y-m-d');

        $hours = $availability->get_available_hours(
            $sundayDate,
            $this->defaultService(duration: 30, slotInterval: 30),
            $this->defaultProvider(),
        );

        $this->assertSame([], $hours);
    }

    // ── RN03 (CT03): Exceções de plano de trabalho (working_plan_exceptions)

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

    // ── RN04 (CT04): Intervalos e pausas cadastradas ──────────────────────

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

    // ── RN07 & RN08 (CT05 & CT06): Agendamentos, indisponibilidades e bloqueios

    public function testGetAvailableHoursBlocksExistingAppointmentsUnavailabilitiesAndBlockedDates(): void
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

            public function get_other_service_attendants_number($start, $end, $serviceId, $providerId): int
            {
                return 0;
            }
        };

        $unavailabilitiesStub = new class ($targetDate) {
            public function __construct(private string $date) {}

            public function get(array $where): array
            {
                return [
                    [
                        'id' => 70,
                        'is_unavailability' => true,
                        'start_datetime' => $this->date . ' 16:00:00',
                        'end_datetime' => $this->date . ' 17:00:00',
                    ],
                ];
            }
        };

        $availability = $this->createAvailabilityWithStubs([
            'appointments_model' => $appointmentsStub,
            'unavailabilities_model' => $unavailabilitiesStub,
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

        $this->assertNotContains('16:00', $hours);
        $this->assertNotContains('16:30', $hours);
        $this->assertContains('17:00', $hours);

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

    // ── RN09 (CT07): Reagendamento ignora o próprio ID sem falso conflito ──

    public function testGetAvailableHoursExcludesCurrentAppointmentIdWhenRescheduling(): void
    {
        $targetDate = (new DateTime('+7 days next monday'))->format('Y-m-d');

        $appointmentsStub = new class ($targetDate) {
            public function __construct(private string $date) {}

            public function get(array $where): array
            {
                if (isset($where['id !=']) && (int) $where['id !='] === 101) {
                    return [];
                }

                return [
                    [
                        'id' => 101,
                        'start_datetime' => $this->date . ' 10:00:00',
                        'end_datetime' => $this->date . ' 11:00:00',
                    ],
                ];
            }

            public function get_other_service_attendants_number($start, $end, $serviceId, $providerId): int
            {
                return 0;
            }
        };

        $availability = $this->createAvailabilityWithStubs([
            'appointments_model' => $appointmentsStub,
        ]);

        $hours = $availability->get_available_hours(
            $targetDate,
            $this->defaultService(duration: 30, slotInterval: 30),
            $this->defaultProvider(),
            101,
        );

        $this->assertContains('10:00', $hours);
        $this->assertContains('10:30', $hours);
    }

    // ── RN10 (CT09): Capacidade simultânea (attendants_number) ─────────────

    public function testGetAvailableHoursRespectsMultipleAttendantsCapacity(): void
    {
        $targetDate = (new DateTime('+7 days next monday'))->format('Y-m-d');

        $oneAppointmentStub = new class ($targetDate) {
            public function __construct(private string $date) {}

            public function get(array $where): array
            {
                return [
                    [
                        'id' => 201,
                        'start_datetime' => $this->date . ' 10:00:00',
                        'end_datetime' => $this->date . ' 11:00:00',
                    ],
                ];
            }

            public function get_other_service_attendants_number($start, $end, $serviceId, $providerId): int
            {
                return 0;
            }
        };

        $availability = $this->createAvailabilityWithStubs([
            'appointments_model' => $oneAppointmentStub,
        ]);

        $hoursWithTwoAttendants = $availability->get_available_hours(
            $targetDate,
            $this->defaultService(duration: 30, slotInterval: 30, attendants: 2),
            $this->defaultProvider(),
        );

        $this->assertContains('10:00', $hoursWithTwoAttendants);
    }

    // ── Validação defensiva de formato de data em get_available_periods() ──

    public function testGetAvailablePeriodsThrowsExceptionOnInvalidDateFormat(): void
    {
        $availability = $this->createAvailabilityWithStubs();

        $this->expectException(InvalidArgumentException::class);
        $availability->get_available_periods('26/08/2026', $this->defaultProvider());
    }

    // ── RN11 (CT08): Antecedência mínima (consider_book_advance_timeout) ──

    public function testConsiderBookAdvanceTimeoutFiltersPastAndKeepsFutureSlots(): void
    {
        $availability = $this->createAvailabilityWithStubs();
        $provider = $this->defaultProvider();
        $tz = new DateTimeZone('UTC');

        $past = new DateTime('-30 minutes', $tz);
        $future = new DateTime('+3 hours', $tz);

        $this->assertSame(
            [],
            $this->invokeProtectedMethod(
                $availability,
                'consider_book_advance_timeout',
                [$past->format('Y-m-d'), [$past->format('H:i')], $provider],
            ),
        );

        $this->assertSame(
            [$future->format('H:i')],
            $this->invokeProtectedMethod(
                $availability,
                'consider_book_advance_timeout',
                [$future->format('Y-m-d'), [$future->format('H:i')], $provider],
            ),
        );
    }

    // ── RN12 (CT10): Limite de agendamento futuro (consider_future_booking_limit)

    public function testConsiderFutureBookingLimitBlocksDatesBeyondLimit(): void
    {
        $availability = $this->createAvailabilityWithStubs();
        $provider = $this->defaultProvider();
        $tz = new DateTimeZone('UTC');

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
