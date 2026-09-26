        function renderProgressContent(variable, ctx) {
            let content = '';
                    // Fortschrittsbalken - nutze Backend-Werte (Backend behandelt bereits Deaktivierung)
                    let rawValue = variable.rawValue;
                    let progressValue = calculateProgressValue(rawValue, variable.progressMin, variable.progressMax);
                    let displayValue = variable.formattedValue;
                    
                    // Verhindere NaN-Werte
                    if (isNaN(progressValue)) {
                        progressValue = 0;
                    }
                    
                    // Berechne Prozentsatz basierend auf Min/Max-Werten
                    const progressPercent = progressValue.toFixed(1) + '%';
                    
                    
                    // Progress-Bar Inhalt basierend auf Konfiguration
                    // Transparenz-Stil für inaktive Progress-Bars
                    const inactiveOpacity = variable.progressbarInactive ? 'opacity: 0.5;' : '';
                    
                    let progressIconHtml = '';
                    if (variable.showIcon && variable.icon && variable.icon !== 'Transparent') {
                        const iconClass = prepareIconForDisplay(variable.icon);
                        if (iconClass) {
                            progressIconHtml = `<i class="${esc(iconClass)} variable-icon" style="color: ${getTextColor(variable)}; ${inactiveOpacity}"></i>`;
                        }
                    }
                    
                    let progressTextParts = [];
                    
                    if (variable.showIcon && progressIconHtml) {
                        progressTextParts.push(progressIconHtml);
                    }
                    
                    if (variable.showLabel && variable.label) {
                        progressTextParts.push(`<span style="color: ${getTextColor(variable)}; font-weight: 700; ${inactiveOpacity}">${esc(variable.label)}:&nbsp;</span>`);
                    }
                    
                    if (variable.showValue) {
                        progressTextParts.push(`<span style="color: ${getTextColor(variable)}; ${inactiveOpacity}">${esc(displayValue)}</span>`);
                    }
                    
                    // Falls nichts konfiguriert ist, zeige Wert als Fallback
                    if (!variable.showIcon && !variable.showLabel && !variable.showValue) {
                        progressTextParts.push(`<span style="color: ${getTextColor(variable)}; ${inactiveOpacity}">${esc(displayValue)}</span>`);
                    }
                    
                    const progressText = progressTextParts.join('');
                    
                    // Zweite Variable für rechte Seite vorbereiten
                    let secondVariableText = '';
                    if (variable.secondVariable && !variable.useSecondVariableAsTarget) {
                        let secondVariableParts = [];
                        
                        // Icon für zweite Variable
                        if (variable.secondVariable.showIcon && variable.secondVariable.icon && variable.secondVariable.icon !== 'Transparent') {
                            const secondIconClass = prepareIconForDisplay(variable.secondVariable.icon);
                            if (secondIconClass) {
                                secondVariableParts.push(`<i class="${esc(secondIconClass)} variable-icon" style="color: ${getTextColor(variable)}; ${inactiveOpacity}"></i>`);
                            }
                        }
                        
                        // Label für zweite Variable
                        if (variable.secondVariable.showLabel && variable.secondVariable.label) {
                            secondVariableParts.push(`<span style="color: ${getTextColor(variable)}; font-weight: 700; ${inactiveOpacity}">${esc(variable.secondVariable.label)}:&nbsp;</span>`);
                        }
                        
                        // Wert für zweite Variable (Backend-Wert direkt verwenden)
                        if (variable.secondVariable.showValue) {
                            secondVariableParts.push(`<span style="color: ${getTextColor(variable)}; ${inactiveOpacity}">${esc(variable.secondVariable.formattedValue)}</span>`);
                        }
                        
                        // Falls nichts konfiguriert ist, zeige Wert als Fallback
                        if (!variable.secondVariable.showIcon && !variable.secondVariable.showLabel && !variable.secondVariable.showValue) {
                            secondVariableParts.push(`<span style="color: ${getTextColor(variable)}; ${inactiveOpacity}">${esc(variable.secondVariable.formattedValue)}</span>`);
                        }
                        
                        if (secondVariableParts.length > 0) {
                            secondVariableText = `<span class="progress-second-variable">${secondVariableParts.join('')}</span>`;
                        }
                    }
                    
                  
                    // Berechne individuelles Padding basierend auf Variable-Textgröße
                    const individualPadding = Math.max(4, Math.round(variable.fontSize / 1)); // Minimum 4px
                    
                    // ANIMATION FIX: Verwende ALTE Breite für DOM, nicht neue!
                    let widthForDOM = progressValue; // Default: neuer Wert
                    
                    // Prüfe ob bereits eine alte Breite gespeichert ist
                    const barKey = String(variable.key ?? variable.id); // je Zeile, nicht je Variable
                    if (state.previousProgressWidths && state.previousProgressWidths[barKey] !== undefined) {
                        const oldWidth = state.previousProgressWidths[barKey];
                        if (Math.abs(progressValue - oldWidth) > 0.1) {
                            // Alte Breite im DOM behalten, damit die Animation den Übergang fahren kann
                            widthForDOM = oldWidth;
                        }
                    }
                    if (!state.previousProgressWidths) {
                        state.previousProgressWidths = {};
                    }
                    state.previousProgressWidths[barKey] = progressValue;
                    
                    // Target marker percent based on optional progressTarget
                    let targetPercent = null;
                    if (variable.progressTarget && (typeof variable.progressTarget.rawValue !== 'undefined')) {
                        const tRaw = parseFloat(variable.progressTarget.rawValue);
                        const { min: tMin, max: tMax } = effectiveRange(variable.progressMin, variable.progressMax);
                        const tRange = tMax - tMin;
                        if (tRange > 0 && !isNaN(tRaw)) {
                            targetPercent = Math.max(0, Math.min(100, ((tRaw - tMin) / tRange) * 100));
                        } else {
                            targetPercent = 0;
                        }
                    }

                    // Build optional target marker HTML
                    let targetMarkerHtml = '';
                    if (targetPercent !== null) {
                        const inactiveOpacity = variable.progressbarInactive ? 'opacity: 0.5;' : '';
                        const posClass = (targetPercent <= 50) ? 'position-before-bar' : '';
                        const tgtLabel = (variable.progressTarget.label && String(variable.progressTarget.label).trim() !== '') ? String(variable.progressTarget.label) : getUiText('targetSoc', 'Target SOC');
                        const tgtText = (typeof variable.progressTarget.formattedValue !== 'undefined') ? String(variable.progressTarget.formattedValue) : '';
                        let markerTextParts = [];
                        if (variable.useSecondVariableAsTarget && variable.secondVariable) {
                            // Icon
                            if (variable.secondVariable.showIcon && variable.secondVariable.icon && variable.secondVariable.icon !== 'Transparent') {
                                const secondIconClass = prepareIconForDisplay(variable.secondVariable.icon);
                                if (secondIconClass) {
                                    markerTextParts.push(`<i class="${esc(secondIconClass)} variable-icon" style="color: ${getTextColor(variable)}; ${inactiveOpacity}"></i>`);
                                }
                            }
                            // Label
                            if (variable.secondVariable.showLabel && tgtLabel) {
                                const colon = variable.secondVariable.showValue ? ':&nbsp;' : '';
                                markerTextParts.push(`<span style="color: ${getTextColor(variable)}; font-weight: 700; ${inactiveOpacity}">${esc(tgtLabel)}${colon}</span>`);
                            }
                            // Value
                            if (variable.secondVariable.showValue) {
                                markerTextParts.push(`<span style="color: ${getTextColor(variable)}; ${inactiveOpacity}">${esc(tgtText)}</span>`);
                            }
                            if (markerTextParts.length === 0) {
                                // Fallback: nur Wert
                                markerTextParts.push(`<span style="color: ${getTextColor(variable)}; ${inactiveOpacity}">${esc(tgtText)}</span>`);
                            }
                        } else {
                            // Fallback: Standarddarstellung Label: Value
                            markerTextParts.push(`<span style="color: ${getTextColor(variable)}; font-weight: 700; ${inactiveOpacity}">${esc(tgtLabel)}:&nbsp;</span>`);
                            markerTextParts.push(`<span style="color: ${getTextColor(variable)}; ${inactiveOpacity}">${esc(tgtText)}</span>`);
                        }
                        const markerTextHtml = markerTextParts.join('');
                        targetMarkerHtml = `
                            <div class="target-marker">
                                <div class="soc-triangle soc-triangle-top"></div>
                                <div class="soc-triangle soc-triangle-bottom"></div>
                            </div>
                            <span class="target-marker-text ${posClass}">${markerTextHtml}</span>
                        `;
                    }

                    const targetPercentStyle = (targetPercent !== null) ? ` --target-percent: ${targetPercent}%;` : '';
                    // Sync marker font-size with variable font-size via CSS var
                    const markerFontSizeVar = (typeof variable.fontSize === 'number' && variable.fontSize !== -1) ? ` --balkenschriftgroesse: ${variable.fontSize}px;` : '';
                    const color1p = normalizeColor(variable.progressColor1);
                    const color2p = normalizeColor(variable.progressColor2);

                    content += `
                        <div class="variable-progress">
                            <div class="progress-container" data-variable-id="${esc(variable.id)}" data-new-width="${progressValue}" style="--progress-color1: ${color1p}; --progress-color2: ${color2p}; --progress-bar-text-padding: ${individualPadding}px;${targetPercentStyle}${markerFontSizeVar}">
                                <div class="progress-bar" style="width: ${widthForDOM}%;"></div>
                                ${targetMarkerHtml}
                                <div class="progress-text">
                                    <span class="progress-main-text">${progressText}</span>
                                    ${secondVariableText}
                                </div>
                            </div>
                        </div>
                    `;
            return content;
        }
        
        function renderSliderContent(variable, ctx) {
            let content = '';
                    const inactiveStyle = variable.progressbarInactive ? 'opacity: 0.5; pointer-events: none;' : '';
                    const { min, max } = effectiveRange(variable.progressMin, variable.progressMax);
                    const range = Math.max(0.000001, max - min);
                    const cur = parseFloat(variable.rawValue) || 0;
                    const percent = Math.max(0, Math.min(100, ((cur - min) / range) * 100));
                    const step = Number.isFinite(Number(variable.sliderStep)) ? Number(variable.sliderStep) : null;
                    const digits = Number.isFinite(Number(variable.sliderDigits)) ? Number(variable.sliderDigits) : 0;
                    const iconHtml = (variable.showIcon && variable.icon && variable.icon !== 'Transparent') ? (()=>{ const c = prepareIconForDisplay(variable.icon); return c ? `<i class="${esc(c)} variable-icon" style="color: var(--accent-color);"></i>` : ''; })() : '';
                    const labelHtml = (variable.showLabel && variable.label) ? `<span class="variable-label" style="font-weight: 700;">${esc(variable.label)}${variable.showValue ? ':&nbsp;' : ''}</span>` : '';
                    const valueHtml = (variable.showValue && typeof variable.formattedValue !== 'undefined') ? `<span class="slider-value">${esc(variable.formattedValue)}</span>` : `<span class="slider-value"></span>`;
                    const individualPadding = 0;
                    const color1s = normalizeColor(variable.sliderColor1);
                    const color2s = normalizeColor(variable.sliderColor2);

                    const suffixGuess = (() => {
                        if (typeof variable.formattedValue === 'string') {
                            const m = variable.formattedValue.match(/^\s*[-+]?\d+(?:[\.,]\d+)?\s*(.*)$/);
                            return m ? m[1] : '';
                        }
                        return '';
                    })();
                    const signChars = (min < 0 || max < 0) ? 1 : 0;
                    const maxAbs = Math.max(Math.abs(min), Math.abs(max));
                    let intDigits = 1;
                    if (Number.isFinite(maxAbs) && maxAbs >= 1) {
                        intDigits = Math.floor(Math.log10(maxAbs)) + 1;
                    }
                    const decimalChars = (digits > 0) ? (1 + digits) : 0; // Komma/Punkt + Nachkommastellen
                    const valueWidthCh = Math.max(4, signChars + intDigits + decimalChars + (suffixGuess ? suffixGuess.length : 0));
                    const suffixAttr = esc(suffixGuess);

                    content += `
                        <div class="slider-container" data-variable-id="${esc(variable.id)}" data-min="${min}" data-max="${max}" data-step="${step !== null ? step : ''}" data-digits="${digits}" data-suffix="${suffixAttr}" data-show-value="${variable.showValue ? '1' : '0'}" data-current-value="${cur}" style="--progress-color1: ${color1s}; --progress-color2: ${color2s}; --slider-dots-color: ${color1s}; --slider-value-width: ${valueWidthCh}ch; ${inactiveStyle}">
                            ${iconHtml}${labelHtml}
                            <button class="slider-step-btn" data-dir="-1">−</button>
                            <div class="slider-track" style="--progress-color1: ${color1s}; --progress-color2: ${color2s}; --slider-dots-color: ${color1s}; --progress-bar-text-padding: ${individualPadding}px;">
                                <div class="slider-fill" style="width: ${percent}%;"></div>
                                <div class="slider-thumb" style="left: ${percent}%;"></div>
                            </div>
                            <button class="slider-step-btn" data-dir="1">+</button>
                            ${valueHtml}
                        </div>
                    `;
            return content;
        }
        
        function renderImageContent(variable, ctx) {
            const { isGrouped, alignment } = ctx;
            let content = '';
                    // Rendering: Symcon Medienobjekt als Data-URI mit Breite/Radius und optional Icon/Label/Value
                    let parts = [];
                    // Image width is now percentage (1-100). Default 40% when not set.
                    const imgWidth = Number.isFinite(Number(variable.imageWidth)) ? Number(variable.imageWidth) : 40;
                    const imgRadius = Number.isFinite(variable.imageBorderRadius) ? variable.imageBorderRadius : 6;
                    const imgWidthForDOM = imgWidth; // Respektiere konfigurierte Breite auch in Gruppen
                    const imgFlex = '0 0 auto'; // Keine automatische Größenanpassung
                    
                    // Text-Align für gruppierte Bilder basierend auf alignment
                    const textAlignStyle = isGrouped ? `text-align: ${alignment};` : '';

                    // Icon optional anzeigen
                    if (variable.showIcon && variable.icon && variable.icon !== 'Transparent') {
                        const iconClass = prepareIconForDisplay(variable.icon);
                        if (iconClass) {
                            parts.push(`<i class="${esc(iconClass)} variable-icon" style="color: ${getTextColor(variable)};"></i>`);
                        }
                    }

                    // Label optional anzeigen (mit Doppelpunkt, wenn Value ebenfalls angezeigt wird)
                    if (variable.showLabel && variable.label) {
                        const labelText = variable.showValue ? `${esc(variable.label)}:&nbsp;` : esc(variable.label);
                        parts.push(`<span class="variable-label" style="color: ${getTextColor(variable)}; font-weight: 700; ${textAlignStyle}">${labelText}</span>`);
                    }

                    const imgAlt = esc(variable.label ? variable.label : getUiText('image', 'Image'));
                    const imgWidthCss = isGrouped ? '100%' : `${imgWidthForDOM}%`;
                    const primarySrc = (typeof variable.imageUrl === 'string') ? variable.imageUrl : '';
                    parts.push(`<img class="variable-inline-image" ${primarySrc ? `src=\"${esc(primarySrc)}\"` : ''} alt="${imgAlt}" style="display: none; width: ${imgWidthCss}; height: auto; border-radius: ${imgRadius}px; flex: ${imgFlex};" onload="this.style.display='';" onerror="this.style.display='none';">`);

                    // Value optional anzeigen
                    if (variable.showValue && typeof variable.formattedValue !== 'undefined') {
                        parts.push(`<span class="variable-value variable-text" style="flex: 0 0 auto; ${textAlignStyle}">${esc(variable.formattedValue)}</span>`);
                    }

                    content += parts.join(' ');
            return content;
        }
        
