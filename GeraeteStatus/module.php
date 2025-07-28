<?php

// Ensure all variable type constants are defined
if (!defined('VARIABLETYPE_BOOLEAN')) {
    define('VARIABLETYPE_BOOLEAN', 0);
}
if (!defined('VARIABLETYPE_INTEGER')) {
    define('VARIABLETYPE_INTEGER', 1);
}
if (!defined('VARIABLETYPE_STRING')) {
    define('VARIABLETYPE_STRING', 3);
}

class UniversalDeviceTile extends IPSModule
{
    // Variablen-Zugriff und Status-Variable-ID
    private $statusId = 0;
    
    /**
     * Icon-Mapping Tabelle (IP-Symcon zu FontAwesome)
     * @var array|null
     */
    protected $iconMapping = null;
    
    public function Create()
    {
        // Nie diese Zeile löschen!
        parent::Create();

        // Alter Gerätestatus (immer oben angezeigt)
        $this->RegisterPropertyInteger('Status', 0);
        $this->RegisterPropertyString('ProfilAssoziazionen', '[]');
        $this->RegisterPropertyInteger('StatusFontSize', 12);
        $this->RegisterPropertyBoolean('StatusShowIcon', true);
        $this->RegisterPropertyBoolean('StatusShowLabel', true);
        $this->RegisterPropertyBoolean('StatusShowValue', true);
        $this->RegisterPropertyString('StatusLabel', '');

        // Neue universelle Variablenliste für konfigurierbare Variablen
        $this->RegisterPropertyString('VariablesList', '[]');
        
        // Zentrale Fortschrittsbalken-Konfiguration
        $this->RegisterPropertyInteger('ProgressBarHeight', 25);
        $this->RegisterPropertyInteger('ProgressBarBorderRadius', 6);
        $this->RegisterPropertyInteger('ProgressBarBackgroundColor', 8947848); // rgba(135, 135, 135, 0.3)
        $this->RegisterPropertyInteger('ProgressBarBackgroundOpacity', 30);
        $this->RegisterPropertyBoolean('ProgressBarShowText', true);
        $this->RegisterPropertyInteger('ProgressBarTextPadding', 12);
        
        // Bildkonfiguration
        $this->RegisterPropertyInteger("Bildauswahl", 0);
        $this->RegisterPropertyFloat("BildBreite", 20);
        $this->RegisterPropertyInteger("Bild_An", 0);
        $this->RegisterPropertyInteger("Bild_Aus", 0);
        $this->RegisterPropertyBoolean('BG_Off', 1);
        $this->RegisterPropertyInteger("bgImage", 0);
        $this->RegisterPropertyFloat('Bildtransparenz', 0.7);
        $this->RegisterPropertyInteger('Kachelhintergrundfarbe', -1);
        $this->RegisterPropertyInteger('ElementSpacing', 5); // Standardwert für Element-Abstand
        
        // Debug-Steuerung
        $this->RegisterPropertyBoolean('DebugEnabled', false);
        
        // Benutzerdefinierte Gruppennamen (Groups 1-10)
        $defaultGroupNames = json_encode([
            ['Group' => 1, 'GroupName' => 'Group 1'],
            ['Group' => 2, 'GroupName' => 'Group 2'],
            ['Group' => 3, 'GroupName' => 'Group 3'],
            ['Group' => 4, 'GroupName' => 'Group 4'],
            ['Group' => 5, 'GroupName' => 'Group 5'],
            ['Group' => 6, 'GroupName' => 'Group 6'],
            ['Group' => 7, 'GroupName' => 'Group 7'],
            ['Group' => 8, 'GroupName' => 'Group 8'],
            ['Group' => 9, 'GroupName' => 'Group 9'],
            ['Group' => 10, 'GroupName' => 'Group 10']
        ]);
        $this->RegisterPropertyString('GroupNamesList', $defaultGroupNames);

        // Visualisierungstyp auf 1 setzen, da wir HTML anbieten möchten
        $this->SetVisualizationType(1);
        
        // Lade das Icon-Mapping
        $this->LoadIconMapping();
    }
    
    /**
     * Zentrale Debug-Funktion - alle Debug-Ausgaben laufen über diese Funktion
     * Sendet NUR ins Meldungsfenster, nicht ins Debug-Protokoll
     * @param string $message Debug-Nachricht
     * @param string $category Optional: Kategorie für bessere Übersicht (default: 'TileVisu DEBUG')
     */
    private function DebugLog($message, $category = 'TileVisu DEBUG') {
        if ($this->ReadPropertyBoolean('DebugEnabled')) {
            IPS_LogMessage($category, $message);
        }
    }

    /**
     * Gibt die Konfigurationsform zurück
     * @return string JSON-String der Konfigurationsform
     */
    public function GetConfigurationForm()
    {
        return file_get_contents(__DIR__ . '/form.json');
    }
    
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        
        // DEBUG TEST: Sofortige Debug-Ausgabe beim Laden
        $this->DebugLog('DEBUG TEST: Module loaded successfully!', 'ApplyChanges');
        
        // Stelle sicher, dass das Icon-Mapping geladen ist
        $this->LoadIconMapping();
        
        // Dynamische Referenzen und Nachrichten für konfigurierte Variablen
        $variablesList = json_decode($this->ReadPropertyString('VariablesList'), true);
        
        // Sammle alle Variablen-IDs
        $ids = [$this->ReadPropertyInteger('bgImage')];
        
        // Füge Status-Variable hinzu
        $statusId = $this->ReadPropertyInteger('Status');
        if ($statusId > 0) {
            $ids[] = $statusId;
        }
        
        if (is_array($variablesList)) {
            foreach ($variablesList as $variable) {
                if (isset($variable['Variable']) && $variable['Variable'] > 0) {
                    $ids[] = $variable['Variable'];
                }
                // Registriere auch SecondVariable falls vorhanden
                if (isset($variable['SecondVariable']) && $variable['SecondVariable'] > 0) {
                    $ids[] = $variable['SecondVariable'];
                }
            }
        }
        
        // Entferne alle alten Referenzen
        $refs = $this->GetReferenceList();
        foreach($refs as $ref) {
            $this->UnregisterReference($ref);
        }
        
        // Registriere neue Referenzen
        foreach ($ids as $id) {
            if ($id > 0) {
                $this->RegisterReference($id);
            }
        }

        // Aktualisiere registrierte Nachrichten
        foreach ($this->GetMessageList() as $senderID => $messageIDs)
        {
            foreach ($messageIDs as $messageID)
            {
                $this->UnregisterMessage($senderID, $messageID);
            }
        }

        // Registriere Nachrichten für Status-Variable
        if ($statusId > 0) {
            $this->RegisterMessage($statusId, VM_UPDATE);
        }
        
        // Registriere Nachrichten für konfigurierte Variablen
        if (is_array($variablesList)) {
            foreach ($variablesList as $variable) {
                if (isset($variable['Variable']) && $variable['Variable'] > 0) {
                    $this->RegisterMessage($variable['Variable'], VM_UPDATE);
                }
                // Registriere auch SecondVariable falls vorhanden
                if (isset($variable['SecondVariable']) && $variable['SecondVariable'] > 0) {
                    $this->RegisterMessage($variable['SecondVariable'], VM_UPDATE);
                }
            }
        }

        // Schicke eine komplette Update-Nachricht an die Darstellung, da sich ja Parameter geändert haben können
        $fullUpdateMessageJson = $this->GetFullUpdateMessage(); // Gibt bereits JSON-String zurück
        $fullUpdateMessage = json_decode($fullUpdateMessageJson, true); // In Array umwandeln
        
        // Füge Asset-Update hinzu für Custom Images und Fallback-Assets
        $assets = $this->GenerateAssets();
        if (!empty($assets)) {
            $fullUpdateMessage['assets'] = $assets;
        }
        
