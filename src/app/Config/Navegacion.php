<?php
namespace App\Config;

/**
 * Navegacion — ACC-01, ARQ-01, UI-01
 * Fuente ÚNICA de lo que ve cada rol: opciones del menú y pantalla de inicio.
 *
 * Es solo presentación: el acceso real lo sigue haciendo cada controlador con
 * AuthMiddleware::requireRole(). Esta tabla debe reflejar esos permisos, y las
 * pruebas (test_navegacion.php y la matriz RF-ACC) comprueban que coincidan:
 * todo enlace que se muestra a un rol debe estar permitido para ese rol.
 *
 * Para agregar un rol nuevo (p. ej. recepcionista):
 *   1. ampliar el ENUM `usuarios.rol` en la base de datos (migración);
 *   2. añadirlo en MENU, INICIO y ETIQUETA de esta clase;
 *   3. permitirlo en los `requireRole()` de las acciones que le correspondan.
 */
final class Navegacion {

    public const ROL_ADMINISTRADOR = 'administrador';
    public const ROL_VETERINARIO   = 'veterinario';

    /** Pantalla a la que se envía a cada rol tras iniciar sesión. */
    private const INICIO = [
        self::ROL_ADMINISTRADOR => '/admin/dashboard',
        self::ROL_VETERINARIO   => '/veterinario/agenda',
    ];

    /** Nombre del rol para mostrar. */
    private const ETIQUETA = [
        self::ROL_ADMINISTRADOR => 'Administrador',
        self::ROL_VETERINARIO   => 'Veterinario',
    ];

    /** Clase CSS de la insignia (badge-admin, badge-vet). */
    private const INSIGNIA = [
        self::ROL_ADMINISTRADOR => 'admin',
        self::ROL_VETERINARIO   => 'vet',
    ];

    /**
     * Opciones del menú por rol. `id` identifica la sección para resaltar la activa.
     *
     * @var array<string, array<int, array{id: string, url: string, etiqueta: string}>>
     */
    private const MENU = [
        self::ROL_ADMINISTRADOR => [
            ['id' => 'dashboard',    'url' => '/admin/dashboard', 'etiqueta' => 'Dashboard'],
            ['id' => 'propietarios', 'url' => '/propietarios',    'etiqueta' => 'Propietarios'],
            ['id' => 'mascotas',     'url' => '/mascotas',        'etiqueta' => 'Mascotas'],
            ['id' => 'citas',        'url' => '/citas',           'etiqueta' => 'Citas'],
            ['id' => 'servicios',    'url' => '/servicios',       'etiqueta' => 'Servicios'],
            ['id' => 'horarios',     'url' => '/horarios',        'etiqueta' => 'Horarios'],
        ],
        self::ROL_VETERINARIO => [
            ['id' => 'agenda',       'url' => '/veterinario/agenda', 'etiqueta' => 'Mi agenda'],
            ['id' => 'mascotas',     'url' => '/mascotas',           'etiqueta' => 'Mascotas'],
            ['id' => 'propietarios', 'url' => '/propietarios',       'etiqueta' => 'Propietarios'],
        ],
    ];

    /**
     * Opciones del menú que corresponden al rol. Un rol desconocido no ve ninguna.
     *
     * @return array<int, array{id: string, url: string, etiqueta: string}>
     */
    public static function enlaces(?string $rol): array {
        return self::MENU[(string) $rol] ?? [];
    }

    /** Pantalla de inicio del rol; `/dashboard` (que decide) si el rol no se conoce. */
    public static function inicio(?string $rol): string {
        return self::INICIO[(string) $rol] ?? '/dashboard';
    }

    public static function etiquetaRol(?string $rol): string {
        return self::ETIQUETA[(string) $rol] ?? ucfirst((string) $rol);
    }

    public static function claseInsignia(?string $rol): string {
        return self::INSIGNIA[(string) $rol] ?? 'vet';
    }

    /** @return string[] Roles que el sistema conoce hoy. */
    public static function roles(): array {
        return array_keys(self::MENU);
    }
}
