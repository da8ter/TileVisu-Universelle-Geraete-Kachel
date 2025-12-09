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
if (!defined('VARIABLETYPE_FLOAT')) {
    define('VARIABLETYPE_FLOAT', 2);
}

// Ensure media message constants are defined (for older environments)
if (!defined('MM_CHANGEFILE')) {
    // IPS_BASE (10000) + IPS_MEDIAMESSAGE (900) + 3
    define('MM_CHANGEFILE', 10903);
}
if (!defined('MM_UPDATE')) {
    // IPS_BASE (10000) + IPS_MEDIAMESSAGE (900) + 5
    define('MM_UPDATE', 10905);
}

class UniversalDeviceTile extends IPSModule
{
    // Variablen-Zugriff und Status-Variable-ID
    
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
        $this->RegisterPropertyBoolean('StatusHide', false);
        $this->RegisterPropertyString('StatusLabel', '');
        $this->RegisterPropertyString('StatusAlignment', 'left');
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

        $this->RegisterAttributeString('HookToken', '');
        $this->RegisterAttributeString('LastVarValues', '{}');
        
        // Lade das Icon-Mapping
        $this->LoadIconMapping();
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
            $variable['ObjectDisplay'] = $this->getObjectDisplayForVariableRow($variable);
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
    
    private function getObjectDisplayForVariableRow($row)
    {
        $displayType = $row['DisplayType'] ?? 'text';
        if ($displayType === 'image') {
            $mid = intval($row['ImageMedia'] ?? 0);
            if ($mid > 0 && IPS_MediaExists($mid)) {
                $obj = IPS_GetObject($mid);
                $name = isset($obj['ObjectName']) ? $obj['ObjectName'] : '';
                $parentName = '';
                if (isset($obj['ParentID']) && $obj['ParentID'] > 0 && IPS_ObjectExists($obj['ParentID'])) {
                    $parent = IPS_GetObject($obj['ParentID']);
                    $parentName = isset($parent['ObjectName']) ? $parent['ObjectName'] : '';
                }
                if ($name !== '' && $parentName !== '') {
                    return $name . ' (' . $parentName . ')';
                }
                return $name;
            }
            return '';
        }
        // OpenObject-Buttons: OpenObjectId-Namen anzeigen (hat Priorität)
        if (($displayType === 'button') && isset($row['OpenObjectId'])) {
            $openObjectId = intval($row['OpenObjectId']);
            if ($openObjectId > 1 && IPS_ObjectExists($openObjectId)) {
                $obj = IPS_GetObject($openObjectId);
                $name = isset($obj['ObjectName']) ? $obj['ObjectName'] : '';
                $parentName = '';
                if (isset($obj['ParentID']) && $obj['ParentID'] > 0 && IPS_ObjectExists($obj['ParentID'])) {
                    $parent = IPS_GetObject($obj['ParentID']);
                    $parentName = isset($parent['ObjectName']) ? $parent['ObjectName'] : '';
                }
                if ($name !== '' && $parentName !== '') {
                    return $name . ' (' . $parentName . ')';
                }
                return $name;
            }
        }
        // Script-Buttons ohne Variable: Script-Namen anzeigen
        if (($displayType === 'button') && isset($row['ScriptID'])) {
            $scriptId = intval($row['ScriptID']);
            if ($scriptId > 0 && function_exists('IPS_ScriptExists') && IPS_ScriptExists($scriptId)) {
                $obj = IPS_GetObject($scriptId);
                $name = isset($obj['ObjectName']) ? $obj['ObjectName'] : '';
                $parentName = '';
                if (isset($obj['ParentID']) && $obj['ParentID'] > 0 && IPS_ObjectExists($obj['ParentID'])) {
                    $parent = IPS_GetObject($obj['ParentID']);
                    $parentName = isset($parent['ObjectName']) ? $parent['ObjectName'] : '';
                }
                if (!empty($row['Label'])) {
                    $name = $row['Label'];
                }
                if ($name !== '' && $parentName !== '') {
                    return $name . ' (' . $parentName . ')';
                }
                return $name;
            }
        }
        $vid = intval($row['Variable'] ?? 0);
        if ($vid > 0 && IPS_VariableExists($vid)) {
            $obj = IPS_GetObject($vid);
            $name = isset($obj['ObjectName']) ? $obj['ObjectName'] : '';
            $parentName = '';
            if (isset($obj['ParentID']) && $obj['ParentID'] > 0 && IPS_ObjectExists($obj['ParentID'])) {
                $parent = IPS_GetObject($obj['ParentID']);
                $parentName = isset($parent['ObjectName']) ? $parent['ObjectName'] : '';
            }
            if ($name !== '' && $parentName !== '') {
                return $name . ' (' . $parentName . ')';
            }
            return $name;
        }
        return '';
    }
    
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        
        
        // Stelle sicher, dass das Icon-Mapping geladen ist
        $this->LoadIconMapping();

        // WebHook für Bildauslieferung registrieren
        $this->RegisterUDTImageHook('/hook/udtimages/' . $this->InstanceID);

        // Dynamische Referenzen und Nachrichten für konfigurierte Variablen
        $variablesList = json_decode($this->ReadPropertyString('VariablesList'), true);
        

