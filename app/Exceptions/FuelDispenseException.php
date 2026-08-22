<?php

namespace App\Exceptions;

use Exception;

/**
 * A fuel workflow rule refused the operation; the message is written for
 * the operator and safe to surface in the UI (pattern follows
 * InsufficientAllocationException).
 */
final class FuelDispenseException extends Exception {}
