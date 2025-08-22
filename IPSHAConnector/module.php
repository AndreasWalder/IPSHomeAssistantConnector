<?php
declare(strict_types=1);

class HAConnector extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString('BaseUrl', '');
        $this->RegisterPropertyString('Token', '');
        $this->RegisterPropertyString('DefaultEntity', '');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
    }

    // ---- Public convenience ----
    public function CallService(string $domain, string $service, array $payload)
    {
        $url = rtrim($this->ReadPropertyString('BaseUrl'), '/') . "/api/services/{$domain}/{$service}";
        return $this->doPost($url, $payload);
    }

    public function TurnOn(string $entity = '', ?int $brightnessPct = null, ?float $transition = null)
    {
        $entity = $this->resolveEntity($entity);
        $payload = ['entity_id' => $entity];
        if ($brightnessPct !== null) {
            $payload['brightness'] = $this->pctToBrightness($brightnessPct);
        }
        if ($transition !== null) {
            $payload['transition'] = $transition;
        }
        return $this->CallService('light', 'turn_on', $payload);
    }

    public function TurnOff(string $entity = '')
    {
        $entity = $this->resolveEntity($entity);
        return $this->CallService('light', 'turn_off', ['entity_id' => $entity]);
    }

    public function SetPercent(string $entity, int $pct)
    {
        $pct = max(0, min(100, $pct));
        if ($pct === 0) {
            return $this->TurnOff($entity);
        }
        return $this->TurnOn($entity, $pct, null);
    }

    // ---- Actions for form buttons ----
    public function TestTurnOn()
    {
        $entity = $this->ReadPropertyString('DefaultEntity');
        if ($entity === '') {
            echo "Bitte Default Entity in den Instanz-Eigenschaften setzen.";
            return false;
        }
        return $this->TurnOn($entity, 100);
    }

    public function TestTurnOff()
    {
        $entity = $this->ReadPropertyString('DefaultEntity');
        if ($entity === '') {
            echo "Bitte Default Entity in den Instanz-Eigenschaften setzen.";
            return false;
        }
        return $this->TurnOff($entity);
    }

    public function TestDim50()
    {
        $entity = $this->ReadPropertyString('DefaultEntity');
        if ($entity === '') {
            echo "Bitte Default Entity in den Instanz-Eigenschaften setzen.";
            return false;
        }
        return $this->SetPercent($entity, 50);
    }

    // ---- Helpers ----
    private function resolveEntity(string $entity): string
    {
        if ($entity !== '') {
            return $entity;
        }
        $def = $this->ReadPropertyString('DefaultEntity');
        if ($def === '') {
            throw new Exception('No entity provided and DefaultEntity not set');
        }
        return $def;
    }

    private function pctToBrightness(int $pct): int
    {
        $raw = (int)round(max(0, min(100, $pct)) * 2.55);
        return max(13, min(255, $raw)); // kleine Untergrenze für robuste Treiber
    }

    private function doPost(string $url, array $payload)
    {
        $token = trim($this->ReadPropertyString('Token'));
        if ($token === '') {
            throw new Exception('Token is empty');
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($body)) {
            $this->SendDebug('HA JSON ERROR', json_last_error_msg(), 0);
            throw new Exception('JSON encoding failed');
        }

        // Symcon HTTP
        $options = [
            'Timeout' => (int)5000,
            'Headers' => array_map('strval', [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ]),
            'Method'  => 'POST',
            'Body'    => (string)$body
        ];

        $this->SendDebug('HA POST URL', $url, 0);
        $this->SendDebug('HA POST Body', $body, 0);
        $this->SendDebug('HA Options Type', gettype($options), 0);
        $this->SendDebug('HA Headers Type', gettype($options['Headers']), 0);
        $this->SendDebug('HA Body Type', gettype($options['Body']), 0);

        $result = @Sys_GetURLContentEx($url, $options);
        if ($result !== false && $result !== null) {
            return $result;
        }

        // cURL-Fallback (falls Symcon-Optionsformat abgelehnt wird)
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT        => 5
            ]);
            $resp = curl_exec($ch);
            $err  = curl_error($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $this->SendDebug('HA cURL Code', (string)$code, 0);
            if ($code >= 200 && $code < 300 && $resp !== false) {
                return $resp;
            }
            $this->SendDebug('HA cURL Error', $err, 0);
        }

        throw new Exception('HTTP request failed');
    }
}
