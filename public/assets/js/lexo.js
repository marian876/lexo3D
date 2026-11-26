/* ============================================================================
   LEXO — Controlador de Cliente
   Temporizador sincronizado con el servidor, polling adaptativo,
   sonido Web Audio, QR SVG real y tarjeta compartible en Canvas.
   ============================================================================ */

(() => {
  'use strict';

  // --- 0. PWA: Service Worker e instalación controlada por el navegador ---
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
  }

  const installButton = document.getElementById('install-app');
  const installStatus = document.getElementById('install-status');
  const supportsWebInstall = typeof Navigator.prototype.install === 'function';
  let deferredInstallPrompt;

  if (installButton) {
    if (supportsWebInstall) {
      installButton.hidden = false;
    }

    window.addEventListener('beforeinstallprompt', (event) => {
      event.preventDefault();
      deferredInstallPrompt = event;
      if (!supportsWebInstall) {
        installButton.hidden = false;
      }
    });

    window.addEventListener('appinstalled', () => {
      deferredInstallPrompt = undefined;
      installButton.hidden = true;
      if (installStatus) installStatus.textContent = 'LEXO fue instalada en tu dispositivo.';
    });

    installButton.addEventListener('click', async () => {
      if (installButton.getAttribute('aria-disabled') === 'true') return;
      installButton.setAttribute('aria-disabled', 'true');

      try {
        if (supportsWebInstall) {
          await navigator.install();
          if (installStatus) installStatus.textContent = 'LEXO fue instalada o abierta.';
          installButton.hidden = true;
        } else if (deferredInstallPrompt) {
          const promptEvent = deferredInstallPrompt;
          deferredInstallPrompt = undefined;
          await promptEvent.prompt();
          const { outcome } = await promptEvent.userChoice;
          if (installStatus) {
            installStatus.textContent = outcome === 'accepted'
              ? 'LEXO fue instalada en tu dispositivo.'
              : 'Instalación cancelada.';
          }
          if (!deferredInstallPrompt) {
            installButton.hidden = true;
          }
        }
      } catch (error) {
        if (installStatus) {
          installStatus.textContent = error?.name === 'AbortError'
            ? 'Instalación cancelada.'
            : 'No se pudo iniciar la instalación.';
        }
      } finally {
        installButton.removeAttribute('aria-disabled');
      }
    });
  }

  // --- 1. Control de tema (dark-mode-toggle guidance) ---
  const themeRadios = document.querySelectorAll('input[name="lexo-color-scheme"]');
  const metaScheme = document.querySelector('meta[name="color-scheme"]');

  const syncThemeUI = (val) => {
    themeRadios.forEach((r) => {
      r.checked = r.value === val;
    });
  };

  const applyThemeChoice = (choice) => {
    const valid = choice === 'light' || choice === 'dark' ? choice : 'light dark';
    if (metaScheme) metaScheme.content = valid;
    try {
      if (valid === 'light dark') {
        localStorage.removeItem('color-scheme');
      } else {
        localStorage.setItem('color-scheme', valid);
      }
    } catch (_) {}
    syncThemeUI(valid);
  };

  let initialTheme = 'light dark';
  try {
    const saved = localStorage.getItem('color-scheme');
    if (saved === 'light' || saved === 'dark') initialTheme = saved;
  } catch (_) {}
  syncThemeUI(initialTheme);

  themeRadios.forEach((radio) => {
    radio.addEventListener('change', (e) => {
      if (e.target.checked) applyThemeChoice(e.target.value);
    });
  });

  window.addEventListener('storage', (e) => {
    if (e.key === 'color-scheme') {
      const next = e.newValue === 'light' || e.newValue === 'dark' ? e.newValue : 'light dark';
      if (metaScheme) metaScheme.content = next;
      syncThemeUI(next);
    }
  });

  // --- 2. Fallback light-dismiss para <dialog closedby="any"> en Safari ---
  if (!('closedBy' in HTMLDialogElement.prototype)) {
    const outside = (e, dialog) => {
      const r = dialog.getBoundingClientRect();
      return e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom;
    };
    document.querySelectorAll('dialog[closedby="any"]').forEach((dialog) => {
      let pressedOutside = false;
      dialog.addEventListener('pointerdown', (e) => {
        pressedOutside = outside(e, dialog);
      });
      dialog.addEventListener('pointerup', (e) => {
        if (pressedOutside && outside(e, dialog)) dialog.close();
      });
    });
  }

  // Botones declarativos de apertura/cierre por si commandfor no está disponible
  document.querySelectorAll('[data-open-dialog]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-open-dialog');
      const target = document.getElementById(targetId);
      if (targetId === 'create-room-dialog') {
        const guestNick = document.getElementById('guest-name')?.value?.trim() || '';
        const modalNick = document.getElementById('create-guest-name');
        const roomName = document.getElementById('room-name');
        if (modalNick && guestNick && !modalNick.value.trim()) {
          modalNick.value = guestNick;
        }
        if (roomName && guestNick && (roomName.value === 'Mesa LEXO' || roomName.value === '')) {
          roomName.value = `Mesa de ${guestNick}`;
        }
      }
      if (target && typeof target.showModal === 'function' && !target.open) {
        target.showModal();
      }
    });
  });
  document.querySelectorAll('[data-close-dialog]').forEach((btn) => {
    btn.addEventListener('click', () => {
      btn.closest('dialog')?.close();
    });
  });

  // --- 3. Sincronización ARIA de validación tras interacción ---
  const flaggedFields = new WeakSet();
  const syncAriaField = (el) => {
    if (!el.matches?.('input, select, textarea')) return;
    let invalid = false;
    try {
      invalid = el.matches(':user-invalid');
    } catch (_) {
      invalid = el.classList.contains('user-invalid-fallback');
    }
    const errorId = el.dataset.error;
    if (invalid) {
      el.setAttribute('aria-invalid', 'true');
      if (errorId) {
        const ids = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter((i) => i && i !== errorId);
        ids.push(errorId);
        el.setAttribute('aria-describedby', ids.join(' '));
      }
      flaggedFields.add(el);
    } else if (flaggedFields.has(el) || el.getAttribute('aria-invalid') !== 'true') {
      el.setAttribute('aria-invalid', 'false');
      if (errorId) {
        const ids = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter((i) => i && i !== errorId);
        if (ids.length) el.setAttribute('aria-describedby', ids.join(' '));
        else el.removeAttribute('aria-describedby');
      }
      flaggedFields.delete(el);
    }
  };
  document.querySelectorAll('input, select, textarea').forEach(syncAriaField);
  document.addEventListener('blur', (e) => syncAriaField(e.target), true);
  document.addEventListener('input', (e) => {
    if (e.target.hasAttribute?.('aria-invalid')) syncAriaField(e.target);
  });

  // --- 4. Audio táctil opcional (Web Audio API sin archivos MP3) ---
  const soundToggle = document.getElementById('lexo-sound-toggle');
  let soundEnabled = true;
  try {
    soundEnabled = localStorage.getItem('lexo-sound') !== 'off';
  } catch (_) {}
  if (soundToggle) {
    soundToggle.checked = soundEnabled;
    soundToggle.addEventListener('change', () => {
      soundEnabled = soundToggle.checked;
      try {
        localStorage.setItem('lexo-sound', soundEnabled ? 'on' : 'off');
      } catch (_) {}
    });
  }

  let audioCtx = null;
  const playTone = (type) => {
    if (!soundEnabled) return;
    try {
      const Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      if (!audioCtx) audioCtx = new Ctx();
      if (audioCtx.state === 'suspended') audioCtx.resume();

      const now = audioCtx.currentTime;
      const osc = audioCtx.createOscillator();
      const gain = audioCtx.createGain();
      osc.connect(gain);
      gain.connect(audioCtx.destination);

      if (type === 'pop') {
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(520, now);
        osc.frequency.exponentialRampToValueAtTime(780, now + 0.08);
        gain.gain.setValueAtTime(0.12, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.11);
        osc.start(now);
        osc.stop(now + 0.12);
      } else if (type === 'err') {
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(210, now);
        osc.frequency.linearRampToValueAtTime(150, now + 0.14);
        gain.gain.setValueAtTime(0.08, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.15);
        osc.start(now);
        osc.stop(now + 0.16);
      } else if (type === 'win') {
        [523.25, 659.25, 783.99].forEach((freq, idx) => {
          const o = audioCtx.createOscillator();
          const g = audioCtx.createGain();
          o.type = 'triangle';
          o.connect(g);
          g.connect(audioCtx.destination);
          o.frequency.setValueAtTime(freq, now + idx * 0.08);
          g.gain.setValueAtTime(0.11, now + idx * 0.08);
          g.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.08 + 0.25);
          o.start(now + idx * 0.08);
          o.stop(now + idx * 0.08 + 0.26);
        });
      }
    } catch (_) {}
  };

  // --- 5. Generador de código QR SVG ligero (Byte mode, Versión 2-4 ECC-L) ---
  // Genera un QR real escaneable por cámara para unir mesas presenciales en 2 segundos.
  const renderQrSvg = (container, text) => {
    if (!container) return;
    const modules = generateQrMatrix(text);
    const size = modules.length;
    let d = '';
    for (let r = 0; r < size; r++) {
      for (let c = 0; c < size; c++) {
        if (modules[r][c]) {
          d += `M${c},${r}h1v1h-1z`;
        }
      }
    }
    container.innerHTML = `<svg viewBox="-2 -2 ${size + 4} ${size + 4}" shape-rendering="crispEdges" role="img" aria-label="Código QR de la sala"><rect x="-2" y="-2" width="${size + 4}" height="${size + 4}" fill="#ffffff"/><path d="${d}" fill="#1E1B2E"/></svg>`;
  };

  // Implementación compacta de QR Code (ECC-L, Byte mode, versiones 1-6)
  function generateQrMatrix(text) {
    const bytes = Array.from(new TextEncoder().encode(text));
    // Capacidad ECC-L Byte mode: v1=17, v2=32, v3=53, v4=78, v5=106, v6=134
    const caps = [0, 17, 32, 53, 78, 106, 134];
    const totalCodewords = [0, 26, 44, 70, 100, 134, 172];
    const ecCodewords = [0, 7, 10, 15, 20, 26, 18]; // v6 uses 2 blocks of 18 (simplified 1 block up to v5, let's support v1-v5!)
    let ver = 1;
    for (let v = 1; v <= 5; v++) {
      if (bytes.length <= caps[v]) { ver = v; break; }
      ver = 5;
    }
    const dataCap = totalCodewords[ver] - ecCodewords[ver];
    const bits = [];
    const pushBits = (val, len) => {
      for (let i = len - 1; i >= 0; i--) bits.push((val >> i) & 1);
    };
    pushBits(0b0100, 4); // Byte mode
    pushBits(bytes.length, 8);
    bytes.forEach((b) => pushBits(b, 8));
    const maxDataBits = dataCap * 8;
    for (let i = 0; i < 4 && bits.length < maxDataBits; i++) bits.push(0);
    while (bits.length % 8 !== 0) bits.push(0);
    const padBytes = [0xec, 0x11];
    let padIdx = 0;
    while (bits.length < maxDataBits) {
      pushBits(padBytes[padIdx % 2], 8);
      padIdx++;
    }
    const dataWords = [];
    for (let i = 0; i < bits.length; i += 8) {
      let w = 0;
      for (let j = 0; j < 8; j++) w = (w << 1) | bits[i + j];
      dataWords.push(w);
    }

    // GF(256) Reed-Solomon
    const gfExp = new Array(512);
    const gfLog = new Array(256);
    let x = 1;
    for (let i = 0; i < 255; i++) {
      gfExp[i] = x;
      gfLog[x] = i;
      x <<= 1;
      if (x & 0x100) x ^= 0x11d;
    }
    for (let i = 255; i < 512; i++) gfExp[i] = gfExp[i - 255];
    const gfMul = (a, b) => (a === 0 || b === 0 ? 0 : gfExp[gfLog[a] + gfLog[b]]);

    const ecLen = ecCodewords[ver];
    let gen = [1];
    for (let i = 0; i < ecLen; i++) {
      const next = new Array(gen.length + 1).fill(0);
      const factor = gfExp[i];
      for (let j = 0; j < gen.length; j++) {
        next[j] ^= gen[j];
        next[j + 1] ^= gfMul(gen[j], factor);
      }
      gen = next;
    }
    const msg = dataWords.concat(new Array(ecLen).fill(0));
    for (let i = 0; i < dataWords.length; i++) {
      const coef = msg[i];
      if (coef !== 0) {
        for (let j = 0; j < gen.length; j++) {
          msg[i + j] ^= gfMul(gen[j], coef);
        }
      }
    }
    const allWords = dataWords.concat(msg.slice(dataWords.length));

    // Construir matriz
    const n = ver * 4 + 17;
    const mat = Array.from({ length: n }, () => new Array(n).fill(null));
    const fixed = Array.from({ length: n }, () => new Array(n).fill(false));

    const setMod = (r, c, val, isFixed = true) => {
      if (r < 0 || r >= n || c < 0 || c >= n) return;
      mat[r][c] = val ? 1 : 0;
      if (isFixed) fixed[r][c] = true;
    };

    const drawFinder = (r0, c0) => {
      for (let r = -1; r <= 7; r++) {
        for (let c = -1; c <= 7; c++) {
          const rr = r0 + r, cc = c0 + c;
          if (rr < 0 || rr >= n || cc < 0 || cc >= n) continue;
          const inOuter = r >= 0 && r <= 6 && c >= 0 && c <= 6;
          const onBorder = r === 0 || r === 6 || c === 0 || c === 6;
          const inCore = r >= 2 && r <= 4 && c >= 2 && c <= 4;
          setMod(rr, cc, inOuter && (onBorder || inCore));
        }
      }
    };

    drawFinder(0, 0);
    drawFinder(0, n - 7);
    drawFinder(n - 7, 0);

    // Alignment pattern para v2..v5
    if (ver >= 2) {
      const alignPos = [0, 0, 18, 22, 26, 30][ver];
      for (let r = -2; r <= 2; r++) {
        for (let c = -2; c <= 2; c++) {
          const on = Math.abs(r) === 2 || Math.abs(c) === 2 || (r === 0 && c === 0);
          setMod(alignPos + r, alignPos + c, on);
        }
      }
    }

    // Timing patterns
    for (let i = 8; i < n - 8; i++) {
      if (!fixed[6][i]) setMod(6, i, i % 2 === 0);
      if (!fixed[i][6]) setMod(i, 6, i % 2 === 0);
    }
    setMod(n - 8, 8, 1);

    // Reservar format info
    for (let i = 0; i < 9; i++) {
      if (!fixed[8][i]) setMod(8, i, 0);
      if (!fixed[i][8]) setMod(i, 8, 0);
    }
    for (let i = 0; i < 8; i++) {
      if (!fixed[8][n - 1 - i]) setMod(8, n - 1 - i, 0);
      if (!fixed[n - 1 - i][8]) setMod(n - 1 - i, 8, 0);
    }

    // Colocar bits en zigzag
    const allBits = [];
    allWords.forEach((w) => {
      for (let b = 7; b >= 0; b--) allBits.push((w >> b) & 1);
    });
    let bitIdx = 0;
    let up = true;
    for (let col = n - 1; col > 0; col -= 2) {
      if (col === 6) col--;
      for (let i = 0; i < n; i++) {
        const r = up ? n - 1 - i : i;
        for (let dc = 0; dc < 2; dc++) {
          const c = col - dc;
          if (!fixed[r][c]) {
            const bit = bitIdx < allBits.length ? allBits[bitIdx++] : 0;
            // Máscara 0: (r + c) % 2 === 0
            const masked = bit ^ ((r + c) % 2 === 0 ? 1 : 0);
            mat[r][c] = masked;
          }
        }
      }
      up = !up;
    }

    // Format string ECC-L (01) + Mask 0 (000) -> BCH = 111011111000100
    const fmt = '111011111000100'.split('').map(Number);
    const fCoords1 = [[8,0],[8,1],[8,2],[8,3],[8,4],[8,5],[8,7],[8,8],[7,8],[5,8],[4,8],[3,8],[2,8],[1,8],[0,8]];
    const fCoords2 = [[n-1,8],[n-2,8],[n-3,8],[n-4,8],[n-5,8],[n-6,8],[n-7,8],[8,n-8],[8,n-7],[8,n-6],[8,n-5],[8,n-4],[8,n-3],[8,n-2],[8,n-1]];
    for (let i = 0; i < 15; i++) {
      mat[fCoords1[i][0]][fCoords1[i][1]] = fmt[i];
      mat[fCoords2[i][0]][fCoords2[i][1]] = fmt[i];
    }
    return mat;
  }

  // --- 6. Controlador en vivo de Sala / Partida / Podio ---
  const roomApp = document.getElementById('lexo-room-app');
  if (!roomApp) return;

  const state = window.__LEXO_INITIAL_STATE__;
  const csrf = window.__LEXO_CSRF__ || '';
  const roomCode = state.room.code;

  let currentState = state;
  let serverOffsetMs = state.server_now_ms - Date.now();
  let timerFrame = null;
  let pollTimeout = null;
  let lastViewKey = '';
  let celebratingRoundId = null;

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));

  const apiPost = async (url, payload) => {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf,
      },
      body: JSON.stringify(payload),
    });
    return res.json();
  };

  const renderAvatar = (p, extraClass = '') => {
    const initial = esc((p.display_name || '?').trim().charAt(0).toUpperCase());
    if (p.avatar_url) {
      return `<span class="lx-avatar ${extraClass}"><img src="${esc(p.avatar_url)}" alt="${esc(p.display_name)}" referrerpolicy="no-referrer"></span>`;
    }
    return `<span class="lx-avatar ${extraClass}" style="background:${esc(p.avatar_color || '#FF5A36')}">${initial}</span>`;
  };

  const getViewKey = (s) => {
    if (s.room.status === 'waiting') return 'lobby';
    if (s.match && s.match.status === 'finished') return `match-done-${s.match.id}`;
    if (s.round && s.round.status === 'active') return `round-active-${s.round.id}`;
    if (s.round && s.round.status === 'finished') return `round-done-${s.round.id}`;
    return 'lobby';
  };

  const renderRoomUI = (s, forceRender = false) => {
    const viewKey = getViewKey(s);
    const shouldRebuild = forceRender || viewKey !== lastViewKey;

    if (!shouldRebuild) {
      // Actualización ligera sin destruir el input o el foco durante la ronda
      if (viewKey === 'lobby') {
        updateLobbyPlayers(s);
      } else if (viewKey.startsWith('round-active-')) {
        updateLiveCounters(s);
      } else if (viewKey.startsWith('round-done-')) {
        updateRoundResultsHostControls(s);
      }
      return;
    }

    lastViewKey = viewKey;
    clearInterval(timerFrame);

    if (viewKey === 'lobby') {
      renderLobbyView(s);
    } else if (viewKey.startsWith('round-active-')) {
      renderRoundActiveView(s);
    } else if (viewKey.startsWith('round-done-')) {
      renderRoundResultsView(s);
      if (celebratingRoundId !== s.round?.id) {
        celebratingRoundId = s.round?.id;
        playTone('win');
      }
    } else if (viewKey.startsWith('match-done-')) {
      renderMatchFinalView(s);
      playTone('win');
    }
  };

  const renderLobbyView = (s) => {
    const r = s.room;
    const shareUrl = `${window.location.origin}/join?code=${encodeURIComponent(r.code)}`;
    const scoringLabel = r.scoring_mode === 'length' ? '📏 Por longitud' : '🔢 Por cantidad';
    const validLabel = r.validation_mode === 'dictionary' ? '📖 Diccionario ES' : '🎲 Mesa libre';

    roomApp.innerHTML = `
      <div class="lx-room-grid">
        <div class="lx-card" style="display:flex;flex-direction:column;gap:16px;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
            <div>
              <span class="lx-chip">Sala privada</span>
              <h1 style="margin:8px 0 4px;font-size:1.5rem;font-weight:900;">${esc(r.name)}</h1>
              <div class="lx-hint">Anfitrión: <strong>${esc(r.host_name)}</strong></div>
            </div>
            <span class="lx-chip lx-chip-green tabular-nums" id="lobby-count">${s.players.length}/${r.max_players}</span>
          </div>

          <div class="lx-card-soft" style="display:flex;align-items:center;justify-content:space-between;gap:14px;">
            <div>
              <div class="lx-hint" style="text-transform:uppercase;font-weight:800;">Código de mesa</div>
              <div style="font-size:1.9rem;font-weight:900;letter-spacing:0.12em;color:var(--lx-amber);" class="tabular-nums">${esc(r.code)}</div>
              <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">
                <button type="button" id="btn-copy-code" class="lx-btn lx-btn-cream lx-btn-sm">📋 Copiar</button>
              </div>
            </div>
            <div class="lx-qr-box" id="lobby-qr"></div>
          </div>

          <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <span class="lx-chip">🔄 ${r.round_count} rondas</span>
            <span class="lx-chip">⏱️ ${r.round_duration_seconds} seg</span>
            <span class="lx-chip">${scoringLabel}</span>
            <span class="lx-chip">${validLabel}</span>
            <span class="lx-chip">🔡 Mín. ${r.min_word_length} letras</span>
          </div>
        </div>

        <div class="lx-card" style="display:flex;flex-direction:column;justify-content:space-between;gap:16px;">
          <div>
            <h2 style="margin:0 0 12px;font-size:1.1rem;font-weight:900;">Jugadores en la mesa</h2>
            <div id="lobby-players-list" style="display:flex;flex-direction:column;gap:10px;"></div>
          </div>

          <div style="display:flex;flex-direction:column;gap:10px;">
            <div id="lobby-host-msg" class="lx-hint" style="text-align:center;"></div>
            ${s.is_host
              ? `<button type="button" id="btn-start-match" class="lx-btn lx-btn-coral lx-btn-lg lx-btn-block">🚀 Comenzar partida</button>`
              : `<div class="lx-card-soft" style="text-align:center;font-weight:800;">Esperando a que ${esc(r.host_name)} inicie la partida...</div>`
            }
          </div>
        </div>
      </div>
    `;

    renderQrSvg(document.getElementById('lobby-qr'), shareUrl);
    updateLobbyPlayers(s);

    document.getElementById('btn-copy-code')?.addEventListener('click', (e) => {
      navigator.clipboard?.writeText(shareUrl);
      const btn = e.currentTarget;
      btn.textContent = '✓ ¡Enlace copiado!';
      setTimeout(() => { btn.textContent = '📋 Copiar'; }, 1400);
    });

    document.getElementById('btn-start-match')?.addEventListener('click', async (e) => {
      const btn = e.currentTarget;
      btn.disabled = true;
      const res = await apiPost('/api/room/start', { code: roomCode });
      if (!res.ok) {
        document.getElementById('lobby-host-msg').innerHTML = `<span style="color:var(--lx-crimson);font-weight:800;">${esc(res.error)}</span>`;
        btn.disabled = false;
        playTone('err');
      } else {
        await pollState(true);
      }
    });
  };

  const updateLobbyPlayers = (s) => {
    const list = document.getElementById('lobby-players-list');
    const count = document.getElementById('lobby-count');
    if (count) count.textContent = `${s.players.length}/${s.room.max_players}`;
    if (!list) return;

    list.innerHTML = s.players.map((p) => {
      const onlineDot = p.connection_state === 'online'
        ? `<span style="color:var(--lx-emerald);font-weight:800;font-size:0.78rem;">● En línea</span>`
        : `<span style="color:var(--lx-muted);font-weight:800;font-size:0.78rem;">○ Reconectando</span>`;
      const hostBadge = Number(p.is_host) === 1
        ? `<span class="lx-chip lx-chip-gold" style="padding:2px 8px;font-size:0.68rem;">ANFITRIÓN</span>`
        : '';
      return `
        <div class="lx-card-soft" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;">
          <div style="display:flex;align-items:center;gap:10px;">
            ${renderAvatar(p)}
            <div>
              <div style="font-weight:900;font-size:0.95rem;display:flex;align-items:center;gap:6px;">
                ${esc(p.display_name)} ${hostBadge}
              </div>
              <div class="lx-hint">${Number(p.stars_total)} ★ · ${Number(p.experience_total)} XP</div>
            </div>
          </div>
          ${onlineDot}
        </div>
      `;
    }).join('');

    const startBtn = document.getElementById('btn-start-match');
    const msg = document.getElementById('lobby-host-msg');
    if (startBtn && msg) {
      if (s.players.length < 2) {
        startBtn.disabled = true;
        msg.textContent = 'Esperando al menos 2 jugadores en la mesa para comenzar.';
      } else {
        startBtn.disabled = false;
        msg.textContent = '¡Mesa lista! Ya pueden comenzar la partida con Lexo 3D.';
      }
    }
  };

  const renderRoundActiveView = (s) => {
    const r = s.room;
    const m = s.match;
    const round = s.round;
    const isPractice = Number(r.max_players) === 1;
    const totalWords = s.my_words.length;
    const totalPts = s.my_words.reduce((acc, w) => acc + Number(w.points_awarded || 0), 0);

    roomApp.innerHTML = `
      <div style="max-width:680px;margin:0 auto;display:flex;flex-direction:column;gap:14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
          <span class="lx-chip">${isPractice ? '🎯 Práctica individual' : `Ronda ${round.round_number} de ${m.round_count_snapshot}`}</span>
          ${isPractice ? '' : '<div id="live-rivals" style="display:flex;align-items:center;gap:6px;"></div>'}
          <span class="lx-chip tabular-nums" id="live-score-chip">${totalWords} palabras · ${totalPts} pts</span>
        </div>

        <div class="lx-card lx-timer-box">
          <div class="lx-hint" style="text-transform:uppercase;font-weight:800;margin-bottom:6px;">${isPractice ? 'Practicá con Lexo3D' : 'Formá palabras con Lexo 3D'}</div>
          <div id="live-timer" class="lx-timer-value tabular-nums">--:--</div>
          <div class="lx-progress-track" style="margin-top:12px;">
            <div id="live-timer-bar" class="lx-progress-fill" style="width:100%;"></div>
          </div>
        </div>

        <div class="lx-card" style="padding:16px;">
          <form id="word-submit-form" style="display:flex;gap:10px;" autocomplete="off">
            <input
              id="word-input"
              type="text"
              inputmode="text"
              autocapitalize="characters"
              autocomplete="off"
              autocorrect="off"
              spellcheck="false"
              placeholder="Escribí una palabra y presioná Enter..."
              aria-label="Escribir palabra"
              class="lx-input"
              style="font-size:1.15rem;font-weight:900;text-transform:uppercase;letter-spacing:0.06em;"
            />
            <button type="submit" class="lx-btn lx-btn-coral" style="padding:0 22px;font-size:1.3rem;" aria-label="Agregar palabra">↵</button>
          </form>
          <div id="word-feedback" aria-live="polite" style="min-height:22px;margin-top:8px;font-size:0.85rem;font-weight:800;"></div>
        </div>

        <div class="lx-card" style="display:flex;flex-direction:column;gap:10px;">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span class="lx-hint" style="text-transform:uppercase;font-weight:800;">Tus palabras registradas</span>
            ${isPractice ? '' : '<span class="lx-hint">🔒 Ocultas a rivales hasta el cierre</span>'}
          </div>
          <div id="my-words-list" class="lx-word-chips"></div>
        </div>
      </div>
    `;

    updateLiveCounters(s);
    renderMyWordsChips(s.my_words);

    const input = document.getElementById('word-input');
    const form = document.getElementById('word-submit-form');
    const feedback = document.getElementById('word-feedback');

    setTimeout(() => input?.focus(), 60);

    let submitting = false;
    form?.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!input || submitting) return;
      const raw = input.value.trim();
      if (!raw) return;

      submitting = true;
      const res = await apiPost('/api/round/word', {
        round_id: round.id,
        word: raw,
      });
      submitting = false;

      if (res.ok) {
        currentState.my_words.unshift(res.word);
        renderMyWordsChips(currentState.my_words, true);
        updateLiveCounters(currentState);
        feedback.style.color = 'var(--lx-emerald)';
        feedback.textContent = `✓ "${res.word.original_text}" sumó +${res.word.points_awarded} pts`;
        input.value = '';
        input.focus();
        playTone('pop');
      } else {
        form.classList.remove('lx-animate-shake');
        void form.offsetWidth;
        form.classList.add('lx-animate-shake');
        feedback.style.color = 'var(--lx-crimson)';
        feedback.textContent = `✕ ${res.error}`;
        input.select();
        input.focus();
        playTone('err');
      }
    });

    const totalDurationMs = Number(m.round_duration_snapshot) * 1000;
    const endsAtMs = Number(round.ends_at_ms);

    const tick = () => {
      const nowServer = Date.now() + serverOffsetMs;
      const remMs = Math.max(0, endsAtMs - nowServer);
      const sec = Math.ceil(remMs / 1000);
      const mm = String(Math.floor(sec / 60)).padStart(2, '0');
      const ss = String(sec % 60).padStart(2, '0');

      const timerEl = document.getElementById('live-timer');
      const barEl = document.getElementById('live-timer-bar');
      if (timerEl && barEl) {
        timerEl.textContent = `${mm}:${ss}`;
        const pct = Math.max(0, Math.min(100, (remMs / totalDurationMs) * 100));
        barEl.style.width = `${pct}%`;

        timerEl.classList.remove('is-warning', 'is-urgent');
        if (sec <= 10) {
          timerEl.classList.add('is-urgent');
          barEl.style.background = 'var(--lx-crimson)';
        } else if (sec <= 20) {
          timerEl.classList.add('is-warning');
          barEl.style.background = '#F59E0B';
        } else {
          barEl.style.background = 'var(--lx-indigo)';
        }
      }

      if (remMs <= 0) {
        clearInterval(timerFrame);
        if (feedback) {
          feedback.style.color = 'var(--lx-indigo)';
          feedback.textContent = '⏱️ ¡Tiempo! Calculando resultados de la ronda...';
        }
        setTimeout(() => pollState(true), 350);
      }
    };

    tick();
    timerFrame = setInterval(tick, 200);
  };

  const updateLiveCounters = (s) => {
    const rivalsEl = document.getElementById('live-rivals');
    if (rivalsEl && Number(s.room.max_players) !== 1) {
      rivalsEl.innerHTML = s.players.map((p) => {
        const opacity = p.connection_state === 'online' ? '1' : '0.45';
        return `<span title="${esc(p.display_name)}" style="opacity:${opacity};">${renderAvatar(p, 'lx-avatar-sm')}</span>`;
      }).join('');
    }
    const chip = document.getElementById('live-score-chip');
    if (chip && s.my_words) {
      const pts = s.my_words.reduce((acc, w) => acc + Number(w.points_awarded || 0), 0);
      chip.textContent = `${s.my_words.length} palabras · ${pts} pts`;
    }
  };

  const renderMyWordsChips = (words, animateFirst = false) => {
    const list = document.getElementById('my-words-list');
    if (!list) return;
    if (!words.length) {
      list.innerHTML = `<span class="lx-hint">Todavía no registraste palabras en esta ronda. ¡Arrancá ya!</span>`;
      return;
    }
    list.innerHTML = words.map((w, idx) => `
      <span class="lx-word-chip ${animateFirst && idx === 0 ? 'lx-animate-pop' : ''}">
        ${esc(w.original_text)}
        <span class="lx-word-pts">+${Number(w.points_awarded)}</span>
      </span>
    `).join('');
  };

  const renderRoundResultsView = (s) => {
    const round = s.round;
    const m = s.match;
    const results = s.round_results || [];
    const canToggleWords = s.is_host && m.validation_mode_snapshot === 'table';

    // Encontrar la palabra más larga de la ronda
    let gem = null;
    results.forEach((r) => {
      (r.words || []).forEach((w) => {
        if (w.validation_status === 'valid' && (!gem || Number(w.letter_count) > Number(gem.letter_count))) {
          gem = { ...w, player: r.display_name };
        }
      });
    });

    roomApp.innerHTML = `
      <div style="max-width:780px;margin:0 auto;display:flex;flex-direction:column;gap:16px;">
        <div class="lx-card" style="text-align:center;">
          <span class="lx-chip lx-chip-gold">Ronda ${round.round_number} de ${m.round_count_snapshot} finalizada</span>
          <h1 style="margin:8px 0 4px;font-size:1.6rem;font-weight:900;">Resultados de la ronda</h1>
          ${gem ? `<p class="lx-hint" style="margin:0;">🏅 Joya de la ronda: <strong>"${esc(gem.original_text)}"</strong> (${gem.letter_count} letras) por ${esc(gem.player)}</p>` : ''}
        </div>

        <div id="round-results-cards" style="display:flex;flex-direction:column;gap:12px;">
          ${results.map((r) => {
            const winTag = Number(r.is_winner) === 1 ? `<span class="lx-chip lx-chip-gold">👑 Ganador de ronda</span>` : '';
            const wordsHtml = (r.words || []).map((w) => {
              const rej = w.validation_status === 'rejected';
              if (canToggleWords) {
                return `<button type="button" data-toggle-word="${w.id}" title="Tocá para invalidar o restaurar en consenso de mesa" class="lx-word-chip ${rej ? 'is-rejected' : ''}" style="cursor:pointer;">
                  ${esc(w.original_text)} <span class="lx-word-pts">${rej ? '✕' : `+${w.points_awarded}`}</span>
                </button>`;
              }
              return `<span class="lx-word-chip ${rej ? 'is-rejected' : ''}">${esc(w.original_text)} <span class="lx-word-pts">${rej ? '✕' : `+${w.points_awarded}`}</span></span>`;
            }).join('');

            return `
              <div class="lx-card" style="${Number(r.is_winner) === 1 ? 'border-color:var(--lx-amber);' : ''}">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
                  <div style="display:flex;align-items:center;gap:10px;">
                    <span style="font-size:1.2rem;font-weight:900;width:28px;">${r.position}º</span>
                    ${renderAvatar(r)}
                    <div>
                      <div style="font-weight:900;">${esc(r.display_name)} ${winTag}</div>
                      <div class="lx-hint">${r.valid_word_count} palabras válidas</div>
                    </div>
                  </div>
                  <div style="font-size:1.35rem;font-weight:900;color:var(--lx-indigo);" class="tabular-nums">${r.competitive_points} pts</div>
                </div>
                <div class="lx-word-chips">${wordsHtml || '<span class="lx-hint">Sin palabras registradas.</span>'}</div>
              </div>
            `;
          }).join('')}
        </div>

        ${canToggleWords ? `<div class="lx-hint" style="text-align:center;">💡 Modo Mesa Libre: como anfitrión podés tocar cualquier palabra para anularla o restaurarla por consenso.</div>` : ''}

        <div>
          ${s.is_host
            ? `<button type="button" id="btn-next-round" class="lx-btn lx-btn-coral lx-btn-lg lx-btn-block">⚡ Iniciar Ronda ${Number(round.round_number) + 1}</button>`
            : `<div class="lx-card-soft" style="text-align:center;font-weight:800;">Esperando al anfitrión para iniciar la Ronda ${Number(round.round_number) + 1}...</div>`
          }
        </div>
      </div>
    `;

    attachRoundResultListeners(round.id);
  };

  const attachRoundResultListeners = (roundId) => {
    document.querySelectorAll('[data-toggle-word]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const wordId = Number(btn.getAttribute('data-toggle-word'));
        const res = await apiPost('/api/round/toggle-word', { round_id: roundId, word_id: wordId });
        if (res.ok) {
          await pollState(true, true);
        }
      });
    });

    document.getElementById('btn-next-round')?.addEventListener('click', async (e) => {
      e.currentTarget.disabled = true;
      const res = await apiPost('/api/round/next', { match_id: currentState.match.id });
      if (res.ok) await pollState(true);
      else e.currentTarget.disabled = false;
    });
  };

  const updateRoundResultsHostControls = (s) => {
    // Si el anfitrión anuló palabras, refrescamos la vista
    renderRoundResultsView(s);
  };

  const renderMatchFinalView = (s) => {
    const standings = s.match_standings || [];
    const m = s.match;
    const isPractice = Number(s.room.max_players) === 1;

    if (isPractice) {
      const me = standings[0] || { valid_word_count: 0, competitive_points: 0, experience_earned: 5 };
      const words = s.my_words || [];
      let longest = null;
      words.forEach((w) => {
        if (!longest || Number(w.letter_count) > Number(longest.letter_count)) longest = w;
      });

      roomApp.innerHTML = `
        <div style="max-width:680px;margin:0 auto;display:flex;flex-direction:column;gap:16px;">
          <div class="lx-card" style="text-align:center;">
            <span class="lx-chip lx-chip-gold">¡PRÁCTICA COMPLETADA!</span>
            <h1 style="margin:8px 0 4px;font-size:1.75rem;font-weight:900;">Resumen de entrenamiento</h1>
            <div class="lx-hint">${Number(m.round_duration_snapshot)} seg · ${m.scoring_mode_snapshot === 'length' ? 'Por longitud' : 'Por cantidad'}</div>
          </div>

          <div class="lx-card" style="display:flex;justify-content:space-around;text-align:center;flex-wrap:wrap;gap:12px;">
            <div><div class="lx-stat-num tabular-nums">${me.valid_word_count}</div><div class="lx-stat-label">Palabras</div></div>
            <div><div class="lx-stat-num tabular-nums" style="color:var(--lx-amber);">${me.competitive_points}</div><div class="lx-stat-label">Puntos</div></div>
            <div><div class="lx-stat-num tabular-nums" style="color:var(--lx-emerald);">+${me.experience_earned} XP</div><div class="lx-stat-label">Entrenamiento</div></div>
          </div>

          ${longest ? `
            <div class="lx-card-soft" style="text-align:center;font-weight:800;">
              🏅 Tu mejor palabra: <strong>"${esc(longest.original_text)}"</strong> (${longest.letter_count} letras)
            </div>
          ` : ''}

          <div class="lx-card">
            <h2 style="margin:0 0 10px;font-size:1rem;font-weight:900;">Tus palabras (${words.length})</h2>
            <div class="lx-word-chips">
              ${words.length ? words.map((w) => `<span class="lx-word-chip">${esc(w.original_text)} <span class="lx-word-pts">+${w.points_awarded}</span></span>`).join('') : '<span class="lx-hint">No registraste palabras en esta práctica.</span>'}
            </div>
          </div>

          <form action="/practice/start" method="post" style="display:flex;flex-direction:column;gap:10px;">
            <input type="hidden" name="csrf_token" value="${esc(csrf)}">
            <input type="hidden" name="round_duration_seconds" value="${Number(m.round_duration_snapshot)}">
            <input type="hidden" name="scoring_mode" value="${esc(m.scoring_mode_snapshot)}">
            <input type="hidden" name="validation_mode" value="${esc(m.validation_mode_snapshot)}">
            <input type="hidden" name="min_word_length" value="${Number(m.min_word_length_snapshot)}">
            <button type="submit" class="lx-btn lx-btn-coral lx-btn-lg lx-btn-block">🔄 Practicar otra vez</button>
            <a href="/" class="lx-btn lx-btn-cream lx-btn-block">🏠 Volver al inicio</a>
          </form>
        </div>
      `;
      return;
    }

    const p1 = standings[0];
    const p2 = standings[1];
    const p3 = standings[2];
    const unlocked = s.unlocked_achievements || [];

    const myStanding = standings.find((st) => Number(st.user_id) === Number(window.__LEXO_USER_ID__));

    roomApp.innerHTML = `
      <div style="max-width:740px;margin:0 auto;display:flex;flex-direction:column;gap:16px;">
        <div class="lx-card" style="text-align:center;">
          <span class="lx-chip lx-chip-gold">¡PARTIDA FINALIZADA!</span>
          <h1 style="margin:8px 0 4px;font-size:1.75rem;font-weight:900;">Podio de la Mesa</h1>
          <div class="lx-hint">${esc(s.room.name)} · ${m.round_count_snapshot} rondas</div>

          <div class="lx-podium">
            <div class="lx-podium-col">
              ${p2 ? `${renderAvatar(p2)}<div style="font-weight:900;font-size:0.9rem;margin-top:4px;">${esc(p2.display_name)}</div><div class="lx-hint">${p2.rounds_won}R · ${p2.competitive_points} pts</div>` : '<div class="lx-hint">—</div>'}
              <div class="lx-podium-step lx-podium-2" style="margin-top:8px;">2º</div>
            </div>
            <div class="lx-podium-col">
              ${p1 ? `<div style="font-size:1.5rem;line-height:1;">👑</div>${renderAvatar(p1, 'lx-avatar-lg')}<div style="font-weight:900;font-size:1rem;margin-top:4px;">${esc(p1.display_name)}</div><div style="font-size:0.82rem;font-weight:800;color:var(--lx-coral);">${p1.rounds_won}R · ${p1.competitive_points} pts</div>` : ''}
              <div class="lx-podium-step lx-podium-1" style="margin-top:8px;">1º <span style="font-size:0.75rem;">+1 ★</span></div>
            </div>
            <div class="lx-podium-col">
              ${p3 ? `${renderAvatar(p3)}<div style="font-weight:900;font-size:0.9rem;margin-top:4px;">${esc(p3.display_name)}</div><div class="lx-hint">${p3.rounds_won}R · ${p3.competitive_points} pts</div>` : '<div class="lx-hint">—</div>'}
              <div class="lx-podium-step lx-podium-3" style="margin-top:8px;">3º</div>
            </div>
          </div>
        </div>

        ${myStanding ? `
          <div class="lx-card" style="display:flex;justify-content:space-around;text-align:center;flex-wrap:wrap;gap:12px;">
            <div><div class="lx-stat-num" style="color:var(--lx-indigo);">${myStanding.final_position}º</div><div class="lx-stat-label">Tu puesto</div></div>
            <div><div class="lx-stat-num">${myStanding.valid_word_count}</div><div class="lx-stat-label">Palabras válidas</div></div>
            <div><div class="lx-stat-num" style="color:var(--lx-emerald);">+${myStanding.experience_earned} XP</div><div class="lx-stat-label">Experiencia</div></div>
            <div><div class="lx-stat-num" style="color:var(--lx-amber);">+${myStanding.stars_earned} ★</div><div class="lx-stat-label">Estrellas</div></div>
          </div>
        ` : ''}

        ${unlocked.length ? `
          <div class="lx-card">
            <h2 style="margin:0 0 10px;font-size:1rem;font-weight:900;">🎖️ Nuevos logros desbloqueados</h2>
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
              ${unlocked.map((a) => `
                <div class="lx-card-soft" style="display:flex;align-items:center;gap:10px;">
                  <span style="font-size:1.5rem;">${esc(a.icon)}</span>
                  <div>
                    <div style="font-weight:900;font-size:0.9rem;">${esc(a.name)}</div>
                    <div class="lx-hint">+${a.reward_experience} XP · +${a.reward_stars} ★</div>
                  </div>
                </div>
              `).join('')}
            </div>
          </div>
        ` : ''}

        <div class="lx-card">
          <h2 style="margin:0 0 10px;font-size:1rem;font-weight:900;">Tabla completa</h2>
          <div class="lx-table-wrap">
            <table class="lx-table">
              <thead><tr><th>#</th><th>Jugador</th><th>Rondas</th><th>Palabras</th><th>Puntos</th></tr></thead>
              <tbody>
                ${standings.map((st) => `
                  <tr>
                    <td>${st.final_position}º</td>
                    <td><div style="display:flex;align-items:center;gap:8px;">${renderAvatar(st, 'lx-avatar-sm')} ${esc(st.display_name)}</div></td>
                    <td class="tabular-nums">${st.rounds_won}</td>
                    <td class="tabular-nums">${st.valid_word_count}</td>
                    <td class="tabular-nums">${st.competitive_points}</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:10px;">
          ${s.is_host
            ? `<button type="button" id="btn-rematch" class="lx-btn lx-btn-coral lx-btn-lg lx-btn-block">🔥 Revancha en esta sala</button>`
            : `<div class="lx-card-soft" style="text-align:center;font-weight:800;">El anfitrión puede abrir una revancha en esta misma sala.</div>`
          }
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <button type="button" id="btn-share-card" class="lx-btn lx-btn-amber">📤 Compartir resultado</button>
            <a href="/" class="lx-btn lx-btn-cream">🏠 Volver al inicio</a>
          </div>
        </div>
      </div>
    `;

    document.getElementById('btn-rematch')?.addEventListener('click', async (e) => {
      e.currentTarget.disabled = true;
      const res = await apiPost('/api/room/rematch', { code: roomCode });
      if (res.ok) await pollState(true);
      else e.currentTarget.disabled = false;
    });

    document.getElementById('btn-share-card')?.addEventListener('click', () => openShareCardModal(s));
  };

  // --- 7. Generador de Tarjeta Viral Compartible en Canvas ---
  const openShareCardModal = (s) => {
    const dialog = document.getElementById('share-dialog');
    const canvas = document.getElementById('share-canvas');
    if (!dialog || !canvas) return;

    const ctx = canvas.getContext('2d');
    const W = canvas.width;
    const H = canvas.height;

    // Fondo índigo arcade con fichas decorativas
    ctx.fillStyle = '#141226';
    ctx.fillRect(0, 0, W, H);

    ctx.fillStyle = '#FF5A36';
    ctx.beginPath();
    ctx.roundRect(40, 44, 58, 58, 14);
    ctx.fill();
    ctx.fillStyle = '#FFFFFF';
    ctx.font = '900 34px system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('L', 69, 85);

    ctx.textAlign = 'left';
    ctx.font = '900 34px system-ui, sans-serif';
    ctx.fillText('LEXO', 112, 85);

    ctx.fillStyle = '#ABA5C9';
    ctx.font = '700 20px system-ui, sans-serif';
    ctx.fillText(`${s.room.name} · ${s.room.code}`, 42, 136);

    ctx.fillStyle = '#FFB800';
    ctx.font = '900 32px system-ui, sans-serif';
    ctx.fillText('PODIO DE LA MESA', 42, 195);

    const standings = (s.match_standings || []).slice(0, 5);
    standings.forEach((st, i) => {
      const y = 230 + i * 76;
      ctx.fillStyle = i === 0 ? '#2A234E' : '#1F1C38';
      ctx.beginPath();
      ctx.roundRect(42, y, W - 84, 62, 16);
      ctx.fill();

      if (i === 0) {
        ctx.strokeStyle = '#FFB800';
        ctx.lineWidth = 3;
        ctx.stroke();
      }

      ctx.fillStyle = i === 0 ? '#FFB800' : '#FFFFFF';
      ctx.font = '900 24px system-ui, sans-serif';
      ctx.fillText(`${st.final_position}º  ${st.display_name}`, 64, y + 39);

      ctx.textAlign = 'right';
      ctx.fillStyle = '#10B981';
      ctx.fillText(`${st.competitive_points} pts`, W - 64, y + 39);
      ctx.textAlign = 'left';
    });

    ctx.fillStyle = '#ABA5C9';
    ctx.font = '700 18px system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('El desafío de las palabras · Lexo 3D', W / 2, H - 36);

    const dlBtn = document.getElementById('btn-download-share');
    if (dlBtn) {
      dlBtn.href = canvas.toDataURL('image/png');
      dlBtn.download = `lexo-${s.room.code}.png`;
    }

    dialog.showModal();
  };

  // --- 8. Loop de Polling Adaptativo ---
  const nextIntervalMs = (s) => {
    if (s.round && s.round.status === 'active') return 3000;
    return 2500;
  };

  const pollState = async (immediate = false, forceRender = false) => {
    clearTimeout(pollTimeout);
    try {
      const res = await fetch(`/api/room/state?code=${encodeURIComponent(roomCode)}`, {
        cache: 'no-store',
      });
      const json = await res.json();
      if (json.ok && json.data) {
        serverOffsetMs = json.data.server_now_ms - Date.now();
        currentState = json.data;
        renderRoomUI(currentState, forceRender);
      }
    } catch (_) {}

    pollTimeout = setTimeout(() => pollState(false), nextIntervalMs(currentState));
  };

  renderRoomUI(currentState, true);
  pollTimeout = setTimeout(() => pollState(false), nextIntervalMs(currentState));
})();
