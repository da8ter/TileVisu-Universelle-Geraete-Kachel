<?php

declare(strict_types=1);

namespace UDT;

/**
 * Farben zum aktuellen Wert einer Variable aus ihrer Darstellung – für Text-Zeilen mit „Farbe aus Darstellung“.
 * Farbe: je Wert aus Aufzählung, Schalter, Optionen oder Intervallen, sonst die Standardfarbe (COLOR) der
 * Wertanzeige, sonst das Profil. Inhaltsfarbe (Schrift im farbigen Feld): je Wert (ContentColorValue), sonst
 * CONTENT_COLOR.
 */
trait ValueColorTrait
{
    /** ['color' => '#RRGGBB'|null, 'content' => '#RRGGBB'|null] */
    private function ValueColors(int $variableId): array
    {
        $none = ['color' => null, 'content' => null];
        if (!IPS_VariableExists($variableId)) {
            return $none;
        }
        $hex = static fn ($v): ?string => is_numeric($v) && (int)$v >= 0 ? '#' . sprintf('%06X', (int)$v) : null;
        $type = IPS_GetVariable($variableId)['VariableType'];
        $value = $this->CurrentValue($variableId);
        $presentation = $this->resolvePresentationArray($variableId, __FUNCTION__);

        // Je Wert: Optionen (Wertanzeige: ColorActive/ColorValue, Aufzählung: Color) und Intervalle
        $colors = [];
        $contents = [];
        $decode = static fn ($list): array => is_array($list = is_string($list) ? json_decode($list, true) : $list) ? $list : [];
        foreach ($decode($presentation['OPTIONS'] ?? null) as $o) {
            if (!is_array($o) || !array_key_exists('Value', $o)) {
                continue;
            }
            $colors[] = [$o['Value'], !empty($o['ColorActive']) ? $hex($o['ColorValue'] ?? -1) : $hex($o['Color'] ?? -1)];
            $contents[] = [$o['Value'], !empty($o['ContentColorActive']) ? $hex($o['ContentColorValue'] ?? -1) : null];
        }
        if (!empty($presentation['INTERVALS_ACTIVE'])) {
            foreach ($decode($presentation['INTERVALS'] ?? null) as $i) {
                if (is_array($i) && isset($i['IntervalMinValue'])) {
                    $colors[] = [$i['IntervalMinValue'], !empty($i['ColorActive']) ? $hex($i['ColorValue'] ?? -1) : null];
                    $contents[] = [$i['IntervalMinValue'], !empty($i['ContentColorActive']) ? $hex($i['ContentColorValue'] ?? -1) : null];
                }
            }
        }
        // Profil und Schalter-Darstellung (COLOR_TRUE/FALSE) liefert die vorhandene Assoziations-Logik
        foreach ($this->GetVariableAssociations($variableId, $type) ?? [] as $a) {
            if (array_key_exists('value', $a)) {
                $colors[] = [$a['value'], $a['color'] ?? null];
            }
        }
        return [
            'color' => $this->MatchValueColor($colors, $value) ?? $hex($presentation['COLOR'] ?? -1),
            'content' => $this->MatchValueColor($contents, $value) ?? $hex($presentation['CONTENT_COLOR'] ?? -1),
        ];
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
