<?php

declare(strict_types=1);

namespace UDT;

/**
 * Aufbau des Visualisierungs-Payloads (GetFullUpdateMessage) sowie der
 * Minimal-Updates und des Wertänderungs-Caches für MessageSink.
 */
trait PayloadTrait
{
    // Generiere eine Nachricht, die alle Elemente in der HTML-Darstellung aktualisiert
    private function GetFullUpdateMessage() {
        $result = [];

        // Frontend-Sichttexte zentral aus locale.json
        $result['uiTexts'] = [
            'on' => $this->Translate('On'),
            'off' => $this->Translate('Off'),
            'targetSoc' => $this->Translate('Target SOC'),
            'deviceImage' => $this->Translate('Device Image'),
            'image' => $this->Translate('Image')
        ];

        $this->appendStatusPayload($result);
        $this->appendVariablesPayload($result);
        $this->appendTilePayload($result);

        return json_encode($result);
    }

    /**
     * Status-Daten (Statuszeile, Profilzuordnung, Statusbild) in das Payload schreiben.
     */
    private function appendStatusPayload(array &$result): void
    {
        // Status-Daten (werden immer oben angezeigt)
        $statusId = $this->ReadPropertyInteger('Status');
        // Neue Option: Statusbereich komplett ausblenden (Funktionalität bleibt erhalten)
        $result['statusHidden'] = $this->ReadPropertyBoolean('StatusHide');
        if ($statusId > 0 && IPS_VariableExists($statusId)) {
            $result['status'] = GetValueFormatted($statusId);
            $result['statusValue'] = GetValue($statusId);
            $result['statusFontSize'] = $this->ReadPropertyInteger('StatusFontSize');
            // Alignment for Status
            $result['statusAlignment'] = $this->ReadPropertyString('StatusAlignment');
            
            // Neue Status-Konfigurationsoptionen
            $result['statusShowIcon'] = $this->ReadPropertyBoolean('StatusShowIcon');
            $result['statusShowLabel'] = $this->ReadPropertyBoolean('StatusShowLabel');
            $result['statusShowValue'] = $this->ReadPropertyBoolean('StatusShowValue');
            $result['statusLabel'] = $this->ReadPropertyString('StatusLabel');
            
            // Status-Icon ermitteln
            $statusIcon = $this->GetIcon($statusId);
            $result['statusIcon'] = $statusIcon;
            
            // Verwende Profilassoziationen für Status-Konfiguration
            $profilAssoziationen = json_decode($this->ReadPropertyString('ProfilAssoziazionen'), true);
            $statusBildauswahlSet = false; // Flag to track if statusBildauswahl was set
            
            // Check if ALL associations have Bildauswahl = "none" AND no default image is configured
            $allAssociationsNone = true; // Assume all are none until we find one with an image
            if (is_array($profilAssoziationen)) {
                foreach ($profilAssoziationen as $assoz) {
                    $bildauswahl = $assoz['Bildauswahl'] ?? 'wm_aus';
                    if ($bildauswahl !== 'none') {
                        $allAssociationsNone = false; // Found at least one association with an image
                        break;
                    }
                }
            }
            
            // Hide image column only if ALL associations are "none" AND no default image is configured
            $hasDefaultImage = $this->ReadPropertyInteger('DefaultImage') > 0 && IPS_MediaExists($this->ReadPropertyInteger('DefaultImage'));
            $hideImageColumn = $allAssociationsNone && !$hasDefaultImage;
            $result['hideImageColumn'] = $hideImageColumn;
            
            if (is_array($profilAssoziationen)) {
                $currentValue = GetValue($statusId);
                $assoziation = $this->FindMatchingAssociation($profilAssoziationen, $currentValue);
                if ($assoziation !== null) {
                        // Neue erweiterte Bildauswahl-Logik
                        $bildauswahl = $assoziation['Bildauswahl'] ?? 'wm_aus';
                        
                        if ($bildauswahl === 'custom') {
                            // Verwende eigenes Bild aus SelectMedia-Feld
                            if (isset($assoziation['EigenesBild']) && $assoziation['EigenesBild'] > 0) {
                                // Asset-Name für Custom Image (wird in GenerateAssets() als base64 geladen)
                                $result['statusBildauswahl'] = 'img_custom_' . $assoziation['EigenesBild'];
                                $result['statusImageUrl'] = $this->BuildImageHookUrl((int)$assoziation['EigenesBild']);
                            } else {
                                // Fallback: Verwende Standard-Bild wenn konfiguriert, sonst 'none'
                                $result['statusBildauswahl'] = $this->getDefaultImageOrNone();
                                if ($result['statusBildauswahl'] !== 'none' && strpos($result['statusBildauswahl'], 'img_default_') === 0) {
                                    $mid = (int)substr($result['statusBildauswahl'], strlen('img_default_'));
                                    $result['statusImageUrl'] = $this->BuildImageHookUrl($mid);
                                } else {
                                    $result['statusImageUrl'] = $this->BuildImageHookUrl(0);
                                }
                            }
                        } elseif ($bildauswahl === 'symcon_icon') {
                            // Verwende Symcon Icon aus SelectIcon-Feld
                            if (isset($assoziation['SymconIcon']) && !empty($assoziation['SymconIcon'])) {
                                $result['statusBildauswahl'] = 'symcon_icon_' . $assoziation['SymconIcon'];
                                // Individuelle Icon-Farbe (transparent => Accent-Color im Frontend)
                                if (array_key_exists('IconColor', $assoziation)) {
                                    $iconColorVal = $assoziation['IconColor'];
                                    $result['statusIconColor'] = ($iconColorVal === -1 || $iconColorVal === 16777215)
                                        ? ''
                                        : ('#' . sprintf('%06X', $iconColorVal));
                                    $result['isStatusIconColorTransparent'] = ($iconColorVal === -1 || $iconColorVal === 16777215);
                                } else {
                                    $result['statusIconColor'] = '';
                                    $result['isStatusIconColorTransparent'] = true;
                                }
                            } else {
                                // Fallback: Verwende Standard-Bild wenn konfiguriert, sonst 'none'
                                $result['statusBildauswahl'] = $this->getDefaultImageOrNone();
                                $result['statusIconColor'] = '';
                                $result['isStatusIconColorTransparent'] = true;
                            }
                        } elseif ($bildauswahl === 'none') {
                            // Fallback: Verwende Standard-Bild wenn konfiguriert, sonst wirklich 'none'
                            $result['statusBildauswahl'] = $this->getDefaultImageOrNone();
                            if ($result['statusBildauswahl'] !== 'none' && strpos($result['statusBildauswahl'], 'img_default_') === 0) {
                                $mid = (int)substr($result['statusBildauswahl'], strlen('img_default_'));
                                $result['statusImageUrl'] = $this->BuildImageHookUrl($mid);
                            } else {
                                $result['statusImageUrl'] = $this->BuildImageHookUrl(0);
                            }
                            $result['statusIconColor'] = '';
                            $result['isStatusIconColorTransparent'] = true;
                        } else {
                            // Verwende vorkonfigurierte Bilder (wm_an, wm_aus, dryer_on, dryer_off, etc.)
                            $result['statusBildauswahl'] = $bildauswahl;
                            $result['statusImageUrl'] = $this->BuildAssetHookUrl($bildauswahl);
                            $result['statusIconColor'] = '';
                            $result['isStatusIconColorTransparent'] = true;
                        }
                        
                        $statusBildauswahlSet = true;
                        $statusColor = $assoziation['StatusColor'] ?? -1;
                        $result['statusColor'] = isset($assoziation['StatusColor']) ? '#' . sprintf('%06X', $assoziation['StatusColor']) : '#000000';
                        $result['isStatusColorTransparent'] = isset($assoziation['StatusColor']) && ($assoziation['StatusColor'] == -1 || $assoziation['StatusColor'] == 16777215);
                }
            }
            
            // Ensure statusBildauswahl is set if we have a status variable
            if (!$statusBildauswahlSet) {
                $result['statusBildauswahl'] = $this->getDefaultImageOrNone();
                if ($result['statusBildauswahl'] !== 'none' && strpos($result['statusBildauswahl'], 'img_default_') === 0) {
                    $mid = (int)substr($result['statusBildauswahl'], strlen('img_default_'));
                    $result['statusImageUrl'] = $this->BuildImageHookUrl($mid);
                } else {
                    $result['statusImageUrl'] = $this->BuildImageHookUrl(0);
                }
            }
        } else {
            // Fallback: Wenn keine Statusvariable konfiguriert ist
            // 1) Bildauswahl: Standard-Bild wenn vorhanden, sonst 'none'
            $result['statusBildauswahl'] = $this->getDefaultImageOrNone();
            // 2) Spaltenanzeige: Wenn KEIN DefaultImage vorhanden ist → gesamte Bildspalte ausblenden
            $hasDefaultImage = $this->ReadPropertyInteger('DefaultImage') > 0 && IPS_MediaExists($this->ReadPropertyInteger('DefaultImage'));
            $result['hideImageColumn'] = !$hasDefaultImage;
        }
        
        // UNIVERSAL GUARANTEE: statusBildauswahl MUST ALWAYS be set
        if (!isset($result['statusBildauswahl'])) {
            $result['statusBildauswahl'] = 'none';
        }
        // Ensure statusAlignment is always present
        if (!isset($result['statusAlignment'])) {
            $result['statusAlignment'] = $this->ReadPropertyString('StatusAlignment');
        }
    }

