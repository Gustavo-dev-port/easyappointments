<?php

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Behat\Step\When;
use Behat\Step\Then;

class FeatureContext implements Context
{
    private int $minAdvanceMinutes;
    private DateTimeImmutable $now;
    private DateTimeImmutable $start;
    private array $violations;

    public function __construct()
    {
        defined('BASEPATH') || define('BASEPATH', __DIR__ . '/../../system/');
        require_once __DIR__ . '/../../application/libraries/BookingPolicyValidator.php';
    }

    #[Given('que a antecedência mínima é de :minutes minutos')]
    public function minAdvanceIs($minutes): void
    {
        $this->minAdvanceMinutes = (int) $minutes;
    }

    #[Given('que agora são :hour::minute do dia :day\/:month\/:year')]
    public function nowIs($hour, $minute, $day, $month, $year): void
    {
        $this->now = new DateTimeImmutable(
            sprintf('%04d-%02d-%02d %02d:%02d:00', (int) $year, (int) $month, (int) $day, (int) $hour, (int) $minute),
        );
    }

    #[When('o cliente escolhe um horário às :hour::minute')]
    public function clientChoosesTime($hour, $minute): void
    {
        $this->start = $this->now->setTime((int) $hour, (int) $minute);

        $validator = new BookingPolicyValidator($this->minAdvanceMinutes);
        $this->violations = $validator->validate($this->start, $this->now);
    }

    #[Then('o agendamento é recusado pela regra :rule')]
    public function bookingIsRejectedByRule($rule): void
    {
        if ($this->violations !== [$rule]) {
            throw new RuntimeException(sprintf(
                'Esperado %s, recebido %s',
                json_encode([$rule]),
                json_encode($this->violations),
            ));
        }
    }

    #[Then('o agendamento é aceito')]
    public function bookingIsAccepted(): void
    {
        if ($this->violations !== []) {
            throw new RuntimeException(sprintf(
                'Esperado %s, recebido %s',
                json_encode([]),
                json_encode($this->violations),
            ));
        }
    }
}
