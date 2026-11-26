<?php
declare(strict_types=1);
?>
<div style="max-width:760px;margin:0 auto;display:flex;flex-direction:column;gap:16px;">
  <div class="lx-card">
    <span class="lx-chip lx-chip-gold">Guía rápida</span>
    <h1 style="margin:8px 0 6px;font-size:1.7rem;font-weight:900;">¿Cómo se juega LEXO?</h1>
    <p class="lx-hint" style="margin:0;">LEXO acompaña tus partidas de <strong>Lexo 3D</strong> con un árbitro digital sincronizado en tiempo real.</p>
  </div>

  <div class="lx-card" style="display:flex;flex-direction:column;gap:12px;">
    <h2 style="margin:0;font-size:1.15rem;font-weight:900;">1. Dinámica con Lexo 3D</h2>
    <ul style="margin:0;padding-left:20px;display:flex;flex-direction:column;gap:6px;">
      <li>Todos los participantes de una sala comparten el mismo juego físico <strong>Lexo 3D</strong> en la mesa.</li>
      <li>Cada jugador arma y anota sus palabras desde su propio dispositivo mientras corre el tiempo común.</li>
      <li>Mientras dura la ronda, tus palabras son privadas: ningún rival puede ver lo que estás escribiendo.</li>
    </ul>
  </div>

  <div class="lx-card" style="display:flex;flex-direction:column;gap:12px;">
    <h2 style="margin:0;font-size:1.15rem;font-weight:900;">2. Reglas de palabras y puntuación</h2>
    <ul style="margin:0;padding-left:20px;display:flex;flex-direction:column;gap:6px;">
      <li>No se pueden repetir palabras dentro de la misma ronda (tampoco cambiando mayúsculas o tildes como <code>árbol</code> y <code>ARBOL</code>; la <strong>Ñ</strong> sí se respeta como letra distinta).</li>
      <li><strong>Por longitud (recomendado):</strong> 2 letras = 1 pt · 3 letras = 2 pts · 4 letras = 3 pts · 5 letras = 4 pts · 6 o más letras = 5 pts.</li>
      <li><strong>Por cantidad:</strong> cada palabra válida suma 1 punto.</li>
      <li><strong>Validación:</strong> el anfitrión puede elegir <em>Mesa libre</em> (reglas ortográficas + revisión por consenso al final de la ronda) o <em>Diccionario español local</em> (<?= (int) $dictCount ?> palabras cargadas).</li>
    </ul>
  </div>

  <div class="lx-card" style="display:flex;flex-direction:column;gap:12px;">
    <h2 style="margin:0;font-size:1.15rem;font-weight:900;">3. Puntos, Estrellas y Experiencia</h2>
    <ul style="margin:0;padding-left:20px;display:flex;flex-direction:column;gap:6px;">
      <li><strong>Puntos competitivos:</strong> definen el ganador de cada ronda, el podio y el Ranking.</li>
      <li><strong>Estrellas (★):</strong> se otorgan por ganar partidas y desbloquear logros.</li>
      <li><strong>Experiencia (XP):</strong> subís de nivel por participar, ganar rondas y completar desafíos.</li>
    </ul>
  </div>
</div>
