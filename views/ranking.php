<?php
declare(strict_types=1);

use App\Support\Response;
?>
<div style="display:flex;flex-direction:column;gap:16px;">
  <div class="lx-card" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
      <span class="lx-chip lx-chip-gold">Clasificación global</span>
      <h1 style="margin:6px 0 0;font-size:1.6rem;font-weight:900;">Ranking de LEXO</h1>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="/ranking?orden=points" class="lx-btn lx-btn-sm <?= $sort === 'points' ? 'lx-btn-coral' : 'lx-btn-cream' ?>">Por puntos</a>
      <a href="/ranking?orden=wins" class="lx-btn lx-btn-sm <?= $sort === 'wins' ? 'lx-btn-coral' : 'lx-btn-cream' ?>">Por victorias</a>
      <a href="/ranking?orden=xp" class="lx-btn lx-btn-sm <?= $sort === 'xp' ? 'lx-btn-coral' : 'lx-btn-cream' ?>">Por experiencia</a>
    </div>
  </div>

  <div class="lx-card">
    <?php if (empty($leaders)): ?>
      <p class="lx-hint" style="margin:0;text-align:center;padding:24px;">Todavía no hay partidas finalizadas en el ranking. ¡Sé el primero en subir al podio!</p>
    <?php else: ?>
      <div class="lx-table-wrap">
        <table class="lx-table">
          <thead>
            <tr><th>#</th><th>Jugador</th><th>Puntos</th><th>Victorias</th><th>Palabras</th><th>XP</th><th>★</th></tr>
          </thead>
          <tbody>
            <?php foreach ($leaders as $idx => $row): ?>
              <tr>
                <td class="tabular-nums"><?= $idx === 0 ? '👑 1' : ($idx + 1) ?></td>
                <td>
                  <div style="display:flex;align-items:center;gap:10px;">
                    <span class="lx-avatar lx-avatar-sm" style="background:<?= Response::e($row['avatar_color']) ?>">
                      <?php if (!empty($row['avatar_url'])): ?>
                        <img src="<?= Response::e($row['avatar_url']) ?>" alt="" referrerpolicy="no-referrer">
                      <?php else: ?>
                        <?= Response::e(mb_strtoupper(mb_substr($row['display_name'], 0, 1, 'UTF-8'), 'UTF-8')) ?>
                      <?php endif; ?>
                    </span>
                    <span><?= Response::e($row['display_name']) ?></span>
                  </div>
                </td>
                <td class="tabular-nums" style="color:var(--lx-indigo);"><?= (int) $row['competitive_points_total'] ?></td>
                <td class="tabular-nums"><?= (int) $row['matches_won'] ?> / <?= (int) $row['matches_played'] ?></td>
                <td class="tabular-nums"><?= (int) $row['valid_words_total'] ?></td>
                <td class="tabular-nums"><?= (int) $row['experience_total'] ?></td>
                <td class="tabular-nums" style="color:var(--lx-amber);"><?= (int) $row['stars_total'] ?> ★</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
