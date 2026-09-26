        // === handleMessage-Teilverarbeiter =================================

        // Status-Schlüssel: Snapshot-Vergleich, Status-Render und Alignment-Restore
        function applyStatusKeys(decodedData) {
            // Set global currentBildauswahl for updateImageVisibility
            if (decodedData.hasOwnProperty('statusBildauswahl')) {
                state.currentBildauswahl = decodedData.statusBildauswahl;
            }
            // Bildspalte vor dem Rendern übernehmen: sonst rendert renderStatus mit dem alten Wert (eine offene
            // Kachel zeigte nach dem Einblenden der Spalte weiter das alte Bild statt des neuen Icons)
            if (decodedData.hasOwnProperty('hideImageColumn')) {
                state.hideImageColumn = decodedData.hideImageColumn;
            }

            // Build a minimal comparable snapshot of all status-relevant props
            const currentStatusSnapshot = {
                status: decodedData.status,
                statusValue: decodedData.statusValue,
                statusFontSize: decodedData.statusFontSize,
                statusAlignment: decodedData.statusAlignment,
                statusShowIcon: decodedData.statusShowIcon,
                statusShowLabel: decodedData.statusShowLabel,
                statusShowValue: decodedData.statusShowValue,
                statusLabel: decodedData.statusLabel,
                statusIcon: decodedData.statusIcon,
                statusBildauswahl: decodedData.statusBildauswahl,
                statusImageUrl: decodedData.statusImageUrl,
                statusIconColor: decodedData.statusIconColor,
                isStatusIconColorTransparent: decodedData.isStatusIconColorTransparent,
                statusColor: decodedData.statusColor,
                isStatusColorTransparent: decodedData.isStatusColorTransparent,
                statusHidden: decodedData.statusHidden,
                hideImageColumn: decodedData.hideImageColumn,
                imageAlignment: state.currentImageAlignment || null
            };

            const lastStatusSnapshotJson = state._lastStatusSnapshotJson || null;
            const currentStatusSnapshotJson = JSON.stringify(currentStatusSnapshot);

            if (lastStatusSnapshotJson !== currentStatusSnapshotJson) {
                // Only re-render if something relevant changed
                renderStatus(decodedData);
                updateImageVisibility();
                state._lastStatusSnapshotJson = currentStatusSnapshotJson;
            }
            
            // Re-apply gespeicherte Bild-/Icon-Ausrichtung nach dem Rendering (Persistenz)
            if (state.currentImageAlignment) {
                applyImageAlignment(state.currentImageAlignment);
            }
        }

        // === Zeilen-Protokoll ==================================================
        // Jede Zeile trägt ihren Schlüssel (Index in der Variablenliste). Updates schicken geänderte Zeilen
        // vollständig ({rows: [...]}); ersetzt wird genau das Element der Zeile, gerendert wie im Erstaufbau.

        function rowElement(key) {
            return document.querySelector(`#variables-container .variable-item[data-row-key="${CSS.escape(String(key))}"]`);
        }

        function applyRowsUpdate(rows) {
            if (!Array.isArray(rows)) return;
            rows.forEach((row) => {
                if (!row || typeof row !== 'object' || row.key === undefined || row.key === null) return;
                const key = String(row.key);
                const previous = state.rows.get(key);
                state.rows.set(key, row);
                syncFullPayloadRow(key, row);
                if (previous && JSON.stringify(previous) === JSON.stringify(row)) return;
                if (holdsSliderRow(key, row)) return;
                replaceRow(key);
            });
        }

        // Das zuletzt empfangene volle Payload mitführen: ein späteres gleiches volles Payload baut nicht neu auf
        function syncFullPayloadRow(key, row) {
            try {
                if (Array.isArray(state.lastVariablesData)) {
                    const i = state.lastVariablesData.findIndex((v) => v && String(v.key) === key);
                    if (i >= 0) state.lastVariablesData[i] = row;
                }
            } catch (_) {}
        }

        function replaceRow(key) {
            const row = state.rows.get(String(key));
            const old = rowElement(key);
            if (!row || !old) return;
            const focus = captureRowFocus(old);
            const tpl = document.createElement('template');
            tpl.innerHTML = renderSingleVariable(row, !!old.closest('.variable-group-item')).trim();
            const el = tpl.content.firstElementChild;
            if (!el) return;
            old.replaceWith(el);
            afterRowsInserted(el);
            restoreRowFocus(el, focus);
        }

        // Nacharbeit nach dem Einsetzen (Erstaufbau oder ersetzte Zeile): Ziel-Marker aus den Daten der eigenen
        // Zeile, Regler-Ereignisse, Balken laufen vom zuletzt gezeigten zum neuen Wert
        function afterRowsInserted(root) {
            try {
                root.querySelectorAll('.progress-container').forEach((pc) => {
                    const item = pc.closest('.variable-item');
                    const row = item ? state.rows.get(item.getAttribute('data-row-key')) : null;
                    if (row && row.displayType === 'progress') {
                        updateTargetMarkerDisplay(pc, row);
                    }
                });
                root.querySelectorAll('.slider-container').forEach((sc) => { attachSliderEvents(sc); });
            } catch (_) {}
            setTimeout(() => { animateProgressBars(root); }, 10);
        }

        function captureRowFocus(el) {
            const active = document.activeElement;
            if (!active || active === el || !el.contains(active)) return null;
            const cls = ['variable-multi-button', 'variable-button', 'slider-step-btn'].find((c) => active.classList.contains(c));
            return cls ? { cls: '.' + cls, value: active.getAttribute('data-association-value'), dir: active.getAttribute('data-dir') } : null;
        }

        function restoreRowFocus(el, focus) {
            if (!focus) return;
            const candidates = Array.from(el.querySelectorAll(focus.cls));
            const target = candidates.find((c) => c.getAttribute('data-association-value') === focus.value && c.getAttribute('data-dir') === focus.dir) || candidates[0];
            if (target) {
                try { target.focus({ preventScroll: true }); } catch (_) {}
            }
        }

        function sliderTolerance(row) {
            const step = Number(row && row.sliderStep);
            const digits = Number(row && row.sliderDigits) || 0;
            return (Number.isFinite(step) && step > 0) ? step / 2 : (digits > 0 ? Math.pow(10, -digits) / 2 : 0.5);
        }

        // Regler: während des Ziehens und solange ein eigener Wert unterwegs ist, bleibt die Zeile stehen;
        // die Bestätigung des eigenen Werts wird sofort gezeigt
        function holdsSliderRow(key, row) {
            if (state.dragRowKey === key) return true;
            const pending = state.sliderPending.get(key);
            if (!pending) return false;
            if (Math.abs(Number(row.rawValue) - pending.intended) <= sliderTolerance(row)) {
                clearTimeout(pending.timer);
                state.sliderPending.delete(key);
                return false;
            }
            return true;
        }

        // Ohne Bestätigung zeigt die Zeile nach 1,2 s wieder den letzten Stand des Servers
        function setSliderPending(key, intended) {
            if (key === undefined || key === null || key === '') return;
            key = String(key);
            const previous = state.sliderPending.get(key);
            if (previous) clearTimeout(previous.timer);
            const timer = setTimeout(() => {
                state.sliderPending.delete(key);
                if (state.dragRowKey !== key) replaceRow(key);
            }, 1200);
            state.sliderPending.set(key, { intended: Number(intended), timer: timer });
        }

        // Vollständiges variables-Payload (Kachel geöffnet, Konfiguration gespeichert): bei jeder Abweichung
        // vollständig neu aufbauen, Zeilen können hinzugekommen, entfallen oder verschoben sein. Wertänderungen
        // kommen als rows. Liefert true, wenn neu aufgebaut werden muss.
        function applyVariablesUpdate(value) {
            if (state.lastVariablesData && JSON.stringify(value) === JSON.stringify(state.lastVariablesData)) {
                return false;
            }
            state.lastVariablesData = value;
            return true;
        }

        // assets-Payload mergen und Statusbild ggf. neu rendern
        function applyAssets(value) {
            // Aktualisiere Assets (Bilder) sofort
            if (typeof value === 'object') {
                window.assets = window.assets || {};
                Object.assign(window.assets, value);
                // Wichtig: Wenn sich Medieninhalte geändert haben, muss das Statusbild neu gerendert werden,
                // da das <img>-src bereits auf den alten base64-String gesetzt wurde.
                // Re-render mit dem zuletzt bekannten Status-Payload.
                try {
                    if (state._lastStatusPayload) {
                        renderStatus(state._lastStatusPayload);
                        updateImageVisibility();
                    } else if (state.currentBildauswahl) {
                        // Fallback: Kein gespeicherter Payload vorhanden -> Bild direkt aktualisieren
                        const key = state.currentBildauswahl;
                        let imageUrl = '';
                        if (key && (key.startsWith('img_custom_') || key.startsWith('img_default_'))) {
                            imageUrl = window.assets[key] || '';
                        } else if (key && key !== 'none') {
                            imageUrl = window.assets[`img_${key}`] || '';
                        }
                        const deviceImg = document.getElementById('image');
                        if (deviceImg && imageUrl) {
                            deviceImg.style.display = 'none';
                            deviceImg.onload = function() { this.style.display = ''; };
                            deviceImg.onerror = function() { this.style.display = 'none'; };
                            deviceImg.src = imageUrl;
                        }
                    }
                } catch (_) {}
            }
        }

        function handleMessage(data) {
            let decodedData;
            if (typeof data === 'string') {
                try {
                    decodedData = JSON.parse(data);
                } catch (_) {
                    return;
                }
            } else if (data && typeof data === 'object') {
                decodedData = data; // already parsed object
            } else {
                return;
            }
            const root = document.documentElement;
            

            
            // Setze InstanceID für RequestAction-Aufrufe
            if (decodedData.instanceid) {
                window.InstanceID = decodedData.instanceid;
            }

            // 🚀 BATCH-RENDERING: Flag um nur einmal zu rendern
            let needsRerender = false;

            // Verarbeite die neuen Parameter
            for (const parameter in decodedData) {
                const value = decodedData[parameter];
                
                switch (parameter.toLowerCase()) {
                    case 'uitexts':
                        if (value && typeof value === 'object') {
                            state.uiTexts = Object.assign({}, state.uiTexts || {}, value);
                        }
                        break;
                    case 'elementspacing':
                        // Setze CSS-Variable für element-spacing
                        root.style.setProperty('--element-spacing', value + 'px');
                        break;
                    case 'bildposition':
                        // Bildposition-Konfiguration verarbeiten
                        applyImagePosition(value);
                        break;
                    case 'showborderline':
                        // Trennlinie ein-/ausblenden
                        const mainContainer = document.querySelector('.main_container');
                        if (mainContainer) {
                            if (value) {
                                mainContainer.classList.remove('hide-border-line');
                            } else {
                                mainContainer.classList.add('hide-border-line');
                            }
                        }
                        break;
                    case 'image1url':
                        // Hintergrund per WebHook-URL; '' = kein Hintergrund (auch ein entferntes Bild verschwindet)
                        try {
                            if (typeof value === 'string') {
                                document.querySelector('body').style.setProperty('--background-image', value ? 'url(' + value + ')' : 'none');
                                state._bgImageFromUrl = true;
                            }
                        } catch (_) {}
                        break;
                    case 'image1':
                        try {
                            if (typeof value === 'string' && value) {
                                const mainDiv = document.querySelector('body');
                                if (!state._bgImageFromUrl) {
                                    mainDiv.style.setProperty('--background-image', 'url(' + value + ')');
                                }
                            }
                        } catch (_) {}
                        break;

                    case 'imagealignment':
                        // Speichere Alignment global und wende sofort an (gilt für Bild ODER Icon)
                        state.currentImageAlignment = value;
                        applyImageAlignment(value);
                        break;
                    case 'status':
                    case 'statusvalue':
                    case 'statuscolor':
                    case 'statusalignment':
                        applyStatusKeys(decodedData);
                        break;
                    case 'hideimagecolumn':
                        // NEW: Handle hideImageColumn flag for complete image column visibility
                        if (decodedData.hasOwnProperty('hideImageColumn')) {
                            state.hideImageColumn = decodedData.hideImageColumn;
                            updateImageVisibility();
                        }
                        break;
                    case 'statusschriftgroesse':
                        renderStatus(decodedData);
                        break;
                    case 'statushidden':
                        // Statusbereich ausblenden; gerendert wird wie bei den übrigen Status-Schlüsseln nur einmal
                        applyStatusKeys(decodedData);
                        break;
                    case 'rows':
                        applyRowsUpdate(value);
                        break;
                    case 'variables':
                        // Zeilen-Schlüssel = Index in der Variablenliste; ältere Payloads ohne Schlüssel nach Position
                        if (Array.isArray(value)) {
                            value.forEach((row, i) => { if (row && typeof row === 'object' && row.key === undefined) row.key = i; });
                        }
                        needsRerender = applyVariablesUpdate(value);
                        break;
                    case 'progressbarconfig':
                        // Re-render nur, wenn sich die Konfiguration wirklich geändert hat
                        const newPbCfg = value || {};
                        const oldPbCfg = state.cachedProgressBarConfig || {};
                        if (JSON.stringify(newPbCfg) !== JSON.stringify(oldPbCfg)) {
                            updateProgressBarStyles(newPbCfg);
                            state.cachedProgressBarConfig = newPbCfg;
                            if (state.lastVariablesData) {
                                needsRerender = true;
                            }
                        }
                        break;
                    case 'buttonconfig':
                        // Re-render nur, wenn sich die Konfiguration wirklich geändert hat
                        const newBtnCfg = value || {};
                        const oldBtnCfg = state.cachedButtonConfig || {};
                        if (JSON.stringify(newBtnCfg) !== JSON.stringify(oldBtnCfg)) {
                            updateButtonStyles(newBtnCfg);
                            state.cachedButtonConfig = newBtnCfg;
                            if (state.lastVariablesData) {
                                needsRerender = true;
                            }
                        }
                        break;
                    case 'assets':
                        applyAssets(value);
                        break;
                    case 'groupnames':
                        // Nur aktualisieren und re-rendern, wenn sich die Gruppennamen geändert haben
                        const newGroupNames = value || {};
                        const oldGroupNames = state.groupNames || {};
                        if (JSON.stringify(newGroupNames) !== JSON.stringify(oldGroupNames)) {
                            state.groupNames = newGroupNames;
                            if (state.lastVariablesData) {
                                needsRerender = true;
                            }
                        }
                        break;
                    case 'bildbreite':
                        root.style.setProperty('--bildbreite', value + 'px');
                        state.currentBildbreite = value;
                        updateImageVisibility();
                        break;
                    case 'bildtransparenz':
                        root.style.setProperty('--bildtransparenz', value);
                        break;
                    case 'kachelhintergrundfarbe':
                        if (value === '#FFFFFFFFFFFFFFFF') {
                            root.style.setProperty('--hintergrundfarbe', 'rgba(0, 0, 0, 0)');
                        } else {
                            root.style.setProperty('--hintergrundfarbe', value);
                        }
                        break;
                    
                    default:
                        // unbekannte Schlüssel ignorieren (Wertänderungen kommen als rows)
                        break;
                }
            }

            // BATCH-RENDERING: Nur einmal rendern am Ende der Message-Verarbeitung
            if (needsRerender && state.lastVariablesData) {
                renderVariables(state.lastVariablesData);
            }
        }

        // Hilfsfunktion für Progress-Bar-Update

