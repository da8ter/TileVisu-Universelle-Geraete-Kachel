<?php

declare(strict_types=1);

namespace UDT;

/**
 * Farbe zum aktuellen Wert einer Variable aus ihrer Darstellung (Aufzählung, Schalter, Wertanzeige mit
 * Optionen oder Intervallen) oder ihrem Profil – für Text-Zeilen mit „Farbe aus Darstellung“.
 */
trait ValueColorTrait
{
    /** '#RRGGBB' oder null, wenn die Darstellung für den aktuellen Wert keine Farbe kennt. */
    private function ValueColor(int $variableId): ?string
    {
        if (!IPS_VariableExists($variableId)) {
            return null;
        }
        $type = IPS_GetVariable($variableId)['VariableType'];
        $colors = [];
        foreach ($this->GetVariableAssociations($variableId, $type) ?? [] as $a) {
            if (array_key_exists('value', $a)) {
                $colors[] = [$a['value'], $a['color'] ?? null];
            }
        }
        // Wertanzeige mit Optionen: Farbe steht dort als ColorActive/ColorValue (nicht als Color wie bei der Aufzählung)
        $presentation = $this->resolvePresentationArray($variableId, __FUNCTION__);
        $options = $presentation['OPTIONS'] ?? null;
        $options = is_string($options) ? json_decode($options, true) : $options;
        foreach (is_array($options) ? $options : [] as $o) {
            if (is_array($o) && array_key_exists('Value', $o) && !empty($o['ColorActive']) && (int)($o['ColorValue'] ?? -1) >= 0) {
                $colors[] = [$o['Value'], '#' . sprintf('%06X', (int)$o['ColorValue'])];
            }
        }
        return $this->MatchValueColor($colors, $this->CurrentValue($variableId));
    }

    /** Exakter Treffer, bei Zahlen sonst der größte Wert darunter (wie Intervalle); nur Einträge mit Farbe. */
    private function MatchValueColor(array $colors, mixed $value): ?string
    {
        $numeric = is_int($value) || is_float($value);
        $best = null;
        foreach ($colors as [$v, $color]) {
            if ($color === null) {
                continue;
            }
            if (is_bool($value) ? (bool)$v === $value : $v == $value) {
                return $color;
            }
            if ($numeric && is_numeric($v) && $v <= $value && ($best === null || $v > $best[0])) {
                $best = [$v, $color];
            }
        }
        return $best[1] ?? null;
    }
}
