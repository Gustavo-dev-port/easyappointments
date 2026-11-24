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
            eval('function setting(string $key, mixed $default = null): mixed { return$default; }');
        }

        require_once APPPATH . 'libraries/Availability.php';
    }

    private function createAvailabilityWithStubs(array $overrides = []): Availability
    {
        $reflection = new ReflectionClass(Availability::class);
        $instance =$reflection->newInstanceWithoutConstructor();

        $blockedPeriodsModel =$overrides['blocked_periods_model'] ?? new class {
            public function is_entire_date_blocked(string $date): bool
            {
                return false;
            }

            public function get_for_period(string $start, string$end): array
            {
                return [];
            }
        };

        $workingPlanExceptionsModel =$overrides['working_plan_exceptions_model'] ?? new class {
            public function get_by_provider(int $providerId): array
            {
                return [];
            }
        };

        $appointmentsModel =$overrides['appointments_model'] ?? new class {
            public function get(array $where): array
            {
                return [];
            }

            public function get_other_service_attendants_number(
                string $start,
                string $end,
                int $serviceId,
                int $providerId,
            ): int {
                return 0;
            }
        };

        $unavailabilitiesModel =$overrides['unavailabilities_model'] ?? new class {
            public function get(array $where): array
            {
                return [];
            }
        };

        $ciStub = new class (
            $blockedPeriodsModel,$workingPlanExceptionsModel,
            $appointmentsModel,$unavailabilitiesModel,
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
                $this->blocked_periods_model =$blockedPeriodsModel;
                $this->working_plan_exceptions_model =$workingPlanExceptionsModel;
                $this->appointments_model =$appointmentsModel;
                $this->unavailabilities_model =$unavailabilitiesModel;
            }
        };

        $ciProperty = $reflection->getProperty('CI');$ciProperty->setValue($instance,$ciStub);

        return $instance;
    }

    private function invokeProtectedMethod(Availability $instance, string $methodName, array$args): mixed
    {
        $method = (new ReflectionClass(Availability::class))->getMethod($methodName);

        return $method->invokeArgs($instance,$args);
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
                'working_plan' => json_encode(array_merge($defaultPlan,$workingPlanOverrides)),
            ],
        ];
    }

    private function defaultService(int $duration = 30, int $slotInterval = 30, int$attendants = 1): array
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
        $availability = $this->createAvailabilityWithStubs();$targetDate = (new DateTime('+7 days next monday'))->format('Y-m-d');

        $hours = $availability->get_available_hours($targetDate,
            $this->defaultService(duration: 30, slotInterval: 30),$this->defaultProvider(),
        );

        $this->assertNotEmpty($hours);$this->assertSame('08:00', $hours[0]);$this->assertSame('17:30', $hours[array_key_last($hours)]);
        $this->assertNotContains('18:00',$hours);
    }

    // ── RN02 (CT02): Dias sem atendimento retornam lista vazia ────────────

    public function testGetAvailableHoursReturnsEmptyArrayOnNonWorkingDay(): void
    {
        $availability = $this->createAvailabilityWithStubs();$sundayDate = (new DateTime('+7 days next sunday'))->format('Y-m-d');

        $hours = $availability->get_available_hours($sundayDate,
            $this->defaultService(duration: 30, slotInterval: 30),$this->defaultProvider(),
        );

        $this->assertSame([],$hours);
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

        $availability =$this->createAvailabilityWithStubs([
            'working_plan_exceptions_model' => $exceptionsStub,