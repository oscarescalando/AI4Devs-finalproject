<?php

// Validación en español (ISSUE-118, TQ-003): el servidor es la única fuente
// de verdad y sus mensajes se pintan bajo el campo (ISSUE-110). Juego mínimo
// de reglas por ahora; las claves que falten caen al fallback (en) hasta el
// barrido i18n completo (TQ-007).

return [
    'accepted' => 'El campo :attribute debe ser aceptado.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'confirmed' => 'El campo :attribute de confirmación no coincide.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'exists' => 'El campo :attribute seleccionado no existe.',
    'in' => 'El campo :attribute seleccionado no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'max' => [
        'array' => 'El campo :attribute no debe tener más de :max elementos.',
        'file' => 'El campo :attribute no debe pesar más de :max kilobytes.',
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute no debe superar :max caracteres.',
    ],
    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
        'file' => 'El campo :attribute debe pesar al menos :min kilobytes.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'unique' => 'El valor de :attribute ya está en uso.',

    'attributes' => [
        'direction' => 'dirección',
        'name' => 'nombre',
        'q' => 'búsqueda',
        'region_id' => 'región',
        'sort' => 'orden',
    ],
];
