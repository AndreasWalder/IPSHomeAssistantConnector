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
    
    public function TurnOn(?int $brightnessPct = null, ?float $transition = null, string $entity = '')
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
    
        // ----- Symcon HTTP -----
        $options = [
            'Timeout' => (int)5000,
            'Headers' => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ],
            'Method'  => 'POST',
            'Body'    => $body
        ];
    
        $this->SendDebug('HA POST URL', $url, 0);
        $this->SendDebug('HA POST Body', $body, 0);
    
        $result = @Sys_GetURLContentEx($url, $options);
        if (is_string($result) && $result !== '') {
            $this->SendDebug('HA POST Result', $result, 0);
            return $result;
        }
    
        $err1 = error_get_last();
        $this->SendDebug('HA POST Sys_GetURLContentEx failed', print_r($err1, true), 0);
    
        // ----- cURL Fallback -----
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
            $curlErr  = curl_error($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
    
            $this->SendDebug('HA cURL HTTP', (string)$httpCode, 0);
            $this->SendDebug('HA cURL Body', is_string($resp) ? $resp : '<no body>', 0);
            if ($curlErr) {
                $this->SendDebug('HA cURL Error', $curlErr, 0);
            }
    
            if ($httpCode >= 200 && $httpCode < 300 && is_string($resp)) {
                return $resp;
            }
    
            // Mehr Aussagekraft in der Exception:
            if ($httpCode === 401) {
                throw new Exception('HTTP 401 Unauthorized: Token ungültig oder fehlt.');
            } elseif ($httpCode === 403) {
                throw new Exception('HTTP 403 Forbidden: Zugriff verweigert (evtl. falsche URL/Proxy/CORS).');
            } elseif ($httpCode === 404) {
                throw new Exception('HTTP 404 Not Found: Endpoint falsch (prüfe BaseUrl/Port/Pfad).');
            } elseif ($httpCode === 0) {
                throw new Exception('HTTP 0: Keine Verbindung (Netzwerk/Firewall/Host nicht erreichbar).');
            } else {
                throw new Exception('HTTP ' . $httpCode . ': ' . substr((string)$resp, 0, 300));
            }
        }
    
        throw new Exception('HTTP request failed (neither Sys_GetURLContentEx nor cURL succeeded).');
    }

    public function TestConnection()
    {
        $base = rtrim($this->ReadPropertyString('BaseUrl'), '/');
        $token = trim($this->ReadPropertyString('Token'));
        if ($base === '' || $token === '') {
            echo "BaseUrl oder Token leer.";
            return false;
        }
        // /api liefert {"message": "API running."} bei Erfolg
        $url = $base . '/api';
        $options = [
            'Timeout' => 3000,
            'Headers' => [
                'Authorization: Bearer ' . $token
            ],
            'Method'  => 'GET'
        ];
        $this->SendDebug('HA GET URL', $url, 0);
        $res = @Sys_GetURLContentEx($url, $options);
        if (!is_string($res) || $res === '') {
            // cURL GET fallback
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_HTTPGET        => true,
                    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_TIMEOUT        => 5
                ]);
                $resp = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err  = curl_error($ch);
                curl_close($ch);
                $this->SendDebug('HA PING HTTP', (string)$code, 0);
                $this->SendDebug('HA PING Body', is_string($resp)?$resp:'<no body>', 0);
                if ($code >= 200 && $code < 300) { echo "OK"; return true; }
                echo "Fehler: HTTP ".$code." ".$err;
                return false;
            }
            echo "Fehler: Sys_GetURLContentEx GET fehlgeschlagen.";
            return false;
        }
        $this->SendDebug('HA GET Body', $res, 0);
        echo "OK";
        return true;
    }

    public function TestStatesLight(string $entity)
    {
        $base = rtrim($this->ReadPropertyString('BaseUrl'), '/');
        $token = trim($this->ReadPropertyString('Token'));
        if ($base === '' || $token === '') { echo "BaseUrl/Token leer"; return false; }
        if ($entity === '') { echo "entity leer"; return false; }
    
        $url = $base . '/api/states/' . $entity;
        $options = [
            'Timeout' => 3000,
            'Headers' => [
                'Authorization: Bearer ' . $token
            ],
            'Method'  => 'GET'
        ];
        $this->SendDebug('HA STATES URL', $url, 0);
        $res = @Sys_GetURLContentEx($url, $options);
        $this->SendDebug('HA STATES Body', is_string($res)?$res:'<no body>', 0);
        echo is_string($res)?$res:'<no body>';
        return $res;
    }
    
}