    /**
     * Globale Kachel-Konfiguration (Progress-/Button-Config, Layout, Hintergrundbild,
     * InstanceID, Gruppennamen) in das Payload schreiben.
     */
    private function appendTilePayload(array &$result): void
    {
        // Zentrale Fortschrittsbalken-Konfiguration
        $result['progressBarConfig'] = [
            'height' => $this->ReadPropertyInteger('ProgressBarHeight'),
            'borderRadius' => $this->ReadPropertyInteger('ProgressBarBorderRadius'),
            'backgroundColor' => '#' . sprintf('%06X', $this->ReadPropertyInteger('ProgressBarBackgroundColor')),
            'backgroundOpacity' => $this->ReadPropertyInteger('ProgressBarBackgroundOpacity') / 100,
            'showText' => $this->ReadPropertyBoolean('ProgressBarShowText'),
            'textPadding' => $this->ReadPropertyInteger('ProgressBarTextPadding')
        ];
        
        // Zentrale Button-Konfiguration
        $result['buttonConfig'] = [
            'height' => $this->ReadPropertyInteger('ButtonHeight')
        ];
        
        // Bild-Konfiguration
        // Note: bildauswahl is now handled per-association in status rendering logic above
        $result['BildBreite'] = $this->ReadPropertyFloat('BildBreite');
        $result['BildPosition'] = $this->ReadPropertyString('BildPosition');
        $result['ShowBorderLine'] = $this->ReadPropertyBoolean('ShowBorderLine');
        $result['ImageAlignment'] = $this->ReadPropertyString('ImageAlignment');
        $result['bildtransparenz'] = $this->ReadPropertyFloat('Bildtransparenz');
        $result['kachelhintergrundfarbe'] = '#' . sprintf('%06X', $this->ReadPropertyInteger('Kachelhintergrundfarbe'));
        
        // Element-Spacing-Konfiguration
        $result['elementSpacing'] = $this->ReadPropertyInteger('ElementSpacing');
        
 
            // Hintergrundbild verarbeiten
        $imageID = $this->ReadPropertyInteger('bgImage');
        if (IPS_MediaExists($imageID)) {
            $image = IPS_GetMedia($imageID);
            if ($image['MediaType'] === MEDIATYPE_IMAGE) {
                $imageFile = explode('.', $image['MediaFile']);
                // Ermittle den Anfang der src basierend auf dem Dateitypen
                $imageContent = \UDT\Helpers::mimePrefixFromExtension((string)end($imageFile));

                // Nur fortfahren, falls Inhalt gesetzt wurde. Ansonsten ist das Bild kein unterstützter Dateityp
                if ($imageContent) {
                    $imageContent .= IPS_GetMediaContent($imageID);
                    $result['image1'] = $imageContent;
                    $result['image1Url'] = $this->BuildImageHookUrl($imageID);
                }
            }
        }
        else{
            $imageContent = 'data:image/png;base64,';

            $imageContent .= base64_encode(file_get_contents(__DIR__ . '/../../imgs/kachelhintergrund1.png'));

            if ($this->ReadPropertyBoolean('BG_Off')) {
                $result['image1'] = $imageContent;
            }
        }
        
        // Füge Instance-ID für RequestAction-Aufrufe hinzu
        $result['instanceid'] = $this->InstanceID;
        
        // Füge Gruppennamen hinzu für Frontend-Verwendung
        try {
            $groupNames = $this->GetAllGroupNames();
            $result['groupNames'] = $groupNames;
        } catch (\Throwable $e) {
            $this->LogCaughtThrowable(__FUNCTION__ . ':GetAllGroupNames', $e);
        }
    }