        $this->UpdateVisualizationValue(json_encode($fullUpdateMessage));
    }


    
    /**
     * Gibt alle Gruppennamen und ShowAbove Konfiguration als Array zurück für Frontend-Verwendung
     * @return array Assoziatives Array mit Gruppennummer als Key und Konfiguration als Value
     */
    public function GetAllGroupNames()
    {
        $groupNamesList = json_decode($this->ReadPropertyString('GroupNamesList'), true);
        $this->DebugLog('GetAllGroupNames - Raw GroupNamesList property: ' . $this->ReadPropertyString('GroupNamesList'));
        $this->DebugLog('GetAllGroupNames - Decoded GroupNamesList: ' . print_r($groupNamesList, true));
        
        $result = [];
        
        if (is_array($groupNamesList)) {
            foreach ($groupNamesList as $index => $group) {
                if (isset($group['GroupName'])) {
                    $groupNumber = $index + 1; // Array-Index 0 = Gruppe 1
                    $showAbove = isset($group['ShowAbove']) ? (bool)$group['ShowAbove'] : false;
                    $this->DebugLog('GetAllGroupNames - Adding group: ' . $groupNumber . ' => ' . $group['GroupName'] . ' (ShowAbove: ' . ($showAbove ? 'true' : 'false') . ')');
                    $result[$groupNumber] = [
                        'name' => $group['GroupName'],
                        'showAbove' => $showAbove
                    ];
                }
            }
        }
        
        // Stelle sicher, dass alle Gruppen 1-10 existieren
        for ($i = 1; $i <= 10; $i++) {
            if (!isset($result[$i])) {
                $result[$i] = [
                    'name' => "Group $i",
                    'showAbove' => false
                ];
            }
        }
        
        return $result;
    }

    // Hilfsmethode zur Asset-Generierung für Custom Images und Fallback-Assets
    private function GenerateAssets() {
        $assets = [];
        
        // Prüfe ob keine Statusvariable konfiguriert ist - dann brauchen wir Fallback-Assets
        $statusId = $this->ReadPropertyInteger('Status');
        $needsFallbackAssets = ($statusId <= 0 || !IPS_VariableExists($statusId));
        
        // Sammle alle benötigten Assets basierend auf ProfilAssoziazionen
        $profilAssoziazionen = json_decode($this->ReadPropertyString('ProfilAssoziazionen'), true);
        $neededAssets = [];
        
        if (is_array($profilAssoziazionen)) {
            foreach ($profilAssoziazionen as $assoziation) {
                $bildauswahl = $assoziation['Bildauswahl'] ?? 'none';
                
                if ($bildauswahl === 'custom' && isset($assoziation['EigenesBild']) && $assoziation['EigenesBild'] > 0) {
                    // Custom Images
                    $mediaId = $assoziation['EigenesBild'];
                    if (IPS_MediaExists($mediaId)) {
                        $media = IPS_GetMedia($mediaId);
                        if ($media['MediaType'] === MEDIATYPE_IMAGE) {
                            $imageFile = explode('.', $media['MediaFile']);
                            $imageContent = $this->GetImageDataUri(end($imageFile));
                            if ($imageContent) {
                                $assets['img_custom_' . $mediaId] = $imageContent . IPS_GetMediaContent($mediaId);
                                $this->DebugLog('Added custom image asset: img_custom_' . $mediaId, 'GenerateAssets');
                            }
                        }
                    }
                } elseif (in_array($bildauswahl, ['wm_an', 'wm_aus', 'dryer_on', 'dryer_off'])) {
                    // Sammle benötigte vorkonfigurierte Assets
                    $neededAssets[$bildauswahl] = true;
                }
            }
        }
        
        // Lade die benötigten vorkonfigurierten Assets
        foreach ($neededAssets as $assetName => $needed) {
            switch ($assetName) {
                case 'wm_an':
                    $assets['img_wm_an'] = 'data:image/webp;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/wm_an.webp'));
                    $this->DebugLog('Added washing machine ON asset', 'GenerateAssets');
                    break;
                case 'wm_aus':
                    $assets['img_wm_aus'] = 'data:image/webp;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/wm_aus.webp'));
                    $this->DebugLog('Added washing machine OFF asset', 'GenerateAssets');
                    break;
                case 'dryer_on':
                    $assets['img_dryer_on'] = 'data:image/webp;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/trockner_an.webp'));
                    $this->DebugLog('Added dryer ON asset', 'GenerateAssets');
                    break;
                case 'dryer_off':
                    $assets['img_dryer_off'] = 'data:image/webp;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/trockner_aus.webp'));
                    $this->DebugLog('Added dryer OFF asset', 'GenerateAssets');
                    break;
            }
        }
        
        // Fallback: Wenn keine Statusvariable konfiguriert ist und noch kein img_wm_an Asset vorhanden, 
        // lade Standard-Waschmaschinen-Asset als Fallback
        if ($needsFallbackAssets && !isset($assets['img_wm_an'])) {
            $assets['img_wm_an'] = 'data:image/webp;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/wm_an.webp'));
            $this->DebugLog('Added fallback asset img_wm_an for missing status variable', 'GenerateAssets');
        }
        
        // Debug: Zeige alle generierten Assets
        $this->DebugLog('GenerateAssets completed. Total assets generated: ' . count($assets), 'GenerateAssets');
        foreach ($assets as $assetName => $assetData) {
            $this->DebugLog('Generated asset: ' . $assetName . ' (size: ' . strlen($assetData) . ' bytes)', 'GenerateAssets');
        }
        
        return $assets;
    }
    
    // Hilfsmethode für Data URI basierend auf Dateierweiterung
    private function GetImageDataUri($extension) {
        switch (strtolower($extension)) {
            case 'bmp': return 'data:image/bmp;base64,';
            case 'jpg':
            case 'jpeg': return 'data:image/jpeg;base64,';
            case 'gif': return 'data:image/gif;base64,';
            case 'png': return 'data:image/png;base64,';
            case 'ico': return 'data:image/x-icon;base64,';
            case 'webp': return 'data:image/webp;base64,';
            default: return null;
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->DebugLog('DEBUG TEST: MessageSink called! SenderID: ' . $SenderID . ', Message: ' . $Message, 'MessageSink');
        
        // Verarbeitung der Status-Variable
        $statusId = $this->ReadPropertyInteger('Status');
        if ($statusId > 0 && $SenderID === $statusId) {
            switch ($Message) {
            case VM_UPDATE:
            $updateData = [
                'status' => GetValueFormatted($statusId),
                'statusValue' => GetValue($statusId),
                'statusFontSize' => $this->ReadPropertyInteger('StatusFontSize'),
                'statusShowIcon' => $this->ReadPropertyBoolean('StatusShowIcon'),
                'statusShowLabel' => $this->ReadPropertyBoolean('StatusShowLabel'),
                'statusShowValue' => $this->ReadPropertyBoolean('StatusShowValue'),
                'statusLabel' => $this->ReadPropertyString('StatusLabel'),
                'statusIcon' => $this->GetIcon($statusId)
            ];
            
            // Verwende Profilassoziationen für Status-Update
            $profilAssoziationen = json_decode($this->ReadPropertyString('ProfilAssoziazionen'), true);
            if (is_array($profilAssoziationen)) {
                $currentValue = GetValue($statusId);
                foreach ($profilAssoziationen as $assoziation) {
                    if (isset($assoziation['AssoziationValue']) && $assoziation['AssoziationValue'] == $currentValue) {
                        $updateData['statusBildauswahl'] = $assoziation['Bildauswahl'] ?? 'wm_aus';
                        $statusColor = $assoziation['StatusColor'] ?? -1;
                        $updateData['statusColor'] = isset($assoziation['StatusColor']) ? '#' . sprintf('%06X', $assoziation['StatusColor']) : '#000000';
                        $updateData['isStatusColorTransparent'] = isset($assoziation['StatusColor']) && ($assoziation['StatusColor'] == -1 || $assoziation['StatusColor'] == 16777215);
                        break;
                    }
                }
            }
            
            $this->UpdateVisualizationValue(json_encode($updateData));
                    break;
            }
        }

        // Dynamische Verarbeitung der konfigurierten Variablen
        $variablesList = json_decode($this->ReadPropertyString('VariablesList'), true);
    
    if (is_array($variablesList)) {
        foreach ($variablesList as $index => $variable) {
            // Prüfe Haupt-Variable
            if (isset($variable['Variable']) && $SenderID === $variable['Variable']) {
                switch ($Message) {
                    case VM_UPDATE:
                        $this->DebugLog('MessageSink: Main variable update detected for index: ' . $index . ', variable ID: ' . $variable['Variable']);
                        
                        // Sende vollständige Update-Nachricht für diese Variable
                        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
                        break;
                }
            }
            // Prüfe SecondVariable
            elseif (isset($variable['SecondVariable']) && $SenderID === $variable['SecondVariable']) {
                switch ($Message) {
                    case VM_UPDATE:
                        $this->DebugLog('MessageSink: SecondVariable update detected for index: ' . $index . ', SecondVariable ID: ' . $variable['SecondVariable']);
                        
                        // Sende vollständige Update-Nachricht für diese Variable (enthält auch SecondVariable-Daten)
                        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
                        break;
                }
            }
        }
    }
}


    /**
     * Verarbeitet RequestAction-Aufrufe vom Frontend
     * @param string $Ident Der Identifier der Aktion
     * @param mixed $value Der Wert der Aktion
     */
    public function RequestAction($Ident, $value) {
        // Prüfe zuerst auf spezielle Aktionen
        if ($Ident === 'UpdateDisplayTypeFields') {
            $this->UDST_UpdateDisplayTypeFields($value);
            return;
        }
        
        // Nachrichten von der HTML-Darstellung schicken immer den Ident passend zur Eigenschaft und im Wert die Differenz, welche auf die Variable gerechnet werden soll
    $variableID = $Ident;
    if (!IPS_VariableExists($variableID)) {
        $this->DebugLog('Error in RequestAction: Variable to be updated does not exist');
        return;
    }
    
    // Ermittle Variablentyp für unterschiedliche Behandlung
    $variable = IPS_GetVariable($variableID);
    $variableType = $variable['VariableType'];
    
    if ($variableType === VARIABLETYPE_BOOLEAN) {
        // Boolean-Variable: Toggle-Verhalten (wie bisher)
        $currentValue = GetValue($variableID);
        $newValue = !$currentValue;
        $this->DebugLog('RequestAction: Boolean variable ' . $variableID . ' toggle from ' . ($currentValue ? 'true' : 'false') . ' to ' . ($newValue ? 'true' : 'false'));
        RequestAction($variableID, $newValue);
    } else if ($variableType === VARIABLETYPE_INTEGER) {
        // Integer-Variable: Verwende den übergebenen Wert direkt (für Multi-Button-Interface)
        $newValue = intval($value);
        $currentValue = GetValue($variableID);
        $this->DebugLog('RequestAction: Integer variable ' . $variableID . ' set from ' . $currentValue . ' to ' . $newValue);
        RequestAction($variableID, $newValue);
    } else if ($variableType === VARIABLETYPE_STRING) {
        // String-Variable: Verwende den übergebenen String-Wert direkt (für Multi-Button-Interface)
        $newValue = strval($value);
        $currentValue = GetValue($variableID);
        $this->DebugLog('RequestAction: String variable ' . $variableID . ' set from "' . $currentValue . '" to "' . $newValue . '"');
        RequestAction($variableID, $newValue);
    } else {
        // Andere Variablentypen: Fallback auf Toggle-Verhalten
        $currentValue = GetValue($variableID);
        $newValue = !$currentValue;
        $this->DebugLog('RequestAction: Unknown variable type ' . $variableType . ' for variable ' . $variableID . ', using toggle behavior');
        RequestAction($variableID, $newValue);
    }
    }


    public function GetVisualizationTile()
    {
        // Füge ein Skript hinzu, um beim Laden, analog zu Änderungen bei Laufzeit, die Werte zu setzen
        $initialHandling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ')</script>';
        $bildauswahl = $this->ReadPropertyInteger('Bildauswahl');



        // Asset-System vereinheitlicht: Verwende dynamische Asset-Generierung statt hardcoded Assets
        $generatedAssets = $this->GenerateAssets();
        $assets = '<script>';
        $assets .= 'window.assets = {};' . PHP_EOL;
        
        // Dynamische Asset-Generierung basierend auf aktueller Konfiguration
        foreach ($generatedAssets as $assetName => $assetData) {
            $assets .= 'window.assets.' . $assetName . ' = "' . $assetData . '";' . PHP_EOL;
        }
        
        // Fallback-Assets basierend auf bildauswahl für Backward-Kompatibilität
        if($bildauswahl == '0') {
            // Waschmaschine: Stelle sicher, dass WM-Assets verfügbar sind
            if (!isset($generatedAssets['img_wm_an'])) {
                $assets .= 'window.assets.img_wm_an = "data:image/webp;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/wm_an.webp')) . '";' . PHP_EOL;
            }
            if (!isset($generatedAssets['img_wm_aus'])) {
                $assets .= 'window.assets.img_wm_aus = "data:image/webp;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/wm_aus.webp')) . '";' . PHP_EOL;
            }
        }
        elseif($bildauswahl == '1') {
            // Trockner: Korrekte Asset-Namen verwenden!
            if (!isset($generatedAssets['img_dryer_on'])) {
                $assets .= 'window.assets.img_dryer_on = "data:image/webp;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/trockner_an.webp')) . '";' . PHP_EOL;
            }
            if (!isset($generatedAssets['img_dryer_off'])) {
                $assets .= 'window.assets.img_dryer_off = "data:image/webp;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/trockner_aus.webp')) . '";' . PHP_EOL;
            }
        }
        
        // Script-Tag schließen für das vereinheitlichte Asset-System
        $assets .= '</script>';
        
        // Custom image handling (legacy support)
        if ($bildauswahl != '0' && $bildauswahl != '1') {
            // Prüfe vorweg, ob ein Bild ausgewählt wurde
        $imageID_Bild_An = $this->ReadPropertyInteger('Bild_An');
        if (IPS_MediaExists($imageID_Bild_An)) {
            $image = IPS_GetMedia($imageID_Bild_An);
            if ($image['MediaType'] === MEDIATYPE_IMAGE) {
                $imageFile = explode('.', $image['MediaFile']);
                $imageContent = '';
                // Falls ja, ermittle den Anfang der src basierend auf dem Dateitypen
                switch (end($imageFile)) {
                    case 'bmp':
                        $imageContent = 'data:image/bmp;base64,';
                        break;

                    case 'jpg':
                    case 'jpeg':
                        $imageContent = 'data:image/jpeg;base64,';
                        break;

                    case 'gif':
                        $imageContent = 'data:image/gif;base64,';
                        break;

                    case 'png':
                        $imageContent = 'data:image/png;base64,';
                        break;

                    case 'ico':
                        $imageContent = 'data:image/x-icon;base64,';
                        break;

                    case 'webp':
                        $imageContent = 'data:image/webp;base64,';
                        break;
                }

                // Nur fortfahren, falls Inhalt gesetzt wurde. Ansonsten ist das Bild kein unterstützter Dateityp
                if ($imageContent) {
                    // Hänge base64-codierten Inhalt des Bildes an
                    $imageContent .= IPS_GetMediaContent($imageID_Bild_An);
                }

            }
        }
        else {
            $imageContent = 'data:image/png;base64,';

            $imageContent .= base64_encode(file_get_contents(__DIR__ . '/../imgs/transparent.webp'));

            
        } 

                // Prüfe vorweg, ob ein Bild ausgewählt wurde
                $imageID_Bild_Aus = $this->ReadPropertyInteger('Bild_Aus');
                if (IPS_MediaExists($imageID_Bild_Aus)) {
                    $image2 = IPS_GetMedia($imageID_Bild_Aus);
                    if ($image2['MediaType'] === MEDIATYPE_IMAGE) {
                        $imageFile2 = explode('.', $image2['MediaFile']);
                        $imageContent2 = '';
                        // Falls ja, ermittle den Anfang der src basierend auf dem Dateitypen
                        switch (end($imageFile2)) {
                            case 'bmp':
                                $imageContent2 = 'data:image/bmp;base64,';
                                break;
        
                            case 'jpg':
                            case 'jpeg':
                                $imageContent2 = 'data:image/jpeg;base64,';
                                break;
        
                            case 'gif':
                                $imageContent2 = 'data:image/gif;base64,';
                                break;
        
                            case 'png':
                                $imageContent2 = 'data:image/png;base64,';
                                break;
        
                            case 'ico':
                                $imageContent2 = 'data:image/x-icon;base64,';
                                break;

                            case 'webp':
                                $imageContent2 = 'data:image/webp;base64,';
                                break;
                        }
        
                        // Nur fortfahren, falls Inhalt gesetzt wurde. Ansonsten ist das Bild kein unterstützter Dateityp
                        if ($imageContent2) {
                            // Hänge base64-codierten Inhalt des Bildes an
                            $imageContent2 .= IPS_GetMediaContent($imageID_Bild_Aus);
                        }
        
                    }
                }
                else {
                    $imageContent2 = 'data:image/png;base64,';

                    $imageContent2 .= base64_encode(file_get_contents(__DIR__ . '/../imgs/transparent.webp'));

                    
                }  

            // Custom image assets already handled by unified asset system above
            // No need for separate hardcoded assets - this is now integrated
        }


         // Formulardaten lesen und Statusmapping Array für Bild und Farbe erstellen
        $assoziationsArray = json_decode($this->ReadPropertyString('ProfilAssoziazionen'), true);
        $statusMappingImage = [];
        $statusMappingColor = [];
        foreach ($assoziationsArray as $item) {
            $statusMappingImage[$item['AssoziationValue']] = $item['Bildauswahl'];
                      
            $statusMappingColor[$item['AssoziationValue']] = $item['StatusColor'] === -1 ? "" : sprintf('%06X', $item['StatusColor']);

        // StatusBalken wurde entfernt - wird nicht mehr verwendet

        }

        $statusImagesJson = json_encode($statusMappingImage);
        $statusColorJson = json_encode($statusMappingColor);
        $images = '<script type="text/javascript">';
        $images .= 'var statusImages = ' . $statusImagesJson . ';';
        $images .= 'var statusColor = ' . $statusColorJson . ';';
        $images .= '</script>';




        // Füge statisches HTML aus Datei hinzu
        $module = file_get_contents(__DIR__ . '/module.html');

        // Gebe alles zurück.
        // Wichtig: $initialHandling nach hinten, da die Funktion handleMessage erst im HTML definiert wird
        return $module . $images . $assets . $initialHandling;
    }



    // Generiere eine Nachricht, die alle Elemente in der HTML-Darstellung aktualisiert
    private function GetFullUpdateMessage() {
        $result = [];
        $this->DebugLog('DEBUG TEST: GetFullUpdateMessage called!', 'GetFullUpdateMessage');
        $this->DebugLog('Starting update message generation', 'GetFullUpdateMessage');
        
        // DIREKTER TEST: Ist Variable 11998 konfiguriert?
        $variablesList = json_decode($this->ReadPropertyString('VariablesList'), true);
        $found11998 = false;
        if (is_array($variablesList)) {
            foreach ($variablesList as $index => $variable) {
                if (isset($variable['Variable']) && $variable['Variable'] == 11998) {
                    $found11998 = true;
                    $this->DebugLog('DIRECT TEST: Variable 11998 FOUND at index ' . $index . ' with config: ' . json_encode($variable));
                    break;
                }
            }
        }
        if (!$found11998) {
            $this->DebugLog('DIRECT TEST: Variable 11998 NOT FOUND in VariablesList! Total variables: ' . (is_array($variablesList) ? count($variablesList) : 'NULL'));
        }
        
        // ARRAY ANALYSIS: Prüfe Array-Struktur
        if (is_array($variablesList)) {
            $this->DebugLog('ARRAY ANALYSIS: Total count = ' . count($variablesList));
            $this->DebugLog('ARRAY ANALYSIS: Max index = ' . (count($variablesList) - 1));
            $this->DebugLog('ARRAY ANALYSIS: Index 11 exists? ' . (isset($variablesList[11]) ? 'YES' : 'NO'));
            if (isset($variablesList[11])) {
                $this->DebugLog('ARRAY ANALYSIS: Index 11 content: ' . json_encode($variablesList[11]));
            }
            
            // ARRAY REINDEX: Reindiziere Array um Lücken zu schließen
            $this->DebugLog('ARRAY REINDEX: Original keys: ' . implode(',', array_keys($variablesList)));
            $variablesList = array_values($variablesList); // Reindiziert von 0 bis count-1
            $this->DebugLog('ARRAY REINDEX: New total count after reindexing = ' . count($variablesList));
            $this->DebugLog('ARRAY REINDEX: New keys: ' . implode(',', array_keys($variablesList)));
            
            // ARRAY REINDEX: Finde Variable 11998 im reindiziertem Array
            $found11998AfterReindex = false;
            foreach ($variablesList as $newIndex => $variable) {
                if (isset($variable['Variable']) && $variable['Variable'] == 11998) {
                    $found11998AfterReindex = true;
                    $this->DebugLog('ARRAY REINDEX: Variable 11998 now at index ' . $newIndex . ' (was at index 11)');
                    break;
                }
            }
            if (!$found11998AfterReindex) {
                $this->DebugLog('ARRAY REINDEX: ERROR - Variable 11998 lost during reindexing!');
            }
        }

        // Status-Daten (werden immer oben angezeigt)
        $statusId = $this->ReadPropertyInteger('Status');
        $this->DebugLog('Processing status variable: ' . $statusId, 'GetFullUpdateMessage');
        if ($statusId > 0 && IPS_VariableExists($statusId)) {
            $result['status'] = GetValueFormatted($statusId);
            $result['statusValue'] = GetValue($statusId);
            $result['statusFontSize'] = $this->ReadPropertyInteger('StatusFontSize');
            
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
            
            // NEW: Check if ALL associations have Bildauswahl = "none" to hide image column completely
            $hideImageColumn = true; // Assume we should hide until we find an image
            if (is_array($profilAssoziationen)) {
                foreach ($profilAssoziationen as $assoz) {
                    $bildauswahl = $assoz['Bildauswahl'] ?? 'wm_aus';
                    if ($bildauswahl !== 'none') {
                        $hideImageColumn = false; // Found at least one association with an image
                        break;
                    }
                }
            } else {
                $hideImageColumn = false; // No associations = show image column with default
            }
            $result['hideImageColumn'] = $hideImageColumn;
            $this->DebugLog('Image column visibility - hideImageColumn: ' . ($hideImageColumn ? 'true' : 'false'), 'GetFullUpdateMessage');
            
            if (is_array($profilAssoziationen)) {
                $currentValue = GetValue($statusId);
                foreach ($profilAssoziationen as $assoziation) {
                    if (isset($assoziation['AssoziationValue']) && $assoziation['AssoziationValue'] == $currentValue) {
                        // Neue erweiterte Bildauswahl-Logik
                        $bildauswahl = $assoziation['Bildauswahl'] ?? 'wm_aus';
                        
                        if ($bildauswahl === 'custom') {
                            // Verwende eigenes Bild aus SelectMedia-Feld
                            if (isset($assoziation['EigenesBild']) && $assoziation['EigenesBild'] > 0) {
                                // Asset-Name für Custom Image (wird in GenerateAssets() als base64 geladen)
                                $result['statusBildauswahl'] = 'img_custom_' . $assoziation['EigenesBild'];
                                $this->DebugLog('Set statusBildauswahl to custom image: img_custom_' . $assoziation['EigenesBild'], 'GetFullUpdateMessage');
                            } else {
                                // Fallback wenn kein eigenes Bild konfiguriert
                                $result['statusBildauswahl'] = 'none';
                                $this->DebugLog('Custom image selected but no EigenesBild configured - fallback to none', 'GetFullUpdateMessage');
                            }
                        } elseif ($bildauswahl === 'none') {
                            // Kein Bild anzeigen
                            $result['statusBildauswahl'] = 'none';
                            $this->DebugLog('Set statusBildauswahl to none', 'GetFullUpdateMessage');
                        } else {
                            // Verwende vorkonfigurierte Bilder (wm_an, wm_aus, dryer_on, dryer_off, etc.)
                            $result['statusBildauswahl'] = $bildauswahl;
                            $this->DebugLog('Set statusBildauswahl to predefined image: ' . $bildauswahl, 'GetFullUpdateMessage');
                        }
                        
                        $statusBildauswahlSet = true;
                        $statusColor = $assoziation['StatusColor'] ?? -1;
                        $result['statusColor'] = isset($assoziation['StatusColor']) ? '#' . sprintf('%06X', $assoziation['StatusColor']) : '#000000';
                        $result['isStatusColorTransparent'] = isset($assoziation['StatusColor']) && ($assoziation['StatusColor'] == -1 || $assoziation['StatusColor'] == 16777215);
                        break;
                    }
                }
            }
            
            // CRITICAL FIX: Ensure statusBildauswahl is ALWAYS set if we have a status variable
            if (!$statusBildauswahlSet) {
                $result['statusBildauswahl'] = 'none'; // Default fallback
                $this->DebugLog('FALLBACK: No matching association found - set statusBildauswahl to none', 'GetFullUpdateMessage');
            }
        } else {
            // Fallback: Wenn keine Statusvariable konfiguriert ist, verwende img_wm_an als Standard
            $result['statusBildauswahl'] = 'none';
            $this->DebugLog('No status variable configured - fallback to none', 'GetFullUpdateMessage');
        }
        
        // UNIVERSAL GUARANTEE: statusBildauswahl MUST ALWAYS be set
        if (!isset($result['statusBildauswahl'])) {
            $result['statusBildauswahl'] = 'none';
            $this->DebugLog('EMERGENCY FALLBACK: statusBildauswahl was not set - forcing to none', 'GetFullUpdateMessage');
        }
        
        // DEBUG: Zeige final gesetzte statusBildauswahl
        if (isset($result['statusBildauswahl'])) {
            $this->DebugLog('FINAL statusBildauswahl sent to frontend: ' . $result['statusBildauswahl'], 'GetFullUpdateMessage');
        }

        // Lade die konfigurierte Variablenliste
        $variablesList = json_decode($this->ReadPropertyString('VariablesList'), true);
        $this->DebugLog('GetFullUpdateMessage: Variables list: ' . json_encode($variablesList));
        
        // Sammle Informationen für jede konfigurierte Variable (Array-Reihenfolge durch changeOrder)
        if (is_array($variablesList)) {
            $variables = [];
            $this->DebugLog('DEBUG VARIABLES LIST: Processing ' . count($variablesList) . ' variables from configuration');
            foreach ($variablesList as $index => $variable) {
                $this->DebugLog('FOREACH START: Processing index ' . $index . ' (Target: find index 11 with Variable 11998)');
                
                // CLEANUP: Removed temporary bypass - GetIcon() now has proper error handling
                
                try {
                $varId = $variable['Variable'] ?? 'NONE';
                $varType = 'UNKNOWN';
                if (isset($variable['Variable']) && IPS_VariableExists($variable['Variable'])) {
                    $varInfo = IPS_GetVariable($variable['Variable']);
                    $varType = $varInfo['VariableType'];
                }
                $typeString = ($varType === 3) ? 'TEXT' : $varType;
                $this->DebugLog('DEBUG FOREACH: Index ' . $index . ', Variable ID: ' . $varId . ', Type: ' . $typeString);

                $this->DebugLog('DEBUG VARIABLE CHECK: Index ' . $index . ', Variable ID: ' . ($variable['Variable'] ?? 'NONE') . ', Group: ' . ($variable['Group'] ?? 'NONE'));
                if (isset($variable['Variable']) && $variable['Variable'] > 0 && IPS_VariableExists($variable['Variable'])) {
                    $this->DebugLog('DEBUG VARIABLE PROCESSING: Variable ID ' . $variable['Variable'] . ' passed validation and will be processed');
                    $this->DebugLog('GetFullUpdateMessage: Processing variable ID: ' . $variable['Variable']);
                    
                    // GRANULARE DIAGNOSE für Index 6
                    if ($index == 6) {
                        $this->DebugLog('INDEX 6 STEP 1: Starting detailed processing of Variable 37555');
                    }
                    // Verwende Variablennamen als Fallback wenn kein Label gesetzt ist
                    $label = $variable['Label'] ?? '';
                    if (empty($label)) {
                        $variableObject = IPS_GetObject($variable['Variable']);
                        $label = $variableObject['ObjectName'];
                        $this->DebugLog('DEBUG: After label assignment for Variable ID: ' . $variable['Variable'] . ', Label: ' . $label);
                    }
                    
                    // GRANULARE DIAGNOSE für Index 6 - GetIcon
                    if ($index == 6) {
                        $this->DebugLog('INDEX 6 STEP 2: About to call GetIcon for Variable 37555');
                    }
                    
                    $this->DebugLog('GetFullUpdateMessage: About to call GetIcon for Variable ID: ' . $variable['Variable'] . ', Index: ' . $index);
                    
                    // PROTECTION: Try-Catch um GetIcon call, um Abstürze zu verhindern
                    try {
                        $icon = $this->GetIcon($variable['Variable']);
                        
                        // GRANULARE DIAGNOSE für Index 6 - GetIcon completed
                        if ($index == 6) {
                            $this->DebugLog('INDEX 6 STEP 3: GetIcon completed for Variable 37555, result: "' . $icon . '"');
                        }
                        
                    } catch (Exception $e) {
                        $icon = '';
                        $this->DebugLog('GETICON ERROR: GetIcon failed for Variable ' . $variable['Variable'] . ' - ' . $e->getMessage());
                        $this->DebugLog('GETICON ERROR: Using empty icon as fallback');
                        
                        if ($index == 6) {
                            $this->DebugLog('INDEX 6 STEP 3: GetIcon FAILED for Variable 37555, using empty icon fallback');
                        }
                        
                    } catch (Error $e) {
                        $icon = '';
                        $this->DebugLog('GETICON FATAL ERROR: GetIcon fatal error for Variable ' . $variable['Variable'] . ' - ' . $e->getMessage());
                        $this->DebugLog('GETICON FATAL ERROR: Using empty icon as fallback');
                        
                        if ($index == 6) {
                            $this->DebugLog('INDEX 6 STEP 3: GetIcon FATAL ERROR for Variable 37555, using empty icon fallback');
                        }
                    }
                    
                    $this->DebugLog('GetFullUpdateMessage: GetIcon returned for Variable ID: ' . $variable['Variable'] . ', Index: ' . $index . ', Icon: "' . $icon . '"');
                    
                    // GRANULARE DIAGNOSE für Index 6 - IPS_GetVariable
                    if ($index == 6) {
                        $this->DebugLog('INDEX 6 STEP 4: About to call IPS_GetVariable for Variable 37555');
                    }
                    
                    $variableInfo = IPS_GetVariable($variable['Variable']);
                    
                    // GRANULARE DIAGNOSE für Index 6 - IPS_GetVariable completed
                    if ($index == 6) {
                        $this->DebugLog('INDEX 6 STEP 5: IPS_GetVariable completed for Variable 37555');
                    }
                    
                    // Extrahiere Button-Farben aus Profil/Darstellung für Bool-Variablen
                    $buttonColors = $this->GetButtonColors($variable['Variable']);
                    
                    // Extrahiere Variable-Associations für Button-Erstellung (Integer + String)
                    $variableAssociations = null;
                    $this->DebugLog('Variable Association Check for Variable ID: ' . $variable['Variable'] . ' - VariableType: ' . $variableInfo['VariableType'] . ' (INTEGER=' . VARIABLETYPE_INTEGER . ', STRING=' . VARIABLETYPE_STRING . ', BOOLEAN=' . VARIABLETYPE_BOOLEAN . '), DisplayType: "' . ($variable['DisplayType'] ?? 'text') . '"');
                    
                    // Spezielle Debug-Ausgabe für Variable 11998
                    if ($variable['Variable'] == 11998) {
                        $this->DebugLog('SPECIAL DEBUG Variable 11998: VariableType=' . $variableInfo['VariableType'] . ', VARIABLETYPE_INTEGER=' . VARIABLETYPE_INTEGER . ', DisplayType="' . ($variable['DisplayType'] ?? 'text') . '"');
                        $this->DebugLog('SPECIAL DEBUG Variable 11998: Type comparison: ' . ($variableInfo['VariableType'] === VARIABLETYPE_INTEGER ? 'TRUE' : 'FALSE') . ', DisplayType comparison: ' . (($variable['DisplayType'] ?? 'text') === 'button' ? 'TRUE' : 'FALSE'));
                    }
                    
                    if (($variable['DisplayType'] ?? 'text') === 'button') {
                        if ($variableInfo['VariableType'] === VARIABLETYPE_INTEGER) {
                            $this->DebugLog('Calling GetIntegerAssociations for Variable ID: ' . $variable['Variable']);
                            $variableAssociations = $this->GetIntegerAssociations($variable['Variable']);
                            $this->DebugLog('GetIntegerAssociations returned: ' . ($variableAssociations ? json_encode($variableAssociations) : 'null'));
                        } elseif ($variableInfo['VariableType'] === VARIABLETYPE_STRING) {
                            $this->DebugLog('Calling GetStringAssociations for Variable ID: ' . $variable['Variable']);
                            $variableAssociations = $this->GetStringAssociations($variable['Variable']);
                            $this->DebugLog('GetStringAssociations returned: ' . ($variableAssociations ? json_encode($variableAssociations) : 'null'));
                        } elseif ($variableInfo['VariableType'] === VARIABLETYPE_BOOLEAN) {
                            // SPECIAL: Nur für Boolean-Variablen mit PRESENTATION GUID, nicht für Standard-Profile
                            $variable_data = IPS_GetVariable($variable['Variable']);
                            if (isset($variable_data['VariableCustomPresentation']) && !empty($variable_data['VariableCustomPresentation'])) {
                                $customPresentation = $variable_data['VariableCustomPresentation'];
                                if (isset($customPresentation['PRESENTATION']) || isset($customPresentation['ICON_TRUE']) || isset($customPresentation['ICON_FALSE'])) {
                                    $this->DebugLog('Calling GetBooleanAssociations for Variable ID with PRESENTATION GUID: ' . $variable['Variable']);
                                    $variableAssociations = $this->GetBooleanAssociations($variable['Variable']);
                                    $this->DebugLog('GetBooleanAssociations returned: ' . ($variableAssociations ? json_encode($variableAssociations) : 'null'));
                                } else {
                                    $this->DebugLog('Skipping Boolean associations for Variable ID: ' . $variable['Variable'] . ' - using standard profile (GetValueFormatted)');
                                }
                            } else {
                                $this->DebugLog('Skipping Boolean associations for Variable ID: ' . $variable['Variable'] . ' - using standard profile (GetValueFormatted)');
                            }
                        } else {
                            $this->DebugLog('Skipping Variable Associations for Variable ID: ' . $variable['Variable'] . ' - unsupported variable type: ' . $variableInfo['VariableType']);
                        }
                    } else {
                        $this->DebugLog('Skipping Variable Associations for Variable ID: ' . $variable['Variable'] . ' - DisplayType is not "button"');
                    }
                    
                    // Extrahiere Min/Max-Werte aus Variablenprofil für Progress-Balken
                    $progressMinMax = $this->GetProgressMinMax($variable['Variable']);
                    
                    // Ermittle Progressbar Active Status basierend auf ProfilAssoziazionen
                    $progressbarActive = true; // Standard: aktiv
                    $profilAssoziationen = json_decode($this->ReadPropertyString('ProfilAssoziazionen'), true);
                    if (is_array($profilAssoziationen)) {
                        $statusId = $this->ReadPropertyInteger('Status');
                        if ($statusId > 0 && IPS_VariableExists($statusId)) {
                            $currentStatusValue = GetValue($statusId);
                            foreach ($profilAssoziationen as $assoziation) {
                                if (isset($assoziation['AssoziationValue']) && $assoziation['AssoziationValue'] == $currentStatusValue) {
                                    $progressbarActive = $assoziation['ProgressbarActive'] ?? true;
                                    break;
                                }
                            }
                        }
                    }
                    
                    // SPECIAL: Für Boolean-Variablen mit PRESENTATION GUID, bei denen GetIcon leer ist
                    // aber die Assoziationen Icons enthalten, verwende das Association-Icon als Haupt-Icon
                    if (($icon === '' || $icon === 'Transparent') && $variableInfo['VariableType'] == VARIABLETYPE_BOOLEAN && !empty($variableAssociations)) {
                        foreach ($variableAssociations as $assoc) {
                            if (!empty($assoc['icon'])) {
                                $icon = $assoc['icon'];
                                $this->DebugLog('GetFullUpdateMessage: Using association icon for Boolean variable ' . $variable['Variable'] . ': ' . $icon);
                                break;
                            }
                        }
                    }
                    
                    $variableData = [
                        'id' => $variable['Variable'],
                        'label' => $label,
                        'displayType' => $variable['DisplayType'] ?? 'text',
                        'variableType' => $variableInfo['VariableType'], // Für Button-Validierung
                        'group' => $variable['Group'] ?? 'keine Gruppe', // Group-Information für Frontend-Gruppierung
                        'showGroupName' => $variable['ShowGroupName'] ?? false, // Show Group Name Flag für Frontend
                        'showIcon' => ($variable['ShowIcon'] ?? true) && !empty($icon) && $icon !== 'Transparent',
                        'showLabel' => $variable['ShowLabel'] ?? true,
                        'showValue' => $variable['ShowValue'] ?? true,
                        'fontSize' => $variable['FontSize'] ?? 12,
                        'textColor' => isset($variable['TextColor']) ? '#' . sprintf('%06X', $variable['TextColor']) : '#000000',
                        'isTextColorTransparent' => isset($variable['TextColor']) && ($variable['TextColor'] == -1 || $variable['TextColor'] == 16777215),
                        'progressColor1' => isset($variable['ProgressColor1']) ? '#' . sprintf('%06X', $variable['ProgressColor1']) : '#4CAF50',
                        'progressColor2' => isset($variable['ProgressColor2']) ? '#' . sprintf('%06X', $variable['ProgressColor2']) : '#2196F3',
                        'boolButtonColor' => isset($variable['boolButtonColor']) ? '#' . sprintf('%06X', $variable['boolButtonColor']) : '#CCCCCC',
                        'isBoolButtonColorTransparent' => isset($variable['boolButtonColor']) && ($variable['boolButtonColor'] == -1 || $variable['boolButtonColor'] == 16777215),
                        'buttonWidth' => $variable['ButtonWidth'] ?? 120,
                        'showBorderLine' => $variable['ShowBorderLine'] ?? false,
                        'progressMin' => $progressMinMax['min'],
                        'progressMax' => $progressMinMax['max'],
                        'formattedValue' => GetValueFormatted($variable['Variable']),
                        'rawValue' => GetValue($variable['Variable']),
                        'icon' => $icon,
                        'progressbarActive' => $progressbarActive, // Progressbar Active Status
                        'variableAssociations' => $variableAssociations, // Variable-Associations für Button-Erstellung (Integer + String)
                    ];
                    
                    // Zweite Variable für Progress-Bars hinzufügen
                    if (isset($variable['SecondVariable']) && $variable['SecondVariable'] > 0 && IPS_VariableExists($variable['SecondVariable'])) {
                        // Icon für zweite Variable ermitteln
                        $secondIcon = $this->GetIcon($variable['SecondVariable']);
                        $this->DebugLog('GetFullUpdateMessage: Icon search result: ' . $secondIcon . ' for variable ID: ' . $variable['SecondVariable']);
                        
                        // SPECIAL: Für Boolean-Variablen mit PRESENTATION GUID, bei denen GetIcon leer ist
                        // aber die Assoziationen Icons enthalten, verwende das Association-Icon als Haupt-Icon
                        if (($secondIcon === '' || $secondIcon === 'Transparent') && $variableInfo['VariableType'] == VARIABLETYPE_BOOLEAN && !empty($variableAssociations)) {
                            foreach ($variableAssociations as $assoc) {
                                if (!empty($assoc['icon'])) {
                                    $secondIcon = $assoc['icon'];
                                    $this->DebugLog('GetFullUpdateMessage: Using association icon for Boolean variable ' . $variable['Variable'] . ': ' . $secondIcon);
                                    $this->DebugLog('GetFullUpdateMessage: Using association icon for Boolean variable ' . $variable['Variable'] . ': ' . $icon);
                                    break;
                                }
                            }
                        }
                        
                        // Label für zweite Variable ermitteln
                        $secondLabel = !empty($variable['SecondVariableLabel']) ? $variable['SecondVariableLabel'] : IPS_GetName($variable['SecondVariable']);
                        
                        $variableData['secondVariable'] = [
                            'id' => $variable['SecondVariable'],
                            'label' => $secondLabel,
                            'formattedValue' => GetValueFormatted($variable['SecondVariable']),
                            'rawValue' => GetValue($variable['SecondVariable']),
                            'icon' => $secondIcon,
                            'showIcon' => ($variable['SecondVariableShowIcon'] ?? true) && !empty($secondIcon) && $secondIcon !== 'Transparent',
                            'showLabel' => $variable['SecondVariableShowLabel'] ?? true,
                            'showValue' => $variable['SecondVariableShowValue'] ?? true
                        ];
                        $this->DebugLog('GetFullUpdateMessage: Second variable added: ' . $variable['SecondVariable'] . ' with config: ' . json_encode($variableData['secondVariable']));
                    }
                    
                    // Spezielle Behandlung für Zeitwerte
                    if (is_string($variableData['rawValue']) && preg_match('/^(\d{2}):(\d{2}):(\d{2})$/', $variableData['rawValue'], $matches)) {
                        $hours = (int)$matches[1];
                        $minutes = (int)$matches[2];
                        $seconds = (int)$matches[3];
                        $variableData['timeInSeconds'] = $hours * 3600 + $minutes * 60 + $seconds;
                    }
                    
                    $this->DebugLog('GetFullUpdateMessage: Variable data: ' . json_encode($variableData));
                    $variables[] = $variableData;
                }
                
                $this->DebugLog('FOREACH END: Successfully completed index ' . $index);
                
                // SPEZIELLE UNTERSUCHUNG: Was passiert nach Index 6?
                if ($index == 6) {
                    $this->DebugLog('POST-INDEX-6: Successfully completed index 6 - now checking if index 7 will be reached');
                    $this->DebugLog('POST-INDEX-6: Memory usage: ' . memory_get_usage() . ' bytes');
                    $this->DebugLog('POST-INDEX-6: Next iteration should process index 7...');
                }
                
                } catch (Exception $e) {
                    $this->DebugLog('FOREACH ERROR: Exception at index ' . $index . ' - ' . $e->getMessage());
                    $this->DebugLog('FOREACH ERROR: This prevented reaching Variable 11998 at index 11!');
                    $this->DebugLog('FOREACH ERROR: Exception details: ' . $e->getFile() . ':' . $e->getLine());
                    // Continue mit nächster Variable statt abzubrechen
                    continue;
                } catch (Error $e) {
                    $this->DebugLog('FOREACH FATAL ERROR: Fatal error at index ' . $index . ' - ' . $e->getMessage());
                    $this->DebugLog('FOREACH FATAL ERROR: This is likely what stops the loop before reaching Variable 11998!');
                    $this->DebugLog('FOREACH FATAL ERROR: Error details: ' . $e->getFile() . ':' . $e->getLine());
                    // Continue mit nächster Variable statt abzubrechen
                    continue;
                }
            }
            
            $result['variables'] = $variables;
        // Debug: Log the first variable object to verify icon mapping
        $this->DebugLog('FirstVarAfterMapping: ' . (isset($variables[0]) ? json_encode($variables[0]) : 'NONE'));
        IPS_LogMessage('FirstVarAfterMapping', isset($variables[0]) ? json_encode($variables[0]) : 'NONE');
        }
        
        // Zentrale Fortschrittsbalken-Konfiguration
        $result['progressBarConfig'] = [
            'height' => $this->ReadPropertyInteger('ProgressBarHeight'),
            'borderRadius' => $this->ReadPropertyInteger('ProgressBarBorderRadius'),
            'backgroundColor' => '#' . sprintf('%06X', $this->ReadPropertyInteger('ProgressBarBackgroundColor')),
            'backgroundOpacity' => $this->ReadPropertyInteger('ProgressBarBackgroundOpacity') / 100,
            'showText' => $this->ReadPropertyBoolean('ProgressBarShowText'),
            'textPadding' => $this->ReadPropertyInteger('ProgressBarTextPadding')
        ];
        
        // Bild-Konfiguration
        // Note: bildauswahl is now handled per-association in status rendering logic above
        $result['BildBreite'] = $this->ReadPropertyFloat('BildBreite');
        $result['bildtransparenz'] = $this->ReadPropertyFloat('Bildtransparenz');
        $result['kachelhintergrundfarbe'] = '#' . sprintf('%06X', $this->ReadPropertyInteger('Kachelhintergrundfarbe'));
        
        // Element-Spacing-Konfiguration
        $result['elementSpacing'] = $this->ReadPropertyInteger('ElementSpacing');
        
        // Debug-Konfiguration für Frontend
        $result['debugEnabled'] = $this->ReadPropertyBoolean('DebugEnabled');

            // Hintergrundbild verarbeiten
        $imageID = $this->ReadPropertyInteger('bgImage');
        if (IPS_MediaExists($imageID)) {
            $image = IPS_GetMedia($imageID);
            if ($image['MediaType'] === MEDIATYPE_IMAGE) {
                $imageFile = explode('.', $image['MediaFile']);
                $imageContent = '';
                // Falls ja, ermittle den Anfang der src basierend auf dem Dateitypen
                switch (end($imageFile)) {
                    case 'bmp':
                        $imageContent = 'data:image/bmp;base64,';
                        break;

                    case 'jpg':
                    case 'jpeg':
                        $imageContent = 'data:image/jpeg;base64,';
                        break;

                    case 'gif':
                        $imageContent = 'data:image/gif;base64,';
                        break;

                    case 'png':
                        $imageContent = 'data:image/png;base64,';
                        break;

                    case 'ico':
                        $imageContent = 'data:image/x-icon;base64,';
                        break;

                    case 'webp':
                        $imageContent = 'data:image/webp;base64,';
                        break;
                }

                // Nur fortfahren, falls Inhalt gesetzt wurde. Ansonsten ist das Bild kein unterstützter Dateityp
                if ($imageContent) {
                    // Hänge base64-codierten Inhalt des Bildes an
                    $imageContent .= IPS_GetMediaContent($imageID);
                    $result['image1'] = $imageContent;
                }
            }
        }
        else{
            $imageContent = 'data:image/png;base64,';
            $imageContent .= base64_encode(file_get_contents(__DIR__ . '/../imgs/kachelhintergrund1.png'));

            if ($this->ReadPropertyBoolean('BG_Off')) {
                $result['image1'] = $imageContent;
            }
        }
        
        // Füge Instance-ID für RequestAction-Aufrufe hinzu
        $result['instanceid'] = $this->InstanceID;
        $this->DebugLog('DEBUG: About to add groupNames to result');
        
        // Füge Gruppennamen hinzu für Frontend-Verwendung
        try {
            $this->DebugLog('DEBUG: Calling GetAllGroupNames()');
            $groupNames = $this->GetAllGroupNames();
            $this->DebugLog('DEBUG: GetAllGroupNames() returned: ' . print_r($groupNames, true));
            $this->DebugLog('GetFullUpdateMessage - Sending groupNames: ' . print_r($groupNames, true));
            $result['groupNames'] = $groupNames;
            $this->DebugLog('DEBUG: Added groupNames to result successfully');
        } catch (Exception $e) {
            $this->DebugLog('ERROR: Exception in groupNames processing: ' . $e->getMessage());
        }
        
        $this->DebugLog('DEBUG: About to return JSON with keys: ' . implode(', ', array_keys($result)));
        return json_encode($result);
    }

    public function UpdateList(int $StatusID)
    {
        $listData = []; // Hier sammeln Sie die Daten für Ihre Liste
    
        $id = $StatusID;

        // Prüfen, ob die übergebene ID einer existierenden Variable entspricht
        if (IPS_VariableExists($id)) {
            // Auslesen des Variablenprofils
            $variable = IPS_GetVariable($id);
            $profileName = $variable['VariableCustomProfile'] ?: $variable['VariableProfile'];
            
            if ($profileName != '') {
                $profile = IPS_GetVariableProfile($profileName);
    
                // Durchlaufen der Profilassoziationen
                foreach ($profile['Associations'] as $association) {
                    $listData[] = [
                        'AssoziationName' => $association['Name'],
                        'AssoziationValue' => $association['Value'],
                        'Bildauswahl' => 'wm_aus',
                        'StatusColor' => '-1'
                    ];
                }
            }
        }
    
        // Konvertieren Sie Ihre Liste in JSON und aktualisieren Sie das Konfigurationsformular
        $jsonListData = json_encode($listData);
        $this->UpdateFormField('ProfilAssoziazionen', 'values', $jsonListData);
    }
    
    





    private function CheckAndGetValueFormatted($property) {
        $id = $this->ReadPropertyInteger($property);
        if (IPS_VariableExists($id)) {
            return GetValueFormatted($id);
        }
        return false;
    }


    private function GetColor($id) {
        $variable = IPS_GetVariable($id);
        $Value = GetValue($id);
        $profile = $variable['VariableCustomProfile'] ?: $variable['VariableProfile'];

        if ($profile && IPS_VariableProfileExists($profile)) {
            $p = IPS_GetVariableProfile($profile);
            
            foreach ($p['Associations'] as $association) {
                if (isset($association['Value'], $association['Color']) && $association['Value'] == $Value) {
                    return $association['Color'] === -1 ? "" : sprintf('%06X', $association['Color']);
                    
                }
            }
        }
        return "";
    }


    private function GetColorRGB($hexcolor) {
        $transparenz = $this->ReadPropertyFloat('InfoMenueTransparenz');
        if($hexcolor != "-1")
        {
                $hexColor = sprintf('%06X', $hexcolor);
                // Prüft, ob der Hex-Farbwert gültig ist
                if (strlen($hexColor) == 6) {
                    $r = hexdec(substr($hexColor, 0, 2));
                    $g = hexdec(substr($hexColor, 2, 2));
                    $b = hexdec(substr($hexColor, 4, 2));
                    return "rgba($r, $g, $b, $transparenz)";
                } else {
                    // Fallback für ungültige Eingaben
                    return $hexColor;
                }
        }
        else {
            return "";
        }
    }

    private function GetIcon($id) {
        try {
            $this->DebugLog('GetIcon called for Variable ID: ' . $id, 'GetIcon');
            
            $variable = IPS_GetVariable($id);
            $this->DebugLog('GetIcon: Got variable data for ID: ' . $id, 'GetIcon');
            
            $Value = GetValue($id);
            $this->DebugLog('GetIcon: Got value for ID: ' . $id . ', Value: ' . $Value, 'GetIcon');
            
            $icon = "";
            
            // Debug-Ausgabe für Variable
            $objName = IPS_GetObject($id)['ObjectName'];
            $this->DebugLog('GetIcon called for Variable ID: ' . $id . ' (Name: ' . $objName . '), Value: ' . $Value, 'GetIcon');
            $this->DebugLog('Starting icon search for Variable ID: ' . $id . ' (Name: ' . $objName . '), Value: ' . $Value, 'GetIcon');
            
            // Vollständige Variable und Objekt Info
            $this->DebugLog('COMPLETE VARIABLE INFO: ' . json_encode($variable, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'GetIcon');
            $obj = IPS_GetObject($id);
            $this->DebugLog('COMPLETE OBJECT INFO: ' . json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 'GetIcon');
        } catch (Exception $e) {
            $this->DebugLog('ERROR in GetIcon for Variable ID ' . $id . ': ' . $e->getMessage(), 'GetIcon');
            return 'Transparent'; // Fallback bei Fehler
        }
        
        // Prüfe VariableCustomPresentation für Icon
        if ($icon == "" && !empty($variable['VariableCustomPresentation'])) {
            $customPresentation = $variable['VariableCustomPresentation'];
            if (isset($customPresentation['ICON']) && $customPresentation['ICON'] != "") {
                $icon = $customPresentation['ICON'];
                $this->DebugLog('Found icon in VariableCustomPresentation: ' . $icon, 'GetIcon');
            } elseif (isset($customPresentation['Icon']) && $customPresentation['Icon'] != "") {
                // Fallback für kleingeschriebenes 'icon' Schlüsselwort
                $icon = $customPresentation['Icon'];
                $this->DebugLog('Found icon in VariableCustomPresentation (lowercase): ' . $icon);
            } elseif (isset($customPresentation['PRESENTATION']) && !empty($customPresentation['PRESENTATION'])) {
                // PRESENTATION GUID Auflösung für Icons - TEMPORÄR DEAKTIVIERT WEGEN GUID PROBLEMEN
                $presentationGuid = trim($customPresentation['PRESENTATION'], '{}');
                $this->DebugLog('Variable ID ' . $id . ': Found PRESENTATION GUID for icon extraction: ' . $presentationGuid, 'GetIcon');
                $this->DebugLog('Variable ID ' . $id . ': PRESENTATION GUID icon extraction temporarily disabled due to GUID format issues', 'GetIcon');
                
                // ALTERNATIVE: Prüfe ob GetBooleanAssociations bereits Icons extrahiert hat
                $this->DebugLog('Variable ID ' . $id . ': Starting alternative GetBooleanAssociations approach', 'GetIcon');
                if ($variable['VariableType'] == 0) { // Boolean Variable
                    $this->DebugLog('Variable ID ' . $id . ': Confirmed Boolean variable, calling GetBooleanAssociations', 'GetIcon');
                    $associations = $this->GetBooleanAssociations($id);
                    $this->DebugLog('Variable ID ' . $id . ': GetBooleanAssociations returned: ' . json_encode($associations), 'GetIcon');
                    if (is_array($associations) && count($associations) > 0) {
                        $currentValue = GetValue($id);
                        $this->DebugLog('Variable ID ' . $id . ': Current value for icon matching: ' . json_encode($currentValue), 'GetIcon');
                        foreach ($associations as $assoc) {
                            $this->DebugLog('Variable ID ' . $id . ': Checking association: ' . json_encode($assoc), 'GetIcon');
                            if (isset($assoc['value']) && $assoc['value'] == $currentValue && isset($assoc['icon']) && !empty($assoc['icon'])) {
                                $icon = $assoc['icon'];
                                $this->DebugLog('Variable ID ' . $id . ': MATCH! Using icon from Boolean associations: ' . $icon, 'GetIcon');
                                break;
                            } else {
                                $this->DebugLog('Variable ID ' . $id . ': No match for this association', 'GetIcon');
                            }
                        }
                        if ($icon == "") {
                            $this->DebugLog('Variable ID ' . $id . ': No matching icon found in associations', 'GetIcon');
                        }
                    } else {
                        $this->DebugLog('Variable ID ' . $id . ': GetBooleanAssociations returned empty or null', 'GetIcon');
                    }
                } else {
                    $this->DebugLog('Variable ID ' . $id . ': Not a Boolean variable, skipping GetBooleanAssociations', 'GetIcon');
                }
            }
        }
        
        // Wenn noch kein Icon gefunden wurde, prüfe Darstellung/Visualisierung und Profile
        if ($icon == "") {
            // Zuerst prüfen ob die Variable eine neue Darstellung/Visualisierung hat
            if (function_exists('IPS_GetVariableVisualization')) {
                try {
                    $visualization = IPS_GetVariableVisualization($id);
                    $this->DebugLog('Variable ID ' . $id . ': Checking visualization...');
                    $this->DebugLog('Checking visualization for variable...');
                    $this->DebugLog('Variable ID ' . $id . ': VISUALIZATION CONTENT: ' . json_encode($visualization));
                    $this->DebugLog('COMPLETE VISUALIZATION CONTENT: ' . json_encode($visualization, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    if ($visualization && isset($visualization['ValueMappings'])) {
                        $this->DebugLog('Found ValueMappings: ' . json_encode($visualization['ValueMappings']));
                        // Suche nach passendem Icon in den ValueMappings
                        foreach ($visualization['ValueMappings'] as $mapping) {
                            if (isset($mapping['Value']) && $mapping['Value'] == $Value && isset($mapping['Icon']) && $mapping['Icon'] != "") {
                                $icon = $mapping['Icon'];
                                $this->DebugLog('Found icon in ValueMappings: ' . $icon);
                                break;
                            }
                        }
                        
                        // Falls kein spezifisches Icon gefunden, verwende Default-Icon der Darstellung
                        if ($icon == "" && isset($visualization['Icon']) && $visualization['Icon'] != "") {
                            $icon = $visualization['Icon'];
                            $this->DebugLog('Using default visualization icon: ' . $icon);
                        }
                    } else {
                        $this->DebugLog('Variable ID ' . $id . ': No visualization or ValueMappings found');
                        $this->DebugLog('No visualization or ValueMappings found');
                    }
                } catch (Exception $e) {
                    $this->DebugLog('Error getting visualization: ' . $e->getMessage());
                    // Falls IPS_GetVariableVisualization fehlschlägt, verwende Fallback zu Profilen
                }
            } else {
                $this->DebugLog('Variable ID ' . $id . ': IPS_GetVariableVisualization function NOT AVAILABLE');
                $this->DebugLog('IPS_GetVariableVisualization function not available');
            }
            
            // Fallback zu klassischen Variablenprofilen wenn kein Icon über Darstellung gefunden
            if ($icon == "") {
                $profile = $variable['VariableCustomProfile'] ?: $variable['VariableProfile'];
                $this->DebugLog('Checking profile: ' . ($profile ?: 'none'));
                
                if ($profile && IPS_VariableProfileExists($profile)) {
                    $p = IPS_GetVariableProfile($profile);
                    $this->DebugLog('Variable ID ' . $id . ': Found profile "' . $profile . '"');
                    $this->DebugLog('Variable ID ' . $id . ': PROFILE CONTENT: ' . json_encode($p));
                    $this->DebugLog('COMPLETE PROFILE CONTENT: ' . json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $this->DebugLog('Profile associations: ' . json_encode($p['Associations']));

                    foreach ($p['Associations'] as $association) {
                        if (isset($association['Value']) && $association['Icon'] != "" && $association['Value'] == $Value) {
                            $icon = $association['Icon'];
                            $this->DebugLog('Found icon in profile associations: ' . $icon);
                            break;
                        }
                    }

                    if ($icon == "" && isset($p['Icon']) && $p['Icon'] != "") {
                        $icon = $p['Icon'];
                        $this->DebugLog('Using default profile icon: ' . $icon);
                    }
                } else {
                    $this->DebugLog('No custom profile exists');
                }
            }
            
            // Finaler Fallback wenn nichts gefunden wurde
            if ($icon == "") {
                $icon = "Transparent";
                $this->DebugLog('Variable ID ' . $id . ': No icon found anywhere, using Transparent as final fallback');
                $this->DebugLog('No icon found anywhere, using Transparent as final fallback');
            }
        }
        


        // Icon-Mapping zu FontAwesome durchführen
        $this->DebugLog('BEFORE MapIconToFontAwesome - Variable ID: ' . $id . ', Icon: "' . $icon . '"');
        $mappedIcon = $this->MapIconToFontAwesome($icon);
        $this->DebugLog('AFTER MapIconToFontAwesome - Variable ID: ' . $id . ', Mapped Icon: "' . $mappedIcon . '"');
        
        // Debug-Ausgabe
        $this->DebugLog('GetIcon RESULT - Variable ID: ' . $id . ', Original Icon: "' . $icon . '", Mapped Icon: "' . $mappedIcon . '"');
        $this->DebugLog('Final result - Variable ID: ' . $id . ', Original Icon: ' . $icon . ', Mapped Icon: ' . $mappedIcon);
        
        return $mappedIcon;
    }
    
    /**
     * Lädt das Icon-Mapping aus der JSON-Datei
     */
    private function LoadIconMapping() {
        $mappingFile = __DIR__ . '/assets/iconMapping.json';
        
        // Debug: Prüfe, ob die Datei existiert
        $this->DebugLog('Attempting to load icon mapping from: ' . $mappingFile);
        
        if (file_exists($mappingFile)) {
            $this->DebugLog('Icon mapping file exists');
            $json = file_get_contents($mappingFile);
            
            if ($json !== false) {
                $this->DebugLog('Icon mapping file content loaded, length: ' . strlen($json));
                $this->iconMapping = json_decode($json, true);
                
                if ($this->iconMapping === null) {
                    $this->DebugLog('JSON decode error: ' . json_last_error_msg());
                    $this->iconMapping = [];
                } else {
                    $this->DebugLog('Icon mapping loaded successfully. Total icons: ' . count($this->iconMapping));
                    
                    // Debug: Prüfe spezifisch, ob das Euro-Icon vorhanden ist
                    if (isset($this->iconMapping['Euro'])) {
                        $this->DebugLog('Euro icon mapping found: Euro → ' . $this->iconMapping['Euro']);
                    } else {
                        $this->DebugLog('Euro icon mapping NOT found in loaded mappings!');
                    }
                    
                    // Debug: Zeige einige Beispiel-Mappings
                    $examples = array_slice($this->iconMapping, 0, 5, true);
                    $debugExamples = [];
                    foreach ($examples as $key => $value) {
                        $debugExamples[] = $key . ' → ' . $value;
                    }
                    $this->DebugLog('Icon mapping examples: ' . implode(', ', $debugExamples));
                }
            } else {
                $this->DebugLog('Failed to read icon mapping file: ' . $mappingFile);
                $this->iconMapping = [];
            }
        } else {
            $this->DebugLog('Icon mapping file not found: ' . $mappingFile);
            $this->iconMapping = [];
        }
    }
    
    /**
     * Wandelt ein IP-Symcon Icon-Name in den entsprechenden FontAwesome-Namen um
     * @param string $iconName Der Original-Icon-Name
     * @return string Der gemappte FontAwesome-Name oder der Original-Name falls kein Mapping gefunden
     */
    private function MapIconToFontAwesome($iconName) {
    // Vorverarbeitung: Whitespace entfernen und Normalisieren
    // Entferne führende/trailing Whitespaces inkl. Unicode-Leerzeichen (NBSP, NNBSP, etc.)
    $iconName = preg_replace('/^[\p{Z}\s\x{00A0}]+|[\p{Z}\s\x{00A0}]+$/u', '', $iconName);
    // Manche Profile liefern bereits eine FontAwesome-Klasse wie "fa-bolt" – das belassen wir
    // Debug speziell für Electricity Icon
    if (strpos($iconName, 'Electricity') !== false) {
        $this->DebugLog('ELECTRICITY DEBUG: Normalized iconName = "' . $iconName . '"');
    }
        // Debug speziell für Electricity Icon
        if (strpos($iconName, 'Electricity') !== false) {
            $this->DebugLog('ELECTRICITY DEBUG: Input iconName = "' . $iconName . '"');
        }
        
        // Wenn kein Icon oder "Transparent", leeren String zurückgeben
        if (empty($iconName) || $iconName === 'Transparent') {
            return '';
        }
        
        
        // Direkte Mapping-Tabelle übersprungen (nicht definiert)
        
        // Entferne fa-Präfix falls vorhanden, um den Basis-Namen zu erhalten
        $baseName = $iconName;
        $hadFaPrefix = false;
        if (strpos($iconName, 'fa-') === 0) {
            $baseName = substr($iconName, 3);
            $hadFaPrefix = true;
            $this->DebugLog('Removed fa- prefix from icon: ' . $iconName . ' → ' . $baseName);
        }
        
        // Stelle sicher, dass das Icon Mapping immer geladen ist
        if ($this->iconMapping === null || empty($this->iconMapping)) {
            $this->LoadIconMapping();
        }
        
        // Versuche den Basis-Namen in der JSON-Mapping-Tabelle zu finden
        if ($this->iconMapping !== null && is_array($this->iconMapping) && !empty($this->iconMapping)) {
            if (isset($this->iconMapping[$baseName])) {
                $mappedName = $this->iconMapping[$baseName];
                $this->DebugLog('Mapped icon from JSON: ' . $baseName . ' → ' . $mappedName);
                
                // Wenn ursprünglich ein fa-Präfix vorhanden war, füge es wieder hinzu
                if ($hadFaPrefix && strpos($mappedName, 'fa-') !== 0) {
                    $mappedName = 'fa-' . $mappedName;
                    $this->DebugLog('Re-added fa- prefix: ' . $mappedName);
                }
                
                return $mappedName;
            } else {
                // Case-insensitive Fallback: Vergleiche alle Keys in Kleinbuchstaben
                $lowerKey = strtolower($baseName);
                foreach ($this->iconMapping as $key => $value) {
                    if (strtolower($key) === $lowerKey) {
                        $this->DebugLog('Case-insensitive mapping hit: ' . $key . ' → ' . $value);
                        return $value;
                    }
                }
            }
        } else {
            $this->DebugLog('Icon mapping table is NULL, trying to load it now');
            $this->LoadIconMapping();
        }
        
        // Debug-Ausgabe zur Überprüfung des Mappings
        if ($this->iconMapping !== null) {
            $this->DebugLog('Icon mapping table has ' . count($this->iconMapping) . ' entries');
            if ($baseName === 'Euro') {
                $this->DebugLog('Special check for Euro icon: ' . (isset($this->iconMapping['Euro']) ? 'Found in mapping' : 'NOT found in mapping'));
            }
        } else {
            $this->DebugLog('Icon mapping table could not be loaded');
        }
        
        // Fallback zurück zum Original
        $this->DebugLog('No mapping found for icon: ' . $iconName . ', returning original');
        return $iconName;
    }
    
    /**
     * Extrahiert Button-Farben aus Variablen-Profil oder Darstellung
     * @param int $variableId Die Variable-ID
     * @return array Array mit 'active' und 'inactive' Farben
     */
    private function GetButtonColors($variableId) {
        $defaultColors = [
            'active' => '#28a745',   // Grün für aktiv/true
            'inactive' => '#dc3545'  // Rot für inaktiv/false
        ];
        
        if (!IPS_VariableExists($variableId)) {
            return $defaultColors;
        }
        
        $variable = IPS_GetVariable($variableId);
        
        // Nur für Bool-Variablen
        if ($variable['VariableType'] !== VARIABLETYPE_BOOLEAN) {
            return $defaultColors;
        }
        
        $profile = $variable['VariableCustomProfile'] ?: $variable['VariableProfile'];
        
        if (empty($profile) || !IPS_VariableProfileExists($profile)) {
            return $defaultColors;
        }
        
        $profileData = IPS_GetVariableProfile($profile);
        $colors = $defaultColors;
        
        // Durchsuche Associations nach Bool-Werten
        if (isset($profileData['Associations']) && is_array($profileData['Associations'])) {
            foreach ($profileData['Associations'] as $association) {
                if (isset($association['Value']) && isset($association['Color'])) {
                    $color = $association['Color'];
                    if ($color !== -1) {
                        $hexColor = '#' . sprintf('%06X', $color);
                        
                        if ($association['Value'] == 1 || $association['Value'] === true) {
                            $colors['active'] = $hexColor;
                        } elseif ($association['Value'] == 0 || $association['Value'] === false) {
                            $colors['inactive'] = $hexColor;
                        }
                    }
                }
            }
        }
        
        // Prüfe auch Darstellung/Visualisierung
        $objectId = $variableId;
        if (IPS_ObjectExists($objectId)) {
            $object = IPS_GetObject($objectId);
            if (isset($object['ObjectVisualization']) && !empty($object['ObjectVisualization'])) {
                $visualization = json_decode($object['ObjectVisualization'], true);
                if (is_array($visualization) && isset($visualization['ValueMappings'])) {
                    foreach ($visualization['ValueMappings'] as $mapping) {
                        if (isset($mapping['Value']) && isset($mapping['Color'])) {
                            $color = $mapping['Color'];
                            if (!empty($color) && $color !== 'transparent') {
                                if ($mapping['Value'] == 1 || $mapping['Value'] === true) {
                                    $colors['active'] = $color;
                                } elseif ($mapping['Value'] == 0 || $mapping['Value'] === false) {
                                    $colors['inactive'] = $color;
                                }
                            }
                        }
                    }
                }
            }
        }
        


        $this->DebugLog('Button colors for variable ' . $variableId . ': active=' . $colors['active'] . ', inactive=' . $colors['inactive']);
        
        return $colors;
    }
    
    /**
     * Extrahiert Min/Max-Werte aus Variablen-Profil oder Darstellung für Progress-Balken
     * @param int $variableId Die Variable-ID
     * @return array Array mit 'min' und 'max' Werten
     */
    private function GetProgressMinMax($variableId) {
        $defaultMinMax = [
            'min' => 0,
            'max' => 100
        ];
        
        if (!IPS_VariableExists($variableId)) {
            $this->DebugLog('Variable ' . $variableId . ' does not exist - using default MinMax');
            return $defaultMinMax;
        }
        
        $variable = IPS_GetVariable($variableId);
        
        // Nur für den erwarteten Variablentyp
        if ($variable['VariableType'] !== VARIABLETYPE_INTEGER) {
            return $defaultMinMax;
        }
        
        $profile = $variable['VariableCustomProfile'] ?: $variable['VariableProfile'];
        
        // VORAB-PRÜFUNG: Hat die Variable überhaupt ein Profil oder eine Darstellung?
        $hasProfile = !empty($profile) && IPS_VariableProfileExists($profile);
        
        $hasPresentation = false;
        if (IPS_ObjectExists($variableId)) {
            $object = IPS_GetObject($variableId);
            $hasPresentation = isset($object['ObjectVisualization']) && !empty($object['ObjectVisualization']);
        }
        
        // Wenn weder Profil noch Darstellung vorhanden: Standard-Fallback verwenden
        if (!$hasProfile && !$hasPresentation) {
            $this->DebugLog('Variable ' . $variableId . ' has no profile or presentation - using default MinMax (0-100)');
            return $defaultMinMax;
        }
        
        $this->DebugLog('Variable ' . $variableId . ' validation: hasProfile=' . ($hasProfile ? 'true' : 'false') . ', hasPresentation=' . ($hasPresentation ? 'true' : 'false'));
        
        // ERSTE PRIORITÄT: Prüfe VariableCustomPresentation (neue IP-Symcon Darstellungen)
        // Boolean-Variablen: Versuche Assoziationen aus den Präsentationsparametern zu extrahieren
        if ($variable['VariableType'] == VARIABLETYPE_BOOLEAN) {
            $customPresentation = $variable['VariableCustom']['CustomPresentation'] ?? [];
            $this->DebugLog('Boolean variable ' . $variableId . ' customPresentation: ' . json_encode($customPresentation));
            
            // Extra Debug für Variable 26746
            if ($variableId == 26746) {
                $this->DebugLog('VARIABLE 26746 DEBUG: Starting icon extraction');
                $this->DebugLog('VARIABLE 26746 DEBUG: ICON_TRUE exists = ' . (isset($customPresentation['ICON_TRUE']) ? 'YES' : 'NO'));
                $this->DebugLog('VARIABLE 26746 DEBUG: ICON_FALSE exists = ' . (isset($customPresentation['ICON_FALSE']) ? 'YES' : 'NO'));
                $this->DebugLog('VARIABLE 26746 DEBUG: USE_ICON_FALSE exists = ' . (isset($customPresentation['USE_ICON_FALSE']) ? 'YES' : 'NO'));
                if (isset($customPresentation['ICON_TRUE'])) {
                    $this->DebugLog('VARIABLE 26746 DEBUG: ICON_TRUE value = ' . $customPresentation['ICON_TRUE']);
                }
                if (isset($customPresentation['ICON_FALSE'])) {
                    $this->DebugLog('VARIABLE 26746 DEBUG: ICON_FALSE value = ' . $customPresentation['ICON_FALSE']);
                }
                if (isset($customPresentation['USE_ICON_FALSE'])) {
                    $this->DebugLog('VARIABLE 26746 DEBUG: USE_ICON_FALSE value = ' . ($customPresentation['USE_ICON_FALSE'] ? 'TRUE' : 'FALSE'));
                }
            }
            
            // Prüfe auf presentationParameters in der customPresentation (beide Strukturen unterstützen)
            $params = null;
            
            // Prüfe zuerst auf direktes VariableProfile
            if (isset($variable['VariableProfile']) && !empty($variable['VariableProfile'])) {
                $profileName = $variable['VariableProfile'];
                $profile = @IPS_GetVariableProfile($profileName);
                if ($profile !== false && isset($profile['Associations'])) {
                    $this->DebugLog('Found VariableProfile for Boolean variable: ' . $profileName);
                    if ($variableId == 26746) {
                        $this->DebugLog('VARIABLE 26746 DEBUG: Using VariableProfile associations: ' . json_encode($profile['Associations']));
                    }
                    return $profile['Associations'];
                }
            }
        }
        
        // ERSTE PRIORITÄT: Prüfe VariableCustomPresentation (neue IP-Symcon Darstellungen)
        if (isset($variable['VariableCustomPresentation']) && !empty($variable['VariableCustomPresentation'])) {
            $customPresentation = $variable['VariableCustomPresentation'];
            $this->DebugLog('Found VariableCustomPresentation: ' . json_encode($customPresentation, JSON_UNESCAPED_UNICODE));
            
            if (isset($customPresentation['MIN']) && isset($customPresentation['MAX'])) {
                if (is_numeric($customPresentation['MIN']) && is_numeric($customPresentation['MAX'])) {
                    $minMax = [
                        'min' => floatval($customPresentation['MIN']),
                        'max' => floatval($customPresentation['MAX'])
                    ];
                    
                    $this->DebugLog('✓ SUCCESS: Progress MinMax from VariableCustomPresentation for variable ' . $variableId . ': min=' . $minMax['min'] . ', max=' . $minMax['max']);
                    return $minMax;
                } else {
                    $this->DebugLog('✗ VariableCustomPresentation MIN/MAX not numeric: MIN=' . json_encode($customPresentation['MIN']) . ', MAX=' . json_encode($customPresentation['MAX']));
                }
            } else {
                $hasMin = isset($customPresentation['MIN']) ? 'YES (' . json_encode($customPresentation['MIN']) . ')' : 'NO';
                $hasMax = isset($customPresentation['MAX']) ? 'YES (' . json_encode($customPresentation['MAX']) . ')' : 'NO';
                $this->DebugLog('✗ VariableCustomPresentation missing MIN/MAX: MIN=' . $hasMin . ', MAX=' . $hasMax);
            }
        } else {
            $this->DebugLog('No VariableCustomPresentation found for variable ' . $variableId);
        }
        
        // ZWEITE PRIORITÄT: Versuche Profil-Min/Max zu verwenden
        if (!empty($profile) && IPS_VariableProfileExists($profile)) {
            $profileData = IPS_GetVariableProfile($profile);
            
            $this->DebugLog('Checking profile "' . $profile . '" for variable ' . $variableId);
            $this->DebugLog('Complete profile data: ' . json_encode($profileData, JSON_UNESCAPED_UNICODE));
            
            if (isset($profileData['MinValue']) && isset($profileData['MaxValue'])) {
                $minMax = [
                    'min' => floatval($profileData['MinValue']),
                    'max' => floatval($profileData['MaxValue'])
                ];
                
                $this->DebugLog('✓ SUCCESS: Progress MinMax from profile "' . $profile . '" for variable ' . $variableId . ': min=' . $minMax['min'] . ', max=' . $minMax['max']);
                return $minMax;
            } else {
                $hasMinValue = isset($profileData['MinValue']) ? 'YES' : 'NO';
                $hasMaxValue = isset($profileData['MaxValue']) ? 'YES' : 'NO';
                $this->DebugLog('✗ Profile "' . $profile . '" missing MinMax values: MinValue=' . $hasMinValue . ', MaxValue=' . $hasMaxValue);
            }
        } else if (!empty($profile)) {
            $this->DebugLog('✗ Profile "' . $profile . '" does not exist or is invalid');
        }
        
        // Fallback: Prüfe Darstellung/Visualisierung
        $objectId = $variableId;
        if (IPS_ObjectExists($objectId)) {
            $object = IPS_GetObject($objectId);
            $this->DebugLog('Checking object visualization for variable ' . $variableId);
            
            if (isset($object['ObjectVisualization']) && !empty($object['ObjectVisualization'])) {
                $visualization = json_decode($object['ObjectVisualization'], true);
                
                $this->DebugLog('Complete visualization data: ' . json_encode($visualization, JSON_UNESCAPED_UNICODE));
                
                if (is_array($visualization)) {
                    // Erweiterte Suche nach Min/Max in allen möglichen Feldern
                    $possibleMinFields = ['MinValue', 'MinimalerWert', 'Minimum', 'Min', 'minValue', 'min'];
                    $possibleMaxFields = ['MaxValue', 'MaximalerWert', 'Maximum', 'Max', 'maxValue', 'max'];
                    
                    $foundMin = null;
                    $foundMax = null;
                    $minFieldName = null;
                    $maxFieldName = null;
                    
                    // Suche alle möglichen Min-Felder
                    foreach ($possibleMinFields as $field) {
                        if (isset($visualization[$field]) && is_numeric($visualization[$field])) {
                            $foundMin = floatval($visualization[$field]);
                            $minFieldName = $field;
                            $this->DebugLog('🔍 Found Min field "' . $field . '" with value: ' . $foundMin);
                            break;
                        }
                    }
                    
                    // Suche alle möglichen Max-Felder
                    foreach ($possibleMaxFields as $field) {
                        if (isset($visualization[$field]) && is_numeric($visualization[$field])) {
                            $foundMax = floatval($visualization[$field]);
                            $maxFieldName = $field;
                            $this->DebugLog('🔍 Found Max field "' . $field . '" with value: ' . $foundMax);
                            break;
                        }
                    }
                    
                    // Verwende gefundene Min/Max-Werte
                    if ($foundMin !== null && $foundMax !== null) {
                        $minMax = [
                            'min' => $foundMin,
                            'max' => $foundMax
                        ];
                        
                        $this->DebugLog('✓ SUCCESS: Progress MinMax from visualization for variable ' . $variableId . ': min=' . $minMax['min'] . ', max=' . $minMax['max'] . ' (using fields: "' . $minFieldName . '", "' . $maxFieldName . '")');
                        return $minMax;
                    } else {
                        $hasMinValue = isset($visualization['MinValue']) ? 'YES (' . $visualization['MinValue'] . ')' : 'NO';
                        $hasMaxValue = isset($visualization['MaxValue']) ? 'YES (' . $visualization['MaxValue'] . ')' : 'NO';
                        $this->DebugLog('✗ Visualization missing direct MinMax: MinValue=' . $hasMinValue . ', MaxValue=' . $hasMaxValue);
                    }
                    
                    // Fallback: Extrahiere Min/Max aus ValueMappings
                    if (isset($visualization['ValueMappings']) && is_array($visualization['ValueMappings'])) {
                        $this->DebugLog('Checking ValueMappings (' . count($visualization['ValueMappings']) . ' entries)');
                        
                        $values = [];
                        foreach ($visualization['ValueMappings'] as $index => $mapping) {
                            if (isset($mapping['Value']) && is_numeric($mapping['Value'])) {
                                $value = floatval($mapping['Value']);
                                $values[] = $value;
                                $this->DebugLog('  ValueMapping[' . $index . ']: Value=' . $value . (isset($mapping['Caption']) ? ' (Caption: "' . $mapping['Caption'] . '")' : ''));
                            } else {
                                $this->DebugLog('  ValueMapping[' . $index . ']: Skipped - no numeric Value (' . json_encode($mapping, JSON_UNESCAPED_UNICODE) . ')');
                            }
                        }
                        
                        if (count($values) > 0) {
                            $minMax = [
                                'min' => min($values),
                                'max' => max($values)
                            ];
                            
                            $this->DebugLog('✓ SUCCESS: Progress MinMax from ValueMappings for variable ' . $variableId . ': min=' . $minMax['min'] . ', max=' . $minMax['max'] . ' (from ' . count($values) . ' values: ' . implode(', ', $values) . ')');
                            return $minMax;
                        } else {
                            $this->DebugLog('✗ No numeric values found in ValueMappings');
                        }
                    } else {
                        $hasValueMappings = isset($visualization['ValueMappings']) ? 'YES (not array)' : 'NO';
                        $this->DebugLog('✗ No usable ValueMappings found: ' . $hasValueMappings);
                    }
                } else {
                    $this->DebugLog('✗ Visualization data is not a valid array');
                }
            } else {
                $this->DebugLog('✗ No ObjectVisualization found for variable ' . $variableId);
            }
        } else {
            $this->DebugLog('✗ Object ' . $variableId . ' does not exist');
        }
        
        $this->DebugLog('Variable ' . $variableId . ' has profile/presentation but no usable MinMax values found - using default MinMax (0-100)');
        return $defaultMinMax;
    }
    
    /**
     * Extrahiert Associations einer Integer-Variable für Button-Erstellung
     * Unterstützt 4 Fälle: Alte Variablenprofile, CustomPresentation mit OPTIONS/TEMPLATE/PRESENTATION GUID
     */
    private function GetIntegerAssociations($variableId) {
        return $this->GetVariableAssociations($variableId, VARIABLETYPE_INTEGER);
    }
    
    /**
     * Extrahiert Associations einer String-Variable für Button-Erstellung
     * Unterstützt 4 Fälle: Alte Variablenprofile, CustomPresentation mit OPTIONS/TEMPLATE/PRESENTATION GUID
     */
    private function GetStringAssociations($variableId) {
        return $this->GetVariableAssociations($variableId, VARIABLETYPE_STRING);
    }
    
    /**
     * Extrahiert Associations einer Boolean-Variable für Button-Erstellung
     * Unterstützt 4 Fälle: Alte Variablenprofile, CustomPresentation mit OPTIONS/TEMPLATE/PRESENTATION GUID
     */
    private function GetBooleanAssociations($variableId) {
        return $this->GetVariableAssociations($variableId, VARIABLETYPE_BOOLEAN);
    }
    
    /**
     * Generische Funktion zum Extrahieren von Associations für Boolean-, Integer- und String-Variablen
     * Unterstützt 4 Fälle: Alte Variablenprofile, CustomPresentation mit OPTIONS/TEMPLATE/PRESENTATION GUID
     */
    private function GetVariableAssociations($variableId, $expectedVariableType) {
        if (!IPS_VariableExists($variableId)) {
            return null;
        }
        
        $variable = IPS_GetVariable($variableId);
        
        // Nur für den erwarteten Variablentyp
        if ($variable['VariableType'] !== $expectedVariableType) {
            return null;
        }
        
        // Bestimme den Gruppennamen für FALL 4 basierend auf Variablentyp
        $groupName = ($expectedVariableType === VARIABLETYPE_INTEGER) ? 'Numeric' : 
                     (($expectedVariableType === VARIABLETYPE_BOOLEAN) ? 'Boolean' : 'String');
        
        // **FALL 1: Alte Variablenprofile**
        $profile = $variable['VariableCustomProfile'] ?: $variable['VariableProfile'];
        if (!empty($profile) && IPS_VariableProfileExists($profile)) {
            $profileData = IPS_GetVariableProfile($profile);
            $this->DebugLog('STANDARD PROFILE DEBUG for variable ' . $variableId . ': Profile = ' . $profile);
            $this->DebugLog('STANDARD PROFILE DEBUG: ProfileData = ' . json_encode($profileData));
            
            if (isset($profileData['Associations']) && is_array($profileData['Associations'])) {
                $associations = [];
                foreach ($profileData['Associations'] as $association) {
                    if (isset($association['Value']) && isset($association['Name'])) {
                        // SPECIAL: Boolean-Wert-Normalisierung für korrektes Association-Matching
                        $normalizedValue = $association['Value'];
                        if ($expectedVariableType === VARIABLETYPE_BOOLEAN) {
                            // Normalisiere Boolean-Werte: false kann als "" oder 0 kommen, true als 1 oder true
                            if ($association['Value'] === '' || $association['Value'] === 0 || $association['Value'] === false) {
                                $normalizedValue = false;
                            } elseif ($association['Value'] === 1 || $association['Value'] === true) {
                                $normalizedValue = true;
                            }
                            $this->DebugLog('BOOLEAN VALUE NORMALIZATION: Original=' . json_encode($association['Value']) . ' → Normalized=' . json_encode($normalizedValue));
                        }
                        
                        $associations[] = [
                            'value' => $normalizedValue, // Normalisierte Werte für Boolean-Variablen
                            'name' => $association['Name'],
                            'color' => isset($association['Color']) && $association['Color'] !== -1 ? '#' . sprintf('%06X', $association['Color']) : null,
                            'icon' => isset($association['Icon']) ? $association['Icon'] : null
                        ];
                        $this->DebugLog('STANDARD PROFILE ASSOCIATION: Original Value=' . json_encode($association['Value']) . ' → Normalized Value=' . json_encode($normalizedValue) . ', Name=' . $association['Name'] . ', Icon=' . ($association['Icon'] ?? 'NULL'));
                    }
                }
                $this->DebugLog('STANDARD PROFILE DEBUG: Returning ' . count($associations) . ' associations');
                return $associations;
            }
        }
        
        $customPresentation = isset($variable['VariableCustomPresentation']) ? $variable['VariableCustomPresentation'] : [];
// **FALL 1.5: Boolean-Präsentationen mit direkten ICON_TRUE/ICON_FALSE Parametern**
        if ($expectedVariableType === VARIABLETYPE_BOOLEAN) {
            // **KRITISCHER FIX: Baue ASSOCIATIONS aus ICON_TRUE/ICON_FALSE auf**
            // Prüfe, ob ICON_TRUE oder ICON_FALSE wirklich gesetzt und nicht leer sind
            $iconTrueSet = isset($customPresentation['ICON_TRUE']) && trim($customPresentation['ICON_TRUE']) !== '';
            $iconFalseSet = isset($customPresentation['ICON_FALSE']) && trim($customPresentation['ICON_FALSE']) !== '';
            if ($iconTrueSet || $iconFalseSet) {
                $this->DebugLog('VARIABLE ' . $variableId . ' DEBUG: Building associations from ICON_TRUE/ICON_FALSE');
                $associations = [];
                // FALSE Association (Wert 0/false)
                if ($iconFalseSet) {
                    $associations[] = [
                        'value' => false,
                        'name' => 'Aus',
                        'color' => null,
                        'icon' => $customPresentation['ICON_FALSE']
                    ];
                    $this->DebugLog('VARIABLE ' . $variableId . ' DEBUG: Added FALSE association with icon: ' . $customPresentation['ICON_FALSE']);
                }
                // TRUE Association (Wert 1/true)
                if ($iconTrueSet) {
                    $associations[] = [
                        'value' => true,
                        'name' => 'An',
                        'color' => null,
                        'icon' => $customPresentation['ICON_TRUE']
                    ];
                    $this->DebugLog('VARIABLE ' . $variableId . ' DEBUG: Added TRUE association with icon: ' . $customPresentation['ICON_TRUE']);
                }
                if (!empty($associations)) {
                    $this->DebugLog('VARIABLE ' . $variableId . ' DEBUG: Returning ' . count($associations) . ' built associations');
                    return $associations;
                }
            }
            $this->DebugLog('Checking Boolean presentation parameters for variable: ' . $variableId);
            $this->DebugLog('CustomPresentation structure: ' . json_encode($customPresentation));
            
            // Spezielle Debug-Logs für Variable 26746
            if ($variableId == 26746) {
                $this->DebugLog('VARIABLE 26746 DEBUG: Starting icon extraction');
                $this->DebugLog('VARIABLE 26746 DEBUG: ICON_TRUE exists = ' . (isset($customPresentation['ICON_TRUE']) ? 'YES' : 'NO'));
                $this->DebugLog('VARIABLE 26746 DEBUG: ICON_FALSE exists = ' . (isset($customPresentation['ICON_FALSE']) ? 'YES' : 'NO'));
                $this->DebugLog('VARIABLE 26746 DEBUG: USE_ICON_FALSE exists = ' . (isset($customPresentation['USE_ICON_FALSE']) ? 'YES' : 'NO'));
                if (isset($customPresentation['ICON_TRUE'])) {
                    $this->DebugLog('VARIABLE 26746 DEBUG: ICON_TRUE value = ' . $customPresentation['ICON_TRUE']);
                }
                if (isset($customPresentation['ICON_FALSE'])) {
                    $this->DebugLog('VARIABLE 26746 DEBUG: ICON_FALSE value = ' . $customPresentation['ICON_FALSE']);
                }
                if (isset($customPresentation['USE_ICON_FALSE'])) {
                    $this->DebugLog('VARIABLE 26746 DEBUG: USE_ICON_FALSE value = ' . ($customPresentation['USE_ICON_FALSE'] ? 'TRUE' : 'FALSE'));
                }
            }
            
            // Prüfe auf presentationParameters in der customPresentation (beide Strukturen unterstützen)
            $params = null;
            
            // Prüfe zuerst auf direktes VariableProfile
            if (isset($variable['VariableProfile']) && !empty($variable['VariableProfile'])) {
                $profileName = $variable['VariableProfile'];
                $profile = @IPS_GetVariableProfile($profileName);
                if ($profile !== false && isset($profile['Associations'])) {
                    $this->DebugLog('Found VariableProfile for Boolean variable: ' . $profileName);
                    if ($variableId == 26746) {
                        $this->DebugLog('VARIABLE 26746 DEBUG: Using VariableProfile associations: ' . json_encode($profile['Associations']));
                    }
                    return $profile['Associations'];
                }
            }
        }
        
        // **FALL 2: CustomPresentation mit direkten OPTIONS**
        if (isset($customPresentation['OPTIONS'])) {
            $options = is_string($customPresentation['OPTIONS']) ? json_decode($customPresentation['OPTIONS'], true) : $customPresentation['OPTIONS'];
            if (is_array($options)) {
                $associations = [];
                foreach ($options as $option) {
                    if (isset($option['Value']) && isset($option['Caption'])) {
                        $associations[] = [
                            'value' => $option['Value'], // Kann Integer oder String sein
                            'name' => $option['Caption'],
                            'color' => isset($option['Color']) && $option['Color'] !== -1 ? '#' . sprintf('%06X', $option['Color']) : null,
                            'icon' => isset($option['IconValue']) && !empty($option['IconValue']) ? $option['IconValue'] : null
                        ];
                    }
                }
                return $associations;
            }
        }
        
        // **FALL 3: CustomPresentation mit TEMPLATE**
        if (isset($customPresentation['TEMPLATE'])) {
            try {
                if (function_exists('IPS_GetTemplate')) {
                    $templateData = IPS_GetTemplate($customPresentation['TEMPLATE']);
                    
                    if (isset($templateData['Values']['OPTIONS'])) {
                        $options = is_string($templateData['Values']['OPTIONS']) ? json_decode($templateData['Values']['OPTIONS'], true) : $templateData['Values']['OPTIONS'];
                        if (is_array($options)) {
                            $associations = [];
                            foreach ($options as $option) {
                                if (isset($option['Value']) && isset($option['Caption'])) {
                                    $associations[] = [
                                        'value' => $option['Value'], // Kann Integer oder String sein
                                        'name' => $option['Caption'],
                                        'color' => isset($option['Color']) && $option['Color'] !== -1 ? '#' . sprintf('%06X', $option['Color']) : null,
                                        'icon' => isset($option['IconValue']) && !empty($option['IconValue']) ? $option['IconValue'] : null
                                    ];
                                }
                            }
                            return $associations;
                        }
                    }
                }
            } catch (Exception $e) {
                // Template-Fehler ignorieren und mit nächstem Fall fortfahren
            }
        }
        
        // **FALL 4: CustomPresentation mit PRESENTATION GUID (Fallback)**
        if (isset($customPresentation['PRESENTATION'])) {
            try {
                if (function_exists('IPS_GetPresentation')) {
                    $presentationData = IPS_GetPresentation($customPresentation['PRESENTATION']);
                    
                    // Falls es ein JSON-String ist, dekodieren
                    if (is_string($presentationData)) {
                        $presentationData = json_decode($presentationData, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            return null;
                        }
                    }
                    
                    // Suche nach der entsprechenden Gruppe ("Numeric" für Integer, "String" für String)
                    if (isset($presentationData['groups']) && is_array($presentationData['groups'])) {
                        foreach ($presentationData['groups'] as $group) {
                            if (isset($group['name']) && $group['name'] === $groupName) {
                                if (isset($group['presentationParameters']['OPTIONS'])) {
                                    $options = is_string($group['presentationParameters']['OPTIONS']) ? json_decode($group['presentationParameters']['OPTIONS'], true) : $group['presentationParameters']['OPTIONS'];
                                    if (is_array($options)) {
                                        // Prüfe auf deutsche Übersetzungen in locale.de
                                        if (isset($presentationData['locale']['de'])) {
                                            $originalOptionsString = $group['presentationParameters']['OPTIONS'];
                                            if (isset($presentationData['locale']['de'][$originalOptionsString])) {
                                                $germanOptionsString = $presentationData['locale']['de'][$originalOptionsString];
                                                $germanOptions = json_decode($germanOptionsString, true);
                                                if (is_array($germanOptions)) {
                                                    $options = $germanOptions; // Verwende deutsche Übersetzungen
                                                }
                                            }
                                        }
                                        
                                        $associations = [];
                                        foreach ($options as $option) {
                                            if (isset($option['Value']) && isset($option['Caption'])) {
                                                $associations[] = [
                                                    'value' => $option['Value'], // Kann Integer oder String sein
                                                    'name' => $option['Caption'],
                                                    'color' => isset($option['Color']) && $option['Color'] !== -1 ? '#' . sprintf('%06X', $option['Color']) : null,
                                                    'icon' => isset($option['IconValue']) && !empty($option['IconValue']) ? $option['IconValue'] : null
                                                ];
                                            }
                                        }
                                        return $associations;
                                    }
                                }
                                break;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Presentation-Fehler ignorieren und fortfahren
            }
        }
        
        return null;
    }
    
}
?>