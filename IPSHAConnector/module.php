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
        return max(13, min(255, $raw));
    }

    private function doPost(string $url, array $payload)
    {
        $token = $this->ReadPropertyString('Token');
        if ($token === '') {
            throw new Exception('Token is empty');
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $options = [
            'Timeout' => 5000,
            'Headers' => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ],
            'Method'  => 'POST',
            'Body'    => $body
        ];

        $this->SendDebug('HA POST', $url, 0);
        $this->SendDebug('HA Body', $body, 0);

        $result = Sys_GetURLContentEx($url, $options);
        if ($result === false || $result === null) {
            $err = print_r(error_get_last(), true);
            $this->SendDebug('HA Error', $err, 0);
            throw new Exception('HTTP request failed');
        }
        return $result;
    }
}
?>