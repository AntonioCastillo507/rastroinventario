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
    "eslogan"     => "Repuestos usados",
    "descripcion" => "Repuestos de calidad para todo tipo de vehículos. Tu rastro de confianza en Panamá.",
    "logo"        => "assets/img/logo.png",

    "telefono"    => "+507 6542-0519",
    "correo"      => "chapimaster507@gmail.com",
    "direccion"   => "Panamá, República de Panamá",
    // Enlace del pie a Google Maps. De EJEMPLO (búsqueda genérica de Panamá): reemplazar por la dirección real antes de publicar.
    // Vacío = búsqueda de Google Maps con el texto de "direccion".
    "mapa"        => "https://www.google.com/maps/search/?api=1&query=Panam%C3%A1%2C+Rep%C3%BAblica+de+Panam%C3%A1",
    "ciudad"      => "Ciudad de Panamá, República de Panamá",
    "pais"        => "Panamá",
    "horario"     => [
        ["dia" => "Lun – Vie", "horas" => "8:00 AM – 5:00 PM", "abierto" => true],
        ["dia" => "Sábado",    "horas" => "8:00 AM – 12:00 PM", "abierto" => true],
        ["dia" => "Domingo",   "horas" => "Cerrado",            "abierto" => false],
    ],
    // Botones de redes y mensajería del pie (y "Síguenos" de Contacto): nombre, url y el icono de Bootstrap Icons.
    // Si una url queda vacía, ese botón no se muestra. Los enlaces de abajo son de ejemplo para la demo.
    "redes"       => [
        ["nombre" => "WhatsApp",  "url" => "https://wa.me/",            "icono" => "whatsapp"],  // enlace de ejemplo: reemplazar por la cuenta real antes de publicar
        ["nombre" => "Instagram", "url" => "https://instagram.com/",    "icono" => "instagram"], // enlace de ejemplo: reemplazar por la cuenta real antes de publicar
        ["nombre" => "TikTok",    "url" => "https://tiktok.com/",       "icono" => "tiktok"],    // enlace de ejemplo: reemplazar por la cuenta real antes de publicar
    ],

    "portada" => [
        "titulo_pagina" => "Partes y Repuestos en Panamá",
        "titulo"        => "Tu pieza al",
        "titulo_resalte" => "mejor precio",
        "subtitulo"     => "Repuestos usados para tu carro en Panamá. Busca por nombre o por marca.",
        "buscador"      => "Buscar: puerta, motor, vidrio, Toyota...",
        // Fotos de fondo del hero (escritorio y móvil). Vacías = hero tipográfico, que es el de la plantilla.
        // Las de Rastro del Istmo viven en assets/img/portada/ (carpeta fuera de git; hay copia en la carpeta de respaldos, fuera del proyecto).
        "foto"          => "assets/img/portada/hero-escritorio.webp",
        "foto_movil"    => "assets/img/portada/hero-movil.webp",
        "categorias_titulo" => "Compra por categoría",
        // Icono de Bootstrap Icons (sin el prefijo "bi-") de cada categoría, por id de sección; el que falte usa el de por defecto.
        "categoria_iconos"  => [1 => "car-front", 2 => "gear-wide-connected", 3 => "lightbulb", 4 => "lightning-charge"],
        "categoria_icono_defecto" => "box-seam",
        "recientes_titulo"  => "Recién llegadas",
        "recientes_ver"     => "Ver las {total} piezas",
        "marcas_titulo"     => "Compra por marca",
        // Textos accesibles de los controles del carrusel de marcas (flechas y pausa)
        "marcas_anterior"   => "Marcas anteriores",
        "marcas_siguiente"  => "Marcas siguientes",
        "marcas_pausar"     => "Pausar el movimiento de las marcas",
        "marcas_reanudar"   => "Reanudar el movimiento de las marcas",
        // Carrusel de piezas del hero (S7b-A.1): {n} y {total} se reemplazan por el número de la pieza y el total
        "hero_carrusel"     => "Piezas disponibles",
        "hero_barra"        => "Ver la pieza {n} de {total}",
        "hero_pausar"       => "Pausar el cambio de piezas",
        "hero_reanudar"     => "Reanudar el cambio de piezas",
        // Cuadro «Por qué elegirnos» (S7b-A.2): textos genéricos de plantilla, sin afirmaciones por confirmar. La foto es nosotros.imagen
        "porque_insignia"   => "Quiénes somos",
        "porque_titulo"     => "Por qué elegirnos",
        "porque_subtitulo"  => "Repuestos usados para tu carro, con trato directo",
        "porque_texto"      => "Conoce quiénes estamos detrás de la tienda y cómo trabajamos para ayudarte a encontrar la pieza que necesitas.",
        "porque_boton"      => "Conoce más sobre nosotros",
        "cta_titulo"        => "¿No encuentras tu pieza?",
        "cta_texto"         => "Escríbenos o llámanos con el nombre de la pieza y tu vehículo.",
        "cta_boton"         => "Contáctanos",
        // Franja de confianza: oculta mientras esta lista esté vacía. Cada elemento: ["icono" => "...", "titulo" => "...", "texto" => "..."].
        // Solo afirmaciones confirmadas (garantía, devolución, retiro, envíos): ninguna se escribe en las vistas.
        "confianza"     => [],
    ],

    "nosotros" => [
        "titulo"         => "El rastro de",
        "titulo_resalte" => "confianza en Panamá",
        "parrafos"       => [
            "Somos un rastro de autos especializado en la comercialización de partes y repuestos de calidad para vehículos de las principales marcas del mercado panameño.",
            "Nuestro inventario se actualiza constantemente, garantizando que encuentres la pieza que necesitas al mejor precio.",
        ],
        "imagen"         => "assets/img/nosotros/equipo.jpg", // foto del equipo (fuera de git). Vacía = el texto ocupa el ancho; al empaquetar la plantilla se deja vacía
        // Las insignias de "destacados" son de ejemplo y no están verificadas: no se publican mientras esto sea false.
        "destacados_visibles" => false,
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

    // Panel: rango que abre el dashboard (7, 30, 90 o 365 = año). Poner 30 cuando haya pedidos recientes.
    "dashboard" => [
        "rango_defecto" => 365,
    ],

    "pie" => "© {anio} {nombre} — {pais}",
];
