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
        $this->RegisterPropertyInteger('StatusFontSize', -1);
        $this->RegisterPropertyBoolean('StatusShowIcon', true);
        $this->RegisterPropertyBoolean('StatusShowLabel', true);
        $this->RegisterPropertyBoolean('StatusShowValue', true);
        $this->RegisterPropertyString('StatusLabel', '');
        $this->RegisterPropertyInteger('DefaultImage', 0);

        // Neue universelle Variablenliste für konfigurierbare Variablen
        $this->RegisterPropertyString('VariablesList', '[]');
        
        // Zentrale Fortschrittsbalken-Konfiguration
        $this->RegisterPropertyInteger('ProgressBarHeight', 25);
        $this->RegisterPropertyInteger('ProgressBarBorderRadius', 6);
        $this->RegisterPropertyInteger('ProgressBarBackgroundColor', 8947848); // rgba(135, 135, 135, 0.3)
        $this->RegisterPropertyInteger('ProgressBarBackgroundOpacity', 30);
        $this->RegisterPropertyBoolean('ProgressBarShowText', true);
        $this->RegisterPropertyInteger('ProgressBarTextPadding', 12);
        
        // Zentrale Button-Konfiguration
        $this->RegisterPropertyInteger('ButtonHeight', 25);
        
        // Zentrale Gruppennamen-Konfiguration
        $this->RegisterPropertyInteger('GroupNameSize', -1);
        
        // Bildkonfiguration
        $this->RegisterPropertyInteger("Bildauswahl", 0);
        $this->RegisterPropertyFloat("BildBreite", 20);
        $this->RegisterPropertyString("BildPosition", "left");
        $this->RegisterPropertyBoolean("ShowBorderLine", true);
        $this->RegisterPropertyString("ImageAlignment", "center");
        $this->RegisterPropertyInteger("Bild_An", 0);
        $this->RegisterPropertyInteger("Bild_Aus", 0);
        $this->RegisterPropertyBoolean('BG_Off', 1);
        $this->RegisterPropertyInteger("bgImage", 0);
        $this->RegisterPropertyFloat('Bildtransparenz', 0.7);
        $this->RegisterPropertyInteger('Kachelhintergrundfarbe', -1);
        $this->RegisterPropertyInteger('ElementSpacing', 5); // Standardwert für Element-Abstand
        
         
        // Benutzerdefinierte Gruppennamen (Groups 1-10) mit expliziten Checkbox-Defaults
        $defaultGroupNames = json_encode([
            ['Group' => 1, 'GroupName' => 'Group 1', 'ShowGroupName' => false, 'ShowAbove' => false, 'line' => false, 'stretch' => false],
            ['Group' => 2, 'GroupName' => 'Group 2', 'ShowGroupName' => false, 'ShowAbove' => false, 'line' => false, 'stretch' => false],
            ['Group' => 3, 'GroupName' => 'Group 3', 'ShowGroupName' => false, 'ShowAbove' => false, 'line' => false, 'stretch' => false],
            ['Group' => 4, 'GroupName' => 'Group 4', 'ShowGroupName' => false, 'ShowAbove' => false, 'line' => false, 'stretch' => false],
            ['Group' => 5, 'GroupName' => 'Group 5', 'ShowGroupName' => false, 'ShowAbove' => false, 'line' => false, 'stretch' => false],
            ['Group' => 6, 'GroupName' => 'Group 6', 'ShowGroupName' => false, 'ShowAbove' => false, 'line' => false, 'stretch' => false],
            ['Group' => 7, 'GroupName' => 'Group 7', 'ShowGroupName' => false, 'ShowAbove' => false, 'line' => false, 'stretch' => false],
            ['Group' => 8, 'GroupName' => 'Group 8', 'ShowGroupName' => false, 'ShowAbove' => false, 'line' => false, 'stretch' => false],
            ['Group' => 9, 'GroupName' => 'Group 9', 'ShowGroupName' => false, 'ShowAbove' => false, 'line' => false, 'stretch' => false],
            ['Group' => 10, 'GroupName' => 'Group 10', 'ShowGroupName' => false, 'ShowAbove' => false, 'line' => false, 'stretch' => false]
        ]);
        $this->RegisterPropertyString('GroupNamesList', $defaultGroupNames);

        // Visualisierungstyp auf 1 setzen, da wir HTML anbieten möchten
        $this->SetVisualizationType(1);
        
        // Lade das Icon-Mapping
        $this->LoadIconMapping();
    }
    

    /**
     * Gibt die Konfigurationsform zurück
     * @return string JSON-String der Konfigurationsform
     */
    public function GetConfigurationForm()
    {
        // Hole die konfigurierten Gruppennamen
        $groupNames = $this->GetAllGroupNames();
        
        // Lade die statische Form als Text und ersetze Gruppennamen direkt
        $formJson = file_get_contents(__DIR__ . '/form.json');
        $formJson = $this->replaceGroupNamesInFormJson($formJson, $groupNames);
        
        // Parse die modifizierte Form
        $form = json_decode($formJson, true);
        
        // Befülle die neue GroupName Anzeige-Spalte mit konfigurierten Gruppennamen
        $this->populateGroupNameColumn($form, $groupNames);
        
        return json_encode($form);
    }
    
    /**
     * Ersetzt Gruppennamen direkt im JSON-Text
     * @param string $formJson Der JSON-Text der Form
     * @param array $groupNames Die konfigurierten Gruppennamen
     * @return string Der modifizierte JSON-Text
     */
    private function replaceGroupNamesInFormJson($formJson, $groupNames)
    {
        
        // Baue die neuen Group-Optionen als Text
        $newOptionsText = "";
        
        // Erste Option: keine Gruppe
        $newOptionsText .= "        [ 'caption' => 'keine Gruppe', 'value' => 'keine Gruppe' ],\n";
        
        // Füge konfigurierte Gruppennamen hinzu
        for ($i = 1; $i <= 10; $i++) {
            $groupName = 'Gruppe ' . $i; // Fallback
            
            // Verwende konfigurierten Namen falls vorhanden
            if (isset($groupNames[$i]) && !empty($groupNames[$i]['name'])) {
                $groupName = $groupNames[$i]['name'];
            }
            
            $comma = ($i < 10) ? ',' : ''; // Letztes Element ohne Komma
            $newOptionsText .= "        [ 'caption' => '" . addslashes($groupName) . "', 'value' => 'Gruppe " . $i . "' ]" . $comma . "\n";
        }
        
        // Definiere das Pattern für die alte Options-Sektion
        $pattern = '/(
        .*?"options" => \[\n).*?(        \],?\n)/s';
        
        // Alternative: Präziserer Pattern für Group Select Optionen
        $pattern = '/("        \[ \'caption\' => \'keine Gruppe\'.*?)\n(.*?)(        \[ \'caption\' => \'Gruppe 10\'.*?)\n/s';
        
        // Noch einfacher: Ersetze direkt die bekannten statischen Zeilen
        $patterns = [
            "        [ 'caption' => 'Gruppe 1', 'value' => 'Gruppe 1' ]," => "        [ 'caption' => '" . addslashes($groupNames[1]['name'] ?? 'Gruppe 1') . "', 'value' => 'Gruppe 1' ],",
            "        [ 'caption' => 'Gruppe 2', 'value' => 'Gruppe 2' ]," => "        [ 'caption' => '" . addslashes($groupNames[2]['name'] ?? 'Gruppe 2') . "', 'value' => 'Gruppe 2' ],",
            "        [ 'caption' => 'Gruppe 3', 'value' => 'Gruppe 3' ]," => "        [ 'caption' => '" . addslashes($groupNames[3]['name'] ?? 'Gruppe 3') . "', 'value' => 'Gruppe 3' ],",
            "        [ 'caption' => 'Gruppe 4', 'value' => 'Gruppe 4' ]," => "        [ 'caption' => '" . addslashes($groupNames[4]['name'] ?? 'Gruppe 4') . "', 'value' => 'Gruppe 4' ],",
            "        [ 'caption' => 'Gruppe 5', 'value' => 'Gruppe 5' ]," => "        [ 'caption' => '" . addslashes($groupNames[5]['name'] ?? 'Gruppe 5') . "', 'value' => 'Gruppe 5' ],",
            "        [ 'caption' => 'Gruppe 6', 'value' => 'Gruppe 6' ]," => "        [ 'caption' => '" . addslashes($groupNames[6]['name'] ?? 'Gruppe 6') . "', 'value' => 'Gruppe 6' ],",
            "        [ 'caption' => 'Gruppe 7', 'value' => 'Gruppe 7' ]," => "        [ 'caption' => '" . addslashes($groupNames[7]['name'] ?? 'Gruppe 7') . "', 'value' => 'Gruppe 7' ],",
            "        [ 'caption' => 'Gruppe 8', 'value' => 'Gruppe 8' ]," => "        [ 'caption' => '" . addslashes($groupNames[8]['name'] ?? 'Gruppe 8') . "', 'value' => 'Gruppe 8' ],",
            "        [ 'caption' => 'Gruppe 9', 'value' => 'Gruppe 9' ]," => "        [ 'caption' => '" . addslashes($groupNames[9]['name'] ?? 'Gruppe 9') . "', 'value' => 'Gruppe 9' ],",
            "        [ 'caption' => 'Gruppe 10', 'value' => 'Gruppe 10' ]" => "        [ 'caption' => '" . addslashes($groupNames[10]['name'] ?? 'Gruppe 10') . "', 'value' => 'Gruppe 10' ]"
        ];
        
        // Ersetze jede Gruppe einzeln
        foreach ($patterns as $search => $replace) {
            $formJson = str_replace($search, $replace, $formJson);
        }
        
        return $formJson;
    }
    

    

    

    

    
    /**
     * Befüllt die neue GroupName Anzeige-Spalte mit konfigurierten Gruppennamen
     * @param array &$form Das Form-Array (per Referenz)
     * @param array $groupNames Die konfigurierten Gruppennamen
     */
    private function populateGroupNameColumn(&$form, $groupNames)
    {
        // Lade die aktuellen VariablesList-Daten
        $currentVariables = json_decode($this->ReadPropertyString('VariablesList'), true);
        if (!is_array($currentVariables)) {
            return;
        }
        
        // Aktualisiere jeden Eintrag mit dem entsprechenden Gruppennamen
        foreach ($currentVariables as &$variable) {
            $technicalGroup = $variable['Group'] ?? 'keine Gruppe';
            $displayName = $this->getGroupDisplayName($technicalGroup, $groupNames);
            $variable['GroupName'] = $displayName;
        }
        
        // Finde und aktualisiere die VariablesList direkt im Form-Array
        $this->updateVariablesListInForm($form, $currentVariables);
    }
    
    /**
     * Aktualisiert die VariablesList direkt im Form-Array
     * @param array &$form Das Form-Array (per Referenz)
     * @param array $updatedVariables Die aktualisierten Variablen-Daten
     */
    private function updateVariablesListInForm(&$form, $updatedVariables)
    {
        $this->findAndUpdateVariablesList($form, $updatedVariables);
    }
    
    /**
     * Rekursive Suche und Update der VariablesList
     * @param array &$element Das aktuelle Element (per Referenz)
     * @param array $updatedVariables Die aktualisierten Variablen-Daten
     */
    private function findAndUpdateVariablesList(&$element, $updatedVariables)
    {
        if (is_array($element)) {
            // Prüfe, ob dies die VariablesList ist
            if (isset($element['name']) && $element['name'] === 'VariablesList') {
                $element['values'] = $updatedVariables;
                return;
            }
            
            // Rekursive Suche in allen Array-Elementen
            foreach ($element as &$subElement) {
                $this->findAndUpdateVariablesList($subElement, $updatedVariables);
            }
        }
    }
    
    /**
     * Findet die VariablesList in der Form-Struktur
     * @param array &$form Das Form-Array (per Referenz)
     * @return array|null Die VariablesList oder null wenn nicht gefunden
     */
    private function findVariablesList(&$form)
    {
        return $this->findElementByName($form, 'VariablesList');
    }
    
    /**
     * Rekursive Suche nach einem Element mit bestimmtem Namen
     * @param array &$element Das aktuelle Element (per Referenz)
     * @param string $name Der gesuchte Name
     * @return array|null Das gefundene Element oder null
     */
    private function findElementByName(&$element, $name)
    {
        if (is_array($element)) {
            // Prüfe, ob dies das gesuchte Element ist
            if (isset($element['name']) && $element['name'] === $name) {
                return $element;
            }
            
            // Rekursive Suche in allen Array-Elementen
            foreach ($element as &$subElement) {
                $found = $this->findElementByName($subElement, $name);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Ermittelt den Anzeigenamen für einen technischen Gruppenwert
     * @param string $technicalGroup Der technische Gruppenwert (z.B. "Gruppe 1")
     * @param array $groupNames Die konfigurierten Gruppennamen
     * @return string Der Anzeigename
     */
    private function getGroupDisplayName($technicalGroup, $groupNames)
    {
        if ($technicalGroup === 'keine Gruppe') {
            return 'keine Gruppe';
        }
        
        // Extrahiere die Gruppennummer aus "Gruppe X"
        if (preg_match('/^Gruppe (\d+)$/', $technicalGroup, $matches)) {
            $groupNumber = (int)$matches[1];
            
            // Suche den konfigurierten Namen
            if (isset($groupNames[$groupNumber]) && !empty($groupNames[$groupNumber]['name'])) {
                return $groupNames[$groupNumber]['name'];
            }
        }
        
        // Fallback: Technischen Wert zurückgeben
        return $technicalGroup;
    }
    
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        
        
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
     * Gibt alle Gruppennamen und ShowAbove/Line Konfiguration als Array zurück für Frontend-Verwendung
     * @return array Assoziatives Array mit Gruppennummer als Key und Konfiguration als Value
     */
    public function GetAllGroupNames()
    {
        $groupNamesList = json_decode($this->ReadPropertyString('GroupNamesList'), true);
        
        $result = [];
        
        if (is_array($groupNamesList)) {
            foreach ($groupNamesList as $index => $group) {
                if (isset($group['GroupName'])) {
                    $groupNumber = $index + 1; // Array-Index 0 = Gruppe 1
                    $showAbove = isset($group['ShowAbove']) ? (bool)$group['ShowAbove'] : false;
                    $showLine = isset($group['line']) ? (bool)$group['line'] : false;
                    $stretch = isset($group['stretch']) ? (bool)$group['stretch'] : false;
                    $showGroupName = isset($group['ShowGroupName']) ? (bool)$group['ShowGroupName'] : false;
                    $groupIcon = isset($group['Groupicon']) ? trim($group['Groupicon']) : '';
                
                    // Map Group Icon durch das bestehende Icon-Mapping-System
                    $mappedIcon = '';
                    if (!empty($groupIcon)) {
                        $mappedIcon = $this->MapIconToFontAwesome($groupIcon);
                    }
                
                    $result[$groupNumber] = [
                        'name' => $group['GroupName'],
                        'showAbove' => $showAbove,
                        'showLine' => $showLine,
                        'stretch' => $stretch,
                        'showGroupName' => $showGroupName,
                        'groupIcon' => $mappedIcon,
                        'fontSize' => $this->ReadPropertyInteger('GroupNameSize') // Globale Schriftgröße für alle Gruppen
                    ];
                }
            }
        }
        
        // Stelle sicher, dass alle Gruppen 1-10 existieren
        for ($i = 1; $i <= 10; $i++) {
            if (!isset($result[$i])) {
                $result[$i] = [
                    'name' => "Group $i",
                    'showAbove' => false,
                    'showLine' => false,
                    'stretch' => false,
                    'showGroupName' => false,
                    'groupIcon' => '',
                    'fontSize' => $this->ReadPropertyInteger('GroupNameSize') // Globale Schriftgröße für alle Gruppen
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
                    break;
                case 'wm_aus':
                    $assets['img_wm_aus'] = 'data:image/webp;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/wm_aus.webp'));
                    break;
                case 'dryer_on':
                    $assets['img_dryer_on'] = 'data:image/webp;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/trockner_an.webp'));
                    break;
                case 'dryer_off':
                    $assets['img_dryer_off'] = 'data:image/webp;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/trockner_aus.webp'));
                    break;
            }
        }
        
        // Standard-Bild laden wenn konfiguriert (für Fallback-Zwecke)
        $defaultImageId = $this->ReadPropertyInteger('DefaultImage');
        if ($defaultImageId > 0 && IPS_MediaExists($defaultImageId)) {
            $media = IPS_GetMedia($defaultImageId);
            if ($media['MediaType'] === MEDIATYPE_IMAGE) {
                $imageFile = explode('.', $media['MediaFile']);
                $imageContent = $this->GetImageDataUri(end($imageFile));
                if ($imageContent) {
                    $assets['img_default_' . $defaultImageId] = $imageContent . IPS_GetMediaContent($defaultImageId);
                }
            }
        }
        
        // Transparentes Platzhalter-Bild wird jetzt inline im Frontend als Data URI verwendet
        
        // Fallback: Wenn keine Statusvariable konfiguriert ist und noch kein img_wm_an Asset vorhanden, 
        // lade Standard-Waschmaschinen-Asset als Fallback
        if ($needsFallbackAssets && !isset($assets['img_wm_an'])) {
            $assets['img_wm_an'] = 'data:image/webp;base64,' . base64_encode(file_get_contents(__DIR__ . '/assets/wm_an.webp'));
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
        
        
        // Verarbeitung der Status-Variable
        $statusId = $this->ReadPropertyInteger('Status');
        if ($statusId > 0 && $SenderID === $statusId) {
            switch ($Message) {
            case VM_UPDATE:
                // Status-Änderung: Vollständige Nachricht mit abgefangenen Progress-Werten senden  
                $fullMessage = $this->GetFullUpdateMessage();
                $interceptedMessage = $this->InterceptProgressValuesIfNeeded($fullMessage);
                $this->UpdateVisualizationValue($interceptedMessage);
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
                        // Variable-Änderung: Vollständige Nachricht mit abgefangenen Progress-Werten senden
                        $fullMessage = $this->GetFullUpdateMessage();
                        $interceptedMessage = $this->InterceptProgressValuesIfNeeded($fullMessage);
                        $this->UpdateVisualizationValue($interceptedMessage);
                        break;
                }
            }
            // Prüfe SecondVariable
            elseif (isset($variable['SecondVariable']) && $SenderID === $variable['SecondVariable']) {
                switch ($Message) {
                    case VM_UPDATE:
                        // SecondVariable-Änderung: Vollständige Nachricht mit abgefangenen Progress-Werten senden
                        $fullMessage = $this->GetFullUpdateMessage();
                        $interceptedMessage = $this->InterceptProgressValuesIfNeeded($fullMessage);
                        $this->UpdateVisualizationValue($interceptedMessage);
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
            $this->UpdateDisplayTypeVisibility($value, $this->InstanceID);
            return;
        }
        
        // Nachrichten von der HTML-Darstellung schicken immer den Ident passend zur Eigenschaft und im Wert die Differenz, welche auf die Variable gerechnet werden soll
    $variableID = $Ident;
    if (!IPS_VariableExists($variableID)) {
        return;
    }
    
    // Ermittle Variablentyp für unterschiedliche Behandlung
    $variable = IPS_GetVariable($variableID);
    $variableType = $variable['VariableType'];
    
    if ($variableType === VARIABLETYPE_BOOLEAN) {
        // Boolean-Variable: Toggle-Verhalten (wie bisher)
        $currentValue = GetValue($variableID);
        $newValue = !$currentValue;
        RequestAction($variableID, $newValue);
    } else if ($variableType === VARIABLETYPE_INTEGER) {
        // Integer-Variable: Verwende den übergebenen Wert direkt (für Multi-Button-Interface)
        $newValue = intval($value);
        $currentValue = GetValue($variableID);
        
        RequestAction($variableID, $newValue);
    } else if ($variableType === VARIABLETYPE_STRING) {
        // String-Variable: Verwende den übergebenen String-Wert direkt (für Multi-Button-Interface)
        $newValue = strval($value);
        $currentValue = GetValue($variableID);
        RequestAction($variableID, $newValue);
    } else {
        // Andere Variablentypen: Fallback auf Toggle-Verhalten
        $currentValue = GetValue($variableID);
        $newValue = !$currentValue;
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
        
       
        

        // Status-Daten (werden immer oben angezeigt)
        $statusId = $this->ReadPropertyInteger('Status');
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
                foreach ($profilAssoziationen as $assoziation) {
                    if (isset($assoziation['AssoziationValue']) && $assoziation['AssoziationValue'] == $currentValue) {
                        // Neue erweiterte Bildauswahl-Logik
                        $bildauswahl = $assoziation['Bildauswahl'] ?? 'wm_aus';
                        
                        if ($bildauswahl === 'custom') {
                            // Verwende eigenes Bild aus SelectMedia-Feld
                            if (isset($assoziation['EigenesBild']) && $assoziation['EigenesBild'] > 0) {
                                // Asset-Name für Custom Image (wird in GenerateAssets() als base64 geladen)
                                $result['statusBildauswahl'] = 'img_custom_' . $assoziation['EigenesBild'];
                            } else {
                                // Fallback: Verwende Standard-Bild wenn konfiguriert, sonst 'none'
                                $result['statusBildauswahl'] = $this->getDefaultImageOrNone();
                            }
                        } elseif ($bildauswahl === 'none') {
                            // Fallback: Verwende Standard-Bild wenn konfiguriert, sonst wirklich 'none'
                            $result['statusBildauswahl'] = $this->getDefaultImageOrNone();
                        } else {
                            // Verwende vorkonfigurierte Bilder (wm_an, wm_aus, dryer_on, dryer_off, etc.)
                            $result['statusBildauswahl'] = $bildauswahl;
                        }
                        
                        $statusBildauswahlSet = true;
                        $statusColor = $assoziation['StatusColor'] ?? -1;
                        $result['statusColor'] = isset($assoziation['StatusColor']) ? '#' . sprintf('%06X', $assoziation['StatusColor']) : '#000000';
                        $result['isStatusColorTransparent'] = isset($assoziation['StatusColor']) && ($assoziation['StatusColor'] == -1 || $assoziation['StatusColor'] == 16777215);
                        break;
                    }
                }
            }
            
            // Ensure statusBildauswahl is set if we have a status variable
            if (!$statusBildauswahlSet) {
                $result['statusBildauswahl'] = $this->getDefaultImageOrNone(); // Fallback auf Standard-Bild oder 'none'
            }
        } else {
            // Fallback: Wenn keine Statusvariable konfiguriert ist, verwende Standard-Bild
            $result['statusBildauswahl'] = $this->getDefaultImageOrNone();
            
            // Image Column Logik wird bereits oben korrekt berechnet (Zeile 847-848)
            // Keine Überschreibung hier - verwende die bereits berechnete Logik
        }
        
        // UNIVERSAL GUARANTEE: statusBildauswahl MUST ALWAYS be set
        if (!isset($result['statusBildauswahl'])) {
            $result['statusBildauswahl'] = 'none';
        }
        

        // Lade die konfigurierte Variablenliste
        $variablesList = json_decode($this->ReadPropertyString('VariablesList'), true);
        
        // Sammle Informationen für jede konfigurierte Variable (Array-Reihenfolge durch changeOrder)
        if (is_array($variablesList)) {
            $variables = [];
            foreach ($variablesList as $index => $variable) {
                
               
                try {
                $varId = $variable['Variable'] ?? 'NONE';
                $varType = 'UNKNOWN';
                if (isset($variable['Variable']) && IPS_VariableExists($variable['Variable'])) {
                    $varInfo = IPS_GetVariable($variable['Variable']);
                    $varType = $varInfo['VariableType'];
                }
                $typeString = ($varType === 3) ? 'TEXT' : $varType;

                
                
                if (isset($variable['Variable']) && $variable['Variable'] > 0 && IPS_VariableExists($variable['Variable'])) {
                    
                    // Verwende Variablennamen als Fallback wenn kein Label gesetzt ist
                    $label = $variable['Label'] ?? '';
                    if (empty($label)) {
                        $variableObject = IPS_GetObject($variable['Variable']);
                        $label = $variableObject['ObjectName'];
                    }
                    
                    
                    
                   
                    // PROTECTION: Try-Catch um GetIcon call, um Abstürze zu verhindern
                    try {
                        $icon = $this->GetIcon($variable['Variable']);
                        
                        
                        
                        
                    } catch (Exception $e) {
                        $icon = '';
                        
                        
                        
                        
                    } catch (Error $e) {
                        $icon = '';
                    
                    }
                    
                    
                    
                    
                    
                    
                    
                    $variableInfo = IPS_GetVariable($variable['Variable']);
                    
                    
                    
                    // Extrahiere Button-Farben aus Profil/Darstellung für Bool-Variablen
                    $buttonColors = $this->GetButtonColors($variable['Variable']);
                    
                    // Extrahiere Variable-Associations für Button-Erstellung (Integer + String)
                    $variableAssociations = null;
                    
                    
                    
                    if (($variable['DisplayType'] ?? 'text') === 'button') {
                        if ($variableInfo['VariableType'] === VARIABLETYPE_INTEGER) {
                            $variableAssociations = $this->GetIntegerAssociations($variable['Variable']);
                        } elseif ($variableInfo['VariableType'] === VARIABLETYPE_STRING) {
                            $variableAssociations = $this->GetStringAssociations($variable['Variable']);
                        } elseif ($variableInfo['VariableType'] === VARIABLETYPE_BOOLEAN) {
                            // SPECIAL: Nur für Boolean-Variablen mit PRESENTATION GUID, nicht für Standard-Profile
                            $variable_data = IPS_GetVariable($variable['Variable']);
                            if (isset($variable_data['VariableCustomPresentation']) && !empty($variable_data['VariableCustomPresentation'])) {
                                $customPresentation = $variable_data['VariableCustomPresentation'];
                                if (isset($customPresentation['PRESENTATION']) || isset($customPresentation['ICON_TRUE']) || isset($customPresentation['ICON_FALSE'])) {
                                    $variableAssociations = $this->GetBooleanAssociations($variable['Variable']);
                                } else {
                                    $variableAssociations = null;
                                }
                            } else {
                                $variableAssociations = null;
                            }
                        } else {
                            $variableAssociations = null;
                        }
                    } else {
                        $variableAssociations = null;
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
                                break;
                            }
                        }
                    }
                    
                  
                    // Backend-basierte Progressbar-Deaktivierung: Überschreibe Werte wenn deaktiviert
                    $finalFormattedValue = GetValueFormatted($variable['Variable']);
                    $finalRawValue = GetValue($variable['Variable']);
                    
                    if (!$progressbarActive && ($variable['DisplayType'] ?? 'text') === 'progress') {
                        // Progressbar deaktiviert: Nur für ECHTE Progress-Variablen Werte auf 0 setzen
                        $finalRawValue = 0;
                        

                        
                        // Spezielle Behandlung für Zeit-Formate
                        if (preg_match('/^\s*(\d+):(\d+):(\d+)\s*$/', $finalFormattedValue)) {
                            // Format: HH:MM:SS -> 00:00:00

                            $finalFormattedValue = '00:00:00';
                        } elseif (preg_match('/^\s*(\d+):(\d+)\s*$/', $finalFormattedValue)) {
                            // Format: H:MM oder HH:MM -> 0:00

                            $finalFormattedValue = '0:00';
                        } elseif (preg_match('/^\s*[0-9.,\-]+\s*(%|V|A|W|kW|kWh|°C|°F|bar|Pa|Hz|m|cm|mm|kg|g|l|ml)\s*$/', $finalFormattedValue)) {
                            // Numerische Werte mit Einheiten

                            $originalFormatted = $finalFormattedValue;
                            if (preg_match('/[^0-9.,\-\s]+/', $originalFormatted, $matches)) {
                                $suffix = $matches[0];
                                $finalFormattedValue = '0 ' . $suffix;
                            } else {
                                $finalFormattedValue = '0';
                            }
                        } elseif (preg_match('/^\s*[0-9.,\-]+\s*$/', $finalFormattedValue)) {
                            // Reine numerische Werte ohne Einheit

                            $finalFormattedValue = '0';
                        } else {
                            // Unbekanntes Format - prüfen was es ist

                            if (preg_match('/[^0-9.,\-\s]+/', $finalFormattedValue, $matches)) {
                                $suffix = $matches[0];

                                $finalFormattedValue = '0 ' . $suffix;
                            } else {
                                // Text-Werte aus Profil-Assoziationen werden NICHT verändert

                                $finalRawValue = GetValue($variable['Variable']); // Restore original raw value
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
                        'showIcon' => $variable['ShowIcon'],
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
                        'formattedValue' => $finalFormattedValue, // Backend-überschriebener Wert
                        'rawValue' => $finalRawValue, // Backend-überschriebener Wert
                        'icon' => $icon,
                        'progressbarActive' => $progressbarActive, // Progressbar Active Status
                        'variableAssociations' => $variableAssociations, // Variable-Associations für Button-Erstellung (Integer + String)
                    ];
                    
                    // Zweite Variable für Progress-Bars hinzufügen
                    if (isset($variable['SecondVariable']) && $variable['SecondVariable'] > 0 && IPS_VariableExists($variable['SecondVariable'])) {
                        // Icon für zweite Variable ermitteln
                        $secondIcon = $this->GetIcon($variable['SecondVariable']);
                        
                        // SPECIAL: Für Boolean-Variablen mit PRESENTATION GUID, bei denen GetIcon leer ist
                        // aber die Assoziationen Icons enthalten, verwende das Association-Icon als Haupt-Icon
                        if (($secondIcon === '' || $secondIcon === 'Transparent') && $variableInfo['VariableType'] == VARIABLETYPE_BOOLEAN && !empty($variableAssociations)) {
                            foreach ($variableAssociations as $assoc) {
                                if (!empty($assoc['icon'])) {
                                    $secondIcon = $assoc['icon'];
                                    break;
                                }
                            }
                        }
                        
                        // Label für zweite Variable ermitteln
                        $secondLabel = !empty($variable['SecondVariableLabel']) ? $variable['SecondVariableLabel'] : IPS_GetName($variable['SecondVariable']);
                        
                        // Backend-basierte Deaktivierung auch für zweite Variable
                        $secondFinalFormattedValue = GetValueFormatted($variable['SecondVariable']);
                        $secondFinalRawValue = GetValue($variable['SecondVariable']);
                        
                        if (!$progressbarActive) {
                            // Progressbar deaktiviert: Setze auch zweite Variable auf 0 mit korrekter Format-Behandlung
                            $secondFinalRawValue = 0;
                            

                            
                            // Spezielle Behandlung für Zeit-Formate (gleiche Logik wie Hauptvariable)
                            if (preg_match('/^\s*(\d+):(\d+):(\d+)\s*$/', $secondFinalFormattedValue)) {
                                // Format: HH:MM:SS -> 00:00:00

                                $secondFinalFormattedValue = '00:00:00';
                            } elseif (preg_match('/^\s*(\d+):(\d+)\s*$/', $secondFinalFormattedValue)) {
                                // Format: H:MM oder HH:MM -> 0:00

                                $secondFinalFormattedValue = '0:00';
                            } elseif (preg_match('/^\s*[0-9.,\-]+\s*(%|V|A|W|kW|kWh|°C|°F|bar|Pa|Hz|m|cm|mm|kg|g|l|ml)\s*$/', $secondFinalFormattedValue)) {
                                // Numerische Werte mit Einheiten

                                $secondOriginalFormatted = $secondFinalFormattedValue;
                                if (preg_match('/[^0-9.,\-\s]+/', $secondOriginalFormatted, $matches)) {
                                    $suffix = $matches[0];
                                    $secondFinalFormattedValue = '0 ' . $suffix;
                                } else {
                                    $secondFinalFormattedValue = '0';
                                }
                            } elseif (preg_match('/^\s*[0-9.,\-]+\s*$/', $secondFinalFormattedValue)) {
                                // Reine numerische Werte ohne Einheit

                                $secondFinalFormattedValue = '0';
                            } else {
                                // Text-Werte aus Profil-Assoziationen werden NICHT verändert

                                $secondFinalRawValue = GetValue($variable['SecondVariable']); // Restore original raw value
                            }
                        }
                        
                        $variableData['secondVariable'] = [
                            'id' => $variable['SecondVariable'],
                            'label' => $secondLabel,
                            'formattedValue' => $secondFinalFormattedValue, // Backend-überschriebener Wert
                            'rawValue' => $secondFinalRawValue, // Backend-überschriebener Wert
                            'icon' => $secondIcon,
                            'showIcon' => ($variable['SecondVariableShowIcon'] ?? true) && !empty($secondIcon) && $secondIcon !== 'Transparent',
                            'showLabel' => $variable['SecondVariableShowLabel'] ?? true,
                            'showValue' => $variable['SecondVariableShowValue'] ?? true
                        ];
                    }
                    
                    // Spezielle Behandlung für Zeitwerte
                    if (is_string($variableData['rawValue']) && preg_match('/^(\d{2}):(\d{2}):(\d{2})$/', $variableData['rawValue'], $matches)) {
                        $hours = (int)$matches[1];
                        $minutes = (int)$matches[2];
                        $seconds = (int)$matches[3];
                        $variableData['timeInSeconds'] = $hours * 3600 + $minutes * 60 + $seconds;
                    }
                    
                    $variables[] = $variableData;
                }
                
                
                
                } catch (Exception $e) {

                    // Continue mit nächster Variable statt abzubrechen
                    continue;
                } catch (Error $e) {

                    // Continue mit nächster Variable statt abzubrechen
                    continue;
                }
            }
            
            $result['variables'] = $variables;
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
        
        // Füge Gruppennamen hinzu für Frontend-Verwendung
        try {
            $groupNames = $this->GetAllGroupNames();
            $result['groupNames'] = $groupNames;
        } catch (Exception $e) {
        }
        
        return json_encode($result);
    }

    /**
     * Legacy function: Progress value interception is now handled in GetFullUpdateMessage()
     * This function is kept for compatibility but does minimal processing
     * @param string $fullMessageJson JSON-String der vollständigen Update-Nachricht
     * @return string Unveränderte JSON-Nachricht (Verarbeitung erfolgt bereits in GetFullUpdateMessage)
     */
    private function InterceptProgressValuesIfNeeded($fullMessageJson) {
        // Progress value processing is now handled directly in GetFullUpdateMessage()
        // to avoid code duplication and ensure consistency
        return $fullMessageJson;
    }

    public function UpdateList(int $StatusID)
    {
        $listData = []; // Hier sammeln Sie die Daten für Ihre Liste
    
    $id = $StatusID;

    // Prüfen, ob die übergebene ID einer existierenden Variable entspricht
    if (IPS_VariableExists($id)) {
        $variable = IPS_GetVariable($id);
        $variableType = $variable['VariableType'];
        
        $associations = null;
        
        // Verwende die existierenden Association-Funktionen basierend auf Variablentyp
        if ($variableType === VARIABLETYPE_BOOLEAN) {
            $associations = $this->GetBooleanAssociations($id);
        } elseif ($variableType === VARIABLETYPE_INTEGER) {
            $associations = $this->GetIntegerAssociations($id);
        }
        
        // Fallback auf klassische Profile wenn keine Associations gefunden
        if (empty($associations)) {
            $profileName = $variable['VariableCustomProfile'] ?: $variable['VariableProfile'];
            
            if ($profileName != '') {
                try {
                    $profile = IPS_GetVariableProfile($profileName);
                    if (isset($profile['Associations'])) {
                        $associations = [];
                        foreach ($profile['Associations'] as $association) {
                            $associations[] = [
                                'name' => $association['Name'],
                                'value' => $association['Value'],
                                'icon' => isset($association['Icon']) ? $association['Icon'] : '',
                                'color' => isset($association['Color']) ? $association['Color'] : null
                            ];
                        }
                    }
                } catch (Exception $e) {
                    // Profil existiert nicht oder ist nicht lesbar
                }
            }
        }
        
        // Konvertiere Associations zu ListData Format
        if (!empty($associations)) {
            foreach ($associations as $association) {
                $listData[] = [
                    'AssoziationName' => $association['name'],
                    'AssoziationValue' => $association['value'],
                    'Bildauswahl' => 'none',
                    'StatusColor' => -1
                ];
            }
        }
    }
    
    // Konvertieren Sie Ihre Liste in JSON und aktualisieren Sie das Konfigurationsformular
    $jsonListData = json_encode($listData);
    $this->UpdateFormField('ProfilAssoziazionen', 'values', $jsonListData);
    }
    
    // Temporary alias for cached form calls - can be removed after Symcon restart
    public function UDST_UpdateDisplayTypeVisibility($id, $displayType, $rowIndex = null)
    {
        // Forward to the new RequestAction system
        $this->UpdateDisplayTypeVisibility($id, $displayType);
    }
    
    public function UpdateDisplayTypeVisibility(string $displayType, int $rowId = null)
    {
        // Basierend auf Display Type verschiedene Felder ein-/ausblenden
        switch ($displayType) {
            case 'text':
                // Bei Text: Show Icon ausblenden, da Text-Variablen normalerweise kein Icon haben
                $this->UpdateFormField('ShowIcon', 'visible', true);
                $this->UpdateFormField('ShowLabel', 'visible', true);
                $this->UpdateFormField('ShowValue', 'visible', true);
                // Progress-spezifische Felder ausblenden
                $this->UpdateFormField('ProgressColor1', 'visible', false);
                $this->UpdateFormField('ProgressColor2', 'visible', false);
                $this->UpdateFormField('SecondVariable', 'visible', false);
                $this->UpdateFormField('SecondVariableShowIcon', 'visible', false);
                $this->UpdateFormField('SecondVariableShowLabel', 'visible', false);
                $this->UpdateFormField('SecondVariableShowValue', 'visible', false);
                $this->UpdateFormField('SecondVariableLabel', 'visible', false);    
                // Button-spezifische Felder ausblenden
                $this->UpdateFormField('ButtonWidth', 'visible', false);
                $this->UpdateFormField('boolButtonColor', 'visible', false);
                // Text-spezifische Felder einblenden
                $this->UpdateFormField('ShowBorderLine', 'visible', true);
                break;
                
            case 'progress':
                // Bei Progress: Alle relevanten Felder einblenden
                $this->UpdateFormField('ShowIcon', 'visible', true);
                $this->UpdateFormField('ShowLabel', 'visible', true);
                $this->UpdateFormField('ShowValue', 'visible', true);
                // Progress-spezifische Felder einblenden
                $this->UpdateFormField('ProgressColor1', 'visible', true);
                $this->UpdateFormField('ProgressColor2', 'visible', true);
                $this->UpdateFormField('SecondVariable', 'visible', true);
                $this->UpdateFormField('SecondVariableShowIcon', 'visible', true);
                $this->UpdateFormField('SecondVariableShowLabel', 'visible', true);
                $this->UpdateFormField('SecondVariableShowValue', 'visible', true);
                $this->UpdateFormField('SecondVariableLabel', 'visible', true);
                // Button-spezifische Felder ausblenden
                $this->UpdateFormField('ButtonWidth', 'visible', false);
                $this->UpdateFormField('boolButtonColor', 'visible', false);
                // Text-spezifische Felder ausblenden
                $this->UpdateFormField('ShowBorderLine', 'visible', false);
                break;
                
            case 'button':
                // Bei Button: Relevante Felder einblenden
                $this->UpdateFormField('ShowIcon', 'visible', true);
                $this->UpdateFormField('ShowLabel', 'visible', true);
                $this->UpdateFormField('ShowValue', 'visible', true);
                // Progress-spezifische Felder ausblenden
                $this->UpdateFormField('ProgressColor1', 'visible', false);
                $this->UpdateFormField('ProgressColor2', 'visible', false);
                $this->UpdateFormField('SecondVariable', 'visible', false);
                $this->UpdateFormField('SecondVariableShowIcon', 'visible', false);
                $this->UpdateFormField('SecondVariableShowLabel', 'visible', false);
                $this->UpdateFormField('SecondVariableShowValue', 'visible', false);
                $this->UpdateFormField('SecondVariableLabel', 'visible', false);    
                // Button-spezifische Felder einblenden
                $this->UpdateFormField('ButtonWidth', 'visible', true);
                $this->UpdateFormField('boolButtonColor', 'visible', true);

                // Text-spezifische Felder ausblenden
                $this->UpdateFormField('ShowBorderLine', 'visible', false);
                break;
        }
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
            $variable = IPS_GetVariable($id);
            
            $Value = GetValue($id);
            
            $icon = "";
            
            // Debug-Ausgabe für Variable
            $objName = IPS_GetObject($id)['ObjectName'];
            
            // Vollständige Variable und Objekt Info
            $obj = IPS_GetObject($id);
        } catch (Exception $e) {
            return 'Transparent'; // Fallback bei Fehler
        }
        
        // Prüfe VariableCustomPresentation für Icon
        if ($icon == "" && !empty($variable['VariableCustomPresentation'])) {
            $customPresentation = $variable['VariableCustomPresentation'];
            
            // Zuerst nach direktem Icon suchen (Standard-Icon)
            if (isset($customPresentation['ICON']) && $customPresentation['ICON'] != "") {
                $icon = $customPresentation['ICON'];
            } elseif (isset($customPresentation['Icon']) && $customPresentation['Icon'] != "") {
                // Fallback für kleingeschriebenes 'icon' Schlüsselwort
                $icon = $customPresentation['Icon'];
            }
            
            // Prüfe auch direkt nach Icon-Feldern ohne PRESENTATION GUID (für einfache Darstellungen)
            if ($icon == "") {
                // SPECIAL: Für Boolean-Variablen mit ICON_TRUE/ICON_FALSE, prüfe USE_ICON_FALSE
                if ($variable['VariableType'] == 0 && (isset($customPresentation['ICON_TRUE']) || isset($customPresentation['ICON_FALSE']))) {
                    // Prüfe USE_ICON_FALSE direkt aus VariableCustomPresentation
                    $useIconFalse = true; // Default
                    if (isset($customPresentation['USE_ICON_FALSE'])) {
                        $useIconFalse = $customPresentation['USE_ICON_FALSE'];
                    } else {
                        // Fallback: Prüfe USE_ICON_FALSE aus PRESENTATION GUID falls vorhanden
                        if (isset($customPresentation['PRESENTATION']) && !empty($customPresentation['PRESENTATION'])) {
                            try {
                                $presentationGuid = trim($customPresentation['PRESENTATION'], '{}');
                                if (@IPS_PresentationExists($presentationGuid)) {
                                    $presentationData = IPS_GetPresentation($presentationGuid);
                                    if ($presentationData && is_string($presentationData)) {
                                        $presentationArray = json_decode($presentationData, true);
                                        if (isset($presentationArray['presentationParameters']['USE_ICON_FALSE'])) {
                                            $useIconFalse = $presentationArray['presentationParameters']['USE_ICON_FALSE'];
                                        }
                                    }
                                }
                            } catch (Exception $e) {
                            }
                        }
                    }
                    
                    // Icon basierend auf USE_ICON_FALSE wählen
                    if ($useIconFalse) {
                        // Wertbasierte Icon-Auswahl
                        $currentValue = GetValue($id);
                        $iconKey = $currentValue ? 'ICON_TRUE' : 'ICON_FALSE';
                    } else {
                        // Immer ICON_TRUE
                        $iconKey = 'ICON_TRUE';
                    }
                    
                    if (isset($customPresentation[$iconKey]) && $customPresentation[$iconKey] != "") {
                        $icon = $customPresentation[$iconKey];
                    }
                } else {
                    // Normale Icon-Feldsuche für Nicht-Boolean oder ohne ICON_TRUE/FALSE
                    $iconFields = ['ICON', 'Icon', 'icon'];
                    foreach ($iconFields as $field) {
                        if (isset($customPresentation[$field]) && $customPresentation[$field] != "") {
                            $icon = $customPresentation[$field];
                            break;
                        }
                    }
                }
            }
            
            // Nur wenn noch kein Standard-Icon gefunden wurde, prüfe PRESENTATION GUID und Associations
            if ($icon == "" && isset($customPresentation['PRESENTATION']) && !empty($customPresentation['PRESENTATION'])) {
                // PRESENTATION GUID Auflösung für Icons - REAKTIVIERT für Variable 37220
                $presentationGuid = trim($customPresentation['PRESENTATION'], '{}');
                
                // IPS_GetPresentation für GUID-Auflösung verwenden - mit Validation
                try {
                    
                    // GUID VALIDATION: Prüfe ob GUID im System existiert
                    if (@IPS_PresentationExists($presentationGuid)) {
                        $presentationData = IPS_GetPresentation($presentationGuid);
                    } else {
                        throw new Exception('GUID not registered in system');
                    }
                    
                    if ($presentationData && is_string($presentationData)) {
                        $presentationArray = json_decode($presentationData, true);
                        if ($presentationArray && is_array($presentationArray)) {
                            
                            // Boolean Variable: Icons sind in presentationParameters gespeichert
                            if ($variable['VariableType'] == 0) { // Boolean
                                if (isset($presentationArray['presentationParameters']) && is_array($presentationArray['presentationParameters'])) {
                                    $params = $presentationArray['presentationParameters'];
                                    $currentValue = GetValue($id);
                                    
                                    // CORRECT LOGIC: Prüfe USE_ICON_FALSE Flag
                                    $useIconFalse = isset($params['USE_ICON_FALSE']) ? $params['USE_ICON_FALSE'] : true;
                                    
                                    if ($useIconFalse) {
                                        // Verwende beide Icons basierend auf Wert
                                        $iconKey = $currentValue ? 'ICON_TRUE' : 'ICON_FALSE';
                                    } else {
                                        // Verwende immer ICON_TRUE
                                        $iconKey = 'ICON_TRUE';
                                    }
                                    
                                    if (isset($params[$iconKey]) && !empty($params[$iconKey])) {
                                        $icon = $params[$iconKey];
                                    }
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                }
                
                // ALTERNATIVE: Prüfe ob GetBooleanAssociations bereits Icons extrahiert hat
                if ($variable['VariableType'] == 0) { // Boolean Variable
                    $associations = $this->GetBooleanAssociations($id);
                    if (is_array($associations) && count($associations) > 0) {
                        $currentValue = GetValue($id);
                        foreach ($associations as $assoc) {
                            if (isset($assoc['value']) && $assoc['value'] == $currentValue && isset($assoc['icon']) && !empty($assoc['icon'])) {
                                $icon = $assoc['icon'];
                                break;
                            }
                        }
                        if ($icon == "") {
                        }
                    }
                }
            }
        }
        
        // Schließe VariableCustomPresentation if-Block (Zeile 1110)
        
        // Wenn noch kein Icon gefunden wurde, prüfe Darstellung/Visualisierung und Profile
        if ($icon == "") {
            // Zuerst prüfen ob die Variable eine neue Darstellung/Visualisierung hat
            if (function_exists('IPS_GetVariableVisualization')) {
                try {
                    $visualization = IPS_GetVariableVisualization($id);
                    if ($visualization && isset($visualization['ValueMappings'])) {
                        foreach ($visualization['ValueMappings'] as $mapping) {
                            if (isset($mapping['Value']) && $mapping['Value'] == $Value && isset($mapping['Icon']) && $mapping['Icon'] != "") {
                                $icon = $mapping['Icon'];
                                break;
                            }
                        }
                        
                        // Falls kein spezifisches Icon gefunden, verwende Default-Icon der Darstellung
                        if ($icon == "" && isset($visualization['Icon']) && $visualization['Icon'] != "") {
                            $icon = $visualization['Icon'];
                        }
                    }
                } catch (Exception $e) {
                }
            }
            
            // Fallback zu klassischen Variablenprofilen wenn kein Icon über Darstellung gefunden
            if ($icon == "") {
                $profile = $variable['VariableCustomProfile'] ?: $variable['VariableProfile'];
                if ($profile && IPS_VariableProfileExists($profile)) {
                    $p = IPS_GetVariableProfile($profile);
                    foreach ($p['Associations'] as $association) {
                        if (isset($association['Value']) && $association['Icon'] != "" && $association['Value'] == $Value) {
                            $icon = $association['Icon'];
                            break;
                        }
                    }

                    if ($icon == "" && isset($p['Icon']) && $p['Icon'] != "") {
                        $icon = $p['Icon'];
                    }
                }
            }
            
            // Finaler Fallback wenn nichts gefunden wurde
            if ($icon == "") {
                $icon = "Transparent";
            }
        }
        
        // Icon-Mapping zu FontAwesome durchführen
        $mappedIcon = $this->MapIconToFontAwesome($icon);
        
        return $mappedIcon;
    }
    
    /**
     * Lädt das Icon-Mapping aus der JSON-Datei
     */
    private function LoadIconMapping() {
        $mappingFile = __DIR__ . '/assets/iconMapping.json';
        
        if (file_exists($mappingFile)) {
            $json = file_get_contents($mappingFile);
            
            if ($json !== false) {
                $this->iconMapping = json_decode($json, true);
                
                if ($this->iconMapping === null) {
                    $this->iconMapping = [];
                }
            }
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
        }
        
        // Stelle sicher, dass das Icon Mapping immer geladen ist
        if ($this->iconMapping === null || empty($this->iconMapping)) {
            $this->LoadIconMapping();
        }
        
        // Versuche den Basis-Namen in der JSON-Mapping-Tabelle zu finden
        if ($this->iconMapping !== null && is_array($this->iconMapping) && !empty($this->iconMapping)) {
            if (isset($this->iconMapping[$baseName])) {
                $mappedName = $this->iconMapping[$baseName];
                
                // Wenn ursprünglich ein fa-Präfix vorhanden war, füge es wieder hinzu
                if ($hadFaPrefix && strpos($mappedName, 'fa-') !== 0) {
                    $mappedName = 'fa-' . $mappedName;
                }
                
                return $mappedName;
            } else {
                // Case-insensitive Fallback: Vergleiche alle Keys in Kleinbuchstaben
                $lowerKey = strtolower($baseName);
                foreach ($this->iconMapping as $key => $value) {
                    if (strtolower($key) === $lowerKey) {
                        return $value;
                    }
                }
            }
        }
        
        // Fallback zurück zum Original
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
        
        // Extrahiere Variable-Info
        $variable = IPS_GetVariable($variableId);
        

        $profileName = $variable['VariableProfile'] ?? 'NONE';
        $customPresentation = is_array($variable['VariableCustomPresentation'] ?? null) ? json_encode($variable['VariableCustomPresentation']) : ($variable['VariableCustomPresentation'] ?? 'NONE');

        
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
        
        return $colors;
    }
    
    /**
     * Extrahiert Min/Max-Werte aus Variablen-Profil oder Darstellung für Progress-Balken
     * Verwendet die gleiche Presentation-Hierarchie wie Icons für konsistente Behandlung
     * @param int $variableId Die Variable-ID
     * @return array Array mit 'min' und 'max' Werten
     */
    private function GetProgressMinMax($variableId) {
        $defaultMinMax = [
            'min' => 0,
            'max' => 100
        ];
        
        if (!IPS_VariableExists($variableId)) {
            return $defaultMinMax;
        }
        
        $variable = IPS_GetVariable($variableId);
        
        // Unterstütze INTEGER und FLOAT Variablen für Progress Bars
        if ($variable['VariableType'] !== VARIABLETYPE_INTEGER && $variable['VariableType'] !== VARIABLETYPE_FLOAT) {
            return $defaultMinMax;
        }
        
        // **PRESENTATION-HIERARCHIE wie bei Icons: Gleiche Taktik für konsistente Behandlung**
        
        // **FALL 1: Alte Variablenprofile (höchste Priorität wie bei Icons)**
        $profile = $variable['VariableCustomProfile'] ?: $variable['VariableProfile'];
        if (!empty($profile) && IPS_VariableProfileExists($profile)) {
            $profileData = IPS_GetVariableProfile($profile);
            
            if (isset($profileData['MinValue']) && isset($profileData['MaxValue'])) {
                $minMax = [
                    'min' => floatval($profileData['MinValue']),
                    'max' => floatval($profileData['MaxValue'])
                ];
                
                return $minMax;
            }
        }
        
        // **FALL 2: CustomPresentation mit direkten MIN/MAX Parametern**
        $customPresentation = isset($variable['VariableCustomPresentation']) ? $variable['VariableCustomPresentation'] : [];
        
        if (!empty($customPresentation)) {
            // Direkte MIN/MAX Parameter
            if (isset($customPresentation['MIN']) && isset($customPresentation['MAX'])) {
                if (is_numeric($customPresentation['MIN']) && is_numeric($customPresentation['MAX'])) {
                    $minMax = [
                        'min' => floatval($customPresentation['MIN']),
                        'max' => floatval($customPresentation['MAX'])
                    ];
                    
                    return $minMax;
                }
            }
            
            // **FALL 3: GUID-basierte Presentations (PRESENTATION)**
            if (isset($customPresentation['PRESENTATION']) && !empty($customPresentation['PRESENTATION'])) {
                $presentationGuid = $customPresentation['PRESENTATION'];
                
                try {
                    // GUID VALIDATION: Prüfe ob GUID im System existiert
                    if (@IPS_PresentationExists($presentationGuid)) {
                        $presentationData = IPS_GetPresentation($presentationGuid);
                    } else {
                        throw new Exception('GUID not registered in system');
                    }
                    
                    if ($presentationData && is_string($presentationData)) {
                        $presentationArray = json_decode($presentationData, true);
                        
                        if ($presentationArray && isset($presentationArray['MinValue']) && isset($presentationArray['MaxValue'])) {
                            $minMax = [
                                'min' => floatval($presentationArray['MinValue']),
                                'max' => floatval($presentationArray['MaxValue'])
                            ];
                            
                            return $minMax;
                        }
                    }
                } catch (Exception $e) {
                }
            }
            
            // **FALL 4: OPTIONS-basierte Presentations**
            if (isset($customPresentation['OPTIONS']) && !empty($customPresentation['OPTIONS'])) {
                $optionsGuid = $customPresentation['OPTIONS'];
                
                try {
                    if (@IPS_PresentationExists($optionsGuid)) {
                        $presentationData = IPS_GetPresentation($optionsGuid);
                        
                        if ($presentationData && is_string($presentationData)) {
                            $presentationArray = json_decode($presentationData, true);
                            
                            if ($presentationArray && isset($presentationArray['MinValue']) && isset($presentationArray['MaxValue'])) {
                                $minMax = [
                                    'min' => floatval($presentationArray['MinValue']),
                                    'max' => floatval($presentationArray['MaxValue'])
                                ];
                                
                                return $minMax;
                            }
                        }
                    }
                } catch (Exception $e) {
                    
                }
            }
            
            // **FALL 5: TEMPLATE-basierte Presentations**
            if (isset($customPresentation['TEMPLATE']) && !empty($customPresentation['TEMPLATE'])) {
                $templateGuid = $customPresentation['TEMPLATE'];
                
                try {
                    if (@IPS_PresentationExists($templateGuid)) {
                        $presentationData = IPS_GetPresentation($templateGuid);
                        
                        if ($presentationData && is_string($presentationData)) {
                            $presentationArray = json_decode($presentationData, true);
                            
                            if ($presentationArray && isset($presentationArray['MinValue']) && isset($presentationArray['MaxValue'])) {
                                $minMax = [
                                    'min' => floatval($presentationArray['MinValue']),
                                    'max' => floatval($presentationArray['MaxValue'])
                                ];
                                
                                return $minMax;
                            }
                        }
                    }
                } catch (Exception $e) {
                    
                }
            }
        }
        
        // **FALL 6: Fallback zu ObjectVisualization (wie bisher)**
        if (IPS_ObjectExists($variableId)) {
            $object = IPS_GetObject($variableId);            
            
            if (isset($object['ObjectVisualization']) && !empty($object['ObjectVisualization'])) {
                $visualization = json_decode($object['ObjectVisualization'], true);
                
                if (is_array($visualization)) {
                    // Erweiterte Suche nach Min/Max in allen möglichen Feldern
                    $possibleMinFields = ['MinValue', 'MinimalerWert', 'Minimum', 'Min', 'minValue', 'min'];
                    $possibleMaxFields = ['MaxValue', 'MaximalerWert', 'Maximum', 'Max', 'maxValue', 'max'];
                    
                    $foundMin = null;
                    $foundMax = null;
                    
                    // Suche alle möglichen Min-Felder
                    foreach ($possibleMinFields as $field) {
                        if (isset($visualization[$field]) && is_numeric($visualization[$field])) {
                            $foundMin = floatval($visualization[$field]);
                            break;
                        }
                    }
                    
                    // Suche alle möglichen Max-Felder
                    foreach ($possibleMaxFields as $field) {
                        if (isset($visualization[$field]) && is_numeric($visualization[$field])) {
                            $foundMax = floatval($visualization[$field]);
                            break;
                        }
                    }
                    
                    // Verwende gefundene Min/Max-Werte
                    if ($foundMin !== null && $foundMax !== null) {
                        $minMax = [
                            'min' => $foundMin,
                            'max' => $foundMax
                        ];
                        
                        return $minMax;
                    }
                    
                    // Fallback: Extrahiere Min/Max aus ValueMappings
                    if (isset($visualization['ValueMappings']) && is_array($visualization['ValueMappings'])) {
                        $values = [];
                        foreach ($visualization['ValueMappings'] as $mapping) {
                            if (isset($mapping['Value']) && is_numeric($mapping['Value'])) {
                                $values[] = floatval($mapping['Value']);
                            }
                        }
                        
                        if (count($values) > 0) {
                            $minMax = [
                                'min' => min($values),
                                'max' => max($values)
                            ];
                            
                            return $minMax;
                        }
                    }
                }
            }
        }
        
        // **LETZTER FALLBACK: Standard Min/Max verwenden**
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
                        }
                        
                        $associations[] = [
                            'value' => $normalizedValue, // Normalisierte Werte für Boolean-Variablen
                            'name' => $association['Name'],
                            'color' => isset($association['Color']) && $association['Color'] !== -1 ? '#' . sprintf('%06X', $association['Color']) : null,
                            'icon' => isset($association['Icon']) ? $association['Icon'] : null
                        ];
                    }
                }
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
                
                // Check USE_ICON_FALSE flag to determine icon behavior
                $useIconFalse = isset($customPresentation['USE_ICON_FALSE']) ? $customPresentation['USE_ICON_FALSE'] : true;
                
                $associations = [];
                // FALSE Association (Wert 0/false)
                if ($iconFalseSet) {
                    $associations[] = [
                        'value' => false,
                        'name' => 'Aus',
                        'color' => null,
                        'icon' => $useIconFalse ? $customPresentation['ICON_FALSE'] : null // Kein Icon wenn USE_ICON_FALSE=false
                    ];
                    $iconUsed = $useIconFalse ? $customPresentation['ICON_FALSE'] : 'NULL (USE_ICON_FALSE=false)';
                }
                // TRUE Association (Wert 1/true)
                if ($iconTrueSet) {
                    $associations[] = [
                        'value' => true,
                        'name' => 'An',
                        'color' => null,
                        'icon' => $useIconFalse ? $customPresentation['ICON_TRUE'] : null // Kein Icon wenn USE_ICON_FALSE=false
                    ];
                    $iconUsed = $useIconFalse ? $customPresentation['ICON_TRUE'] : 'NULL (USE_ICON_FALSE=false)';
                }
                if (!empty($associations)) {
                    return $associations;
                }
            }
            
                    
            // Prüfe auf presentationParameters in der customPresentation (beide Strukturen unterstützen)
            $params = null;
            
            // Prüfe zuerst auf direktes VariableProfile
            if (isset($variable['VariableProfile']) && !empty($variable['VariableProfile'])) {
                $profileName = $variable['VariableProfile'];
                $profile = @IPS_GetVariableProfile($profileName);
                if ($profile !== false && isset($profile['Associations'])) {
                    return $profile['Associations'];
                }
            }
        }
        
        // **STRING/INTEGER PRESENTATION GUID AUFLÖSUNG für Profile ohne direkte OPTIONS**
        if (($expectedVariableType === VARIABLETYPE_STRING || $expectedVariableType === VARIABLETYPE_INTEGER) && 
            isset($customPresentation['PRESENTATION']) && !empty($customPresentation['PRESENTATION'])) {
            $presentationGuid = trim($customPresentation['PRESENTATION'], '{}');
            
            try {
                if (function_exists('IPS_GetPresentation')) {
                    $presentationData = @IPS_GetPresentation($presentationGuid);
                    if ($presentationData !== false && !empty($presentationData)) {
                        if (isset($presentationData['Associations']) && is_array($presentationData['Associations'])) {
                            return $presentationData['Associations'];
                        }
                    }
                }
            } catch (Exception $e) {
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
    
    /**
     * Hilfsfunktion: Gibt das konfigurierte Standard-Bild zurück oder 'none' wenn nicht konfiguriert
     * @return string Asset-Name für das Standard-Bild oder 'none'
     */
    private function getDefaultImageOrNone() {
        $defaultImageId = $this->ReadPropertyInteger('DefaultImage');
        if ($defaultImageId > 0 && IPS_MediaExists($defaultImageId)) {
            return 'img_default_' . $defaultImageId;
        }
        return 'none';
    }
    
}
?>