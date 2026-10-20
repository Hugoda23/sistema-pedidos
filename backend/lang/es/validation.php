<?php

/*
|------------------------------------------------------------------------------
| Mensajes de validación — español
|------------------------------------------------------------------------------
| Cubre las reglas que usa FadeApp. `APP_FALLBACK_LOCALE=en` deja los mensajes
| de Laravel en inglés para cualquier regla que no esté aquí, en lugar de
| mostrar la clave cruda.
*/

return [
    'accepted' => 'Debes aceptar el campo :attribute.',
    'after' => 'El campo :attribute debe ser una fecha posterior a :date.',
    'after_or_equal' => 'El campo :attribute debe ser una fecha igual o posterior a :date.',
    'array' => 'El campo :attribute debe ser una lista.',
    'before' => 'El campo :attribute debe ser una fecha anterior a :date.',
    'before_or_equal' => 'El campo :attribute debe ser una fecha igual o anterior a :date.',
    'between' => [
        'array' => 'El campo :attribute debe tener entre :min y :max elementos.',
        'file' => 'El campo :attribute debe pesar entre :min y :max kilobytes.',
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
        'string' => 'El campo :attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'confirmed' => 'La confirmación del campo :attribute no coincide.',
    'date' => 'El campo :attribute no es una fecha válida.',
    'date_format' => 'El campo :attribute no corresponde al formato :format.',
    'decimal' => 'El campo :attribute debe tener :decimal decimales.',
    'different' => 'El campo :attribute y :other deben ser distintos.',
    'digits' => 'El campo :attribute debe tener :digits dígitos.',
    'digits_between' => 'El campo :attribute debe tener entre :min y :max dígitos.',
    'email' => 'El campo :attribute debe ser una dirección de correo válida.',
    'ends_with' => 'El campo :attribute debe terminar con alguno de estos valores: :values.',
    'enum' => 'El valor del campo :attribute no es válido.',
    'exists' => 'El valor del campo :attribute no existe.',
    'file' => 'El campo :attribute debe ser un archivo.',
    'filled' => 'El campo :attribute no puede quedar vacío.',
    'gt' => [
        'numeric' => 'El campo :attribute debe ser mayor que :value.',
        'string' => 'El campo :attribute debe tener más de :value caracteres.',
    ],
    'gte' => [
        'numeric' => 'El campo :attribute debe ser mayor o igual que :value.',
        'string' => 'El campo :attribute debe tener al menos :value caracteres.',
    ],
    'image' => 'El campo :attribute debe ser una imagen.',
    'in' => 'El valor del campo :attribute no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'lt' => [
        'numeric' => 'El campo :attribute debe ser menor que :value.',
        'string' => 'El campo :attribute debe tener menos de :value caracteres.',
    ],
    'lte' => [
        'numeric' => 'El campo :attribute debe ser menor o igual que :value.',
        'string' => 'El campo :attribute no debe tener más de :value caracteres.',
    ],
    'max' => [
        'array' => 'El campo :attribute no debe tener más de :max elementos.',
        'file' => 'El campo :attribute no debe pesar más de :max kilobytes.',
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute no debe tener más de :max caracteres.',
    ],
    'mimes' => 'El campo :attribute debe ser un archivo de tipo: :values.',
    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
        'file' => 'El campo :attribute debe pesar al menos :min kilobytes.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'not_in' => 'El valor del campo :attribute no es válido.',
    'numeric' => 'El campo :attribute debe ser un número.',
    'present' => 'El campo :attribute debe estar presente.',
    'prohibited' => 'El campo :attribute no está permitido.',
    'regex' => 'El formato del campo :attribute no es válido.',
    'required' => 'El campo :attribute es obligatorio.',
    'required_if' => 'El campo :attribute es obligatorio cuando :other es :value.',
    'required_with' => 'El campo :attribute es obligatorio cuando :values está presente.',
    'required_without' => 'El campo :attribute es obligatorio cuando :values no está presente.',
    'same' => 'El campo :attribute y :other deben coincidir.',
    'size' => [
        'array' => 'El campo :attribute debe tener :size elementos.',
        'file' => 'El campo :attribute debe pesar :size kilobytes.',
        'numeric' => 'El campo :attribute debe ser :size.',
        'string' => 'El campo :attribute debe tener :size caracteres.',
    ],
    'starts_with' => 'El campo :attribute debe empezar con alguno de estos valores: :values.',
    'string' => 'El campo :attribute debe ser texto.',
    'timezone' => 'El campo :attribute debe ser una zona horaria válida.',
    'unique' => 'El campo :attribute ya está en uso.',
    'uploaded' => 'No se pudo subir el campo :attribute.',
    'url' => 'El campo :attribute debe ser una URL válida.',

    'password' => [
        'letters' => 'La contraseña debe contener al menos una letra.',
        'mixed' => 'La contraseña debe contener mayúsculas y minúsculas.',
        'numbers' => 'La contraseña debe contener al menos un número.',
        'symbols' => 'La contraseña debe contener al menos un símbolo.',
        'uncompromised' => 'Esta contraseña apareció en una filtración de datos. Elige otra.',
    ],

    /*
    | Mensajes por campo concreto; los nombres legibles de cada campo se
    | declaran en el método `attributes()` de cada FormRequest.
    */
    'custom' => [],

    'attributes' => [
        'name' => 'nombre',
        'email' => 'correo',
        'password' => 'contraseña',
        'telefono' => 'teléfono',
        'permisos' => 'permisos',
        'nombre' => 'nombre',
        'codigo' => 'código',
        'descripcion' => 'nombre del repuesto',
        'cantidad' => 'cantidad',
        'precio' => 'precio',
        'anticipo' => 'anticipo',
    ],
];
