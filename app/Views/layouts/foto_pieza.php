<?php
/**
 * Foto de una pieza en su baldosa blanca 1:1 (DESIGN.md E4): la foto completa con object-fit: contain, sin recortar ni estirar.
 * Recibe $fotoParte (arreglo con imagen_grande y thumbnail), $fotoTam ("thumbnails" | "medium" | "large"),
 * $fotoClase (tamaño: foto-48, foto-64, foto-80, foto-96, foto-160 o foto-ancha) y $fotoAlt (vacío si el nombre ya está al lado).
 * Sin imagen en disco muestra el marcador neutro en la misma baldosa, nunca una imagen rota.
 */
$fotoRuta  = ImageHelper::fuente($fotoParte["imagen_grande"] ?? null, $fotoParte["thumbnail"] ?? null, $fotoTam ?? "thumbnails");
$fotoClase = $fotoClase ?? "";
$fotoAlt   = $fotoAlt ?? "";
$fotoLazy  = $fotoLazy ?? true;
?><span class="foto-baldosa <?=htmlspecialchars($fotoClase)?>"><?php if($fotoRuta !== ""): ?><img src="<?=APP_URL?>/uploads/<?=htmlspecialchars($fotoRuta)?>" alt="<?=htmlspecialchars($fotoAlt)?>" decoding="async"<?=$fotoLazy?' loading="lazy"':''?>><?php else: ?><i aria-hidden="true" class="bi bi-image"></i><?php endif; ?></span>
