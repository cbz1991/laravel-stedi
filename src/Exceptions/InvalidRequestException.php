<?php

namespace Cabuz\Stedi\Exceptions;

/**
 * The request was rejected as invalid (400, 413 payload too large, 422).
 */
class InvalidRequestException extends StediException {}
