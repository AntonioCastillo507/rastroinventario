-- Opiniones de demostración para la portada (S7b-B.2). Todo inventado.
-- Marca de «dato demo»: auto_pub = 2 (el formulario público escribe 1 y el valor por defecto es 0).
-- La tabla comentarios no guarda correo ni usuario, así que ese valor es lo que las identifica;
-- database/quitar_demo.sql las borra por él. Se puede repetir: primero quita las demo anteriores.
-- Cada reseña se pega a la primera pieza que lleve ese nombre; si la pieza no existe, esa reseña no se inserta.
-- Uso:  mysql -u root -h 127.0.0.1 -P 3306 --default-character-set=utf8mb4 NOMBRE_BASE < database\demo_opiniones.sql
-- (en la base real: respaldo en C:\wamp64\respaldo_rastro\ antes y verificación con SELECT después)

SET NAMES utf8mb4;

DELETE FROM comentarios WHERE auto_pub = 2;

INSERT INTO comentarios (parte_id, nombre_visitante, comentario, rating, auto_pub, publicado, creado_en)
SELECT p.id, d.nombre, d.texto, d.rating, 2, 1, NOW() - INTERVAL d.dias DAY - INTERVAL d.horas HOUR
FROM (
  SELECT 'Puerta Delantera Izquierda' COLLATE utf8mb4_unicode_ci AS pieza, 'Marcos Villarreal' AS nombre, 5 AS rating, 2 AS dias, 3 AS horas,
         'Vine por la puerta de mi Hyundai y quedó perfecta, mismo color y sin golpes. Me atendieron rápido y hasta me ayudaron a cargarla al carro.' AS texto
  UNION ALL SELECT 'Alternador 90A', 'Yahaira Samaniego', 5, 5, 6,
         'Mi carro se quedaba sin batería cada dos días. Cambié el alternador y problema resuelto. Buen precio comparado con otros lugares.'
  UNION ALL SELECT 'Faro Delantero Izquierdo', 'Rolando Batista', 4, 9, 1,
         'El faro está en buen estado y calzó sin problema. Tenía un rayoncito mínimo por detrás, pero nada que se note. Recomendado.'
  UNION ALL SELECT 'Bomba de Agua', 'Katherine Quintero', 5, 12, 4,
         'Excelente atención. Me explicaron cómo instalarla y funcionó de una. Ya llevo un mes y cero fugas.'
  UNION ALL SELECT 'Radiador de Aluminio', 'Ricardo Pinzón', 4, 16, 2,
         'Buen radiador, mantiene la temperatura bien hasta en el tranque del mediodía. Solo tardaron un poco en confirmarme que lo tenían.'
  UNION ALL SELECT 'Batería 12V 55Ah', 'Dayana Ábrego', 4, 21, 5,
         'La batería arrancó de una y llevo dos meses sin problemas. Me hubiera gustado que me la probaran ahí mismo, pero todo bien.'
  UNION ALL SELECT 'Espejo Retrovisor Ext. Izq.', 'Javier Concepción', 5, 25, 3,
         'Me chocaron el espejo en el estacionamiento y aquí encontré el reemplazo el mismo día. Llegó completo y con su motorcito. Muy servicial la gente.'
  UNION ALL SELECT 'Parachoques Frontal', 'Lissette Gómez', 3, 29, 7,
         'La pieza sirve, pero traía unos rayones que no se veían en la foto. Igual el precio fue justo y me hicieron un descuento por eso.'
  UNION ALL SELECT 'Motor de Arranque', 'Eduardo Barría', 4, 34, 2,
         'Mi carro no prendía en las mañanas. Con este arranque prende a la primera. Buena pieza usada y buen trato.'
  UNION ALL SELECT 'Parabrisas Delantero', 'Nathaly Rodríguez', 5, 40, 6,
         'Parabrisas sin una sola raya y a mejor precio que en el taller. Súper recomendado, ya les mandé a dos amigos.'
) d
JOIN partes p ON p.nombre = d.pieza AND p.id = (SELECT MIN(p2.id) FROM partes p2 WHERE p2.nombre = d.pieza);
