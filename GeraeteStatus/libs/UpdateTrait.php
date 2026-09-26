<?php

declare(strict_types=1);

namespace UDT;

/**
 * Wertänderungen an die offene Kachel (Zeilen-Protokoll): gesendet werden nur die betroffenen Zeilen,
 * jede vollständig und unter ihrem Zeilen-Schlüssel, in einer Nachricht {"rows": [...], …Statusteil}.
 */
trait UpdateTrait
{
    /**
     * VM_UPDATE von $senderID: die Zeilen, die ihn als Variable oder zweite Variable zeigen; ist er die
     * Statusvariable, zusätzlich der Statusteil und alle Balkenzeilen (Balken aktiv/inaktiv).
     * $data[1] meldet, ob sich der Wert geändert hat; ein Schreiben ohne Änderung braucht kein Update.
     */
    private function HandleVariableUpdate(int $senderID, array $data): void
    {
        if (($data[1] ?? true) === false) {
            return;
        }
        $rows = json_decode($this->ReadPropertyString('VariablesList'), true);
        $rows = is_array($rows) ? $rows : [];
        $keys = [];
        foreach ($rows as $key => $row) {
            if (is_array($row) && ((int)($row['Variable'] ?? 0) === $senderID || (int)($row['SecondVariable'] ?? 0) === $senderID)) {
                $keys[] = (int)$key;
            }
        }
        $message = [];
        if ($senderID === $this->ReadPropertyInteger('Status')) {
            $this->appendStatusPayload($message);
            foreach ($rows as $key => $row) {
                if (is_array($row) && ($row['DisplayType'] ?? 'text') === 'progress') {
                    $keys[] = (int)$key;
                }
            }
        }
        $keys = array_values(array_unique($keys));
        sort($keys);
        $progressbarActive = $this->IsProgressbarActive();
        $built = [];
        foreach ($keys as $key) {
            $row = $this->buildRow($key, $rows[$key], $progressbarActive);
            if ($row !== null) {
                $built[] = $row;
            }
        }
        $built = $this->DropUnchangedRows($built);
        if ($built !== []) {
            $message['rows'] = $built;
        }
        if ($message !== []) {
            $this->UpdateVisualizationValue(json_encode($message));
        }
    }

    /**
     * Zeilen, die genau so schon beim Frontend sind, entfallen. Der Puffer merkt je Zeilen-Schlüssel den
     * Fingerabdruck des zuletzt gesendeten Stands (Buffer: nur im Speicher, nicht in settings.json).
     */
    private function DropUnchangedRows(array $rows): array
    {
        $sent = json_decode($this->GetBuffer('SentRows'), true);
        $sent = is_array($sent) ? $sent : [];
        $changed = [];
        foreach ($rows as $row) {
            $key = (string)$row['key'];
            $hash = md5((string)json_encode($row));
            if (($sent[$key] ?? null) !== $hash) {
                $changed[] = $row;
                $sent[$key] = $hash;
            }
        }
        $this->SetBuffer('SentRows', json_encode($sent));
        return $changed;
    }

    /** Ein vollständiger Aufbau (Kachel geöffnet, Konfiguration gespeichert) setzt den Puffer auf genau diese Zeilen. */
    private function RememberSentRows(array $rows): void
    {
        $sent = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['key'])) {
                $sent[(string)$row['key']] = md5((string)json_encode($row));
            }
        }
        $this->SetBuffer('SentRows', json_encode($sent));
    }
}
