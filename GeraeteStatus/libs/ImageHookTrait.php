<?php

declare(strict_types=1);

namespace UDT;

/**
 * Bildauslieferung über den WebHook /hook/udtimages/<InstanceID>:
 * Hook-Registrierung im WebHook Control, Token-geschützte Auslieferung von
 * Medienobjekten und mitgelieferten Assets sowie Asset-/URL-Generierung.
 */
trait ImageHookTrait
{
    private function GetWebhookControlInstanceId(): int
    {
        if (!function_exists('IPS_GetInstanceListByModuleID')) {
            return 0;
        }

        $webhookModuleId = '{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}';
        $ids = IPS_GetInstanceListByModuleID($webhookModuleId);
        if (!is_array($ids) || count($ids) === 0) {
            return 0;
        }

        return (int)$ids[0];
    }

    // Hilfsmethode zur Asset-Generierung für Custom Images und Fallback-Assets
    private function GenerateAssets(): array {
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
        $whId = $this->GetWebhookControlInstanceId();
        if ($whId <= 0) {
            $this->LogMessage('WebHook Control not found. Skipping hook registration.', KL_WARNING);
            return;
        }

        $instToken = $this->ReadAttributeString('HookToken');
        if ($instToken === '') {
            try {
                $instToken = bin2hex(random_bytes(16));
            } catch (\Throwable $e) {
                $instToken = substr(sha1(uniqid('', true)), 0, 32);
                $this->LogCaughtThrowable(__FUNCTION__ . ':tokenFallback', $e);
            }
            $this->WriteAttributeString('HookToken', $instToken);
        }

        $hooksRaw = IPS_GetProperty($whId, 'Hooks');
        $hooks = Helpers::decodeJsonArray($hooksRaw, __FUNCTION__ . ':Hooks');
        if (!is_array($hooks)) {
            if (trim((string)$hooksRaw) !== '') {
                $this->LogMessage('Invalid webhook hooks JSON. Registration skipped to avoid overwriting hooks.', KL_ERROR);
                return;
            }
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

        IPS_SetProperty($whId, 'Hooks', json_encode(array_values($hooks)));
        IPS_ApplyChanges($whId);
    }

    private function UnregisterUDTImageHook(string $hookPath): void
    {
        $whId = $this->GetWebhookControlInstanceId();
        if ($whId <= 0) {
            return;
        }

        $hooksRaw = IPS_GetProperty($whId, 'Hooks');
        $hooks = Helpers::decodeJsonArray($hooksRaw, __FUNCTION__ . ':Hooks');
        if (!is_array($hooks)) {
            return;
        }

        $changed = false;
        $filteredHooks = [];
        foreach ($hooks as $hook) {
            if (!is_array($hook)) {
                continue;
            }

            $isCurrentHook = (($hook['Hook'] ?? '') === $hookPath);
            if ($isCurrentHook) {
                $changed = true;
                continue;
            }

            $filteredHooks[] = $hook;
        }

        if (!$changed) {
            return;
        }

        IPS_SetProperty($whId, 'Hooks', json_encode(array_values($filteredHooks)));
        IPS_ApplyChanges($whId);
    }

    /**
     * Die Bildmedien, die diese Kachel zeigt: Standardbild, Hintergrundbild, eigene Bilder der
     * Status-Zuordnungen und Bildzeilen. Nur diese liefert der Bild-Hook aus.
     */
    private function GetConfiguredMediaIds(): array
    {
        $ids = [$this->ReadPropertyInteger('DefaultImage'), $this->ReadPropertyInteger('bgImage')];
        $associations = json_decode($this->ReadPropertyString('ProfilAssoziazionen'), true);
        foreach (is_array($associations) ? $associations : [] as $association) {
            if (is_array($association) && ($association['Bildauswahl'] ?? '') === 'custom') {
                $ids[] = (int)($association['EigenesBild'] ?? 0);
            }
        }
        $rows = json_decode($this->ReadPropertyString('VariablesList'), true);
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row) && ($row['DisplayType'] ?? 'text') === 'image') {
                $ids[] = (int)($row['ImageMedia'] ?? 0);
            }
        }
        return array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0 && IPS_MediaExists($id))));
    }

    /**
     * Größte Bild-Antwort, die Symcon unverändert ausliefert: die Summe der Ausgabe einer Anfrage darf
     * ScriptOutputBufferLimit (ab Werk 1 MiB) nicht überschreiten, sonst ersetzt Symcon die Antwort
     * durch einen Fehlertext (bei HTTP 200). 1 KiB Reserve für unerwartete Ausgaben.
     */
    private function GetHookBodyLimit(): int
    {
        $limit = 1048576;
        try {
            $option = IPS_GetOption('ScriptOutputBufferLimit');
            if (is_numeric($option) && (int)$option > 0) {
                $limit = (int)$option;
            }
        } catch (\Throwable $e) {
            $this->LogCaughtThrowable(__FUNCTION__, $e);
        }
        return max(0, $limit - 1024);
    }

    /** Meldet ein Bild über der Ausgabegrenze einmal je Medium (bis zum nächsten Kernelstart), nicht bei jedem Abruf. */
    private function ReportOversizeImage(int $mediaId, int $size, int $limit): void
    {
        $reported = json_decode($this->GetBuffer('OversizeImages'), true);
        $reported = is_array($reported) ? $reported : [];
        if (in_array($mediaId, $reported, true)) {
            return;
        }
        $reported[] = $mediaId;
        $this->SetBuffer('OversizeImages', json_encode($reported));
        $this->LogMessage(sprintf($this->Translate('Image #%d is too large for the tile (%d bytes, limit %d bytes) and is shown as a placeholder. Use a smaller image or raise ScriptOutputBufferLimit.'),
            $mediaId, $size, $limit), KL_WARNING);
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

    protected function ProcessHookData(): void
    {
        // Pfade relativ zum Modulordner (diese Datei liegt in libs/)
        $assetsDir = __DIR__ . '/../assets';
        $placeholder = __DIR__ . '/../../imgs/transparent.webp';
        $instT = $this->ReadAttributeString('HookToken');
        $token = isset($_GET['token']) ? (string)$_GET['token'] : '';
        if (!is_string($token) || $token === '' || $instT === '' || !hash_equals($instT, $token)) {
            http_response_code(403);
            return;
        }
        $mid = isset($_GET['mid']) ? (int)$_GET['mid'] : 0;
        if ($mid > 0 && !in_array($mid, $this->GetConfiguredMediaIds(), true)) {
            http_response_code(404);
            $this->SendDebug(__FUNCTION__, 'Medium ' . $mid . ' ist in dieser Kachel nicht konfiguriert', 0);
            return;
        }
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) { ob_end_clean(); }
        }
        header('X-Accel-Buffering: no');
        if (function_exists('ignore_user_abort')) { ignore_user_abort(true); }
        if (function_exists('set_time_limit')) {
            try {
                set_time_limit(0);
            } catch (\Throwable $e) {
                $this->LogCaughtThrowable(__FUNCTION__ . ':set_time_limit', $e);
            }
        }
        $MAX_SIZE = $this->GetHookBodyLimit();
        $detectMime = function(string $bin) {
            return Helpers::detectMimeFromBinary($bin);
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
        $streamFile = function(string $file, string $mime, bool $longCache = false) use ($MAX_SIZE, $sendNotModified, $placeholder, $detectMime) {
            if (!is_file($file) || !is_readable($file)) {
                http_response_code(404);
                $this->LogMessage('UDTImagesHook: File not found or unreadable: ' . $file, KL_ERROR);
                exit;
            }
            $size = filesize($file);
            if ($size === false) {
                http_response_code(500);
                $this->LogMessage('UDTImagesHook: filesize failed: ' . $file, KL_ERROR);
                exit;
            }
            if ($size > $MAX_SIZE) {
                if ($file === $placeholder || !is_file($placeholder)) {
                    http_response_code(413);
                    $this->LogMessage('UDTImagesHook: File too large: ' . $size, KL_ERROR);
                    exit;
                }
                // über der Ausgabegrenze: Platzhalter statt einer von Symcon ersetzten Antwort
                $file = $placeholder;
                $size = (int)filesize($placeholder);
                $mime = $detectMime((string)file_get_contents($placeholder, false, null, 0, 12));
                $longCache = false;
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
                $this->LogMessage('UDTImagesHook: fopen failed: ' . $file, KL_ERROR);
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
        if ($mid > 0 && IPS_MediaExists($mid)) {
            $m = IPS_GetMedia($mid);
            if ($m['MediaType'] === MEDIATYPE_IMAGE) {
                if (!empty($m['MediaFile']) && is_file($m['MediaFile'])) {
                    $fileSize = (int)filesize($m['MediaFile']);
                    if ($fileSize > $MAX_SIZE) {
                        $this->ReportOversizeImage($mid, $fileSize, $MAX_SIZE); // $streamFile liefert den Platzhalter
                    }
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
                    $this->LogMessage('UDTImagesHook: Empty media content mid=' . $mid, KL_ERROR);
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
                    $this->ReportOversizeImage($mid, $decodedLen, $MAX_SIZE);
                    $streamFile($placeholder, 'image/webp', false);
                }
                header('Cache-Control: no-store');
                header('Content-Type: ' . $mime);
                header('Content-Length: ' . max(0, $decodedLen));
                $out = fopen('php://output', 'wb');
                if ($out === false) {
                    http_response_code(500);
                    $this->LogMessage('UDTImagesHook: open php://output failed', KL_ERROR);
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
        $this->LogMessage('UDTImagesHook: Not found', KL_ERROR);
    }
}
