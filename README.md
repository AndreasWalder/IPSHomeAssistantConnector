# IPSHomeAssistantConnector

Ein modernes, flexibles IP-Symcon-Modul zur Ansteuerung von Home Assistant über die REST-API – mit zentraler Konfiguration von Base-URL und Token, direkter Steuerung von Lichtern, Covern und beliebigen Services, sowie Test-Buttons direkt im Instanzformular.

## ✨ Features
- Zentrale Konfiguration von Base-URL und Token
- Generische Service Calls: `CallService(domain, service, payload)`
- Convenience-Methoden für Lights (TurnOn, TurnOff, SetPercent)
- Unterstützung für Cover-Services (open, close, stop, tilt)
- Test-Buttons im Formular (On, Off, Dim 50 %)

## 🛠️ Installation
Repository klonen in deinen IP-Symcon-Module-Ordner:

```bash
git clone https://github.com/AndreasWalder/IPSHomeAssistantConnector
```

Unter Windows:
```
C:\\ProgramData\\Symcon\\modules\\IPSHomeAssistantConnector
```

Symcon-Dienst neu starten und Modul in der Verwaltungskonsole hinzufügen.

## 🚀 Beispiele
```php
$instanzID = 12345; // ID deiner HA Connector Instanz

HAC_TurnOn($instanzID, 100);
HAC_TurnOff($instanzID);
HAC_SetPercent($instanzID, 'light.led_dach_led_dach', 25);
HAC_CallService($instanzID, 'cover', 'open_cover', ['entity_id' => 'cover.vorhang_vorne']);
```

## 🧑‍💻 Autor & Lizenz
Erstellt von **Andreas Walder**  
MIT-Lizenz (LICENSE liegt bei)
