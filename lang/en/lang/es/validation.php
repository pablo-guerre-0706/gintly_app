<?php

declare(strict_types=1);

// Líneas de idioma de validación — Español (Gintly)
// Contiene las traducciones estándar y heredadas para que ningún error se muestre en
// inglés, usando marcadores que se sustituyen en ejecución.

return [
    // Reglas estándar

    'accepted'             => 'El campo :attribute debe ser aceptado.',
    'accepted_if'          => 'El campo :attribute debe ser aceptado cuando :other es :value.',
    'active_url'           => 'El campo :attribute no es una URL válida.',
    'after'                => 'El campo :attribute debe ser una fecha posterior a :date.',
    'after_or_equal'       => 'El campo :attribute debe ser una fecha posterior o igual a :date.',
    'alpha'                => 'El campo :attribute solo debe contener letras.',
    'alpha_dash'           => 'El campo :attribute solo debe contener letras, números, guiones y guiones bajos.',
    'alpha_num'            => 'El campo :attribute solo debe contener letras y números.',
    'array'                => 'El campo :attribute debe ser un conjunto.',
    'ascii'                => 'El campo :attribute solo debe contener caracteres alfanuméricos y símbolos de un solo byte.',
    'before'               => 'El campo :attribute debe ser una fecha anterior a :date.',
    'before_or_equal'      => 'El campo :attribute debe ser una fecha anterior o igual a :date.',
    'between'              => [
        'array'   => 'El campo :attribute debe tener entre :min y :max elementos.',
        'file'    => 'El campo :attribute debe pesar entre :min y :max kilobytes.',
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
        'string'  => 'El campo :attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean'              => 'El campo :attribute debe ser verdadero o falso.',
    'can'                  => 'El campo :attribute contiene un valor no autorizado.',
    'confirmed'            => 'La confirmación de :attribute no coincide.',
    'contains'             => 'Al campo :attribute le falta un valor requerido.',
    'current_password'     => 'La contraseña actual no es correcta.',
    'date'                 => 'El campo :attribute no es una fecha válida.',
    'date_equals'          => 'El campo :attribute debe ser una fecha igual a :date.',
    'date_format'          => 'El campo :attribute no corresponde al formato :format.',
    'decimal'              => 'El campo :attribute debe tener :decimal decimales.',
    'declined'             => 'El campo :attribute debe ser rechazado.',
    'declined_if'          => 'El campo :attribute debe ser rechazado cuando :other es :value.',
    'different'            => 'Los campos :attribute y :other deben ser diferentes.',
    'digits'               => 'El campo :attribute debe tener :digits dígitos.',
    'digits_between'       => 'El campo :attribute debe tener entre :min y :max dígitos.',
    'dimensions'           => 'El campo :attribute tiene dimensiones de imagen no válidas.',
    'distinct'             => 'El campo :attribute contiene un valor duplicado.',
    'doesnt_end_with'      => 'El campo :attribute no debe terminar con uno de los siguientes valores: :values.',
    'doesnt_start_with'    => 'El campo :attribute no debe comenzar con uno de los siguientes valores: :values.',
    'email'                => 'El campo :attribute no es un correo electrónico válido.',
    'ends_with'            => 'El campo :attribute debe terminar con uno de los siguientes valores: :values.',
    'enum'                 => 'El valor seleccionado en :attribute no es válido.',
    'exists'               => 'El valor seleccionado en :attribute no existe o no está disponible.',
    'extensions'           => 'El campo :attribute debe tener una de las siguientes extensiones: :values.',
    'file'                 => 'El campo :attribute debe ser un archivo.',
    'filled'               => 'El campo :attribute es obligatorio.',
    'gt'                   => [
        'array'   => 'El campo :attribute debe tener más de :value elementos.',
        'file'    => 'El campo :attribute debe pesar más de :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser mayor que :value.',
        'string'  => 'El campo :attribute debe tener más de :value caracteres.',
    ],
    'gte'                  => [
        'array'   => 'El campo :attribute debe tener :value elementos o más.',
        'file'    => 'El campo :attribute debe pesar :value kilobytes o más.',
        'numeric' => 'El campo :attribute debe ser mayor o igual que :value.',
        'string'  => 'El campo :attribute debe tener :value caracteres o más.',
    ],
    'hex_color'            => 'El campo :attribute debe ser un color hexadecimal válido.',
    'image'                => 'El campo :attribute debe ser una imagen.',
    'in'                   => 'El valor seleccionado en :attribute no es válido.',
    'in_array'             => 'El campo :attribute no existe en :other.',
    'integer'              => 'El campo :attribute debe ser un número entero.',
    'ip'                   => 'El campo :attribute debe ser una dirección IP válida.',
    'ipv4'                 => 'El campo :attribute debe ser una dirección IPv4 válida.',
    'ipv6'                 => 'El campo :attribute debe ser una dirección IPv6 válida.',
    'json'                 => 'El campo :attribute debe ser una cadena JSON válida.',
    'list'                 => 'El campo :attribute debe ser una lista.',
    'lowercase'            => 'El campo :attribute debe estar en minúsculas.',
    'lt'                   => [
        'array'   => 'El campo :attribute debe tener menos de :value elementos.',
        'file'    => 'El campo :attribute debe pesar menos de :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser menor que :value.',
        'string'  => 'El campo :attribute debe tener menos de :value caracteres.',
    ],
    'lte'                  => [
        'array'   => 'El campo :attribute no debe tener más de :value elementos.',
        'file'    => 'El campo :attribute debe pesar :value kilobytes o menos.',
        'numeric' => 'El campo :attribute debe ser menor o igual que :value.',
        'string'  => 'El campo :attribute debe tener :value caracteres o menos.',
    ],
    'mac_address'          => 'El campo :attribute debe ser una dirección MAC válida.',
    'max'                  => [
        'array'   => 'El campo :attribute no debe tener más de :max elementos.',
        'file'    => 'El campo :attribute no debe pesar más de :max kilobytes.',
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string'  => 'El campo :attribute no debe tener más de :max caracteres.',
    ],
    'max_digits'           => 'El campo :attribute no debe tener más de :max dígitos.',
    'mimes'                => 'El campo :attribute debe ser un archivo de tipo: :values.',
    'mimetypes'            => 'El campo :attribute debe ser un archivo de tipo: :values.',
    'min'                  => [
        'array'   => 'El campo :attribute debe tener al menos :min elementos.',
        'file'    => 'El campo :attribute debe pesar al menos :min kilobytes.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string'  => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'min_digits'           => 'El campo :attribute debe tener al menos :min dígitos.',
    'missing'              => 'El campo :attribute debe estar ausente.',
    'missing_if'           => 'El campo :attribute debe estar ausente cuando :other es :value.',
    'missing_unless'       => 'El campo :attribute debe estar ausente a menos que :other sea :value.',
    'missing_with'         => 'El campo :attribute debe estar ausente cuando :values está presente.',
    'missing_with_all'     => 'El campo :attribute debe estar ausente cuando :values están presentes.',
    'multiple_of'          => 'El campo :attribute debe ser múltiplo de :value.',
    'not_in'               => 'El valor seleccionado en :attribute no es válido.',
    'not_regex'            => 'El formato del campo :attribute no es válido.',
    'numeric'              => 'El campo :attribute debe ser un número.',
    'password'             => [
        // Respaldo en la raíz para la comprobación de compromiso (algunas
        // rutas de la regla emiten 'validation.uncompromised' a este nivel).
        'letters'       => 'El campo :attribute debe contener al menos una letra.',
        'mixed'         => 'El campo :attribute debe contener al menos una letra mayúscula y una minúscula.',
        'numbers'       => 'El campo :attribute debe contener al menos un número.',
        'symbols'       => 'El campo :attribute debe contener al menos un símbolo.',
        'uncompromised' => 'El campo :attribute ha aparecido en una filtración de datos. Elija un valor distinto.',
    ],
    'present'              => 'El campo :attribute debe estar presente.',
    'present_if'           => 'El campo :attribute debe estar presente cuando :other es :value.',
    'present_unless'       => 'El campo :attribute debe estar presente a menos que :other sea :value.',
    'present_with'         => 'El campo :attribute debe estar presente cuando :values está presente.',
    'present_with_all'     => 'El campo :attribute debe estar presente cuando :values están presentes.',
    'prohibited'           => 'El campo :attribute está prohibido.',
    'prohibited_if'        => 'El campo :attribute está prohibido cuando :other es :value.',
    'prohibited_unless'    => 'El campo :attribute está prohibido a menos que :other esté en :values.',
    'prohibits'            => 'El campo :attribute prohíbe que :other esté presente.',
    'regex'                => 'El formato del campo :attribute no es válido.',
    'required'             => 'El campo :attribute es obligatorio.',
    'required_array_keys'  => 'El campo :attribute debe contener entradas para: :values.',
    'required_if'          => 'El campo :attribute es obligatorio cuando :other es :value.',
    'required_if_accepted' => 'El campo :attribute es obligatorio cuando :other es aceptado.',
    'required_if_declined' => 'El campo :attribute es obligatorio cuando :other es rechazado.',
    'required_unless'      => 'El campo :attribute es obligatorio a menos que :other esté en :values.',
    'required_with'        => 'El campo :attribute es obligatorio cuando :values está presente.',
    'required_with_all'    => 'El campo :attribute es obligatorio cuando :values están presentes.',
    'required_without'     => 'El campo :attribute es obligatorio cuando :values no está presente.',
    'required_without_all' => 'El campo :attribute es obligatorio cuando ninguno de :values está presente.',
    'same'                 => 'Los campos :attribute y :other deben coincidir.',
    'size'                 => [
        'array'   => 'El campo :attribute debe contener :size elementos.',
        'file'    => 'El campo :attribute debe pesar :size kilobytes.',
        'numeric' => 'El campo :attribute debe ser :size.',
        'string'  => 'El campo :attribute debe tener :size caracteres.',
    ],
    'starts_with'          => 'El campo :attribute debe comenzar con uno de los siguientes valores: :values.',
    'string'               => 'El campo :attribute debe ser una cadena de texto.',
    'timezone'             => 'El campo :attribute debe ser una zona horaria válida.',
    'ulid'                 => 'El campo :attribute debe ser un ULID válido.',
    'uncompromised'        => 'El campo :attribute ha aparecido en una filtración de datos. Elija un valor distinto.',
    'unique'               => 'El campo :attribute ya está en uso.',
    'uppercase'            => 'El campo :attribute debe estar en mayúsculas.',
    'url'                  => 'El campo :attribute no es una URL válida.',
    'uuid'                 => 'El campo :attribute debe ser un UUID válido.',


    // Traducciones globales específicas por campo y regla que tienen prioridad sobre
    // los mensajes genéricos.

    'custom' => [
        'current_password' => [
            'current_password' => 'La contraseña indicada no es correcta.',
        ],
        'password' => [
            'different' => 'La nueva contraseña debe ser distinta de la actual.',
        ],
    ],


    // Asigna nombres legibles en español a los campos globales para evitar tener que 
    // traducirlos individualmente en cada FormRequest.Atributos personalizados

    'attributes' => [

        // Identidad y sesión
        'name'                  => 'nombre',
        'email'                 => 'correo electrónico',
        'password'              => 'contraseña',
        'password_confirmation' => 'confirmación de contraseña',
        'current_password'      => 'contraseña actual',
        'remember'              => 'recordar sesión',
        'business_slug'         => 'negocio',
        'role'                  => 'rol',
        'is_active'             => 'estado',
        'branch_id'             => 'sucursal',

        // Negocio y configuración
        'business_id'           => 'negocio',
        'tax_rate'              => 'tasa impositiva',
        'timezone'              => 'zona horaria',
        'plan'                  => 'plan',
        'status'                => 'estado',

        // Sucursales
        'address'               => 'dirección',
        'manager_user_id'       => 'responsable',
        'opened_at'             => 'fecha de apertura',

        // Filtros y paginación
        'page'                  => 'página',
        'per_page'              => 'registros por página',
        'sort'                  => 'campo de ordenamiento',
        'direction'             => 'sentido de ordenamiento',
        'search'                => 'término de búsqueda',
        'trashed'               => 'registros dados de baja',
        'from'                  => 'fecha inicial',
        'to'                    => 'fecha final',

        // Auditoría
        'user_id'               => 'usuario',
        'action'                => 'acción',
        'auditable_type'        => 'tipo de entidad',
        'auditable_id'          => 'identificador de la entidad',
        'ip_address'            => 'dirección de origen',

        // Catálogo (anticipado para MOD-02, evita duplicar en cada request)
        'sku'                   => 'código SKU',
        'type'                  => 'tipo',
        'parent_id'             => 'categoría padre',
        'category_id'           => 'categoría',
        'brand_id'              => 'marca',
        'unit_of_measure_id'    => 'unidad de medida',
        'tracks_inventory'      => 'controla inventario',
        'price'                 => 'precio',
        'cost'                  => 'costo',
    ],

];
