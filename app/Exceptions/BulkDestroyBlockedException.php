<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown inside a transaction when a bulk-delete operation hits a downstream
 * reference that prevents deletion (e.g. student group referenced by schedule).
 * Caught by the controller to roll back and surface a user-friendly error.
 */
class BulkDestroyBlockedException extends RuntimeException
{
}
