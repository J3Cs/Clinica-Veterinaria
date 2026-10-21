# ADR-001 — Arquitectura MVC en Capas

**Fecha:** 2026-10-21
**Estado:** Aceptada
**Incremento:** I0 (US-00)
**Regla de origen:** ARQ-01

---

## Contexto

El documento del proyecto exige separar responsabilidades en capas distintas. Se necesita un patrón que permita escalar los módulos (propietarios, mascotas, citas, historial) sin mezclar lógica de negocio con acceso a datos ni con presentación.

## Decisión

Se adopta una arquitectura de **4 capas estrictas**:

```
Vista (View)
  └── Controller
        └── Service   ← lógica de negocio
              └── Repository
                    └── Model   ← SQL puro, PDO
```

### Reglas de cada capa

| Capa | Puede hacer | No puede hacer |
|------|-------------|----------------|
| Model | SQL con PDO prepared statements | Lógica de negocio, HTML |
| Repository | Filtros de negocio sobre modelos | SQL directo, HTML |
| Service | Reglas de negocio, validaciones | HTML, acceso directo a BD |
| Controller | Coordinar flujo, pasar datos a vista | Lógica de negocio, SQL |
| View | Renderizar HTML con `htmlspecialchars` | Consultar BD, lógica de negocio |

## Consecuencias

- **Positivas:** Módulos aislados, fácil de añadir nuevos endpoints sin tocar otras capas.
- **Negativas:** Más archivos por funcionalidad (aceptable para el tamaño del proyecto).
- **Future stories:** Cada nueva historia (US-01…) crea sus propios Model/Repository/Service/Controller/View dentro de la misma estructura.
