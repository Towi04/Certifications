<?php
/**
 * Punto de entrada si el Document Root del subdomain apunta a la raíz del repo
 * (no a /public). Redirige el front controller.
 */
require __DIR__ . '/public/index.php';