    // === Minimal-Update Builder: Status ===
    private function buildMinimalStatusUpdate(array $fullPayload): array
    {
        if (!is_array($fullPayload)) return [];
        $keys = [
            'status',
            'statusValue',
            'statusAlignment',
            'statusLabel',
            'statusShowIcon',
            'statusIcon',
            'statusShowLabel',
            'statusShowValue',
            'statusFontSize',
            'statusBildauswahl',
            'statusImageUrl',
            'statusColor',
            'isStatusColorTransparent',
            'statusIconColor',
            'isStatusIconColorTransparent',
            'hideImageColumn',
            'statusHidden'
        ];
        $minimal = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $fullPayload)) {
                $minimal[$k] = $fullPayload[$k];
            }
        }
        return $minimal;
    }

    // === Minimal-Update Builder: var_<index> für Haupt-Variable ===
    private function buildMinimalVarUpdateForVariable(array $fullPayload, int $changedVariableId): array
    {
        if (!isset($fullPayload['variables']) || !is_array($fullPayload['variables'])) {
            return [];
        }
        foreach ($fullPayload['variables'] as $i => $var) {
            $vid = $var['variableId'] ?? ($var['id'] ?? 0);
            if ($vid === $changedVariableId) {
                $key = 'var_' . $i;
                $payload = [];
                // Rohwert und formatierter Wert bereitstellen
                if (array_key_exists('rawValue', $var)) {
                    $payload[$key . '_value'] = $var['rawValue'];
                }
                $payload[$key] = $var['formattedValue'] ?? '';
                // NEU: Icon der Hauptvariable mitschicken
                if (isset($var['icon'])) {
                    $payload[$key . '_icon'] = $var['icon'];
                }
                return $payload;
            }
        }
        return [];
    }

    // === Minimal-Update Builder: var_<index> für zugehörige Haupt-Variable anhand SecondVariable-ID ===
    private function buildMinimalVarUpdateForSecondVariable(array $fullPayload, int $secondVariableId): array
    {
        if (!isset($fullPayload['variables']) || !is_array($fullPayload['variables'])) {
            return [];
        }
        foreach ($fullPayload['variables'] as $i => $var) {
            if (isset($var['secondVariable']) && is_array($var['secondVariable'])) {
                $sid = $var['secondVariable']['id'] ?? 0;
                if ($sid === $secondVariableId) {
                    $key = 'var_' . $i;
                    $payload = [];
                    if (array_key_exists('rawValue', $var)) {
                        $payload[$key . '_value'] = $var['rawValue'];
                    }
                    $payload[$key] = $var['formattedValue'] ?? '';
                    // NEU: Werte und Icon der SecondVariable mitschicken
                    if (array_key_exists('rawValue', $var['secondVariable'])) {
                        $payload[$key . '_second_value'] = $var['secondVariable']['rawValue'];
                    }
                    if (array_key_exists('formattedValue', $var['secondVariable'])) {
                        $payload[$key . '_second'] = $var['secondVariable']['formattedValue'];
                    }
                    if (isset($var['secondVariable']['icon'])) {
                        $payload[$key . '_second_icon'] = $var['secondVariable']['icon'];
                    }
                    return $payload;
                }
            }
        }
        return [];
    }

    /**
     * Prüft anhand des LastVarValues-Caches, ob sich der Wert der Variable geändert hat,
     * und merkt sich den neuen Wert. Liefert false bei unverändertem Wert.
     */
    private function hasValueChangedAndRemember(int $senderId): bool
    {
        $last = json_decode($this->ReadAttributeString('LastVarValues'), true);
        if (!is_array($last)) { $last = []; }
        $cur = GetValue($senderId);
        $k = strval($senderId);
        if (array_key_exists($k, $last) && $last[$k] === $cur) {
            return false;
        }
        $last[$k] = $cur;
        $this->WriteAttributeString('LastVarValues', json_encode($last));
        return true;
    }

    /**
     * Sendet ein Minimal-Update in zwei Wellen (erst formatierte Werte, dann _value-Rohwerte).
     * Fällt bei leerem Minimal-Update auf die vollständige Nachricht zurück.
     */
    private function sendSplitMinimalUpdate(array $minimal, string $fullMessageJson): void
    {
        if (empty($minimal)) {
            $this->UpdateVisualizationValue($fullMessageJson);
            return;
        }
        foreach ($minimal as $k => $v) {
            if (substr($k, -6) !== '_value') {
                $this->UpdateVisualizationValue(json_encode([$k => $v]));
            }
        }
        foreach ($minimal as $k => $v) {
            if (substr($k, -6) === '_value') {
                $this->UpdateVisualizationValue(json_encode([$k => $v]));
            }
        }
    }

    /**
     * Hilfsfunktion: Gibt das konfigurierte Standard-Bild zurück oder 'none' wenn nicht konfiguriert
     * @return string Asset-Name für das Standard-Bild oder 'none'
     */
    private function getDefaultImageOrNone(): string {
        $defaultImageId = $this->ReadPropertyInteger('DefaultImage');
        if ($defaultImageId > 0 && IPS_MediaExists($defaultImageId)) {
            return 'img_default_' . $defaultImageId;
        }
        return 'none';
    }
}
