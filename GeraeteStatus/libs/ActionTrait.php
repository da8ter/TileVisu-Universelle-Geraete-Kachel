<?php

declare(strict_types=1);

namespace UDT;

/**
 * Bedienung aus der Kachel: requestAction-Idents auf das Ziel einer Zeile auflösen und nur dort wirken.
 * Zeilen-Schlüssel 'row_<key>' (Index in der Variablenliste) bestimmen Zeile und Darstellungsart genau;
 * übergangsweise gelten auch die Variablen-ID und 'script_<ID>' einer bedienbaren Zeile.
 */
trait ActionTrait
{
    /**
     * Ziel eines Idents als [Art, ID, Darstellungsart] mit Art 'script' oder 'variable';
     * null, wenn er keine bedienbare Zeile dieser Kachel trifft.
     */
    private function ResolveActionTarget(string $ident): ?array
    {
        $rows = json_decode($this->ReadPropertyString('VariablesList'), true);
        $rows = is_array($rows) ? $rows : [];
        if (preg_match('/^row_(0|[1-9][0-9]{0,4})$/', $ident, $m) === 1) {
            $row = $rows[(int)$m[1]] ?? null;
            return is_array($row) ? $this->RowActionTarget($row) : null;
        }
        // Übergang, bis das Frontend Zeilen-Schlüssel sendet: Regler-Werte nur rasten, wenn die Variable
        // ausschließlich als Regler dasteht (Multi-Buttons senden Zuordnungswerte)
        $kinds = $this->GetRequestableIdents()[$ident] ?? null;
        if ($kinds === null) {
            return null;
        }
        if (strpos($ident, 'script_') === 0) {
            return ['script', (int)substr($ident, 7), 'button'];
        }
        return ['variable', (int)$ident, ($kinds['slider'] && !$kinds['button']) ? 'slider' : 'button'];
    }

    /** Bedienbares Ziel einer Zeile: Variable einer Button- oder Regler-Zeile, Script eines Script-Buttons. */
    private function RowActionTarget(array $row): ?array
    {
        $displayType = $row['DisplayType'] ?? 'text';
        $variableID = (int)($row['Variable'] ?? 0);
        if ($variableID > 0 && IPS_VariableExists($variableID)) {
            return ($displayType === 'button' || $displayType === 'slider') ? ['variable', $variableID, $displayType] : null;
        }
        $scriptID = (int)($row['ScriptID'] ?? 0);
        return ($displayType === 'button' && $scriptID > 0) ? ['script', $scriptID, 'button'] : null;
    }

    /**
     * Alte Idents (Übergang): die Variable jeder Button- und Regler-Zeile, 'script_<ID>' jedes
     * Script-Buttons; je Ident, ob er als Regler und/oder als Button in der Kachel steht.
     */
    private function GetRequestableIdents(): array
    {
        $idents = [];
        $rows = json_decode($this->ReadPropertyString('VariablesList'), true);
        foreach (is_array($rows) ? $rows : [] as $row) {
            $target = is_array($row) ? $this->RowActionTarget($row) : null;
            if ($target === null) {
                continue;
            }
            [$kind, $id, $displayType] = $target;
            $ident = $kind === 'script' ? 'script_' . $id : (string)$id;
            $kinds = $idents[$ident] ?? ['slider' => false, 'button' => false];
            $kinds[$displayType] = true;
            $idents[$ident] = $kinds;
        }
        return $idents;
    }

    /** Führt die Bedienung auf dem Ziel aus: Script starten oder den Wert an die Variable geben. */
    private function RunActionTarget(array $target, $value): void
    {
        [$kind, $id, $displayType] = $target;
        if ($kind === 'script') {
            if (IPS_ScriptExists($id)) {
                try {
                    IPS_RunScript($id);
                } catch (\Throwable $e) {
                    $this->LogCaughtThrowable(__FUNCTION__, $e);
                }
            }
            return;
        }
        if (!IPS_VariableExists($id)) {
            return;
        }
        switch (IPS_GetVariable($id)['VariableType']) {
            case VARIABLETYPE_BOOLEAN:
                RequestAction($id, !GetValue($id));
                break;
            case VARIABLETYPE_INTEGER:
                RequestAction($id, $displayType === 'slider' ? $this->NormalizeSliderValue($id, $value, true) : intval($value));
                break;
            case VARIABLETYPE_FLOAT:
                RequestAction($id, $this->NormalizeSliderValue($id, $value, false));
                break;
            case VARIABLETYPE_STRING:
                RequestAction($id, strval($value));
                break;
        }
    }

    /**
     * Wert eines Reglers für $variableID: auf die Schrittweite gerastet und auf den Bereich begrenzt.
     * Ein Maximum von 0 ist ein Maximum (-80..0); nur ohne gültigen Bereich (max <= min, etwa 0..0)
     * gilt 0..100 wie im Frontend.
     */
    private function NormalizeSliderValue(int $variableID, $value, bool $integer)
    {
        $bounds = $this->GetProgressMinMax($variableID);
        $min = (float)($bounds['min'] ?? 0);
        $max = (float)($bounds['max'] ?? 100);
        if ($max <= $min) {
            $min = 0.0;
            $max = 100.0;
        }
        $cfg = $this->GetSliderStepAndDigits($variableID);
        $step = (float)($cfg['step'] ?? 0);
        $digits = (int)($cfg['digits'] ?? 0);
        if ($step <= 0) {
            $step = $integer ? 1.0 : ($digits > 0 ? 10 ** -$digits : ($max - $min) / 100);
        }
        $number = is_numeric($value) ? (float)$value : $min;
        $number = $min + round(($number - $min) / $step) * $step;
        $number = min($max, max($min, $number));
        // auf die Genauigkeit der Schrittweite runden (0,05 → 2 Stellen), nicht auf die Anzeige-Stellen
        return $integer ? (int)round($number) : round($number, min(10, max(self::DecimalsOf($step), self::DecimalsOf($min))));
    }

    /** Nachkommastellen einer Zahl aus Konfiguration oder Darstellung (0.05 → 2, 1.0 → 0). */
    private static function DecimalsOf(float $number): int
    {
        $text = rtrim(rtrim(sprintf('%.10F', abs($number)), '0'), '.');
        $dot = strpos($text, '.');
        return $dot === false ? 0 : strlen($text) - $dot - 1;
    }
}
