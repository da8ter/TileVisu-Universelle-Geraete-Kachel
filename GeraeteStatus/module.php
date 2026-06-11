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

require_once __DIR__ . '/libs/CommonHelpers.php';
require_once __DIR__ . '/libs/ImageHookTrait.php';
require_once __DIR__ . '/libs/IconTrait.php';
require_once __DIR__ . '/libs/PresentationTrait.php';
require_once __DIR__ . '/libs/PayloadTrait.php';
require_once __DIR__ . '/libs/PayloadVariablesTrait.php';

class UniversalDeviceTile extends IPSModule
{
    use \UDT\ImageHookTrait;
    use \UDT\IconTrait;
    use \UDT\PresentationTrait;
    use \UDT\PayloadTrait;
    use \UDT\PayloadVariablesTrait;

    // Variablen-Zugriff und Status-Variable-ID
    
    
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

    
    

    /**
     * Gibt die Konfigurationsform zurück
     * @return string JSON-String der Konfigurationsform
     */
    public function GetConfigurationForm()
    {
        $groupNames = $this->GetAllGroupNames();
        $formPath = __DIR__ . '/form.json';
        $formJson = file_get_contents($formPath);
        if ($formJson === false) {
            $this->LogMessage('Could not read form.json.', KL_ERROR);
            return '{}';
        }

        $form = \UDT\Helpers::decodeJsonArray($formJson, __FUNCTION__ . ':form.json');
        if (!is_array($form)) {
            $this->LogMessage('form.json is invalid JSON.', KL_ERROR);
            return '{}';
        }

        $groupOptions = $this->BuildGroupSelectOptions($groupNames);
        $this->UpdateGroupSelectOptionsInForm($form, $groupOptions);
        $this->UpdateGroupOptionsInDynamicFormScript($form, $groupOptions);
        $this->populateGroupNameColumn($form, $groupNames);

        return json_encode($form);
    }

    /**
     * @param array $groupNames Die konfigurierten Gruppennamen
     * @return array
     */
    private function BuildGroupSelectOptions(array $groupNames): array
    {
        $options = [
            [
                'caption' => 'keine Gruppe',
                'value' => 'keine Gruppe'
            ]
        ];

        for ($i = 1; $i <= 10; $i++) {
            $caption = 'Gruppe ' . $i;
            if (isset($groupNames[$i]['name']) && trim((string)$groupNames[$i]['name']) !== '') {
                $caption = (string)$groupNames[$i]['name'];
            }

            $options[] = [
                'caption' => $caption,
                'value' => 'Gruppe ' . $i
            ];
        }

        return $options;
    }

    private function UpdateGroupSelectOptionsInForm(&$element, array $groupOptions): void
    {
        if (!is_array($element)) {
            return;
        }

        if (($element['name'] ?? '') === 'Group' &&
            isset($element['edit']) && is_array($element['edit']) &&
            (($element['edit']['type'] ?? '') === 'Select')) {
            $element['edit']['options'] = $groupOptions;
        }

        foreach ($element as &$subElement) {
            $this->UpdateGroupSelectOptionsInForm($subElement, $groupOptions);
        }
    }

    private function UpdateGroupOptionsInDynamicFormScript(&$element, array $groupOptions): void
    {
        if (!is_array($element)) {
            return;
        }

        if (isset($element['form']) && is_array($element['form'])) {
            $element['form'] = $this->PatchGroupOptionsInFormScriptLines($element['form'], $groupOptions);
        }

        foreach ($element as &$subElement) {
            $this->UpdateGroupOptionsInDynamicFormScript($subElement, $groupOptions);
        }
    }

    private function PatchGroupOptionsInFormScriptLines(array $formLines, array $groupOptions): array
    {
        $patched = [];
        $inGroupSelect = false;
        $inOptions = false;
        $optionsInjected = false;
        $groupOptionCount = count($groupOptions);

        foreach ($formLines as $line) {
            if (!is_string($line)) {
                $patched[] = $line;
                continue;
            }

            if (!$inGroupSelect &&
                strpos($line, "'name' => 'Group'") !== false &&
                strpos($line, "'type' => 'Select'") !== false) {
                $inGroupSelect = true;
            }

            if ($inGroupSelect && !$inOptions && strpos($line, "'options' => [") !== false) {
                $patched[] = $line;

                foreach ($groupOptions as $index => $option) {
                    $caption = addslashes((string)($option['caption'] ?? ''));
                    $value = addslashes((string)($option['value'] ?? ''));
                    $comma = ($index < ($groupOptionCount - 1)) ? ',' : '';
                    $patched[] = "        [ 'caption' => '" . $caption . "', 'value' => '" . $value . "' ]" . $comma;
                }

                $inOptions = true;
                $optionsInjected = true;
                continue;
            }

            if ($inOptions) {
                if (trim($line) === '],') {
                    $patched[] = $line;
                    $inOptions = false;
                }
                continue;
            }

            $patched[] = $line;

            if ($inGroupSelect && strpos($line, "'visible' => true") !== false) {
                $inGroupSelect = false;
            }
        }

        return $optionsInjected ? $patched : $formLines;
    }

