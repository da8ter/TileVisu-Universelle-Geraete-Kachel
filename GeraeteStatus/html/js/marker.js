        // Update or create the target marker overlay for a progress bar
        function updateTargetMarkerDisplay(progressContainer, variable) {
            try {
                const tgt = variable && variable.progressTarget ? variable.progressTarget : null;
                const hasTarget = !!(tgt && (typeof tgt.rawValue !== 'undefined' || typeof tgt.formattedValue !== 'undefined'));
                if (!hasTarget) {
                    const oldMarker = progressContainer.querySelector('.target-marker');
                    const oldText = progressContainer.querySelector('.target-marker-text');
                    if (oldMarker) oldMarker.remove();
                    if (oldText) oldText.remove();
                    progressContainer.style.removeProperty('--target-percent');
                    return;
                }
                const { min: minValue, max: maxValue } = effectiveRange(variable.progressMin, variable.progressMax);
                const raw = parseFloat(tgt.rawValue);
                let percent = 0;
                const range = maxValue - minValue;
                if (range > 0 && !isNaN(raw)) {
                    percent = Math.max(0, Math.min(100, ((raw - minValue) / range) * 100));
                }
                progressContainer.style.setProperty('--target-percent', percent + '%');

                let marker = progressContainer.querySelector('.target-marker');
                if (!marker) {
                    marker = document.createElement('div');
                    marker.className = 'target-marker';
                    marker.innerHTML = '<div class="soc-triangle soc-triangle-top"></div><div class="soc-triangle soc-triangle-bottom"></div>';
                    progressContainer.appendChild(marker);
                }
                let textEl = progressContainer.querySelector('.target-marker-text');
                const label = (tgt.label && String(tgt.label).trim() !== '') ? String(tgt.label) : getUiText('targetSoc', 'Target SOC');
                const displayVal = (typeof tgt.formattedValue !== 'undefined') ? String(tgt.formattedValue) : (isNaN(raw) ? '' : raw.toLocaleString('de-DE', { maximumFractionDigits: 1 }) + ' %');
                if (!textEl) {
                    textEl = document.createElement('span');
                    textEl.className = 'target-marker-text';
                    progressContainer.appendChild(textEl);
                }
                // Synchronisiere Marker-Schriftgröße mit Progress-Text
                try {
                    // 1) CSS-Variable für globale Konsistenz
                    if (typeof variable.fontSize === 'number' && variable.fontSize !== -1) {
                        progressContainer.style.setProperty('--balkenschriftgroesse', variable.fontSize + 'px');
                    } else {
                        // Ermittele tatsächliche Schriftgröße aus dem Progress-Text
                        const ref = progressContainer.querySelector('.progress-text .progress-main-text') || progressContainer.querySelector('.progress-text') || progressContainer;
                        const cs = window.getComputedStyle(ref);
                        if (cs && cs.fontSize) {
                            progressContainer.style.setProperty('--balkenschriftgroesse', cs.fontSize);
                            textEl.style.fontSize = cs.fontSize; // 2) Direkt setzen, falls CSS-Var überschrieben wird
                        }
                    }
                } catch (_) {}
                // Marker-Text basierend auf SecondVariable-Show-Flags zusammenstellen (falls aktiviert)
                const inactiveOpacity = variable.progressbarInactive ? 'opacity: 0.5;' : '';
                let markerHtml = '';
                if (variable.useSecondVariableAsTarget && variable.secondVariable) {
                    const parts = [];
                    // Icon
                    if (variable.secondVariable.showIcon && variable.secondVariable.icon && variable.secondVariable.icon !== 'Transparent') {
                        const iconClass = prepareIconForDisplay(variable.secondVariable.icon);
                        if (iconClass) {
                            parts.push(`<i class="${esc(iconClass)} variable-icon" style="color: ${getTextColor(variable)}; ${inactiveOpacity}"></i>`);
                        }
                    }
                    // Label
                    if (variable.secondVariable.showLabel && label) {
                        const colon = variable.secondVariable.showValue ? ':&nbsp;' : '';
                        parts.push(`<span style="color: ${getTextColor(variable)}; font-weight: 700; ${inactiveOpacity}">${esc(label)}${colon}</span>`);
                    }
                    // Value
                    if (variable.secondVariable.showValue) {
                        parts.push(`<span style="color: ${getTextColor(variable)}; ${inactiveOpacity}">${esc(displayVal)}</span>`);
                    }
                    // Fallback: wenn nichts angezeigt werden soll, dann nur Wert
                    if (parts.length === 0) {
                        parts.push(`<span style="color: ${getTextColor(variable)}; ${inactiveOpacity}">${esc(displayVal)}</span>`);
                    }
                    markerHtml = parts.join('');
                } else {
                    // Standard: Label: Wert
                    markerHtml = `<span style="color: ${getTextColor(variable)}; font-weight: 700; ${inactiveOpacity}">${esc(label)}:&nbsp;</span>` +
                                 `<span style="color: ${getTextColor(variable)}; ${inactiveOpacity}">${esc(displayVal)}</span>`;
                }
                textEl.innerHTML = markerHtml;
                textEl.classList.remove('position-before-bar');
                // Always reset to visible before re-evaluating collision
                textEl.style.visibility = 'visible';
                requestAnimationFrame(() => {
                    try {
                        const containerRect = progressContainer.getBoundingClientRect();
                        const firstRect = textEl.getBoundingClientRect();
                        const overflowsRight = firstRect.right > (containerRect.right - 2);
                        let positionChanged = false;
                        if (overflowsRight) {
                            textEl.classList.add('position-before-bar');
                            const markerX = containerRect.left + (containerRect.width * (percent / 100));
                            const maxLeftWidth = Math.max(0, Math.floor(markerX - containerRect.left - 6));
                            if (maxLeftWidth > 0) {
                                textEl.style.maxWidth = maxLeftWidth + 'px';
                            }
                            positionChanged = true;
                        } else {
                            textEl.style.maxWidth = '';
                        }
                        const doOverlapCheck = () => {
                            const textRect = textEl.getBoundingClientRect();
                            const progressText = progressContainer.querySelector('.progress-text');
                            const mainTextEl = progressContainer.querySelector('.progress-text .progress-main-text');
                            // Wenn Progress-Text unsichtbar ist, nie verstecken
                            if (!progressText) {
                                textEl.style.visibility = 'visible';
                                return;
                            }
                            const ptStyle = window.getComputedStyle(progressText);
                            const ptRect = progressText.getBoundingClientRect();
                            const progressTextHidden = (ptStyle.display === 'none') || (ptRect.width === 0 || ptRect.height === 0);
                            if (progressTextHidden) {
                                textEl.style.visibility = 'visible';
                                return;
                            }
                            if (mainTextEl) {
                                const mainRect = mainTextEl.getBoundingClientRect();
                                const containerRect2 = progressContainer.getBoundingClientRect();
                                const margin = 2; // px tolerance to avoid flicker
                                // Rechte Sichtgrenze: bis zum linken Rand der SecondVariable (falls vorhanden)
                                let rightBound = containerRect2.right;
                                const secondEl = progressContainer.querySelector('.progress-text .progress-second-variable');
                                if (secondEl) {
                                    const secondRect = secondEl.getBoundingClientRect();
                                    rightBound = Math.max(mainRect.left, secondRect.left - 8);
                                }
                                
                                // WICHTIG: Prüfe gegen die tatsächlichen Kind-Elemente (SVG, spans), 
                                // NICHT gegen mainTextEl selbst (der hat flex:1 und geht über volle Breite)
                                const childNodes = Array.from(mainTextEl.querySelectorAll('svg, i, span, b, strong'));
                                let contentLeft = Infinity;
                                let contentRight = -Infinity;
                                
                                childNodes.forEach(node => {
                                    const r = node.getBoundingClientRect();
                                    if (r.width > 0 && r.height > 0) {
                                        contentLeft = Math.min(contentLeft, r.left);
                                        contentRight = Math.max(contentRight, r.right);
                                    }
                                });
                                
                                // Wenn keine sichtbaren Kinder gefunden: kein Text → keine Kollision
                                if (contentLeft === Infinity || contentRight === -Infinity) {
                                    textEl.style.visibility = 'visible';
                                    return;
                                }
                                
                                // Begrenze auf rightBound (für SecondVariable)
                                contentRight = Math.min(contentRight, rightBound);
                                const overlapsHorizontally = !(
                                    (textRect.right <= (contentLeft + margin)) ||
                                    (textRect.left >= (contentRight - margin))
                                );
                                textEl.style.visibility = overlapsHorizontally ? 'hidden' : 'visible';
                            } else {
                                textEl.style.visibility = 'visible';
                            }
                        };
                        // Always one more rAF to let layout settle (after class/size changes)
                        requestAnimationFrame(doOverlapCheck);
                    } catch (_) {}
                });
            } catch (e) {
                // ignore
            }
        }

