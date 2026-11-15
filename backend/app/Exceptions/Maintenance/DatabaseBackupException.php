<?php

namespace App\Exceptions\Maintenance;

use RuntimeException;

/** Un respaldo o una restauración no se pudo completar. El mensaje es para quien ejecuta el comando. */
class DatabaseBackupException extends RuntimeException
{
}
