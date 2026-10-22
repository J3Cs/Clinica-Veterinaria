<?php
/**
 * Componente Reutilizable: Buscador Dinámico — TEC-01, UI-01
 *
 * Parámetros admitidos:
 * @var string      $searchId          Prefijo único para los IDs (por defecto: 'search')
 * @var string      $placeholder       Texto de sugerencia dentro del input
 * @var string      $ariaLabel         Etiqueta descriptiva para accesibilidad
 * @var bool|null   $showLiveDropdown  Habilita el contenedor de sugerencias en vivo (por defecto: false)
 * @var bool|null   $showCount         Habilita el contador de resultados (por defecto: true)
 */

$searchId         = $searchId ?? 'search';
$placeholder      = $placeholder ?? 'Buscar...';
$ariaLabel        = $ariaLabel ?? 'Buscar en el listado';
$showLiveDropdown = $showLiveDropdown ?? false;
$showCount        = $showCount ?? true;
?>
<div class="search-card" id="<?= htmlspecialchars($searchId, ENT_QUOTES, 'UTF-8') ?>-container">
    <div class="search-form-group">
        <div class="search-input-wrapper">
            <span class="search-icon-badge" aria-hidden="true">🔍</span>
            <input
                type="search"
                id="<?= htmlspecialchars($searchId, ENT_QUOTES, 'UTF-8') ?>-input"
                class="search-input-field"
                placeholder="<?= htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="off"
                aria-label="<?= htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') ?>"
                <?php if ($showLiveDropdown): ?>
                    aria-expanded="false"
                    aria-haspopup="listbox"
                    aria-controls="<?= htmlspecialchars($searchId, ENT_QUOTES, 'UTF-8') ?>-dropdown"
                <?php endif; ?>
            >
            <button
                type="button"
                id="<?= htmlspecialchars($searchId, ENT_QUOTES, 'UTF-8') ?>-clear"
                class="search-clear-btn"
                title="Limpiar búsqueda"
                aria-label="Limpiar búsqueda"
            >✕</button>
        </div>

        <?php if ($showLiveDropdown): ?>
            <!-- Dropdown de sugerencias interactivas -->
            <div
                id="<?= htmlspecialchars($searchId, ENT_QUOTES, 'UTF-8') ?>-dropdown"
                class="search-live-dropdown"
                role="listbox"
                aria-label="Sugerencias de búsqueda"
            ></div>
        <?php endif; ?>
    </div>

    <?php if ($showCount): ?>
        <!-- Contador de resultados -->
        <div
            class="search-result-count"
            id="<?= htmlspecialchars($searchId, ENT_QUOTES, 'UTF-8') ?>-count"
            aria-live="polite"
        ></div>
    <?php endif; ?>
</div>

