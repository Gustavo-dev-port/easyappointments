<?php

namespace {
    // Minimal stand-in for the framework controller. Availability::$CI is typed EA_Controller|CI_Controller, so the
    // methods that talk to models need an instance of one of them.
    if (!class_exists('CI_Controller')) {
        #[AllowDynamicProperties]
        class CI_Controller {}
    }
}

namespace Tests\Unit\Library {
    use Availability;
    use CI_Controller;
    use DateTime;
    use ReflectionClass;
    use ReflectionMethod;
    use Tests\TestCase;

    /**
     * Shared fixtures for the Availability tests: hand-written fakes for the external model boundary (the database)
     * and helpers to build the library without the CodeIgniter constructor.
     */
    abstract class AvailabilityTestCase extends TestCase
    {
        protected string $originalTimezone;

        /** @var array Records returned by appointments_model->get() */
        protected array $appointments = [];
        protected array $unavailabilities = [];
        protected array $blockedPeriods = [];
        protected array $exceptions = [];
        protected bool $entireDateBlocked = false;

        /** @var array<int, array> Where clauses received by the appointments/unavailabilities models. */
        public array $appointmentWheres = [];
        public array $unavailabilityWheres = [];

        /** @var array<string, int> attendants per slot start (H:i) for the same service */
        protected array $attendantsBySlot = [];
        /** @var array<string, int> attendants per slot start (H:i) for other services */
        protected array $otherServiceBySlot = [];
        /** @var array Arguments received by get_other_service_attendants_number */
        public array $otherServiceCalls = [];

        protected function setUp(): void
        {
            $this->originalTimezone = date_default_timezone_get();
            $GLOBALS['ea_test_settings'] = [];
            $this->appointments = [];
            $this->unavailabilities = [];
            $this->blockedPeriods = [];
            $this->exceptions = [];
            $this->entireDateBlocked = false;
            $this->appointmentWheres = [];
            $this->unavailabilityWheres = [];
            $this->attendantsBySlot = [];
            $this->otherServiceBySlot = [];
            $this->otherServiceCalls = [];
        }

        protected function tearDown(): void
        {
            date_default_timezone_set($this->originalTimezone);
            $GLOBALS['ea_test_settings'] = [];
        }

        protected function availability(): Availability
        {
            require_once APPPATH . 'libraries/Availability.php';

            $availability = (new ReflectionClass(Availability::class))->newInstanceWithoutConstructor();

            $test = $this;

            $ci = new CI_Controller();

            $ci->appointments_model = new class($test) {
                public function __construct(private AvailabilityTestCase $t) {}

                public function get(array|string|null $where = null): array
                {
                    $this->t->appointmentWheres[] = $where;
                    return $this->t->records('appointments');
                }

                public function get_other_service_attendants_number(
                    DateTime $start,
                    DateTime $end,
                    int $service_id,
                    int $provider_id,
                    ?int $exclude_appointment_id = null,
                ): int {
                    $this->t->otherServiceCalls[] = [$start->format('H:i'), $exclude_appointment_id];
                    return $this->t->records('other')[$start->format('H:i')] ?? 0;
                }

                public function get_attendants_number_for_period(
                    DateTime $start,
                    DateTime $end,
                    int $service_id,
                    int $provider_id,
                    ?int $exclude_appointment_id = null,
                ): int {
                    return $this->t->records('attendants')[$start->format('H:i')] ?? 0;
                }
            };

            $ci->unavailabilities_model = new class($test) {
                public function __construct(private AvailabilityTestCase $t) {}

                public function get(array|string|null $where = null): array
                {
                    $this->t->unavailabilityWheres[] = $where;
                    return $this->t->records('unavailabilities');
                }
            };

            $ci->blocked_periods_model = new class($test) {
                public function __construct(private AvailabilityTestCase $t) {}

                public function get_for_period(string $start_date, string $end_date): array
                {
                    return $this->t->records('blocked');
                }

                public function is_entire_date_blocked(string $date): bool
                {
                    return $this->t->records('entire');
                }
            };

            $ci->working_plan_exceptions_model = new class($test) {
                public function __construct(private AvailabilityTestCase $t) {}

                public function get_by_provider(int $provider_id): array
                {
                    return $this->t->records('exceptions');
                }
            };

            (new ReflectionClass(Availability::class))->getProperty('CI')->setValue($availability, $ci);

            return $availability;
        }

        /**
         * Known defects are skipped so the suite stays green. Set RUN_KNOWN_BUGS=1 to execute them and watch them
         * fail against the current production code.
         */
        protected function skipKnownBug(string $message): void
        {
            if (!getenv('RUN_KNOWN_BUGS')) {
                $this->markTestSkipped($message);
            }
        }

        /** Exposes fixture data to the anonymous fakes. */
        public function records(string $name): mixed
        {
            return match ($name) {
                'appointments' => $this->appointments,
                'unavailabilities' => $this->unavailabilities,
                'blocked' => $this->blockedPeriods,
                'exceptions' => $this->exceptions,
                'entire' => $this->entireDateBlocked,
                'attendants' => $this->attendantsBySlot,
                'other' => $this->otherServiceBySlot,
            };
        }

        /** Same working plan for every weekday. */
        protected function provider(
            string $start = '09:00',
            string $end = '17:00',
            array $breaks = [],
            string $timezone = 'UTC',
        ): array {
            $day = ['start' => $start, 'end' => $end, 'breaks' => $breaks];
            $plan = [];
            foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $name) {
                $plan[$name] = $day;
            }

            return [
                'id' => 7,
                'timezone' => $timezone,
                'settings' => ['working_plan' => json_encode($plan)],
            ];
        }

        protected function providerWithPlan(array $plan, string $timezone = 'UTC'): array
        {
            return ['id' => 7, 'timezone' => $timezone, 'settings' => ['working_plan' => json_encode($plan)]];
        }

        protected function appointment(string $start, string $end): array
        {
            return ['id' => 1, 'start_datetime' => $start, 'end_datetime' => $end];
        }

        /** Calls a protected method of Availability. */
        protected function callProtected(Availability $availability, string $method, mixed ...$args): mixed
        {
            return (new ReflectionMethod(Availability::class, $method))->invoke($availability, ...$args);
        }

        /** @return string[] ['start-end', ...] for readable assertions. */
        protected function flatten(array $periods): array
        {
            return array_map(fn($p) => $p['start'] . '-' . $p['end'], $periods);
        }
    }
}