        // Sammle alle Variablen-IDs
        $ids = [$this->ReadPropertyInteger('bgImage')];
        // Sammle relevante Medienobjekte für Referenzen & Nachrichten (Default + Custom Images + Hintergrund)
        $mediaIds = [];
        
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
                // Sammle Medien für DisplayType=image
                if ((($variable['DisplayType'] ?? 'text') === 'image')) {
                    $imageId = intval($variable['ImageMedia'] ?? 0);
                    if ($imageId > 0 && IPS_MediaExists($imageId)) {
                        $mediaIds[] = $imageId;
                    }
                }
            }
        }

        // DefaultImage referenzieren und für Medien-Events vormerken
        $defaultImageId = $this->ReadPropertyInteger('DefaultImage');
        if ($defaultImageId > 0 && IPS_MediaExists($defaultImageId)) {
            $ids[] = $defaultImageId;
            $mediaIds[] = $defaultImageId;
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

        // Registriere Nachrichten für Medien-Objekte (Inhalte geändert)
        foreach (array_unique($mediaIds) as $mid) {
            if ($mid > 0) {
                // Aktualisierung und Dateiänderung beobachten
                $this->RegisterMessage($mid, MM_UPDATE);
                $this->RegisterMessage($mid, MM_CHANGEFILE);
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
        if (is_array($profilAssoziazionen)) {
            foreach ($profilAssoziazionen as $assoziation) {
                $bildauswahl = $assoziation['Bildauswahl'] ?? 'none';
                
                if ($bildauswahl === 'custom' && isset($assoziation['EigenesBild']) && $assoziation['EigenesBild'] > 0) {
                    $mediaId = (int)$assoziation['EigenesBild'];
                    if ($mediaId > 0 && IPS_MediaExists($mediaId)) {
                        $assets['img_custom_' . $mediaId] = $this->BuildImageHookUrl($mediaId);
                    }
                } elseif (in_array($bildauswahl, ['wm_an', 'wm_aus', 'dryer_on', 'dryer_off'])) {
                    $assets['img_' . $bildauswahl] = $this->BuildAssetHookUrl($bildauswahl);
                }
            }
        }
        // Standard-Bild optional als Hook-URL mappen (nur für Konsistenz, Status nutzt ohnehin statusImageUrl)
        $defaultImageId = $this->ReadPropertyInteger('DefaultImage');
        if ($defaultImageId > 0 && IPS_MediaExists($defaultImageId)) {
            $assets['img_default_' . $defaultImageId] = $this->BuildImageHookUrl($defaultImageId);
        }

        // Fallback: Wenn keine Statusvariable konfiguriert ist und noch kein img_wm_an Asset vorhanden, 
        // mappe Standard-Waschmaschinen-Asset als Fallback (WebHook)
        if ($needsFallbackAssets && !isset($assets['img_wm_an'])) {
            $assets['img_wm_an'] = $this->BuildAssetHookUrl('wm_an');
        }
        
        
        return $assets;
    }

    private function RegisterUDTImageHook(string $hookPath): void
    {
        $webhookModuleId = '{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}';
        $ids = @IPS_GetInstanceListByModuleID($webhookModuleId);
        if (!is_array($ids) || count($ids) === 0) {
            $this->LogMessage('WebHook Control not found. Skipping hook registration.', KL_WARNING);
            return;
        }
        $whId = $ids[0];
        $instToken = $this->ReadAttributeString('HookToken');
        if ($instToken === '') {
            try {
                $instToken = bin2hex(random_bytes(16));
            } catch (Throwable $e) {
                $instToken = substr(sha1(uniqid('', true)), 0, 32);
            }
            $this->WriteAttributeString('HookToken', $instToken);
        }

        $hooks = @json_decode(IPS_GetProperty($whId, 'Hooks'), true);
        if (!is_array($hooks)) {
            $hooks = [];
        }
        $found = false;
        foreach ($hooks as &$h) {
            if (isset($h['Hook']) && $h['Hook'] === $hookPath) {
                $h['TargetID'] = $this->InstanceID;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $hooks[] = [
                'Hook' => $hookPath,
                'TargetID' => $this->InstanceID
            ];
        }
        IPS_SetProperty($whId, 'Hooks', json_encode($hooks));
        IPS_ApplyChanges($whId);
    }

    

    private function BuildImageHookUrl(int $mediaId): string
    {
        $base = '/hook/udtimages/' . $this->InstanceID;
        $token = $this->ReadAttributeString('HookToken');
        if ($mediaId > 0) {
            $q = 'mid=' . (int)$mediaId;
        } else {
            $q = 'placeholder=1&ts=' . time();
        }
        if ($token !== '') {
            $q .= '&token=' . rawurlencode($token);
        }
        return $base . '?' . $q;
    }

    private function BuildAssetHookUrl(string $name): string
    {
        $safe = preg_replace('/[^a-z0-9_\-]/i', '', $name);
        $token = $this->ReadAttributeString('HookToken');
        $base = '/hook/udtimages/' . $this->InstanceID;
        $q = 'asset=' . $safe;
        if ($token !== '') {
            $q .= '&token=' . rawurlencode($token);
        }
        return $base . '?' . $q;
    }

    protected function ProcessHookData()
    {
        $assetsDir = __DIR__ . '/assets';
        $placeholder = __DIR__ . '/../imgs/transparent.webp';
        $instT = $this->ReadAttributeString('HookToken');
        $token = isset($_GET['token']) ? (string)$_GET['token'] : '';
        if (!is_string($token) || $token === '' || $instT === '' || !hash_equals($instT, $token)) {
            http_response_code(403);
            return;
        }
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) { ob_end_clean(); }
        }
        header('X-Accel-Buffering: no');
        if (function_exists('ignore_user_abort')) { ignore_user_abort(true); }
        if (function_exists('set_time_limit')) { @set_time_limit(0); }
        $MAX_SIZE = 5 * 1024 * 1024;
        $detectMime = function(string $bin) {
            $hdr = substr($bin, 0, 12);
            if (strncmp($hdr, "\xFF\xD8\xFF", 3) === 0) return 'image/jpeg';
            if (strncmp($hdr, "\x89PNG\x0D\x0A\x1A\x0A", 8) === 0) return 'image/png';
            if (strncmp($hdr, 'GIF87a', 6) === 0 || strncmp($hdr, 'GIF89a', 6) === 0) return 'image/gif';
            if (substr($hdr, 0, 4) === 'RIFF' && substr($hdr, 8, 4) === 'WEBP') return 'image/webp';
            if (strncmp($hdr, 'BM', 2) === 0) return 'image/bmp';
            return 'application/octet-stream';
        };
        $sendNotModified = function(string $etag = '', int $lastMod = 0) {
            $ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
            $ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : 0;
            if ($etag !== '' && $ifNoneMatch === $etag) {
                header('ETag: ' . $etag);
                http_response_code(304);
                exit;
            }
            if ($lastMod > 0 && $ifModifiedSince && $ifModifiedSince >= $lastMod) {
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastMod) . ' GMT');
                http_response_code(304);
                exit;
            }
        };
        $streamFile = function(string $file, string $mime, bool $longCache = false) use ($MAX_SIZE, $sendNotModified) {
            if (!is_file($file) || !is_readable($file)) {
                http_response_code(404);
                IPS_LogMessage('UDTImagesHook', 'File not found or unreadable: ' . $file);
                exit;
            }
            $size = filesize($file);
            if ($size === false) {
                http_response_code(500);
                IPS_LogMessage('UDTImagesHook', 'filesize failed: ' . $file);
                exit;
            }
            if ($size > $MAX_SIZE) {
                http_response_code(413);
                IPS_LogMessage('UDTImagesHook', 'File too large: ' . $size);
                exit;
            }
            $mtime = filemtime($file) ?: time();
            $etag = 'W/"' . dechex($size) . '-' . dechex($mtime) . '"';
            if ($longCache) {
                header('Cache-Control: public, max-age=31536000, immutable');
            } else {
                header('Cache-Control: public, max-age=0, must-revalidate');
            }
            header('ETag: ' . $etag);
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
            $sendNotModified($etag, $mtime);
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . $size);
            $fh = fopen($file, 'rb');
            if ($fh === false) {
                http_response_code(500);
                IPS_LogMessage('UDTImagesHook', 'fopen failed: ' . $file);
                exit;
            }
            $chunk = 65536;
            while (!feof($fh)) {
                $buf = fread($fh, $chunk);
                if ($buf === false) { break; }
                echo $buf;
                flush();
            }
            fclose($fh);
            exit;
        };
        $mid = isset($_GET['mid']) ? (int)$_GET['mid'] : 0;
        if ($mid > 0 && IPS_MediaExists($mid)) {
            $m = IPS_GetMedia($mid);
            if ($m['MediaType'] === MEDIATYPE_IMAGE) {
                if (!empty($m['MediaFile']) && is_file($m['MediaFile'])) {
                    $fh = fopen($m['MediaFile'], 'rb');
                    if ($fh !== false) {
                        $hdr = fread($fh, 12);
                        fclose($fh);
                        $mime = $detectMime($hdr ?: '');
                        $streamFile($m['MediaFile'], $mime, false);
                    }
                }
                $b64 = IPS_GetMediaContent($mid);
                if (!is_string($b64) || $b64 === '') {
                    http_response_code(404);
                    IPS_LogMessage('UDTImagesHook', 'Empty media content mid=' . $mid);
                    return;
                }
                $prefixSample = base64_decode(substr($b64, 0, 24), true);
                $mime = $detectMime($prefixSample ?: '');
                $len = strlen($b64);
                $padding = 0;
                if ($len >= 2 && substr($b64, -2) === '==') { $padding = 2; }
                elseif ($len >= 1 && substr($b64, -1) === '=') { $padding = 1; }
                $decodedLen = (int)floor($len / 4) * 3 - $padding;
                if ($decodedLen > $MAX_SIZE) {
                    http_response_code(413);
                    IPS_LogMessage('UDTImagesHook', 'Media too large mid=' . $mid . ' size=' . $decodedLen);
                    return;
                }
                header('Cache-Control: no-store');
                header('Content-Type: ' . $mime);
                header('Content-Length: ' . max(0, $decodedLen));
                $out = fopen('php://output', 'wb');
                if ($out === false) {
                    http_response_code(500);
                    IPS_LogMessage('UDTImagesHook', 'open php://output failed');
                    return;
                }
                $filter = stream_filter_append($out, 'convert.base64-decode', STREAM_FILTER_WRITE);
                $buffer = '';
                $chunkSize = 65536;
                for ($i = 0; $i < $len; $i += $chunkSize) {
                    $chunk = substr($b64, $i, $chunkSize);
                    $buffer .= $chunk;
                    $toWrite = strlen($buffer) - (strlen($buffer) % 4);
                    if ($toWrite > 0) {
                        fwrite($out, substr($buffer, 0, $toWrite));
                        $buffer = substr($buffer, $toWrite);
                        flush();
                    }
                }
                if ($buffer !== '') {
                    fwrite($out, $buffer);
                }
                if (is_resource($filter)) { stream_filter_remove($filter); }
                fclose($out);
                return;
            }
        }
        $asset = isset($_GET['asset']) ? preg_replace('/[^a-z0-9_\-]/i', '', (string)$_GET['asset']) : '';
        if ($asset !== '') {
            // Support aliasing for legacy/localized filenames
            // dryer_on  -> trockner_an
            // dryer_off -> trockner_aus
            $namesToTry = [$asset];
            if ($asset === 'dryer_on') {
                $namesToTry[] = 'trockner_an';
            } elseif ($asset === 'dryer_off') {
                $namesToTry[] = 'trockner_aus';
            }
            foreach ($namesToTry as $name) {
                $candidates = [
                    $assetsDir . '/' . $name . '.webp',
                    $assetsDir . '/' . $name . '.png',
                    $assetsDir . '/' . $name . '.jpg',
                    $assetsDir . '/' . $name . '.jpeg',
                    $assetsDir . '/' . $name . '.gif',
                ];
                foreach ($candidates as $file) {
                    if (is_file($file)) {
                        $fh = fopen($file, 'rb');
                        if ($fh === false) { continue; }
                        $hdr = fread($fh, 12);
                        fclose($fh);
                        $mime = $detectMime($hdr ?: '');
                        $streamFile($file, $mime, true);
                    }
                }
            }
        }
        if (isset($_GET['placeholder']) && is_file($placeholder)) {
            $fh = fopen($placeholder, 'rb');
            if ($fh !== false) { $hdr = fread($fh, 12); fclose($fh); }
            $mime = $detectMime(isset($hdr) ? $hdr : '');
            $streamFile($placeholder, $mime, true);
        }
        http_response_code(404);
        IPS_LogMessage('UDTImagesHook', 'Not found');
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        // ...
        $statusId = $this->ReadPropertyInteger('Status');
        if ($statusId > 0 && $SenderID === $statusId) {
            switch ($Message) {
            case VM_UPDATE:
                $last = json_decode($this->ReadAttributeString('LastVarValues'), true);
                if (!is_array($last)) { $last = []; }
                $cur = GetValue($SenderID);
                $k = strval($SenderID);
                if (array_key_exists($k, $last) && $last[$k] === $cur) { break; }
                $last[$k] = $cur;
                $this->WriteAttributeString('LastVarValues', json_encode($last));
                // Status-Änderung: Sende minimalen Status-Update-Payload
                $fullMessage = $this->GetFullUpdateMessage();
                $fullArray = json_decode($fullMessage, true);
                $minimal = $this->buildMinimalStatusUpdate($fullArray);
                if (!empty($minimal)) {
                    $this->UpdateVisualizationValue(json_encode($minimal));
                    // WICHTIG: Variablen neu schicken, damit progressbarActive/Inaktiv sofort wirkt
                    if (isset($fullArray['variables']) && is_array($fullArray['variables'])) {
                        $this->UpdateVisualizationValue(json_encode(['variables' => $fullArray['variables']]));
                    }
                } else {
                    // Fallback: vollständige Nachricht
                    $this->UpdateVisualizationValue($fullMessage);
                }
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
                            $last = json_decode($this->ReadAttributeString('LastVarValues'), true);
                            if (!is_array($last)) { $last = []; }
                            $cur = GetValue($SenderID);
                            $k = strval($SenderID);
                            if (array_key_exists($k, $last) && $last[$k] === $cur) { break; }
                            $last[$k] = $cur;
                            $this->WriteAttributeString('LastVarValues', json_encode($last));
                            // Variable-Änderung: Sende minimalen var_<index> und var_<index>_value Payload
                            $fullMessage = $this->GetFullUpdateMessage();
                            $fullArray = json_decode($fullMessage, true);
                            $minimal = $this->buildMinimalVarUpdateForVariable($fullArray, $SenderID);
                            if (!empty($minimal)) {
                                // Split into two messages like Wallbox: formatted first, then rawValue
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
                            } else {
                                // Fallback: vollständige Nachricht
                                $this->UpdateVisualizationValue($fullMessage);
                            }
                            break;
                    }
                }
                // Prüfe SecondVariable
                elseif (isset($variable['SecondVariable']) && $SenderID === $variable['SecondVariable']) {
                    switch ($Message) {
                        case VM_UPDATE:
                            $last = json_decode($this->ReadAttributeString('LastVarValues'), true);
                            if (!is_array($last)) { $last = []; }
                            $cur = GetValue($SenderID);
                            $k = strval($SenderID);
                            if (array_key_exists($k, $last) && $last[$k] === $cur) { break; }
                            $last[$k] = $cur;
                            $this->WriteAttributeString('LastVarValues', json_encode($last));
                            // SecondVariable-Änderung: Sende minimalen Update-Payload für die zugehörige Progress-Variable
                            $fullMessage = $this->GetFullUpdateMessage();
                            $fullArray = json_decode($fullMessage, true);
                            $minimal = $this->buildMinimalVarUpdateForSecondVariable($fullArray, $SenderID);
                            if (!empty($minimal)) {
                                // Split into two messages like Wallbox: formatted first, then rawValue
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
                            } else {
                                // Fallback: vollständige Nachricht
                                $this->UpdateVisualizationValue($fullMessage);
                            }
                            break;
                    }
                }
                
            }
        }

        // Medien-Änderungen (DefaultImage, Custom Images aus Assoziationen, Hintergrundbild)
        if ($Message === MM_UPDATE || $Message === MM_CHANGEFILE) {
            // Aktualisiere Assets (img_custom_*, img_default_* sowie vorkonfigurierte Assets)
            try {
                $assets = $this->GenerateAssets();
                if (!empty($assets)) {
                    $this->UpdateVisualizationValue(json_encode(['assets' => $assets]));
                }
            } catch (Exception $e) {
                // ignore
            } catch (Error $e) {
                // ignore
            }

            // Minimal-Updates für Variablen mit DisplayType=image, deren ImageMedia dieses Media ist
            try {
                $variablesList = json_decode($this->ReadPropertyString('VariablesList'), true);
                if (is_array($variablesList) && $SenderID > 0) {
                    $imageVarUpdates = [];
                    foreach ($variablesList as $idx => $variable) {
                        if ((($variable['DisplayType'] ?? 'text') === 'image')) {
                            $imageId = intval($variable['ImageMedia'] ?? 0);
                            if ($imageId === $SenderID) {
                                // Bestimme DOM-ID: mit Variable-ID falls vorhanden, sonst 'image_<index>'
                                $domId = isset($variable['Variable']) && $variable['Variable'] > 0
                                    ? strval($variable['Variable'])
                                    : ('image_' . $idx);

                                $imageVarUpdates[] = [
                                    'id' => $domId,
                                    'imageUrl' => $this->BuildImageHookUrl($imageId)
                                ];
                            }
                        }
                    }
                    if (!empty($imageVarUpdates)) {
                        $this->UpdateVisualizationValue(json_encode(['imageVarUpdate' => $imageVarUpdates]));
                    }
                }
            } catch (Exception $e) {
                // ignore
            } catch (Error $e) {
                // ignore
            }

            // Falls das Hintergrundbild betroffen ist: Aktualisiere image1Url separat
            $bgImageId = $this->ReadPropertyInteger('bgImage');
            if ($SenderID === $bgImageId && $bgImageId > 0 && IPS_MediaExists($bgImageId)) {
                $image = IPS_GetMedia($bgImageId);
                if ($image['MediaType'] === MEDIATYPE_IMAGE) {
                    $this->UpdateVisualizationValue(json_encode(['image1Url' => $this->BuildImageHookUrl($bgImageId)]));
                }
            }
            return; // nichts weiter zu tun
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
        
        // Script-Buttons unterstützen: Ident kann 'script_<ID>' sein → Script ausführen
        if (is_string($Ident) && strpos($Ident, 'script_') === 0) {
            $scriptId = intval(substr($Ident, 7));
            if ($scriptId > 0 && function_exists('IPS_ScriptExists') && IPS_ScriptExists($scriptId)) {
                @IPS_RunScript($scriptId);
            }
            return;
        }
        
        // Nachrichten von der HTML-Darstellung schicken immer den Ident passend zur Eigenschaft und im Wert die Differenz, welche auf die Variable gerechnet werden soll
    $variableID = $Ident;
    if (!IPS_VariableExists($variableID)) {
        // Falls eine Script-ID direkt gesendet wurde (z. B. numerisch), führe Script aus
        $maybeScriptId = intval($Ident);
        if ($maybeScriptId > 0 && function_exists('IPS_ScriptExists') && IPS_ScriptExists($maybeScriptId)) {
            @IPS_RunScript($maybeScriptId);
        }
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
    } else if ($variableType === VARIABLETYPE_FLOAT) {
        $newValue = floatval($value);
        $bounds = $this->GetProgressMinMax($variableID);
        $minV = isset($bounds['min']) ? floatval($bounds['min']) : 0.0;
        $maxV = isset($bounds['max']) ? floatval($bounds['max']) : 100.0;
        if ($maxV < $minV) { $tmp = $minV; $minV = $maxV; $maxV = $tmp; }
        $cfg = $this->GetSliderStepAndDigits($variableID);
        $step = isset($cfg['step']) ? floatval($cfg['step']) : 0.0;
        $digits = isset($cfg['digits']) ? intval($cfg['digits']) : 0;
        $range = $maxV - $minV;
        if ($range <= 0) { $minV = 0.0; $maxV = 100.0; $range = 100.0; }
        if ($step <= 0) {
            if ($digits > 0) {
                $step = pow(10, -$digits);
            } else {
                $step = $range / 100.0;
            }
        }
        $ratio = ($newValue - $minV) / $step;
        $rounded = round($ratio);
        $newValue = $minV + ($rounded * $step);
        if ($digits >= 0) { $newValue = floatval(number_format($newValue, $digits, '.', '')); }
        if ($newValue < $minV) $newValue = $minV;
        if ($newValue > $maxV) $newValue = $maxV;
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
        $initialHandling = '<script>handleMessage(' . $this->GetFullUpdateMessage() . ')</script>';
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
                foreach ($profilAssoziationen as $assoziation) {
                    if (isset($assoziation['AssoziationValue']) && $assoziation['AssoziationValue'] == $currentValue) {
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
                        break;
                    }
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
                            // Für Boolean: immer Associations aus Profil/Presentation ermitteln (Fallback intern geregelt)
                            $variableAssociations = $this->GetBooleanAssociations($variable['Variable']);
                        } else {
                            $variableAssociations = null;
                        }
                    } else {
                        $variableAssociations = null;
                    }
                    
                    // Extrahiere Min/Max-Werte aus Variablenprofil für Progress-Balken
                    $progressMinMax = $this->GetProgressMinMax($variable['Variable']);
                    
                    // Ermittle Progressbar Active Status basierend auf ProfilAssoziationen
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
                    
                    if (!$progressbarActive && (($variable['DisplayType'] ?? 'text') === 'progress')) {
                        // Progressbar deaktiviert: Zeige "-" für alle Werte und mache Text/Icon 50% transparent
                        $finalRawValue = 0;
                        $finalFormattedValue = '-';
                    }
                    
                    $variableData = [
                        'id' => $variable['Variable'],
                        'label' => $label,
                        'displayType' => $variable['DisplayType'] ?? 'text',
                        'variableType' => $variableInfo['VariableType'], // Für Button-Validierung
                        'group' => $variable['Group'] ?? 'keine Gruppe', // Group-Information für Frontend-Gruppierung
                        'showIcon' => $variable['ShowIcon'],
                        'showLabel' => $variable['ShowLabel'] ?? true,
                        'showValue' => $variable['ShowValue'] ?? true,
                        'fontSize' => $variable['FontSize'] ?? 12,
                        'textColor' => isset($variable['TextColor']) ? '#' . sprintf('%06X', $variable['TextColor']) : '#000000',
                        'isTextColorTransparent' => isset($variable['TextColor']) && ($variable['TextColor'] == -1 || $variable['TextColor'] == 16777215),
                        'progressColor1' => isset($variable['ProgressColor1']) ? '#' . sprintf('%06X', $variable['ProgressColor1']) : '#4CAF50',
                        'progressColor2' => isset($variable['ProgressColor2']) ? '#' . sprintf('%06X', $variable['ProgressColor2']) : '#2196F3',
                        // Slider-spezifische Farben (separat von Progress)
                        'sliderColor1' => isset($variable['SliderColor1']) ? '#' . sprintf('%06X', $variable['SliderColor1']) : (isset($variable['ProgressColor1']) ? '#' . sprintf('%06X', $variable['ProgressColor1']) : '#4CAF50'),
                        'sliderColor2' => isset($variable['SliderColor2']) ? '#' . sprintf('%06X', $variable['SliderColor2']) : (isset($variable['ProgressColor2']) ? '#' . sprintf('%06X', $variable['ProgressColor2']) : '#2196F3'),
                        'boolButtonColor' => isset($variable['boolButtonColor']) ? '#' . sprintf('%06X', $variable['boolButtonColor']) : '#CCCCCC',
                        'isBoolButtonColorTransparent' => isset($variable['boolButtonColor']) && ($variable['boolButtonColor'] == -1 || $variable['boolButtonColor'] == 16777215),
                        'buttonWidth' => $variable['ButtonWidth'] ?? 120,
                        'showBorderLine' => $variable['ShowBorderLine'] ?? false,
                        'alignment' => $variable['VerticalAlignment'] ?? 'left',
                        'progressMin' => $progressMinMax['min'],
                        'progressMax' => $progressMinMax['max'],
                        'formattedValue' => $finalFormattedValue, // Backend-überschriebener Wert
                        'rawValue' => $finalRawValue, // Backend-überschriebener Wert
                        'icon' => $icon,
                        'progressbarActive' => $progressbarActive, // Progressbar Active Status
                        'progressbarInactive' => (!$progressbarActive && (($variable['DisplayType'] ?? 'text') === 'progress')), // 50% Transparenz Flag nur für Progress
                        'useSecondVariableAsTarget' => (bool)($variable['UseSecondVariableAsTarget'] ?? false),
                        'variableAssociations' => $variableAssociations, // Variable-Associations für Button-Erstellung (Integer + String)
                        'scriptId' => intval($variable['ScriptID'] ?? 0),
                        'openObjectId' => intval($variable['OpenObjectId'] ?? 0),
                    ];

                    // Bild (Symcon Medienobjekt) als eigene Darstellungsart
                    if (($variable['DisplayType'] ?? 'text') === 'image') {
                        $imageId = intval($variable['ImageMedia'] ?? 0);
                        $variableData['imageMediaId'] = $imageId;
                        $variableData['imageUrl'] = $this->BuildImageHookUrl($imageId);
                        // Image width now in percent (1-100), default 40
                        $variableData['imageWidth'] = max(1, min(100, intval($variable['ImageWidth'] ?? 40)));
                        $variableData['imageBorderRadius'] = intval($variable['ImageBorderRadius'] ?? 6);
                    }
                    // Slider Zusatzdaten
                    if (($variable['DisplayType'] ?? 'text') === 'slider') {
                        $sd = $this->GetSliderStepAndDigits($variable['Variable']);
                        $variableData['sliderStep'] = isset($sd['step']) ? $sd['step'] : null;
                        $variableData['sliderDigits'] = isset($sd['digits']) ? $sd['digits'] : null;
                    }
                    
                    // Vorbereitung für SecondVariable als Marker
                    $useSecondAsTarget = !empty($variable['UseSecondVariableAsTarget']);
                    $secondPresent = false; $secondId = 0; $secondLabel = ''; $secondFinalRawValue = 0; $secondFinalFormattedValue = '';

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
                            // Progressbar deaktiviert: Zeige "-" für zweite Variable und mache Text/Icon 50% transparent
                            $secondFinalRawValue = 0;
                            $secondFinalFormattedValue = '-';
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

                        // Für Marker-Nutzung merken
                        $secondPresent = true;
                        $secondId = (int)$variable['SecondVariable'];
                        // Werte bereits in $secondFinal* enthalten
                    }
                    // Marker nur aus SecondVariable, wenn konfiguriert
                    if (($variable['DisplayType'] ?? 'text') === 'progress') {
                        if ($useSecondAsTarget && $secondPresent) {
                            $variableData['progressTarget'] = [
                                'id' => $secondId,
                                'label' => $secondLabel,
                                'formattedValue' => $secondFinalFormattedValue,
                                'rawValue' => $secondFinalRawValue
                            ];
                        }
                    }
                    // Spezielle Behandlung für Zeitwerte entfernt (nicht verwendet)
                    
                    $variables[] = $variableData;
                } else if ((($variable['DisplayType'] ?? 'text') === 'image')) {
                    // SUPPORT IMAGE ROWS WITHOUT A VARIABLE ID
                    $imageId = intval($variable['ImageMedia'] ?? 0);
                    // Label-Fallback
                    $label = $variable['Label'] ?? '';
                    // Alignment/Textfarbe
                    $textColor = isset($variable['TextColor']) ? '#' . sprintf('%06X', $variable['TextColor']) : '#000000';
                    $isTextColorTransparent = isset($variable['TextColor']) && ($variable['TextColor'] == -1 || $variable['TextColor'] == 16777215);
                    $variables[] = [
                        'id' => 'image_' . $index, // synthetische ID
                        'label' => $label,
                        'displayType' => 'image',
                        'group' => $variable['Group'] ?? 'keine Gruppe',
                        'showGroupName' => $variable['ShowGroupName'] ?? false,
                        'showIcon' => false,
                        'showLabel' => false,
                        'showValue' => false,
                        'fontSize' => $variable['FontSize'] ?? 12,
                        'textColor' => $textColor,
                        'isTextColorTransparent' => $isTextColorTransparent,
                        'alignment' => $variable['VerticalAlignment'] ?? 'left',
                        'imageMediaId' => $imageId,
                        'imageUrl' => $this->BuildImageHookUrl($imageId),
                        // Image width now in percent (1-100), default 40
                        'imageWidth' => max(1, min(100, intval($variable['ImageWidth'] ?? 40))),
                        'imageBorderRadius' => intval($variable['ImageBorderRadius'] ?? 6),
                        'showBorderLine' => false,
                        // folgende Felder für API-Konsistenz, ohne Relevanz
                        'variableType' => 3,
                        'progressbarActive' => true,
                        'progressbarInactive' => false,
                    ];
                } else if ((($variable['DisplayType'] ?? 'text') === 'button') && intval($variable['ScriptID'] ?? 0) > 0) {
                    // SUPPORT BUTTON ROWS WITHOUT A VARIABLE ID: Script-Button (stateless)
                    $scriptId = intval($variable['ScriptID']);
                    // Label ermitteln
                    $label = $variable['Label'] ?? '';
                    // Wenn kein eigenes Label gesetzt ist, verwende den Skript-Namen
                    if ($label === '' && IPS_ScriptExists($scriptId)) {
                        $obj = IPS_GetObject($scriptId);
                        if (isset($obj['ObjectName'])) {
                            $label = $obj['ObjectName'];
                        }
                    }
                    // Script-Objekt-Icon (falls gesetzt) mappen
                    $scriptIcon = '';
                    try {
                        if (IPS_ScriptExists($scriptId)) {
                            $obj = IPS_GetObject($scriptId);
                            $objIcon = isset($obj['ObjectIcon']) ? trim($obj['ObjectIcon']) : '';
                            if ($objIcon !== '') {
                                $scriptIcon = $this->MapIconToFontAwesome($objIcon);
                            }
                        }
                    } catch (Exception $e) { /* ignore */ }
                    // Farben/Styles wie bei anderen Buttons
                    $textColor = isset($variable['TextColor']) ? '#' . sprintf('%06X', $variable['TextColor']) : '#000000';
                    $isTextColorTransparent = isset($variable['TextColor']) && ($variable['TextColor'] == -1 || $variable['TextColor'] == 16777215);
                    $variables[] = [
                        'id' => 'script_' . $scriptId,
                        'label' => $label,
                        'displayType' => 'button',
                        'variableType' => 0, // Behandle als Bool-Button für Rendering
                        'group' => $variable['Group'] ?? 'keine Gruppe',
                        'showGroupName' => $variable['ShowGroupName'] ?? false,
                        'showIcon' => $variable['ShowIcon'] ?? false,
                        'showLabel' => $variable['ShowLabel'] ?? true,
                        'showValue' => $variable['ShowValue'] ?? false,
                        'fontSize' => $variable['FontSize'] ?? 12,
                        'textColor' => $textColor,
                        'isTextColorTransparent' => $isTextColorTransparent,
                        'alignment' => $variable['VerticalAlignment'] ?? 'left',
                        'formattedValue' => '',
                        'rawValue' => 0,
                        'icon' => $scriptIcon,
                        'boolButtonColor' => isset($variable['boolButtonColor']) ? '#' . sprintf('%06X', $variable['boolButtonColor']) : '#CCCCCC',
                        'isBoolButtonColorTransparent' => isset($variable['boolButtonColor']) && ($variable['boolButtonColor'] == -1 || $variable['boolButtonColor'] == 16777215),
                        'buttonWidth' => $variable['ButtonWidth'] ?? 120,
                        'progressbarActive' => true,
                        'progressbarInactive' => false,
                        'scriptId' => $scriptId,
                        'openObjectId' => intval($variable['OpenObjectId'] ?? 0),
                    ];
                } else if ((($variable['DisplayType'] ?? 'text') === 'button') && intval($variable['OpenObjectId'] ?? 0) > 1) {
                    // SUPPORT BUTTON ROWS WITHOUT VARIABLE OR SCRIPT: OpenObject-Button (stateless)
                    $openObjectId = intval($variable['OpenObjectId']);
                    $label = $variable['Label'] ?? '';
                    $objectIcon = '';
                    try {
                        if (IPS_ObjectExists($openObjectId)) {
                            $obj = IPS_GetObject($openObjectId);
                            if ($label === '' && isset($obj['ObjectName'])) {
                                $label = $obj['ObjectName'];
                            }
                            $objIcon = isset($obj['ObjectIcon']) ? trim($obj['ObjectIcon']) : '';
                            if ($objIcon !== '') {
                                $objectIcon = $this->MapIconToFontAwesome($objIcon);
                            }
                        }
                    } catch (Exception $e) { /* ignore */ }
                    $textColor = isset($variable['TextColor']) ? '#' . sprintf('%06X', $variable['TextColor']) : '#000000';
                    $isTextColorTransparent = isset($variable['TextColor']) && ($variable['TextColor'] == -1 || $variable['TextColor'] == 16777215);
                    $variables[] = [
                        'id' => 'object_' . $openObjectId,
                        'label' => $label,
                        'displayType' => 'button',
                        'variableType' => 0, // wie Bool-Button rendern
                        'group' => $variable['Group'] ?? 'keine Gruppe',
                        'showGroupName' => $variable['ShowGroupName'] ?? false,
                        'showIcon' => $variable['ShowIcon'] ?? false,
                        'showLabel' => $variable['ShowLabel'] ?? true,
                        'showValue' => $variable['ShowValue'] ?? false,
                        'fontSize' => $variable['FontSize'] ?? 12,
                        'textColor' => $textColor,
                        'isTextColorTransparent' => $isTextColorTransparent,
                        'alignment' => $variable['VerticalAlignment'] ?? 'left',
                        'formattedValue' => '',
                        'rawValue' => 1,
                        'icon' => $objectIcon,
                        'boolButtonColor' => isset($variable['boolButtonColor']) ? '#' . sprintf('%06X', $variable['boolButtonColor']) : '#CCCCCC',
                        'isBoolButtonColorTransparent' => isset($variable['boolButtonColor']) && ($variable['boolButtonColor'] == -1 || $variable['boolButtonColor'] == 16777215),
                        'buttonWidth' => $variable['ButtonWidth'] ?? 120,
                        'progressbarActive' => true,
                        'progressbarInactive' => false,
                        'scriptId' => 0,
                        'openObjectId' => $openObjectId,
                    ];
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
                    $imageContent .= IPS_GetMediaContent($imageID);
                    $result['image1'] = $imageContent;
                    $result['image1Url'] = $this->BuildImageHookUrl($imageID);
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

    public function UDST_UpdateList(int $id, int $Status): void
    {
        // $id is provided by the form system (InstanceID or row context) but not required here
        $this->UpdateList($Status);
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
                    'EigenesBild' => 0,
                    'SymconIcon' => '',
                    'IconColor' => -1,
                    'StatusColor' => -1,
                    'ProgressbarActive' => true
                ];
            }
        }
    }
    
    // Konvertieren Sie Ihre Liste in JSON und aktualisieren Sie das Konfigurationsformular
    $jsonListData = json_encode($listData);
    $this->UpdateFormField('ProfilAssoziazionen', 'values', $jsonListData);
    }
    
    // Temporary alias for cached form calls - can be removed after Symcon restart
    public function UDST_UpdateDisplayTypeVisibility(int $id, string $displayType, ?int $rowIndex = null): void
    {
        // Forward to the new RequestAction system (displayType first, then optional row index/id)
        $this->UpdateDisplayTypeVisibility($displayType, $rowIndex);
    }
    
    public function UpdateDisplayTypeVisibility(string $displayType, ?int $rowId = null)
    {
        $supportsSelectObject = ((float)IPS_GetKernelVersion() > 8.1);
        // Basierend auf Display Type verschiedene Felder ein-/ausblenden
        switch ($displayType) {
            case 'text':
                // Bei Text: Show Icon ausblenden, da Text-Variablen normalerweise kein Icon haben
                $this->UpdateFormField('ShowIcon', 'visible', true);
                $this->UpdateFormField('ShowLabel', 'visible', true);
                $this->UpdateFormField('ShowValue', 'visible', true);
                // Variable wieder einblenden
                $this->UpdateFormField('Variable', 'visible', true);
                // ScriptID ausblenden
                $this->UpdateFormField('ScriptID', 'visible', false);
                // Generelle Text-Einstellungen sichtbar
                $this->UpdateFormField('Label', 'visible', true);
                $this->UpdateFormField('FontSize', 'visible', true);
                $this->UpdateFormField('TextColor', 'visible', true);
                // Progress-Felder ausblenden
                $this->UpdateFormField('ProgressColor1', 'visible', false);
                $this->UpdateFormField('ProgressColor2', 'visible', false);
                $this->UpdateFormField('SliderColor1', 'visible', false);
                $this->UpdateFormField('SliderColor2', 'visible', false);
                $this->UpdateFormField('SecondVariable', 'visible', false);
                $this->UpdateFormField('SecondVariableShowIcon', 'visible', false);
                $this->UpdateFormField('SecondVariableShowLabel', 'visible', false);
                $this->UpdateFormField('SecondVariableShowValue', 'visible', false);
                $this->UpdateFormField('SecondVariableLabel', 'visible', false);
                $this->UpdateFormField('UseSecondVariableAsTarget', 'visible', false);
                $this->UpdateFormField('SecondVariablePopupButton', 'visible', false);
                // Button-Felder ausblenden
                $this->UpdateFormField('ButtonWidth', 'visible', false);
                $this->UpdateFormField('boolButtonColor', 'visible', false);
                // Text-spezifische Felder
                $this->UpdateFormField('ShowBorderLine', 'visible', true);
                $this->UpdateFormField('VerticalAlignment', 'visible', true);
                // OpenObjectId bei Text ausblenden
                $this->UpdateFormField('OpenObjectId', 'visible', false);
                // Image-Felder ausblenden
                $this->UpdateFormField('ImageMedia', 'visible', false);
                $this->UpdateFormField('ImageWidth', 'visible', false);
                $this->UpdateFormField('ImageBorderRadius', 'visible', false);
                break;
            case 'image':
                // Image-Display: relevante Felder steuern
                // Grundfelder
                $this->UpdateFormField('ShowIcon', 'visible', false);
                $this->UpdateFormField('ShowLabel', 'visible', false);
                $this->UpdateFormField('ShowValue', 'visible', false);
                // Schriftgröße und Textfarbe ausblenden
                $this->UpdateFormField('FontSize', 'visible', false);
                $this->UpdateFormField('TextColor', 'visible', false);
                // Label-Feld ausblenden
                $this->UpdateFormField('Label', 'visible', false);
                // SelectVariable ausblenden, Media zeigen
                $this->UpdateFormField('Variable', 'visible', false);
                // ScriptID ausblenden
                $this->UpdateFormField('ScriptID', 'visible', false);

                // Progress-Felder ausblenden
                $this->UpdateFormField('ProgressColor1', 'visible', false);
                $this->UpdateFormField('ProgressColor2', 'visible', false);
                $this->UpdateFormField('SliderColor1', 'visible', false);
                $this->UpdateFormField('SliderColor2', 'visible', false);
                $this->UpdateFormField('SecondVariable', 'visible', false);
                $this->UpdateFormField('SecondVariableShowIcon', 'visible', false);
                $this->UpdateFormField('SecondVariableShowLabel', 'visible', false);
                $this->UpdateFormField('SecondVariableShowValue', 'visible', false);
                $this->UpdateFormField('SecondVariableLabel', 'visible', false);
                $this->UpdateFormField('UseSecondVariableAsTarget', 'visible', false);
                $this->UpdateFormField('SecondVariablePopupButton', 'visible', false);

                // Button-Felder ausblenden
                $this->UpdateFormField('ButtonWidth', 'visible', false);
                $this->UpdateFormField('boolButtonColor', 'visible', false);

                // Image-spezifische Felder einblenden
                $this->UpdateFormField('ImageMedia', 'visible', true);
                $this->UpdateFormField('ImageWidth', 'visible', true);
                $this->UpdateFormField('ImageBorderRadius', 'visible', true);

                // Alignment sichtbar, Borderline ausblenden
                $this->UpdateFormField('VerticalAlignment', 'visible', true);
                $this->UpdateFormField('ShowBorderLine', 'visible', false);
                // OpenObjectId bei Image ausblenden
                $this->UpdateFormField('OpenObjectId', 'visible', false);
                break;
            case 'progress':
                // Progress: relevante Felder ein-/ausblenden
                $this->UpdateFormField('ShowIcon', 'visible', true);
                $this->UpdateFormField('ShowLabel', 'visible', true);
                $this->UpdateFormField('ShowValue', 'visible', true);
                // Variable benötigt
                $this->UpdateFormField('Variable', 'visible', true);
                // ScriptID ausblenden
                $this->UpdateFormField('ScriptID', 'visible', false);
                // Generelle Text-Einstellungen sichtbar
                $this->UpdateFormField('Label', 'visible', true);
                $this->UpdateFormField('FontSize', 'visible', true);
                $this->UpdateFormField('TextColor', 'visible', true);
                // Progress-Farben sichtbar
                $this->UpdateFormField('ProgressColor1', 'visible', true);
                $this->UpdateFormField('ProgressColor2', 'visible', true);
                $this->UpdateFormField('SliderColor1', 'visible', false);
                $this->UpdateFormField('SliderColor2', 'visible', false);
                // SecondVariable-Block sichtbar
                $this->UpdateFormField('SecondVariable', 'visible', true);
                $this->UpdateFormField('SecondVariableShowIcon', 'visible', true);
                $this->UpdateFormField('SecondVariableShowLabel', 'visible', true);
                $this->UpdateFormField('SecondVariableShowValue', 'visible', true);
                $this->UpdateFormField('SecondVariableLabel', 'visible', true);
                $this->UpdateFormField('UseSecondVariableAsTarget', 'visible', true);
                $this->UpdateFormField('SecondVariablePopupButton', 'visible', true);
                // Button-Felder ausblenden
                $this->UpdateFormField('ButtonWidth', 'visible', false);
                $this->UpdateFormField('boolButtonColor', 'visible', false);
                // Image-Felder ausblenden
                $this->UpdateFormField('ImageMedia', 'visible', false);
                $this->UpdateFormField('ImageWidth', 'visible', false);
                $this->UpdateFormField('ImageBorderRadius', 'visible', false);
                // Text-Felder ausblenden
                $this->UpdateFormField('ShowBorderLine', 'visible', false);
                // Ausrichtung bei Progress ausblenden
                $this->UpdateFormField('VerticalAlignment', 'visible', false);
                // OpenObjectId bei Progress ausblenden
                $this->UpdateFormField('OpenObjectId', 'visible', false);
                break;
            case 'slider':
                // Slider: ähnlich Progress, aber ohne SecondVariable-Block
                $this->UpdateFormField('ShowIcon', 'visible', true);
                $this->UpdateFormField('ShowLabel', 'visible', true);
                $this->UpdateFormField('ShowValue', 'visible', true);
                // Variable benötigt
                $this->UpdateFormField('Variable', 'visible', true);
                // ScriptID ausblenden
                $this->UpdateFormField('ScriptID', 'visible', false);
                // Generelle Text-Einstellungen sichtbar
                $this->UpdateFormField('Label', 'visible', true);
                $this->UpdateFormField('FontSize', 'visible', true);
                $this->UpdateFormField('TextColor', 'visible', true);
                // Progress-Farnen unsichtbarSlider-Farben sichtbar
                $this->UpdateFormField('ProgressColor1', 'visible', false);
                $this->UpdateFormField('ProgressColor2', 'visible', false);
                $this->UpdateFormField('SliderColor1', 'visible', true);
                $this->UpdateFormField('SliderColor2', 'visible', true);
                // SecondVariable-Block ausblenden (Slider nutzt keinen Marker)
                $this->UpdateFormField('SecondVariable', 'visible', false);
                $this->UpdateFormField('SecondVariableShowIcon', 'visible', false);
                $this->UpdateFormField('SecondVariableShowLabel', 'visible', false);
                $this->UpdateFormField('SecondVariableShowValue', 'visible', false);
                $this->UpdateFormField('SecondVariableLabel', 'visible', false);
                $this->UpdateFormField('UseSecondVariableAsTarget', 'visible', false);
                $this->UpdateFormField('SecondVariablePopupButton', 'visible', false);
                // Button-Felder ausblenden
                $this->UpdateFormField('ButtonWidth', 'visible', false);
                $this->UpdateFormField('boolButtonColor', 'visible', false);
                // Image-Felder ausblenden
                $this->UpdateFormField('ImageMedia', 'visible', false);
                $this->UpdateFormField('ImageWidth', 'visible', false);
                $this->UpdateFormField('ImageBorderRadius', 'visible', false);
                // Text-Felder ausblenden
                $this->UpdateFormField('ShowBorderLine', 'visible', false);
                // Ausrichtung bei Slider ausblenden (feste horizontale Ausrichtung)
                $this->UpdateFormField('VerticalAlignment', 'visible', true);
                // OpenObjectId bei Slider ausblenden
                $this->UpdateFormField('OpenObjectId', 'visible', false);
                break;
            case 'button':
                // Button-Display: relevante Felder steuern
                // Grundfelder
                $this->UpdateFormField('ShowIcon', 'visible', true);
                $this->UpdateFormField('ShowLabel', 'visible', true);
                $this->UpdateFormField('ShowValue', 'visible', true);
                // Variable sichtbar
                $this->UpdateFormField('Variable', 'visible', true);
                // ScriptID sichtbar (optional, ermöglicht Script-Buttons ohne Variable)
                $this->UpdateFormField('ScriptID', 'visible', true);
                // OpenObjectId sichtbar (optional, ermöglicht Öffnen von Objekten)
                $this->UpdateFormField('OpenObjectId', 'visible', $supportsSelectObject);
                // Button-spezifische Felder sichtbar
                $this->UpdateFormField('boolButtonColor', 'visible', true);
                $this->UpdateFormField('ButtonWidth', 'visible', true);
                $this->UpdateFormField('VerticalAlignment', 'visible', true);
                // Generelle Text-Einstellungen sichtbar
                $this->UpdateFormField('Label', 'visible', true);
                $this->UpdateFormField('FontSize', 'visible', true);
                $this->UpdateFormField('TextColor', 'visible', true);

                // Progress-Felder ausblenden
                $this->UpdateFormField('ProgressColor1', 'visible', false);
                $this->UpdateFormField('ProgressColor2', 'visible', false);
                $this->UpdateFormField('SliderColor1', 'visible', false);
                $this->UpdateFormField('SliderColor2', 'visible', false);
                $this->UpdateFormField('SecondVariable', 'visible', false);
                $this->UpdateFormField('SecondVariableShowIcon', 'visible', false);
                $this->UpdateFormField('SecondVariableShowLabel', 'visible', false);
                $this->UpdateFormField('SecondVariableShowValue', 'visible', false);
                $this->UpdateFormField('SecondVariableLabel', 'visible', false);
                $this->UpdateFormField('SecondVariablePopupButton', 'visible', false);

                // Image-Felder ausblenden
                $this->UpdateFormField('ImageMedia', 'visible', false);
                $this->UpdateFormField('ImageWidth', 'visible', false);
                $this->UpdateFormField('ImageBorderRadius', 'visible', false);

                // Button-spezifische Felder einblenden
                $this->UpdateFormField('ButtonWidth', 'visible', true);
                $this->UpdateFormField('boolButtonColor', 'visible', true);

                // Alignment sichtbar, Borderline ausblenden
                $this->UpdateFormField('VerticalAlignment', 'visible', true);
                $this->UpdateFormField('ShowBorderLine', 'visible', false);
                break;
            
            default:
                // Default-Fall: Alle Felder ausblenden
                $this->UpdateFormField('ShowIcon', 'visible', false);
                $this->UpdateFormField('ShowLabel', 'visible', false);
                $this->UpdateFormField('ShowValue', 'visible', false);
                $this->UpdateFormField('ProgressColor1', 'visible', false);
                $this->UpdateFormField('ProgressColor2', 'visible', false);
                $this->UpdateFormField('SliderColor1', 'visible', false);
                $this->UpdateFormField('SliderColor2', 'visible', false);
                $this->UpdateFormField('SecondVariable', 'visible', false);
                $this->UpdateFormField('SecondVariableShowIcon', 'visible', false);
                $this->UpdateFormField('SecondVariableShowLabel', 'visible', false);
                $this->UpdateFormField('SecondVariableShowValue', 'visible', false);
                $this->UpdateFormField('SecondVariableLabel', 'visible', false);
                $this->UpdateFormField('SecondVariablePopupButton', 'visible', false);
                $this->UpdateFormField('ButtonWidth', 'visible', false);
                $this->UpdateFormField('boolButtonColor', 'visible', false);
                $this->UpdateFormField('ImageMedia', 'visible', false);
                $this->UpdateFormField('ImageWidth', 'visible', false);
                $this->UpdateFormField('ImageBorderRadius', 'visible', false);
                $this->UpdateFormField('VerticalAlignment', 'visible', false);
                $this->UpdateFormField('ShowBorderLine', 'visible', false);
                $this->UpdateFormField('ScriptID', 'visible', false);
                $this->UpdateFormField('Label', 'visible', false);
                $this->UpdateFormField('OpenObjectId', 'visible', false);

                break;
        }
    }


    // Entfernte Helferfunktionen (CheckAndGetValueFormatted, GetColor, GetColorRGB) wurden bereinigt, da ungenutzt

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
        
        // Prüfe auf Legacy-Präsentation (GUID) und merke Flag
        $legacyGuid = '4153A8D4-5C33-C65F-C1F3-7B61AAF99B1C';
        $isLegacyPresentation = false;
        $presentationArr = [];
        if (!empty($variable['VariableCustomPresentation'])) {
            $presentationArr = $variable['VariableCustomPresentation'];
        } elseif (!empty($variable['VariablePresentation'])) {
            $presentationArr = $variable['VariablePresentation'];
        }
        if (is_array($presentationArr) && isset($presentationArr['PRESENTATION'])) {
            $presentGuidTrim = trim((string)$presentationArr['PRESENTATION'], '{} ');
            $isLegacyPresentation = (strcasecmp($presentGuidTrim, $legacyGuid) === 0);
        }
        
        // Prüfe VariableCustomPresentation für Icon
        if ($icon == "" && !empty($variable['VariableCustomPresentation']) && !$isLegacyPresentation) {
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
            
            // Zusätzliche Unterstützung: Numerische Variablen (INTEGER/FLOAT) mit INTERVALS/OPTIONS
            if ($variable['VariableType'] == VARIABLETYPE_INTEGER || $variable['VariableType'] == VARIABLETYPE_FLOAT) {
                $options = null;
                $numericIconFound = false; // Wenn true, nicht mehr mit OPTIONS überschreiben
                
                // 1) INTERVALS direkt aus der CustomPresentation (JSON-String oder Array)
                $intervals = null;
                if (isset($customPresentation['INTERVALS'])) {
                    $intervals = is_string($customPresentation['INTERVALS']) ? @json_decode($customPresentation['INTERVALS'], true) : $customPresentation['INTERVALS'];
                }
                // Nur anwenden wenn aktiv oder Flag fehlt
                $intervalsActive = isset($customPresentation['INTERVALS_ACTIVE']) ? (bool)$customPresentation['INTERVALS_ACTIVE'] : true;
                if ($intervalsActive && is_array($intervals)) {
                    $current = floatval($Value);
                    foreach ($intervals as $interval) {
                        $iconActive = isset($interval['IconActive']) ? (bool)$interval['IconActive'] : false;
                        $iconValue = isset($interval['IconValue']) ? trim((string)$interval['IconValue']) : '';
                        if (!$iconActive || $iconValue === '') {
                            continue;
                        }
                        $min = array_key_exists('IntervalMinValue', $interval) ? floatval($interval['IntervalMinValue']) : -INF;
                        $max = array_key_exists('IntervalMaxValue', $interval) ? floatval($interval['IntervalMaxValue']) : INF;
                        if ($current >= $min && $current <= $max) {
                            $icon = $iconValue;
                            $numericIconFound = true;
                            break;
                        }
                    }
                }
                // FALL A: Direkte OPTIONS in der CustomPresentation (kann Array oder JSON-String sein)
                if (isset($customPresentation['OPTIONS'])) {
                    $options = is_string($customPresentation['OPTIONS']) ? @json_decode($customPresentation['OPTIONS'], true) : $customPresentation['OPTIONS'];
                }
                // FALL B: TEMPLATE liefert OPTIONS
                if (!is_array($options) && isset($customPresentation['TEMPLATE']) && function_exists('IPS_GetTemplate')) {
                    try {
                        $templateData = IPS_GetTemplate($customPresentation['TEMPLATE']);
                        if (isset($templateData['Values']['OPTIONS'])) {
                            $options = is_string($templateData['Values']['OPTIONS']) ? @json_decode($templateData['Values']['OPTIONS'], true) : $templateData['Values']['OPTIONS'];
                        }
                    } catch (Exception $e) {
                    }
                }
                // FALL B.2: OPTIONS verweist direkt auf eine GUID mit OPTIONS-Definitionen
                if (!is_array($options) && isset($customPresentation['OPTIONS']) && is_string($customPresentation['OPTIONS'])) {
                    $optionsGuid = trim($customPresentation['OPTIONS'], '{}');
                    try {
                        if (function_exists('IPS_PresentationExists') && @IPS_PresentationExists($optionsGuid)) {
                            $presentationData = IPS_GetPresentation($optionsGuid);
                            if ($presentationData) {
                                $presentationArray = is_string($presentationData) ? json_decode($presentationData, true) : $presentationData;
                                if (is_array($presentationArray)) {
                                    // Versuche gängige Stellen für OPTIONS zu finden
                                    if (isset($presentationArray['Values']['OPTIONS'])) {
                                        $options = is_string($presentationArray['Values']['OPTIONS']) ? @json_decode($presentationArray['Values']['OPTIONS'], true) : $presentationArray['Values']['OPTIONS'];
                                    } elseif (isset($presentationArray['presentationParameters']['OPTIONS'])) {
                                        $options = is_string($presentationArray['presentationParameters']['OPTIONS']) ? @json_decode($presentationArray['presentationParameters']['OPTIONS'], true) : $presentationArray['presentationParameters']['OPTIONS'];
                                    } elseif (isset($presentationArray['OPTIONS'])) {
                                        $options = is_string($presentationArray['OPTIONS']) ? @json_decode($presentationArray['OPTIONS'], true) : $presentationArray['OPTIONS'];
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                    }
                }
                // FALL C: PRESENTATION GUID -> Gruppe "Numeric" enthält OPTIONS
                if (!is_array($options) && isset($customPresentation['PRESENTATION']) && !empty($customPresentation['PRESENTATION'])) {
                    try {
                        $presentationGuid = trim($customPresentation['PRESENTATION'], '{}');
                        
                        // IPS_GetPresentation für GUID-Auflösung verwenden - mit Validation
                        if (@IPS_PresentationExists($presentationGuid)) {
                            $presentationData = IPS_GetPresentation($presentationGuid);
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
                                                // Immer ICON_TRUE
                                                $iconKey = 'ICON_TRUE';
                                            }
                                            
                                            if (isset($params[$iconKey]) && !empty($params[$iconKey])) {
                                                $icon = $params[$iconKey];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                    }
                    
                    // Auswertung der Optionen (unterstützt Value und Min/Max-Intervalle)
                    if (!$numericIconFound && is_array($options)) {
                        $current = floatval($Value);
                        foreach ($options as $option) {
                            // Icon-Feld ermitteln (IconValue bevorzugt, sonst Icon)
                            $optIcon = null;
                            if (isset($option['IconValue']) && trim((string)$option['IconValue']) !== '') {
                                $optIcon = $option['IconValue'];
                            } elseif (isset($option['Icon']) && trim((string)$option['Icon']) !== '') {
                                $optIcon = $option['Icon'];
                            }
                            if ($optIcon === null) {
                                continue; // Kein Icon definiert
                            }
                            
                            $hasRange = (isset($option['Min']) || isset($option['Max']) || isset($option['MinValue']) || isset($option['MaxValue']));
                            if ($hasRange) {
                                $min = isset($option['Min']) ? floatval($option['Min']) : (isset($option['MinValue']) ? floatval($option['MinValue']) : -INF);
                                $max = isset($option['Max']) ? floatval($option['Max']) : (isset($option['MaxValue']) ? floatval($option['MaxValue']) : INF);
                                if ($current >= $min && $current <= $max) {
                                    $icon = $optIcon;
                                    break;
                                }
                            } elseif (isset($option['Value'])) {
                                // Diskreter Vergleich (Float-Toleranz)
                                $optVal = $option['Value'];
                                if (is_numeric($optVal)) {
                                    if (abs(floatval($optVal) - $current) < 1e-9) {
                                        $icon = $optIcon;
                                        break;
                                    }
                                } else {
                                    if ($optVal == $Value) { // String-Vergleich für gemischte Optionen
                                        $icon = $optIcon;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            // Nur wenn noch kein Standard-Icon gefunden wurde, prüfe PRESENTATION GUID und Associations
            if ($icon == "") {
                // Zuerst prüfen ob die Variable eine neue Darstellung/Visualisierung hat
                if (function_exists('IPS_GetVariableVisualization')) {
                    try {
                        $visualization = IPS_GetVariableVisualization($id);
                        if ($visualization && isset($visualization['ValueMappings'])) {
                            foreach ($visualization['ValueMappings'] as $mapping) {
                                // Bereichsunterstützung: Min/Max Felder prüfen (verschiedene Schlüssel möglich)
                                if (isset($mapping['Icon']) && $mapping['Icon'] != "") {
                                    $v = floatval($Value);
                                    $hasRange = (isset($mapping['MinValue']) || isset($mapping['MaxValue']) || isset($mapping['Minimum']) || isset($mapping['Maximum']) || isset($mapping['Min']) || isset($mapping['Max']));
                                    if ($hasRange) {
                                        $min = isset($mapping['MinValue']) ? floatval($mapping['MinValue']) : (isset($mapping['Minimum']) ? floatval($mapping['Minimum']) : (isset($mapping['Min']) ? floatval($mapping['Min']) : -INF));
                                        $max = isset($mapping['MaxValue']) ? floatval($mapping['MaxValue']) : (isset($mapping['Maximum']) ? floatval($mapping['Maximum']) : (isset($mapping['Max']) ? floatval($mapping['Max']) : INF));
                                        if ($v >= $min && $v <= $max) {
                                            $icon = $mapping['Icon'];
                                            break;
                                        }
                                    }
                                }
                                // Exakter Value-Match als Fallback
                                if (isset($mapping['Value']) && isset($mapping['Icon']) && $mapping['Icon'] != "") {
                                    if ($mapping['Value'] == $Value) {
                                        $icon = $mapping['Icon'];
                                        break;
                                    }
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
        
        // Schließe VariableCustomPresentation if-Block (Zeile 1110)
        
        // Wenn noch kein Icon gefunden wurde, prüfe Darstellung/Visualisierung und Profile
        if ($icon == "" && !$isLegacyPresentation) {
            // Zuerst prüfen ob die Variable eine neue Darstellung/Visualisierung hat
            if (function_exists('IPS_GetVariableVisualization')) {
                try {
                    $visualization = IPS_GetVariableVisualization($id);
                    if ($visualization && isset($visualization['ValueMappings'])) {
                        foreach ($visualization['ValueMappings'] as $mapping) {
                            // Bereichsunterstützung: Min/Max Felder prüfen (verschiedene Schlüssel möglich)
                            if (isset($mapping['Icon']) && $mapping['Icon'] != "") {
                                $v = floatval($Value);
                                $hasRange = (isset($mapping['MinValue']) || isset($mapping['MaxValue']) || isset($mapping['Minimum']) || isset($mapping['Maximum']) || isset($mapping['Min']) || isset($mapping['Max']));
                                if ($hasRange) {
                                    $min = isset($mapping['MinValue']) ? floatval($mapping['MinValue']) : (isset($mapping['Minimum']) ? floatval($mapping['Minimum']) : (isset($mapping['Min']) ? floatval($mapping['Min']) : -INF));
                                    $max = isset($mapping['MaxValue']) ? floatval($mapping['MaxValue']) : (isset($mapping['Maximum']) ? floatval($mapping['Maximum']) : (isset($mapping['Max']) ? floatval($mapping['Max']) : INF));
                                    if ($v >= $min && $v <= $max) {
                                        $icon = $mapping['Icon'];
                                        break;
                                    }
                                }
                            }
                            // Exakter Value-Match als Fallback
                            if (isset($mapping['Value']) && isset($mapping['Icon']) && $mapping['Icon'] != "") {
                                if ($mapping['Value'] == $Value) {
                                    $icon = $mapping['Icon'];
                                    break;
                                }
                            }
                        }
                        
                        // Falls kein spezifisches Icon gefunden, verwende Default-Icon der Darstellung
                        if ($icon == "" && isset($visualization['Icon']) && $visualization['Icon'] != "") {
                            $icon = $visualization['Icon'];
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
            
            // Fallback zu klassischen Variablenprofilen wenn kein Icon über Darstellung gefunden (Standard-Profile)
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
        
        // Letzter, allgemeiner Fallback auf klassische Profile – auch wenn zuvor "Transparent" gesetzt wurde
        // Unterstützt zusätzlich das Profil aus PRESENTATION ([PROFILE]) falls VariableProfile leer ist
        if ($icon === '' || $icon === 'Transparent') {
            $profile = $variable['VariableCustomProfile'] ?: $variable['VariableProfile'];
            if (empty($profile)) {
                $pres1 = (isset($variable['VariableCustomPresentation']) && is_array($variable['VariableCustomPresentation'])) ? $variable['VariableCustomPresentation'] : [];
                $pres2 = (isset($variable['VariablePresentation']) && is_array($variable['VariablePresentation'])) ? $variable['VariablePresentation'] : [];
                if (isset($pres1['PROFILE']) && !empty($pres1['PROFILE'])) {
                    $profile = $pres1['PROFILE'];
                } elseif (isset($pres2['PROFILE']) && !empty($pres2['PROFILE'])) {
                    $profile = $pres2['PROFILE'];
                }
            }
            if (!empty($profile) && IPS_VariableProfileExists($profile)) {
                $p = IPS_GetVariableProfile($profile);
                if (isset($p['Associations']) && is_array($p['Associations'])) {
                    foreach ($p['Associations'] as $association) {
                        if (isset($association['Value']) && isset($association['Icon']) && $association['Icon'] !== '' && $association['Value'] == $Value) {
                            $icon = $association['Icon'];
                            break;
                        }
                    }
                }
                if (($icon === '' || $icon === 'Transparent') && isset($p['Icon']) && $p['Icon'] !== '') {
                    $icon = $p['Icon'];
                }
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
        $profileMinMax = null; // Profil-Werte nur als Fallback verwenden
        
        if (!IPS_VariableExists($variableId)) {
            return $defaultMinMax;
        }
        
        $variable = IPS_GetVariable($variableId);
        
        // Unterstütze INTEGER und FLOAT Variablen für Progress Bars
        if ($variable['VariableType'] !== VARIABLETYPE_INTEGER && $variable['VariableType'] !== VARIABLETYPE_FLOAT) {
            return $defaultMinMax;
        }
        
        // **PRESENTATION-HIERARCHIE wie bei Icons: Gleiche Taktik für konsistente Behandlung**

        // Hilfsfunktion: Min/Max rekursiv aus beliebigen Strukturen extrahieren
        $extractMinMax = function($arr) use (&$extractMinMax) {
            if (!is_array($arr)) return null;
            $minKeys = ['MinValue','MinimalerWert','Minimum','Min','minValue','min'];
            $maxKeys = ['MaxValue','MaximalerWert','Maximum','Max','maxValue','max'];
            $foundMin = null; $foundMax = null;
            foreach ($minKeys as $k) { if (isset($arr[$k]) && is_numeric($arr[$k])) { $foundMin = (float)$arr[$k]; break; } }
            foreach ($maxKeys as $k) { if (isset($arr[$k]) && is_numeric($arr[$k])) { $foundMax = (float)$arr[$k]; break; } }
            if ($foundMin !== null && $foundMax !== null) {
                return ['min' => $foundMin, 'max' => $foundMax];
            }
            // Rekursiv in Unterstrukturen suchen (einschließlich JSON-Strings)
            foreach ($arr as $v) {
                if (is_array($v)) {
                    $res = $extractMinMax($v);
                    if (is_array($res)) return $res;
                } elseif (is_string($v)) {
                    $decoded = @json_decode($v, true);
                    if (is_array($decoded)) {
                        $res = $extractMinMax($decoded);
                        if (is_array($res)) return $res;
                    }
                }
            }
            return null;
        };
        
        // **FALL 1: Alte Variablenprofile (nun nur noch Fallback, Präsentationen haben Vorrang)**
        $profile = $variable['VariableCustomProfile'] ?: $variable['VariableProfile'];
        if (!empty($profile) && IPS_VariableProfileExists($profile)) {
            $profileData = IPS_GetVariableProfile($profile);
            
            if (isset($profileData['MinValue']) && isset($profileData['MaxValue'])) {
                $profileMinMax = [
                    'min' => floatval($profileData['MinValue']),
                    'max' => floatval($profileData['MaxValue'])
                ];
            }
        }
        
        // **FALL 2: CustomPresentation mit direkten MIN/MAX Parametern**
        // Presentation source: prefer CustomPresentation, fallback to standard VariablePresentation (Option B)
        // Normalize to array (can be JSON string or array depending on IPS version)
        $customPresentation = [];
        if (isset($variable['VariableCustomPresentation']) && !empty($variable['VariableCustomPresentation'])) {
            $raw = $variable['VariableCustomPresentation'];
        } elseif (isset($variable['VariablePresentation']) && !empty($variable['VariablePresentation'])) {
            $raw = $variable['VariablePresentation'];
        } else {
            $raw = [];
        }
        if (is_string($raw)) {
            $decodedTop = @json_decode($raw, true);
            if (is_array($decodedTop)) {
                $customPresentation = $decodedTop;
            }
        } elseif (is_array($raw)) {
            $customPresentation = $raw;
        }
        // Wenn Präsentation nur GUID enthält, versuche vollständige Variable-Präsentation aufzulösen
        if (!empty($customPresentation) && isset($customPresentation['PRESENTATION']) && count($customPresentation) <= 2) {
            if (function_exists('IPS_GetVariablePresentation')) {
                try {
                    $fullPresentation = @IPS_GetVariablePresentation($variableId);
                    if (is_array($fullPresentation) && !empty($fullPresentation)) {
                        $customPresentation = $fullPresentation;
                    }
                } catch (Exception $e) { /* ignore */ }
            }
        }
        
        if (!empty($customPresentation)) {
            // Direkte MIN/MAX Parameter
            $directMinMax = null;
            if ((isset($customPresentation['MIN']) && isset($customPresentation['MAX'])) || (isset($customPresentation['Min']) && isset($customPresentation['Max']))) {
                $minVal = isset($customPresentation['MIN']) ? $customPresentation['MIN'] : $customPresentation['Min'];
                $maxVal = isset($customPresentation['MAX']) ? $customPresentation['MAX'] : $customPresentation['Max'];
                if (is_numeric($minVal) && is_numeric($maxVal)) {
                    $directMinMax = ['min' => (float)$minVal, 'max' => (float)$maxVal];
                }
            }
            if (!$directMinMax) {
                $directMinMax = $extractMinMax($customPresentation);
            }
            if (is_array($directMinMax)) return $directMinMax;
            
            // **FALL 3: GUID-basierte Presentations (PRESENTATION)**
            if (isset($customPresentation['PRESENTATION']) && !empty($customPresentation['PRESENTATION'])) {
                $presentationGuid = trim((string)$customPresentation['PRESENTATION'], "{} ");
                try {
                    if (function_exists('IPS_PresentationExists') && @IPS_PresentationExists($presentationGuid)) {
                        $presentationData = IPS_GetPresentation($presentationGuid);
                        $presentationArray = is_string($presentationData) ? @json_decode($presentationData, true) : $presentationData;
                        if (is_array($presentationArray)) {
                            // Erst in presentationParameters schauen, dann global
                            if (isset($presentationArray['presentationParameters']) && is_array($presentationArray['presentationParameters'])) {
                                $mm = $extractMinMax($presentationArray['presentationParameters']);
                                if (is_array($mm)) return $mm;
                            }
                            $mm = $extractMinMax($presentationArray);
                            if (is_array($mm)) return $mm;
                        }
                    }
                } catch (Exception $e) { }
            }
            
            // **FALL 4: OPTIONS-basierte Presentations**
            if (isset($customPresentation['OPTIONS']) && !empty($customPresentation['OPTIONS'])) {
                $opt = $customPresentation['OPTIONS'];
                // A) Direkte JSON-OPTIONS durchsuchen
                if (is_string($opt)) {
                    $decoded = @json_decode($opt, true);
                    if (is_array($decoded)) {
                        $mm = $extractMinMax($decoded);
                        if (is_array($mm)) return $mm;
                    }
                } elseif (is_array($opt)) {
                    $mm = $extractMinMax($opt);
                    if (is_array($mm)) return $mm;
                }
                // B) OPTIONS als GUID interpretieren
                $optionsGuid = is_string($opt) ? trim($opt, "{} ") : '';
                if ($optionsGuid !== '') {
                    try {
                        if (function_exists('IPS_PresentationExists') && @IPS_PresentationExists($optionsGuid)) {
                            $presentationData = IPS_GetPresentation($optionsGuid);
                            $presentationArray = is_string($presentationData) ? @json_decode($presentationData, true) : $presentationData;
                            if (is_array($presentationArray)) {
                                $mm = $extractMinMax($presentationArray);
                                if (is_array($mm)) return $mm;
                            }
                        }
                    } catch (Exception $e) { }
                }
            }
            
            // **FALL 5: TEMPLATE-basierte Presentations**
            if (isset($customPresentation['TEMPLATE']) && !empty($customPresentation['TEMPLATE'])) {
                $templateGuid = trim((string)$customPresentation['TEMPLATE'], "{} ");
                try {
                    if (function_exists('IPS_GetTemplate')) {
                        $templateData = @IPS_GetTemplate($templateGuid);
                        // Erwartete Struktur: ['Values' => ...] aber wir scannen alles
                        if (is_array($templateData)) {
                            if (isset($templateData['Values']) && is_array($templateData['Values'])) {
                                $mm = $extractMinMax($templateData['Values']);
                                if (is_array($mm)) return $mm;
                            }
                            $mm = $extractMinMax($templateData);
                            if (is_array($mm)) return $mm;
                        }
                    } else {
                        // Fallback: manche Systeme liefern Template-Daten auch über IPS_GetPresentation
                        if (function_exists('IPS_PresentationExists') && @IPS_PresentationExists($templateGuid)) {
                            $presentationData = IPS_GetPresentation($templateGuid);
                            $presentationArray = is_string($presentationData) ? @json_decode($presentationData, true) : $presentationData;
                            if (is_array($presentationArray)) {
                                $mm = $extractMinMax($presentationArray);
                                if (is_array($mm)) return $mm;
                            }
                        }
                    }
                } catch (Exception $e) { }
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
        
        // **Falls noch nichts gefunden: Profil-Werte verwenden (Fallback)**
        if (is_array($profileMinMax)) {
            return $profileMinMax;
        }
        // **LETZTER FALLBACK: Standard Min/Max verwenden**
        return $defaultMinMax;
    }

    private function GetSliderStepAndDigits($variableId) {
        $res = ['step' => null, 'digits' => 0];
        if (!IPS_VariableExists($variableId)) {
            return $res;
        }
        $variable = IPS_GetVariable($variableId);
        $debug = ($variableId == 49382); // Debug für Problem-Variable
        // 1) Profil-Werte (falls vorhanden)
        $profileName = $variable['VariableCustomProfile'] ?: $variable['VariableProfile'];
        if (!empty($profileName) && IPS_VariableProfileExists($profileName)) {
            $profileData = IPS_GetVariableProfile($profileName);
            if (isset($profileData['Digits'])) {
                $res['digits'] = (int)$profileData['Digits'];
            }
            if (isset($profileData['StepSize'])) {
                $res['step'] = (float)$profileData['StepSize'];
                if ($debug) IPS_LogMessage('GetSliderStep', "ID $variableId: Profil StepSize = {$res['step']}");
            }
        }

        // Helper: extrahiere Step/Digits rekursiv aus beliebigen Präsentations-Strukturen
        $extractStepDigits = function ($arr) use (&$res, &$extractStepDigits) {
            if (!is_array($arr)) return;
            $keysStep = [
                'step','stepsize','STEP','Step','StepSize','STEP_SIZE','stepSize',
                'INCREMENT','Increment','increment','StepValue','STEPVALUE','step_value','StepWidth',
                // Häufige Varianten in Präsentationen
                'smallestStep','SmallestStep','SMALLESTSTEP','SMALLEST_STEP','smallStep','SmallStep','small_step'
            ];
            $keysDigits = ['digits','DIGITS','Digits'];
            foreach ($keysStep as $k) {
                if (isset($arr[$k]) && is_numeric($arr[$k])) { $res['step'] = (float)$arr[$k]; break; }
            }
            foreach ($keysDigits as $k) {
                if (isset($arr[$k]) && is_numeric($arr[$k])) { $res['digits'] = (int)$arr[$k]; break; }
            }
            // Rekursiv in alle Unterstrukturen (PARAMETERS, Values, OPTIONS, usw.)
            foreach ($arr as $k => $v) {
                if (is_array($v)) {
                    $extractStepDigits($v);
                } elseif (is_string($v)) {
                    $decoded = @json_decode($v, true);
                    if (is_array($decoded)) $extractStepDigits($decoded);
                }
            }
        };

        // 2) Präsentation direkt aus Variable lesen (Custom bevorzugt) – JSON-String sicher dekodieren
        $presentation = [];
        $presentationRaw = null;
        if (isset($variable['VariableCustomPresentation']) && !empty($variable['VariableCustomPresentation'])) {
            $presentationRaw = $variable['VariableCustomPresentation'];
        } elseif (isset($variable['VariablePresentation']) && !empty($variable['VariablePresentation'])) {
            $presentationRaw = $variable['VariablePresentation'];
        }
        if (!empty($presentationRaw)) {
            if (is_string($presentationRaw)) {
                $decodedTop = @json_decode($presentationRaw, true);
                if (is_array($decodedTop)) {
                    $presentation = $decodedTop;
                }
            } elseif (is_array($presentationRaw)) {
                $presentation = $presentationRaw;
            }
        }
        
        // WICHTIG: Wenn Präsentation nur GUID-Referenz enthält, hole vollständige Daten über IPS_GetVariablePresentation
        if (!empty($presentation) && isset($presentation['PRESENTATION']) && count($presentation) <= 2) {
            // Präsentation enthält nur GUID (+ evtl. 1-2 andere Keys) -> vollständige Daten laden
            if (function_exists('IPS_GetVariablePresentation')) {
                try {
                    $fullPresentation = @IPS_GetVariablePresentation($variableId);
                    if (is_array($fullPresentation) && !empty($fullPresentation)) {
                        if ($debug) IPS_LogMessage('GetSliderStep', "ID $variableId: Aufgelöste Präsentation via IPS_GetVariablePresentation = " . json_encode($fullPresentation));
                        $presentation = $fullPresentation;
                    }
                } catch (Exception $e) { /* ignore */ }
            }
        }
        if (!empty($presentation)) {
            if ($debug) IPS_LogMessage('GetSliderStep', "ID $variableId: Presentation (Top-Level) = " . json_encode($presentation));
            $extractStepDigits($presentation);
            if ($debug) IPS_LogMessage('GetSliderStep', "ID $variableId: Nach Top-Level Extraktion: step={$res['step']}, digits={$res['digits']}");
            // GUID-gestützte Präsentation
            if (isset($presentation['PRESENTATION']) && !empty($presentation['PRESENTATION']) && function_exists('IPS_GetPresentation')) {
                $guid = trim((string)$presentation['PRESENTATION'], "{} ");
                if ($debug) IPS_LogMessage('GetSliderStep', "ID $variableId: Lade GUID-Präsentation: $guid");
                try {
                    $pdata = @IPS_GetPresentation($guid);
                    if (is_array($pdata)) { 
                        if ($debug) IPS_LogMessage('GetSliderStep', "ID $variableId: GUID-Daten = " . json_encode($pdata));
                        $extractStepDigits($pdata); 
                        if ($debug) IPS_LogMessage('GetSliderStep', "ID $variableId: Nach GUID Extraktion: step={$res['step']}, digits={$res['digits']}");
                    }
                } catch (Exception $e) { /* ignore */ }
            }
            // TEMPLATE-basierte Struktur (liefert meist Values)
            if (isset($presentation['TEMPLATE']) && function_exists('IPS_GetTemplate')) {
                try {
                    $tdata = @IPS_GetTemplate($presentation['TEMPLATE']);
                    if (is_array($tdata)) { $extractStepDigits($tdata); }
                } catch (Exception $e) { /* ignore */ }
            }
        }

        // 3) Fallbacks falls Step nicht gefunden
        if ($res['step'] === null || $res['step'] <= 0) {
            $digits = max(0, (int)$res['digits']);
            if ($variable['VariableType'] === VARIABLETYPE_INTEGER) {
                $res['step'] = 1;
                if ($debug) IPS_LogMessage('GetSliderStep', "ID $variableId: Fallback für Integer -> step=1");
            } else {
                $res['step'] = ($digits > 0) ? pow(10, -$digits) : 0.0;
            }
        }
        if ($debug) IPS_LogMessage('GetSliderStep', "ID $variableId: FINAL step={$res['step']}, digits={$res['digits']}");
        return $res;
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
        
        // Präsentation früh bestimmen (Custom bevorzugt, sonst Standard)
        $customPresentation = [];
        if (isset($variable['VariableCustomPresentation']) && !empty($variable['VariableCustomPresentation'])) {
            $customPresentation = $variable['VariableCustomPresentation'];
        } elseif (isset($variable['VariablePresentation']) && !empty($variable['VariablePresentation'])) {
            $customPresentation = $variable['VariablePresentation'];
        }
        
        // Sonderfall: VARIABLE_PRESENTATION_LEGACY -> Profil verwenden, Präsentation ignorieren
        // GUID: {4153A8D4-5C33-C65F-C1F3-7B61AAF99B1C}
        if (is_array($customPresentation) && isset($customPresentation['PRESENTATION'])) {
            $legacyGuid = '4153A8D4-5C33-C65F-C1F3-7B61AAF99B1C';
            $presentGuidRaw = (string)$customPresentation['PRESENTATION'];
            $presentGuidTrim = trim($presentGuidRaw, "{} ");
            if (strcasecmp($presentGuidTrim, $legacyGuid) === 0) {
                // Präsentation als nicht vorhanden behandeln, damit Profil greift
                $customPresentation = [];
            }
        }
        
        // Bestimme den Gruppennamen für FALL 4 basierend auf Variablentyp
        $groupName = ($expectedVariableType === VARIABLETYPE_INTEGER) ? 'Numeric' : 
                     (($expectedVariableType === VARIABLETYPE_BOOLEAN) ? 'Boolean' : 'String');
        
        // **FALL 1: Alte Variablenprofile**
        $profile = $variable['VariableCustomProfile'] ?: $variable['VariableProfile'];
        // Wenn sowohl Profil als auch Präsentation vorhanden sind, bevorzugen wir die Präsentation
        if (!empty($profile) && IPS_VariableProfileExists($profile) && empty($customPresentation)) {
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
                            // Mappe Icon-Namen (z. B. "Climate" -> "arrows-spin") bevor das Frontend rendert
                            'icon' => (isset($association['Icon']) && $association['Icon'] !== '') ? $this->MapIconToFontAwesome($association['Icon']) : null
                        ];
                    }
                }
                return $associations;
            }
        }
        
        // Presentation source for associations: prefer VariableCustomPresentation, fallback to VariablePresentation (Option B)
        // Enables buttons for standard (non-custom) presentations that define OPTIONS/PRESENTATION/TEMPLATE
        $customPresentation = [];
        if (isset($variable['VariableCustomPresentation']) && !empty($variable['VariableCustomPresentation'])) {
            $customPresentation = $variable['VariableCustomPresentation'];
        } elseif (isset($variable['VariablePresentation']) && !empty($variable['VariablePresentation'])) {
            $customPresentation = $variable['VariablePresentation'];
        }
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
            if (empty($customPresentation) && isset($variable['VariableProfile']) && !empty($variable['VariableProfile'])) {
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
                            // Mappe IconValue über die zentrale Mapping-Funktion
                            'icon' => (isset($option['IconValue']) && !empty($option['IconValue'])) ? $this->MapIconToFontAwesome($option['IconValue']) : null
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
                                        // Mappe IconValue aus Template-Optionen
                                        'icon' => (isset($option['IconValue']) && !empty($option['IconValue'])) ? $this->MapIconToFontAwesome($option['IconValue']) : null
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
                                                    // Mappe IconValue aus Presentation-Optionen
                                                    'icon' => (isset($option['IconValue']) && !empty($option['IconValue'])) ? $this->MapIconToFontAwesome($option['IconValue']) : null
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