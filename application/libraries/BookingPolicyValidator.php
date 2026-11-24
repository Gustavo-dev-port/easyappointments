<?php

defined('BASEPATH') or exit('No direct script access allowed');
// Proteção do CodeIgniter

class BookingPolicyValidator
{
    /**
     * RN11: o agendamento precisa ser feito com no mínimo esta antecedência, em minutos.
     * A borda é aceita: começar exatamente na antecedência mínima é permitido, só recusa abaixo dela.
     */
    public const RULE_MIN_ADVANCE = 'RN11';

    private int $minAdvanceMinutes;

    public function __construct(int $minAdvanceMinutes)
    {
        $this->minAdvanceMinutes = $minAdvanceMinutes;
    }
    
    public function validate(DateTimeImmutable $start, DateTimeImmutable $now): array
    {
        $advanceMinutes = ($start->getTimestamp() - $now->getTimestamp()) / 60;

        if ($advanceMinutes < $this->minAdvanceMinutes) {
            return [self::RULE_MIN_ADVANCE];
        }

        return [];
    }

} 