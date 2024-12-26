<?php

namespace App\Exceptions;

use App\Models\Currency;
use Carbon\Carbon;
use Exception;

class CurrencyRateConversionException extends Exception
{
    protected Currency $currencyFrom;
    protected ?Currency $currencyTo;
    protected ?Carbon $date;

    public function __construct($message, $currencyFrom, $currencyTo = null, $date = null)
    {
        parent::__construct($message);
        $this->currencyFrom = $currencyFrom;
        $this->currencyTo = $currencyTo;
        $this->date = $date;
    }

    public function getCurrencyFrom()
    {
        return $this->currencyFrom;
    }

    public function getCurrencyTo()
    {
        return $this->currencyTo;
    }

    public function getDate()
    {
        return $this->date;
    }
}
