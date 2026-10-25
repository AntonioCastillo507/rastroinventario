<?php
/**
 * Marca: nombre, logo, datos del negocio y textos del sitio. Versionado y sin secretos
 * (las llaves y contraseñas van en config.local.php, que git ignora).
 * Estos son los valores de demostración de Rastro del Istmo: quien compre la plantilla cambia solo este
 * archivo y las vistas, los correos, la factura PDF y los títulos de página lo reflejan.
 * Se lee con Marca::get("clave.anidada"); los textos salen sin escapar (la vista usa Marca::h()).
 * En "pie" se sustituyen {anio}, {nombre} y {pais}.
 */
return [
    "nombre"      => "Rastro del Istmo",
    "eslogan"     => "Rastro & Partes",
    "descripcion" => "Repuestos de calidad para todo tipo de vehículos. Tu rastro de confianza en Panamá.",
    "logo"        => "assets/img/logo.png",

    "telefono"    => "+507 6542-0519",
    "correo"      => "chapimaster507@gmail.com",
    "direccion"   => "Panamá, República de Panamá",
    "ciudad"      => "Ciudad de Panamá, República de Panamá",
    "pais"        => "Panamá",
    "horario"     => [
        ["dia" => "Lun – Vie", "horas" => "8:00 AM – 5:00 PM", "abierto" => true],
        ["dia" => "Sábado",    "horas" => "8:00 AM – 12:00 PM", "abierto" => true],
        ["dia" => "Domingo",   "horas" => "Cerrado",            "abierto" => false],
    ],
    // Cada red: nombre, url y el icono de Bootstrap Icons. Aquí van las cuentas reales del negocio.
    "redes"       => [
        ["nombre" => "Facebook",  "url" => "https://www.facebook.com/",  "icono" => "facebook"],
        ["nombre" => "Instagram", "url" => "https://www.instagram.com/", "icono" => "instagram"],
    ],

    "portada" => [
        "titulo_pagina" => "Partes y Repuestos en Panamá",
        "etiqueta"      => "Rastro de autos — Panamá",
        "titulo"        => "Tu pieza al",
        "titulo_resalte" => "mejor precio",
        "subtitulo"     => "Repuestos originales para las principales marcas del mercado panameño.",
        "buscador"      => "Buscar: puerta, motor, vidrio, Toyota...",
        "secciones_titulo"   => "Explorar por",
        "secciones_resalte"  => "Sección",
        "secciones_subtitulo" => "Inventario organizado por área del rastro",
        "destacadas_titulo"   => "Piezas",
        "destacadas_resalte"  => "Destacadas",
        "destacadas_subtitulo" => "Las piezas con mayor disponibilidad en stock",
    ],

    "nosotros" => [
        "titulo"         => "El rastro de",
        "titulo_resalte" => "confianza en Panamá",
        "parrafos"       => [
            "Somos un rastro de autos especializado en la comercialización de partes y repuestos de calidad para vehículos de las principales marcas del mercado panameño.",
            "Nuestro inventario se actualiza constantemente, garantizando que encuentres la pieza que necesitas al mejor precio.",
        ],
        "imagen"         => "assets/img/carro1.jpg",
        "destacados"     => [
            ["icono" => "shield-check", "titulo" => "Calidad garantizada",  "texto" => "Piezas revisadas"],
            ["icono" => "truck",        "titulo" => "Entrega segura",       "texto" => "A todo el país"],
            ["icono" => "headset",      "titulo" => "Soporte directo",      "texto" => "Lun–Sáb disponible"],
            ["icono" => "cash-coin",    "titulo" => "Mejores precios",      "texto" => "Transparentes y justos"],
        ],
    ],

    "contacto" => [
        "titulo" => "Contacto",
        "intro"  => "Escríbenos o llámanos y te ayudamos a encontrar la pieza que necesitas.",
    ],

    "impuesto" => [
        "nombre"      => "ITBMS",
        "tasa"        => 0.07,
        "descripcion" => "Impuesto de Transferencia de Bienes Muebles y Servicios (Ley 75 de 1976, Panamá)",
    ],

    "pie" => "© {anio} {nombre} — {pais}",
];
