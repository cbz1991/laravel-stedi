<?php

namespace Cabuz\Stedi\Exceptions;

/**
 * The API key is not allowed to access the resource, or the feature is not available in test mode (403).
 */
class PermissionDeniedException extends StediException {}
