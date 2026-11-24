<?php

defined('BASEPATH') or exit('No direct script access allowed');
// Proteção do CodeIgniter

class BookingPolicyValidator
{
    private int $minAdvanceMinutes;
    //antecedencia minima guardada

    public function __construct(int $minAdvanceMinutes)
    {
        $this->minAdvanceMinutes = $minAdvanceMinutes;
    }

    public function validate(DateTimeImmutable $start, DateTimeImmutable $now): array
    {
        return [];

    }

} 