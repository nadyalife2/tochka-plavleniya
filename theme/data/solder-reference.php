<?php
/**
 * solder-reference.php — Wrapper
 * Этот файл теперь обращается к materials-registry.php для единой архитектуры.
 */
require_once __DIR__ . '/materials-registry.php';
$SOLDER_DATA = $MATERIALS_REGISTRY['solders'];

