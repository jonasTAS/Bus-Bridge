# Bus Bridge API

Eine CiviCRM Extension für die API.

## Requirements

- CiviCRM ab Version 5.0
- CiviCRM API v3 (v4 wird noch nicht unterstützt)

## Installation

1. Kopiert die [neueste Release-Version](https://github.com/jonasTAS/Bus-Bridge/releases) unter euer */ext* Verzeichnis von CiviCRM
2. Aktiviert die Extension unter den Systemeinstellungen
3. Fügt einen Aktivitätstyp mit dem Namen *"Automatische Email"* hinzu

## Nutzung

Ihr könnt die Bus Bridge Schnittstelle über den API Explorer benutzen. Dieser findet sich unter *Support > Developer > Api Explorer v3*. Die Entity ist BBContact und die Action heißt submit.

**Contact data**
JSON Format. Contact_type muss mit angegeben werden.

**Activity data**
Zusammen mit dem Kontakt und dessen Gruppen können auch Aktivitäten hinzugefügt werden. Diese müssen die Aktivitätstyp ID, den Betreff und gegebenenfalls das Erstellungsdatum beinhalten. Optional ist auch die status_id konfigurierbar.

**Group data**
JSON Array. Jeder Eintrag beinhaltet den civi-internen Titel (Achtung: NICHT den Namen!) der Gruppe, gefolgt von einem Doppelpunkt und der Art, wie die Gruppe hinzugefügt werden soll:

Es gibt verschiedene Arten, wie der Kontakt zu einer Gruppe hinzugefügt werden kann:
- *Added*: Der Kontakt wird direkt zu der Gruppe hinzugefügt. Dies ist der Standard, wenn die Gruppe ohne Doppelpunkt und Art angegeben wird.
- *Pending*: Die Gruppe wird nur als Pending hinzugefügt. Es wird eine Email verschickt, mit der die Kontaktperson ihren Beitritt zu der Gruppe erst bestätigen kann.
- *Newsletter*: Ähnlich wie Pending, hier erfolgen der Beitritt zur Gruppe und das Verschicken der Email nur, wenn der Parameter "Wants Newsletter" (Siehe weiter unten) auf "Yes" steht.
- *NewAndDoiAccepted*: Diese Gruppe ist beispielsweise zur Nutzung von Welcome Journeys. Diese Gruppe wird nur hinzugefügt, wenn der Nutzer neu erstellt wird, also noch nicht in der Datenbank von CiviCRM vorhanden ist UND "Wants Newsletter" auf "Yes" steht.

**Wants newsletter**
Einfache "Yes/No" Auswahl. Mithilfe dieser Variable wird entschieden, ob der Kontakt zur Newsletter-Gruppe hinzugefügt wird, siehe **Group data** und "Newsletter".



### Beispiel
```
Contact data: {"first_name":"Max", "last_name":"Mustermann", "email":"max.mustermann@gmail.de",  "contact_type":"individual"}
Activity data: [{"activity_type_id": "E-Mail", "subject": "Test", "timestamp": "1616246070"}]
Group data: ["Testnewsletter_1:Newsletter", "Testgruppe_2:Added"]
Wants newsletter: "Yes"
```

## Lizenz

Diese Extension wurde von Jens Schuppe ([Systopia](https://systopia.de)) geschrieben. Sie wurde unter der (GNU Affero Public License 3.0)[www.gnu.org/licenses/agpl.html] veröffentlicht.

Kleinere Änderungen und das Rebranding stammen von Jonas Marx für ***[The Animal Society](https://animalsociety.de)***.
