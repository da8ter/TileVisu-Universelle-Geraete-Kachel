        // Wertebereich eines Balkens oder Reglers: ein Maximum von 0 ist ein Maximum (-80..0);
        // nur ohne gültigen Bereich (max <= min, etwa 0..0) gilt 0..100
        function effectiveRange(min, max) {
            const lo = parseFloat(min);
            const hi = parseFloat(max);
            return (Number.isFinite(lo) && Number.isFinite(hi) && hi > lo) ? { min: lo, max: hi } : { min: 0, max: 100 };
        }

        function sliderRange(sc) {
            return effectiveRange(sc.getAttribute('data-min'), sc.getAttribute('data-max'));
        }

        function clampAndRound(val, min, max, step) {
            let v = Number(val);
            if (!Number.isFinite(v)) v = min;
            if (max < min) { const t = min; min = max; max = t; }
            if (!Number.isFinite(step) || step <= 0) {
                const range = (max - min);
                step = range > 0 ? (range / 100) : 1;
            }
            const ratio = (v - min) / step;
            const rounded = Math.round(ratio);
            v = min + (rounded * step);
            if (v < min) v = min;
            if (v > max) v = max;
            // auf die Genauigkeit der Schrittweite runden (0,05 → 2 Stellen), nicht auf die Anzeige-Stellen
            return Number(v.toFixed(Math.min(10, Math.max(decimalsOf(step), decimalsOf(min)))));
        }

        function decimalsOf(n) {
            const text = String(Math.abs(Number(n)));
            const exp = text.match(/e-(\d+)$/);
            if (exp) return parseInt(exp[1], 10);
            const dot = text.indexOf('.');
            return dot < 0 ? 0 : text.length - dot - 1;
        }

        function updateSliderUI(sc, percent, displayText) {
            const fill = sc.querySelector('.slider-fill');
            const thumb = sc.querySelector('.slider-thumb');
            if (fill) fill.style.width = `${percent}%`;
            if (thumb) thumb.style.left = `${percent}%`;
            const valEl = sc.querySelector('.slider-value');
            const showVal = (() => {
                try {
                    const a = sc.getAttribute('data-show-value');
                    return a === '1' || a === 'true';
                } catch (_) { return true; }
            })();
            if (valEl && typeof displayText !== 'undefined') {
                let suffix = sc.getAttribute('data-suffix') || '';
                if (!suffix) {
                    const cur = valEl.textContent || '';
                    const m = cur.match(/^\s*[-+]?\d+(?:[\.,]\d+)?\s*(.*)$/);
                    suffix = m ? m[1] : '';
                    if (!suffix) {
                        try {
                            const item = sc.closest('.variable-item');
                            const fv = item ? state.rows.get(item.getAttribute('data-row-key'))?.formattedValue : undefined;
                            if (typeof fv === 'string') {
                                const mF = fv.match(/^\s*[-+]?\d+(?:[\.,]\d+)?\s*(.*)$/);
                                suffix = mF ? mF[1] : '';
                            }
                        } catch (_) {}
                    }
                    if (suffix) sc.setAttribute('data-suffix', suffix);
                }
                if (typeof displayText === 'number') {
                    const digits = Number(sc.getAttribute('data-digits')) || 0;
                    const numStr = Number(displayText).toFixed(Math.max(0, digits));
                    if (showVal) {
                        valEl.textContent = suffix ? `${numStr} ${suffix}` : numStr;
                    } else {
                        valEl.textContent = '';
                    }
                    try { sc.setAttribute('data-current-value', String(displayText)); } catch (_) {}
                } else {
                    if (showVal) {
                        valEl.textContent = String(displayText);
                    } else {
                        valEl.textContent = '';
                    }
                    const m2 = String(displayText).match(/^\s*[-+]?\d+(?:[\.,]\d+)?\s*(.*)$/);
                    const s2 = m2 ? m2[1] : '';
                    if (s2) sc.setAttribute('data-suffix', s2);
                    try {
                        const mNum = String(displayText).match(/[-+]?\d+(?:[\.,]\d+)?/);
                        if (mNum) sc.setAttribute('data-current-value', String(Number(mNum[0].replace(',', '.'))));
                    } catch (_) {}
                }
            }
        }

        function updateSliderPattern(sc) {
            try {
                const track = sc.querySelector('.slider-track');
                if (!track) return;
                const rect = track.getBoundingClientRect();
                const { min, max } = sliderRange(sc);
                let step = NaN;
                const stepAttr = sc.getAttribute('data-step');
                if (stepAttr !== null && stepAttr !== '' && !isNaN(Number(stepAttr))) {
                    step = Number(stepAttr);
                } else {
                    // sonst aus den Daten der Zeile
                    const item = sc.closest('.variable-item');
                    const vc = item ? state.rows.get(item.getAttribute('data-row-key')) : null;
                    if (vc && Number.isFinite(Number(vc.sliderStep))) {
                        step = Number(vc.sliderStep);
                    }
                }
                const range = Math.max(0.000001, max - min);
                // default: 100 steps if not provided
                const s = (Number.isFinite(step) && step > 0) ? step : (range / 100);
                // compute integer number of intervals using floor to avoid off-by-one
                const stepsCount = Math.max(1, Math.floor((range / s) + 1e-9));
                const px = (rect.width > 0 && stepsCount > 0) ? (rect.width / stepsCount) : 12;
                track.style.setProperty('--slider-step-px', `${px}px`);
                const heightPx = rect.height || parseFloat(getComputedStyle(track).height) || 6;
                let dotPx = Math.max(1, Math.round(heightPx * 0.5));
                // keep clear gap: cap dot size to 40% of tile width
                if (px && isFinite(px)) {
                    dotPx = Math.min(dotPx, Math.max(1, Math.floor(px * 0.4)));
                }
                track.style.setProperty('--slider-dot-size', `${dotPx}px`);
                const dotRadius = Math.max(1, Math.floor(Math.min(dotPx / 2, (isFinite(px) ? px * 0.25 : dotPx / 2))));
                track.style.setProperty('--slider-dot-radius', `${dotRadius}px`);
            } catch (_) {}
        }

        function attachSliderEvents(sc) {
            const track = sc.querySelector('.slider-track');
            const minusBtn = sc.querySelector('.slider-step-btn[data-dir="-1"]');
            const plusBtn = sc.querySelector('.slider-step-btn[data-dir="1"]');
            if (!track) return;
            const id = sc.getAttribute('data-variable-id');
            const { min, max } = sliderRange(sc);
            const digits = Number(sc.getAttribute('data-digits')) || 0;
            const stepAttr = sc.getAttribute('data-step');
            const step = (stepAttr !== null && stepAttr !== '' && !isNaN(Number(stepAttr))) ? Number(stepAttr) : NaN;

            // initialize dotted spacing according to step size
            updateSliderPattern(sc);
            // extra passes to ensure track width is known (iOS/WebView layout timing)
            try { requestAnimationFrame(() => updateSliderPattern(sc)); } catch (_) {}
            try { setTimeout(() => updateSliderPattern(sc), 0); } catch (_) {}
            try {
                if (typeof ResizeObserver !== 'undefined') {
                    const ro = new ResizeObserver(() => updateSliderPattern(sc));
                    ro.observe(track);
                } else {
                    window.addEventListener('resize', () => updateSliderPattern(sc));
                }
            } catch (_) {}

            const getPercentFromClientX = (clientX) => {
                const rect = track.getBoundingClientRect();
                const x = Math.min(Math.max(clientX - rect.left, 0), rect.width);
                return rect.width > 0 ? (x / rect.width) * 100 : 0;
            };
            const percentToValue = (p) => {
                const range = max - min;
                const raw = min + (range * (p / 100));
                return clampAndRound(raw, min, max, step);
            };
            const valueToPercent = (v) => {
                const range = max - min;
                if (range <= 0) return 0;
                return Math.max(0, Math.min(100, ((v - min) / range) * 100));
            };

            const rowKey = (sc.closest('.variable-item') || sc).getAttribute('data-row-key');
            let dragging = false;
            let dragValue = null;
            const onPointerMove = (ev) => {
                if (!dragging) return;
                const p = getPercentFromClientX(ev.clientX);
                const v = percentToValue(p);
                dragValue = v;
                const percent = valueToPercent(v);
                updateSliderUI(sc, percent, v);
            };
            const endDrag = () => {
                if (!dragging) return;
                dragging = false;
                if (state.dragRowKey === rowKey) state.dragRowKey = null;
                try { window.removeEventListener('pointermove', onPointerMove); } catch (_) {}
                try { window.removeEventListener('pointerup', endDrag, true); } catch (_) {}
                if (dragValue !== null && typeof requestAction === 'function') {
                    // Dataset aktualisieren, damit +/- den neuen Wert als Basis nutzt
                    try { sc.setAttribute('data-current-value', String(dragValue)); } catch (_) {}
                    requestAction('row_' + rowKey, dragValue);
                    setSliderPending(rowKey, dragValue);
                } else {
                    replaceRow(rowKey); // Updates während des Ziehens nachholen
                }
                dragValue = null;
            };
            track.addEventListener('pointerdown', (ev) => {
                if (sc.style.pointerEvents === 'none') return;
                dragging = true;
                state.dragRowKey = rowKey;
                const p = getPercentFromClientX(ev.clientX);
                const v = percentToValue(p);
                dragValue = v;
                const percent = valueToPercent(v);
                updateSliderUI(sc, percent, v);
                try { track.setPointerCapture(ev.pointerId); } catch (_) {}
                window.addEventListener('pointermove', onPointerMove);
                window.addEventListener('pointerup', endDrag, true);
            });

            const stepClick = (dir) => {
                const vc = state.rows.get(rowKey);
                let base = NaN;
                // 1) bevorzugt aktueller UI-Wert (Dataset)
                try {
                    const ds = sc.getAttribute('data-current-value');
                    if (ds !== null && ds !== '' && !isNaN(Number(ds))) {
                        base = Number(ds);
                    }
                } catch (_) {}
                // 2) Cache
                if (!Number.isFinite(base)) {
                    base = Number(vc?.rawValue);
                }
                // 3) Fallback: Text
                if (!Number.isFinite(base)) {
                    try {
                        const txt = sc.querySelector('.slider-value')?.textContent || '';
                        const match = txt.match(/[-+]?\d+(?:[\.,]\d+)?/);
                        if (match) base = Number(match[0].replace(',', '.'));
                    } catch (_) {}
                }
                if (!Number.isFinite(base)) base = min;
                const s = Number.isFinite(step) && step > 0 ? step : ((max - min) > 0 ? (max - min) / 100 : 1);
                const next = clampAndRound(base + dir * s, min, max, s);
                // Optimistic UI
                try {
                    sc.setAttribute('data-current-value', String(next));
                    updateSliderUI(sc, valueToPercent(next), next);
                } catch (_) {}
                if (typeof requestAction === 'function') {
                    requestAction('row_' + rowKey, next);
                    setSliderPending(rowKey, next);
                }
            };
            if (minusBtn) minusBtn.addEventListener('click', (ev) => { ev.preventDefault(); ev.stopPropagation(); stepClick(-1); });
            if (plusBtn) plusBtn.addEventListener('click', (ev) => { ev.preventDefault(); ev.stopPropagation(); stepClick(1); });
        }

