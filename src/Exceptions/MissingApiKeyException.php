<?php

namespace Cabuz\Stedi\Exceptions;

class MissingApiKeyException extends StediException
{
    public static function make(): self
    {
        return new self('No Stedi API key configured. Set STEDI_API_KEY in your .env file or call Stedi::withApiKey().');
    }
}
