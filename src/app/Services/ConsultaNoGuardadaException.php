<?php
namespace App\Services;

use RuntimeException;

/**
 * ConsultaNoGuardadaException — US-06 (T-044)
 * Se lanza DENTRO de la transacción cuando una regla impide terminar el guardado
 * (por ejemplo, la cita dejó de estar Programada): obliga a revertir todo.
 */
class ConsultaNoGuardadaException extends RuntimeException {
}
