<?php

namespace App\Exceptions\Exams;

use App\Support\Exams\DistributionMessages;

class StaleDistributionException extends ExamStateException
{
    protected $message = DistributionMessages::STALE;
}
