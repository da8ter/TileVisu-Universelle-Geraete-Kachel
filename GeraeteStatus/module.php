<?php

declare(strict_types=1);

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
require_once __DIR__ . '/libs/FormTrait.php';

class UniversalDeviceTile extends IPSModule
{
    use \UDT\ImageHookTrait;
    use \UDT\IconTrait;
    use \UDT\PresentationTrait;
    use \UDT\PayloadTrait;
    use \UDT\PayloadVariablesTrait;
    use \UDT\FormTrait;

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
        $this->RegisterPropertyFloat("BildBreite", 20.0);
        $this->RegisterPropertyString("BildPosition", "left");
        $this->RegisterPropertyBoolean("ShowBorderLine", true);
        $this->RegisterPropertyString("ImageAlignment", "center");
        $this->RegisterPropertyInteger("Bild_An", 0);
        $this->RegisterPropertyInteger("Bild_Aus", 0);
        $this->RegisterPropertyBoolean('BG_Off', true);
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

    private function LogCaughtThrowable(string $context, Throwable $e): void
    {
        $this->SendDebug($context, $e->getMessage(), 0);
    }

    private function IsKernelReady(): bool
    {
        if (!function_exists('IPS_GetKernelRunlevel') || !defined('KR_READY')) {
            return true;
        }

        return IPS_GetKernelRunlevel() == KR_READY;
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
        // Prüfe zuerst auf spezielle Aktionen
        if ($Ident === 'UpdateDisplayTypeFields') {
            $this->UpdateDisplayTypeVisibility((string)$value, $this->InstanceID);
            return;
        }
        
        // Nur Bedienelemente dieser Kachel: fremde Variablen und Scripts sind über die Kachel nicht erreichbar
        if (!isset($this->GetRequestableIdents()[(string)$Ident])) {
            $this->SendDebug(__FUNCTION__, 'Abgelehnt, keine Zeile dieser Kachel: ' . $Ident, 0);
            return;
        }

        // Script-Buttons: Ident 'script_<ID>' → Script ausführen
        if (strpos((string)$Ident, 'script_') === 0) {
            $scriptId = intval(substr((string)$Ident, 7));
            if (IPS_ScriptExists($scriptId)) {
                try {
                    IPS_RunScript($scriptId);
                } catch (Throwable $e) {
                    $this->LogCaughtThrowable(__FUNCTION__ . ':RunScriptByPrefix', $e);
                }
            }
            return;
        }
        
        // Nachrichten von der HTML-Darstellung schicken immer den Ident passend zur Eigenschaft und im Wert die Differenz, welche auf die Variable gerechnet werden soll
        $variableID = (int)$Ident;
        if (!IPS_VariableExists($variableID)) {
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

    /**
     * Idents, die das Frontend per requestAction senden darf: die Variable jeder Button- und
     * Regler-Zeile, 'script_<ID>' jedes Script-Buttons (Zeile ohne Variable).
     */
    private function GetRequestableIdents(): array
    {
        $idents = [];
        $rows = json_decode($this->ReadPropertyString('VariablesList'), true);
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $displayType = $row['DisplayType'] ?? 'text';
            $variableID = (int)($row['Variable'] ?? 0);
            $scriptID = (int)($row['ScriptID'] ?? 0);
            if ($variableID > 0 && IPS_VariableExists($variableID)) {
                if ($displayType === 'button' || $displayType === 'slider') {
                    $idents[(string)$variableID] = true;
                }
            } elseif ($displayType === 'button' && $scriptID > 0) {
                $idents['script_' . $scriptID] = true;
            }
        }
        return $idents;
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

        // Füge statisches HTML aus Datei hinzu
        $module = file_get_contents(__DIR__ . '/module.html');

        // Gebe alles zurück.
        // Wichtig: $initialHandling nach hinten, da die Funktion handleMessage erst im HTML definiert wird
        return $module . $assets . $initialHandling;
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



}
?>