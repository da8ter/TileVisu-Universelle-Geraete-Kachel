        function renderStatus(data) {
            // Speichere den letzten Status-Payload für spätere Re-Renders (z.B. bei Asset-/Medien-Updates)
            try { state._lastStatusPayload = data; } catch(_) {}
            const statusContainer = document.getElementById('status-container');
            const root = document.documentElement;
            
            // Neue Option: Statusbereich komplett ausblenden, unabhängig vom Statuswert
            const forceHideStatus = !!data.statusHidden;
            // Zeige Status-Container nur wenn nicht versteckt und Status vorhanden ist
            if (!forceHideStatus && data.status) {
                // Alignment mapping for status
                const alignment = (data.statusAlignment || 'left');
                const justifyMap = alignment === 'center' ? 'center' : (alignment === 'right' ? 'flex-end' : 'flex-start');
                // Use flex container for horizontal alignment
                statusContainer.style.display = 'flex';
                statusContainer.style.justifyContent = justifyMap;
                statusContainer.style.alignItems = 'center';
                statusContainer.style.width = '100%';
                // Bestimme Status-Farbe: verwende --content-color wenn transparent, ansonsten konfigurierte Farbe
                const statusColor = data.isStatusColorTransparent ? 'var(--content-color)' : (data.statusColor || 'var(--content-color)');
                statusContainer.style.setProperty('--status-color', statusColor);
                // Nur font-size setzen wenn nicht -1 - unterscheide zwischen -1 und undefined
                const statusFontSize = data.statusFontSize !== undefined ? data.statusFontSize : 12;
                if (statusFontSize !== -1) {
                    statusContainer.style.fontSize = statusFontSize + 'px';
                } else {
                    statusContainer.style.fontSize = ''; // Reset to default
                }
                
                let statusContent = '';
                
                // Status-Inhalt basierend auf Konfiguration zusammenstellen (wie bei Textvariablen)
                let statusParts = [];
                
                // Icon für Status-Bereich (wenn konfiguriert)
                if (data.statusShowIcon && data.statusIcon && data.statusIcon !== 'Transparent') {
                    const iconClass = prepareIconForDisplay(data.statusIcon);
                    if (iconClass) {
                        statusParts.push(`<i class="${esc(iconClass)}" style="color: var(--status-color); margin-right: 4px;"></i>`);
                    }
                }
                
                // Label für Status-Bereich (wenn konfiguriert)
                if (data.statusShowLabel) {
                    const label = data.statusLabel || 'Status'; // Fallback zu "Status" wenn kein Custom Label
                    // Füge ein geschütztes Leerzeichen NUR hinzu, wenn auch ein Wert angezeigt wird
                    const trailingSpace = data.statusShowValue ? '&nbsp;' : '';
                    statusParts.push(`<span class="status-label" style="color: var(--status-color);">${esc(label)}:${trailingSpace}</span>`);
                }
                
                // Wert für Status-Bereich (wenn konfiguriert)
                if (data.statusShowValue) {
                    statusParts.push(`<span class="status-value" style="color: var(--status-color);">${esc(data.status)}</span>`);
                }
                
                // Fallback: Falls nichts konfiguriert ist, zeige Wert
                if (!data.statusShowIcon && !data.statusShowLabel && !data.statusShowValue) {
                    statusParts.push(`<span class="status-value" style="color: var(--status-color);">${esc(data.status)}</span>`);
                }
                
                // Keine zusätzlichen Text-Knoten zwischen Flex-Items erzeugen
                statusContent = statusParts.join('');
                statusContainer.innerHTML = statusContent;
            } else {
                statusContainer.style.display = 'none';
            }
            
            // Gerätebild wird separat im div1 Container angezeigt - IMMER prüfen, auch ohne Status
            if (data.statusBildauswahl) {
                let imageUrl = null;
                
                // DOM-Elemente ZUERST definieren, bevor sie verwendet werden
                const deviceImgContainer = document.getElementById('div1');
                const deviceImg = document.getElementById('image');
                
                // GLOBALE ENTSCHEIDUNG: Soll die gesamte Bildspalte versteckt werden?
                if (state.hideImageColumn) {
                    if (deviceImgContainer) {
                        deviceImgContainer.classList.add('hidden');
                        deviceImgContainer.classList.remove('div1');
                    }
                    return;
                }
                
                // PRO-ASSOZIATION-ENTSCHEIDUNG: Welches Bild/Icon soll für diese Assoziation angezeigt werden?
                if (data.statusBildauswahl.startsWith('symcon_icon_')) {
                    // FontAwesome Icon verwenden
                    const iconName = data.statusBildauswahl.replace('symcon_icon_', '');
                    // WICHTIG: #image darf NICHT Voraussetzung sein (wird im Icon-Modus entfernt)
                    if (iconName && deviceImgContainer) {
                        // Entferne das <img> Element komplett aus dem DOM (nur wenn vorhanden)
                        if (deviceImg && deviceImg.parentNode) {
                            deviceImg.parentNode.removeChild(deviceImg);
                        }
                        
                        // Prüfe ob schon ein Icon-Container existiert
                        let iconContainer = deviceImgContainer.querySelector('.fontawesome-icon-container');
                        if (!iconContainer) {
                            iconContainer = document.createElement('div');
                            iconContainer.className = 'fontawesome-icon-container';
                            deviceImgContainer.appendChild(iconContainer);
                        }
                        
                        // Bereite FontAwesome Icon-Klasse vor (nutze zentrale Logik)
                        const iconClass = prepareIconForDisplay(iconName);
                        
                        // Setze das FontAwesome Icon
                        iconContainer.innerHTML = `<i class="${esc(iconClass)}"></i>`;
                        // Farbwahl: individuelle Icon-Farbe oder Fallback auf --accent-color
                        const chosenIconColor = (data.statusIconColor && data.statusIconColor !== '')
                            ? data.statusIconColor
                            : 'var(--accent-color)';
                        iconContainer.style.color = chosenIconColor;
                        // Wandle neu eingefügte <i>-Tags in SVG um (wichtig bei Statuswechsel)
                        try {
                            if (window.FontAwesome && window.FontAwesome.dom && typeof window.FontAwesome.dom.i2svg === 'function') {
                                window.FontAwesome.dom.i2svg({ node: iconContainer, observeMutations: false });
                            }
                        } catch (e) {
                            // still OK, FA wandelt ggf. asynchron per MutationObserver um
                        }
                        // Nur Anzeige steuern, restliche Layout-Eigenschaften kommen aus CSS
                        // Entferne evtl. veraltetes display:none aus vorherigem Modus
                        try { iconContainer.style.removeProperty('display'); } catch (_) {}
                        iconContainer.style.display = 'flex';
                        // WICHTIG: Nicht vollflächig, damit Ausrichtung links/rechts greift
                        iconContainer.style.flex = '0 0 auto';
                        iconContainer.style.width = 'auto';
                        // Margin-basierte Ausrichtung für Icon-Container (wirkt unabhängig von Containerbreite)
                        if (state.currentImageAlignment) {
                            iconContainer.style.margin = alignmentToMargin(state.currentImageAlignment);
                        }
                        
                        deviceImgContainer.classList.remove('hidden');
                        deviceImgContainer.classList.add('div1');
                        return; // Verlasse die Funktion, da Icon gerendert wurde
                    }
                } else if (data.statusImageUrl) {
                    // Bevorzuge WebHook-URL wenn vorhanden (Media oder Asset)
                    try {
                        if (!deviceImgContainer) return;
                        const oldIcon = deviceImgContainer.querySelector('.fontawesome-icon-container');
                        if (oldIcon) oldIcon.remove();
                        let imgEl = document.getElementById('image');
                        if (!imgEl) {
                            imgEl = document.createElement('img');
                            imgEl.id = 'image';
                            imgEl.alt = getUiText('deviceImage', 'Device Image');
                            deviceImgContainer.appendChild(imgEl);
                        }
                        imgEl.style.display = 'none';
                        imgEl.style.visibility = 'hidden';
                        try { imgEl.decoding = 'async'; } catch (_) {}
                        try { imgEl.loading = 'eager'; } catch (_) {}
                        let fallbackUrl = null;
                        if (data.statusBildauswahl && typeof data.statusBildauswahl === 'string') {
                            if (window.assets && window.assets[`img_${data.statusBildauswahl}`]) {
                                fallbackUrl = window.assets[`img_${data.statusBildauswahl}`];
                            } else if (data.statusBildauswahl === 'none') {
                                fallbackUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAABBJREFUeNpi+P//PwNAgAEACPwC/tuiTRYAAAAASUVORK5CYII=';
                            }
                        }

                        let triedFallback = false;
                        imgEl.onload = () => {
                            imgEl.style.display = 'block';
                            imgEl.style.visibility = 'visible';
                        };
                        imgEl.onerror = () => {
                            if (!triedFallback && fallbackUrl) {
                                triedFallback = true;
                                imgEl.src = fallbackUrl;
                            } else {
                                imgEl.style.display = 'none';
                                imgEl.style.visibility = 'hidden';
                            }
                        };
                        imgEl.src = data.statusImageUrl;
                        try {
                            if (imgEl.complete && imgEl.naturalWidth > 0) {
                                imgEl.style.display = 'block';
                                imgEl.style.visibility = 'visible';
                            }
                        } catch (_) {}
                        deviceImgContainer.classList.remove('hidden');
                        deviceImgContainer.classList.add('div1');
                    } catch (_) {}
                    return;
                } else if (data.statusBildauswahl.startsWith('img_custom_')) {
                    // Custom Image über Asset-System verwenden
                    imageUrl = window.assets[data.statusBildauswahl];
                } else if (data.statusBildauswahl.startsWith('img_default_')) {
                    // Standard-Bild (Fallback) über Asset-System verwenden
                    imageUrl = window.assets[data.statusBildauswahl];
                } else if (data.statusBildauswahl === 'none') {
                    // Keine Bild für diese Assoziation -> transparentes Platzhalter-Bild verwenden
                    // 1x1 transparentes PNG als Data URI (neue, korrekte Variante)
                    imageUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAABBJREFUeNpi+P//PwNAgAEACPwC/tuiTRYAAAAASUVORK5CYII=';
                } else {
                    // Vorkonfigurierte Bilder verwenden (wm_an, wm_aus, dryer_on, dryer_off, etc.)
                    imageUrl = window.assets[`img_${data.statusBildauswahl}`];
                }
                
                // Für Bilder: Icon-Container entfernen und img-Element anzeigen
                if (deviceImgContainer) {
                    // Existierenden Icon-Container entfernen (mutual exclusive)
                    const oldIcon = deviceImgContainer.querySelector('.fontawesome-icon-container');
                    if (oldIcon) oldIcon.remove();
                    // img-Element wiederherstellen falls es entfernt wurde
                    let deviceImg = document.getElementById('image');
                    if (!deviceImg) {
                        deviceImg = document.createElement('img');
                        deviceImg.id = 'image';
                        deviceImg.alt = getUiText('deviceImage', 'Device Image');
                        deviceImgContainer.appendChild(deviceImg);
                    }
                    // VOR dem Laden verstecken, Sichtbarkeit erst in onload setzen
                    deviceImg.style.display = 'none';
                    deviceImg.style.visibility = 'hidden';
                    // Event-Handler zurücksetzen
                    deviceImg.onload = null;
                    deviceImg.onerror = null;
                }
                
                if (imageUrl && deviceImgContainer) {
                    let deviceImg = document.getElementById('image');
                    if (deviceImg) {
                        // Nach erfolgreichem Laden erst anzeigen
                        deviceImg.onload = () => {
                            deviceImg.style.display = 'block';
                            deviceImg.style.visibility = 'visible';
                        };
                        // Bei Fehler: transparentes Pixel setzen und verborgen lassen, um Blaue-Kasten zu vermeiden
                        deviceImg.onerror = () => {
                            deviceImg.src = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAABBJREFUeNpi+P//PwNAgAEACPwC/tuiTRYAAAAASUVORK5CYII=';
                            deviceImg.style.display = 'none';
                            deviceImg.style.visibility = 'hidden';
                            // Fehlerfall: Icon sichtbar lassen (kein Hide des Icon-Containers)
                        };
                        deviceImg.src = imageUrl;
                        deviceImgContainer.classList.remove('hidden');
                        deviceImgContainer.classList.add('div1');
                    }

                } else {

                    if (deviceImgContainer) {
                        deviceImgContainer.classList.add('hidden');
                        deviceImgContainer.classList.remove('div1');
                    }
                }
            }
        }

