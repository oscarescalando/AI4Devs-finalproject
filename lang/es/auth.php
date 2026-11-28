<?php

// Mensajes de autenticación (ISSUE-118): genéricos, sin pistas del sistema
// (regla de estilo §1.7: el login no describe roles, flujos ni dominios).

return [
    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña proporcionada es incorrecta.',
    'throttle' => 'Demasiados intentos. Vuelve a intentarlo en :seconds segundos.',
    'session_expired' => 'Tu sesión expiró. Inicia sesión de nuevo para continuar.',
];