    private function LogCaughtThrowable(string $context, Throwable $e): void
    {
        $this->SendDebug($context, $e->getMessage(), 0);
    }

    /**
     * Formatiert einen Objektnamen mit Parent-Namen als "Name (Parent)".
     * Optional kann der Name überschrieben werden (z. B. durch ein konfiguriertes Label).
     */
    private function formatObjectWithParent(int $objectId, string $overrideName = ''): string
    {
        $obj = IPS_GetObject($objectId);
        $name = isset($obj['ObjectName']) ? $obj['ObjectName'] : '';
        if ($overrideName !== '') {
            $name = $overrideName;
        }
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

    private function IsKernelReady(): bool
    {
        if (!function_exists('IPS_GetKernelRunlevel') || !defined('KR_READY')) {
            return true;
        }

        return IPS_GetKernelRunlevel() == KR_READY;
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
                return $this->formatObjectWithParent($mid);
            }
            return '';
        }
        // OpenObject-Buttons: OpenObjectId-Namen anzeigen (hat Priorität)
        if (($displayType === 'button') && isset($row['OpenObjectId'])) {
            $openObjectId = intval($row['OpenObjectId']);
            if ($openObjectId > 1 && IPS_ObjectExists($openObjectId)) {
                return $this->formatObjectWithParent($openObjectId);
            }
        }
        // Script-Buttons ohne Variable: Script-Namen anzeigen (Label überschreibt den Namen)
        if (($displayType === 'button') && isset($row['ScriptID'])) {
            $scriptId = intval($row['ScriptID']);
            if ($scriptId > 0 && function_exists('IPS_ScriptExists') && IPS_ScriptExists($scriptId)) {
                $override = !empty($row['Label']) ? strval($row['Label']) : '';
                return $this->formatObjectWithParent($scriptId, $override);
            }
        }
        $vid = intval($row['Variable'] ?? 0);
        if ($vid > 0 && IPS_VariableExists($vid)) {
            return $this->formatObjectWithParent($vid);
        }
        return '';
    }
    
    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        if (!$this->IsKernelReady()) {
            return;
        }

        // Cache zurücksetzen für frischen Zustand nach Neustart
        $this->WriteAttributeString('LastVarValues', '{}');

        // Stelle sicher, dass das Icon-Mapping geladen ist
        $this->LoadIconMapping();

        // WebHook für Bildauslieferung registrieren
        $this->RegisterUDTImageHook('/hook/udtimages/' . $this->InstanceID);

        // Dynamische Referenzen und Nachrichten für konfigurierte Variablen
        $variablesList = \UDT\Helpers::decodeJsonArray($this->ReadPropertyString('VariablesList'), __FUNCTION__ . ':VariablesList');
        if (!is_array($variablesList)) {
            $variablesList = [];
        }
        

        // Sammle alle Variablen-IDs
        $ids = [$this->ReadPropertyInteger('bgImage')];
        // Sammle relevante Medienobjekte für Referenzen & Nachrichten (Default + Custom Images + Hintergrund)
        $mediaIds = [];
        
        // Füge Status-Variable hinzu
        $statusId = $this->ReadPropertyInteger('Status');
        if ($statusId > 0) {
            $ids[] = $statusId;
        }
        
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
        foreach ($variablesList as $variable) {
            if (isset($variable['Variable']) && $variable['Variable'] > 0) {
                $this->RegisterMessage($variable['Variable'], VM_UPDATE);
            }
            // Registriere auch SecondVariable falls vorhanden
            if (isset($variable['SecondVariable']) && $variable['SecondVariable'] > 0) {
                $this->RegisterMessage($variable['SecondVariable'], VM_UPDATE);
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
        $fullUpdateMessage = \UDT\Helpers::decodeJsonArray($fullUpdateMessageJson, __FUNCTION__ . ':GetFullUpdateMessage');
        if (!is_array($fullUpdateMessage)) {
            $fullUpdateMessage = [];
        }
        
        // Füge Asset-Update hinzu für Custom Images und Fallback-Assets
        $assets = $this->GenerateAssets();
        if (!empty($assets)) {
            $fullUpdateMessage['assets'] = $assets;
        }
        
        $this->UpdateVisualizationValue(json_encode($fullUpdateMessage));
    }

    public function Destroy()
    {
        if ($this->IsKernelReady()) {
            $this->UnregisterUDTImageHook('/hook/udtimages/' . $this->InstanceID);
        }

        parent::Destroy();
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

    

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message === IPS_KERNELSTARTED) {
            $this->ApplyChanges();
            return;
        }

        // Volles Update-Payload höchstens einmal pro MessageSink-Lauf bauen
        // (Status-Zweig und Variablenliste können beide zutreffen, die Werte
        // ändern sich dazwischen nicht).
        $fullMessage = null;

        // Status-Variable: minimalen Status-Payload senden.
        // Achtung: bewusst kein return — die Status-Variable kann zusätzlich
        // in der Variablenliste konfiguriert sein (Dedup verhindert Doppel-Updates).
        $statusId = $this->ReadPropertyInteger('Status');
        if ($Message === VM_UPDATE && $statusId > 0 && $SenderID === $statusId
            && $this->hasValueChangedAndRemember($SenderID)) {
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
        }

        // Dynamische Verarbeitung der konfigurierten Variablen (Haupt- und SecondVariable)
        if ($Message === VM_UPDATE) {
            $variablesList = json_decode($this->ReadPropertyString('VariablesList'), true);
            if (is_array($variablesList)) {
                foreach ($variablesList as $variable) {
                    $isMain = isset($variable['Variable']) && $SenderID === $variable['Variable'];
                    $isSecond = !$isMain && isset($variable['SecondVariable']) && $SenderID === $variable['SecondVariable'];
                    if (!$isMain && !$isSecond) {
                        continue;
                    }
                    if (!$this->hasValueChangedAndRemember($SenderID)) {
                        continue;
                    }
                    if ($fullMessage === null) {
                        $fullMessage = $this->GetFullUpdateMessage();
                    }
                    $fullArray = json_decode($fullMessage, true);
                    $minimal = $isMain
                        ? $this->buildMinimalVarUpdateForVariable($fullArray, $SenderID)
                        : $this->buildMinimalVarUpdateForSecondVariable($fullArray, $SenderID);
                    $this->sendSplitMinimalUpdate($minimal, $fullMessage);
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
        // TEMPORÄR (Refactoring-Regressionstest): Payload-Snapshot in tests/snapshots/_live/ schreiben.
        // Wird vor dem Release wieder entfernt.
        if ($Ident === 'RefactorDebugSnapshot') {
            $dir = __DIR__ . '/../tests/snapshots/_live';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents($dir . '/' . $this->InstanceID . '.json', $this->GetDebugPayload());
            return;
        }

        // Prüfe zuerst auf spezielle Aktionen
        if ($Ident === 'UpdateDisplayTypeFields') {
            $this->UpdateDisplayTypeVisibility($value, $this->InstanceID);
            return;
        }
        
        // Script-Buttons unterstützen: Ident kann 'script_<ID>' sein → Script ausführen
        if (is_string($Ident) && strpos($Ident, 'script_') === 0) {
            $scriptId = intval(substr($Ident, 7));
            if ($scriptId > 0 && function_exists('IPS_ScriptExists') && IPS_ScriptExists($scriptId)) {
                try {
                    IPS_RunScript($scriptId);
                } catch (Throwable $e) {
                    $this->LogCaughtThrowable(__FUNCTION__ . ':RunScriptByPrefix', $e);
                }
            }
            return;
        }
        
        // Nachrichten von der HTML-Darstellung schicken immer den Ident passend zur Eigenschaft und im Wert die Differenz, welche auf die Variable gerechnet werden soll
        $variableID = $Ident;
        if (!IPS_VariableExists($variableID)) {
            // Falls eine Script-ID direkt gesendet wurde (z. B. numerisch), führe Script aus
            $maybeScriptId = intval($Ident);
            if ($maybeScriptId > 0 && function_exists('IPS_ScriptExists') && IPS_ScriptExists($maybeScriptId)) {
                try {
                    IPS_RunScript($maybeScriptId);
                } catch (Throwable $e) {
                    $this->LogCaughtThrowable(__FUNCTION__ . ':RunScriptByIdent', $e);
                }
            }
            return;
        }

        // Ermittle Variablentyp für unterschiedliche Behandlung
        $variable = IPS_GetVariable($variableID);
        $variableType = $variable['VariableType'];

        if ($variableType === VARIABLETYPE_BOOLEAN) {
            // Boolean-Variable: Toggle-Verhalten (wie bisher)
            $newValue = !GetValue($variableID);
            RequestAction($variableID, $newValue);
        } else if ($variableType === VARIABLETYPE_INTEGER) {
            // Integer-Variable: Verwende den übergebenen Wert direkt (für Multi-Button-Interface)
            $newValue = intval($value);
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
            RequestAction($variableID, $newValue);
        } else {
            // Andere Variablentypen: Fallback auf Toggle-Verhalten
            $newValue = !GetValue($variableID);
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
        


         // Formulardaten lesen und Statusmapping Array für Bild und Farbe erstellen
        $assoziationsArray = json_decode($this->ReadPropertyString('ProfilAssoziazionen'), true);
        $statusMappingImage = [];
        $statusMappingColor = [];
        foreach ($assoziationsArray as $item) {
            $statusMappingImage[$item['AssoziationValue']] = $item['Bildauswahl'];
                      
            $statusMappingColor[$item['AssoziationValue']] = $item['StatusColor'] === -1 ? "" : sprintf('%06X', $item['StatusColor']);
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



    public function UpdateList(int $StatusID)
    {
        $listData = []; // Hier sammeln Sie die Daten für Ihre Liste
    
    $id = $StatusID;

    // Prüfen, ob die übergebene ID einer existierenden Variable entspricht
    if (IPS_VariableExists($id)) {
        $variable = IPS_GetVariable($id);
        $variableType = $variable['VariableType'];
        
        // Alle Variablentypen über GetVariableAssociations (nutzt IPS_GetVariablePresentation + Profil-Fallback)
        $associations = $this->GetVariableAssociations($id, $variableType);
        
        // Konvertiere Associations zu ListData Format
        if (!empty($associations)) {
            foreach ($associations as $association) {
                $hasIcon = isset($association['icon']) && !empty($association['icon']);
                $listData[] = [
                    'AssoziationName' => $association['name'],
                    'AssoziationValue' => $association['value'],
                    'Bildauswahl' => $hasIcon ? 'symcon_icon' : 'none',
                    'EigenesBild' => 0,
                    'SymconIcon' => $hasIcon ? $association['icon'] : '',
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
    
    /**
     * Feld-Sichtbarkeiten je Darstellungsart für die VariablesList-Bearbeitung.
     * Bewusste Eigenheiten des bisherigen Verhaltens bleiben erhalten:
     * - 'button' setzt UseSecondVariableAsTarget nicht (bleibt im zuletzt gesetzten Zustand)
     * - 'default' lässt Variable, FontSize und TextColor unangetastet
     * - OpenObjectId wird bei 'button' dynamisch gesetzt (SelectObject erst ab Kernel > 8.1)
     */
    private const DISPLAY_TYPE_FIELD_VISIBILITY = [
        'text' => [
            'ShowIcon' => true, 'ShowLabel' => true, 'ShowValue' => true,
            'Variable' => true, 'ScriptID' => false,
            'Label' => true, 'FontSize' => true, 'TextColor' => true,
            'ProgressColor1' => false, 'ProgressColor2' => false,
            'SliderColor1' => false, 'SliderColor2' => false,
            'SecondVariable' => false, 'SecondVariableShowIcon' => false,
            'SecondVariableShowLabel' => false, 'SecondVariableShowValue' => false,
            'SecondVariableLabel' => false, 'UseSecondVariableAsTarget' => false,
            'SecondVariablePopupButton' => false,
            'ButtonWidth' => false, 'boolButtonColor' => false,
            'ShowBorderLine' => true, 'VerticalAlignment' => true,
            'OpenObjectId' => false,
            'ImageMedia' => false, 'ImageWidth' => false, 'ImageBorderRadius' => false,
        ],
        'image' => [
            'ShowIcon' => false, 'ShowLabel' => false, 'ShowValue' => false,
            'Variable' => false, 'ScriptID' => false,
            'Label' => false, 'FontSize' => false, 'TextColor' => false,
            'ProgressColor1' => false, 'ProgressColor2' => false,
            'SliderColor1' => false, 'SliderColor2' => false,
            'SecondVariable' => false, 'SecondVariableShowIcon' => false,
            'SecondVariableShowLabel' => false, 'SecondVariableShowValue' => false,
            'SecondVariableLabel' => false, 'UseSecondVariableAsTarget' => false,
            'SecondVariablePopupButton' => false,
            'ButtonWidth' => false, 'boolButtonColor' => false,
            'ShowBorderLine' => false, 'VerticalAlignment' => true,
            'OpenObjectId' => false,
            'ImageMedia' => true, 'ImageWidth' => true, 'ImageBorderRadius' => true,
        ],
        'progress' => [
            'ShowIcon' => true, 'ShowLabel' => true, 'ShowValue' => true,
            'Variable' => true, 'ScriptID' => false,
            'Label' => true, 'FontSize' => true, 'TextColor' => true,
            'ProgressColor1' => true, 'ProgressColor2' => true,
            'SliderColor1' => false, 'SliderColor2' => false,
            'SecondVariable' => true, 'SecondVariableShowIcon' => true,
            'SecondVariableShowLabel' => true, 'SecondVariableShowValue' => true,
            'SecondVariableLabel' => true, 'UseSecondVariableAsTarget' => true,
            'SecondVariablePopupButton' => true,
            'ButtonWidth' => false, 'boolButtonColor' => false,
            'ShowBorderLine' => false, 'VerticalAlignment' => false,
            'OpenObjectId' => false,
            'ImageMedia' => false, 'ImageWidth' => false, 'ImageBorderRadius' => false,
        ],
        'slider' => [
            'ShowIcon' => true, 'ShowLabel' => true, 'ShowValue' => true,
            'Variable' => true, 'ScriptID' => false,
            'Label' => true, 'FontSize' => true, 'TextColor' => true,
            'ProgressColor1' => false, 'ProgressColor2' => false,
            'SliderColor1' => true, 'SliderColor2' => true,
            'SecondVariable' => false, 'SecondVariableShowIcon' => false,
            'SecondVariableShowLabel' => false, 'SecondVariableShowValue' => false,
            'SecondVariableLabel' => false, 'UseSecondVariableAsTarget' => false,
            'SecondVariablePopupButton' => false,
            'ButtonWidth' => false, 'boolButtonColor' => false,
            'ShowBorderLine' => false, 'VerticalAlignment' => true,
            'OpenObjectId' => false,
            'ImageMedia' => false, 'ImageWidth' => false, 'ImageBorderRadius' => false,
        ],
        'button' => [
            'ShowIcon' => true, 'ShowLabel' => true, 'ShowValue' => true,
            'Variable' => true, 'ScriptID' => true,
            'Label' => true, 'FontSize' => true, 'TextColor' => true,
            'ProgressColor1' => false, 'ProgressColor2' => false,
            'SliderColor1' => false, 'SliderColor2' => false,
            'SecondVariable' => false, 'SecondVariableShowIcon' => false,
            'SecondVariableShowLabel' => false, 'SecondVariableShowValue' => false,
            'SecondVariableLabel' => false,
            'SecondVariablePopupButton' => false,
            'ButtonWidth' => true, 'boolButtonColor' => true,
            'ShowBorderLine' => false, 'VerticalAlignment' => true,
            'ImageMedia' => false, 'ImageWidth' => false, 'ImageBorderRadius' => false,
        ],
        'default' => [
            'ShowIcon' => false, 'ShowLabel' => false, 'ShowValue' => false,
            'ScriptID' => false, 'Label' => false,
            'ProgressColor1' => false, 'ProgressColor2' => false,
            'SliderColor1' => false, 'SliderColor2' => false,
            'SecondVariable' => false, 'SecondVariableShowIcon' => false,
            'SecondVariableShowLabel' => false, 'SecondVariableShowValue' => false,
            'SecondVariableLabel' => false,
            'SecondVariablePopupButton' => false,
            'ButtonWidth' => false, 'boolButtonColor' => false,
            'ShowBorderLine' => false, 'VerticalAlignment' => false,
            'OpenObjectId' => false,
            'ImageMedia' => false, 'ImageWidth' => false, 'ImageBorderRadius' => false,
        ],
    ];

    public function UpdateDisplayTypeVisibility(string $displayType, ?int $rowId = null)
    {
        $fields = self::DISPLAY_TYPE_FIELD_VISIBILITY[$displayType] ?? self::DISPLAY_TYPE_FIELD_VISIBILITY['default'];
        foreach ($fields as $field => $visible) {
            $this->UpdateFormField($field, 'visible', $visible);
        }
        // SelectObject steht erst ab Kernel > 8.1 zur Verfügung
        if ($displayType === 'button') {
            $supportsSelectObject = ((float)IPS_GetKernelVersion() > 8.1);
            $this->UpdateFormField('OpenObjectId', 'visible', $supportsSelectObject);
        }
    }



    // TEMPORÄR (Refactoring-Regressionstest): liefert das volle Visualisierungs-Payload
    // inkl. generierter Assets. Wird vor dem Release wieder entfernt.
    public function GetDebugPayload()
    {
        return json_encode([
            'payload' => json_decode($this->GetFullUpdateMessage(), true),
            'assets'  => $this->GenerateAssets()
        ]);
    }

}
?>