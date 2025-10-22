/*
 * GIMA Rolltor Steuerung 2025
 * ------------------------------------
 * Copyright (c) 2025 Tobias Guggenberger
 * 
 * Funktionen:
 * - Automatischer Hardware-Reset alle 2 Stunden (Watchdog)
 * - Alle 5 Minuten HTTP-POST an /api/arduino-relay-status.php
 * - Synchronisation von Soll-/Ist-Zustand der Relais
 * - Serielle Debug-Ausgaben für Monitoring
 * 
 * Hardware: Arduino UNO/Nano/Mega mit Ethernet Shield W5100/W5500
 */

#include <SPI.h>
#include <Ethernet.h>
#include <avr/wdt.h>

//**************************************************************************
// Netzwerkkonfiguration
//**************************************************************************
byte mac[] = { 0xDE, 0xAD, 0xBE, 0xEF, 0xFE, 0xED };
IPAddress ip(192, 168, 0, 50);
IPAddress server(192, 168, 0, 100);  // IP deines Webservers

EthernetClient client;

//**************************************************************************
// Zeitsteuerung
//**************************************************************************
unsigned long lastReport = 0;
const unsigned long REPORT_INTERVAL = 5UL * 60UL * 1000UL; // 5 Minuten
unsigned long lastReset = 0;
const unsigned long RESET_INTERVAL = 2UL * 60UL * 60UL * 1000UL; // 2 Stunden

//**************************************************************************
// Relais-Pins (anpassen je nach Hardware)
const int RELAY1 = 4;
const int RELAY2 = 5;
const int RELAY3 = 6;
const int RELAY4 = 7;

//**************************************************************************
// Funktionsprototypen
//**************************************************************************
void setup();
void loop();
void checkAutoReset();
void maybeReportStatus();
String getRelayBitmap();
void applyDesiredBitmap(const char* bitmap);
void sendHttpPost(const String& json);

//**************************************************************************
// SETUP
//**************************************************************************
void setup() {
  Serial.begin(9600);
  Serial.println(F("=== GIMA Rolltor Steuerung startet ==="));

  // Ethernet starten
  Ethernet.begin(mac, ip);
  delay(1000);
  Serial.print(F("IP-Adresse: "));
  Serial.println(Ethernet.localIP());

  // Relaispins initialisieren
  pinMode(RELAY1, OUTPUT);
  pinMode(RELAY2, OUTPUT);
  pinMode(RELAY3, OUTPUT);
  pinMode(RELAY4, OUTPUT);

  // Relais in sicheren Grundzustand (z. B. geschlossen)
  digitalWrite(RELAY1, LOW);
  digitalWrite(RELAY2, LOW);
  digitalWrite(RELAY3, LOW);
  digitalWrite(RELAY4, LOW);

  // Watchdog aktivieren (8 Sekunden, wird im Loop gefüttert)
  wdt_enable(WDTO_8S);

  Serial.println(F("[INFO] Setup abgeschlossen, starte Hauptloop..."));
}

//**************************************************************************
// LOOP
//**************************************************************************
void loop() {
  wdt_reset();           // Watchdog füttern
  checkAutoReset();      // 2h-Autoreset prüfen
  maybeReportStatus();   // Statusmeldung prüfen/senden
}

//**************************************************************************
// Automatischer Reset alle 2 Stunden
//**************************************************************************
void checkAutoReset() {
  if (millis() - lastReset >= RESET_INTERVAL) {
    Serial.println(F("[RESET] Automatischer 2h-Reset ausgelöst."));
    wdt_enable(WDTO_15MS); // Sofortiger Reset
    while (true);           // warten auf Reset
  }
}

//**************************************************************************
// Statusmeldung an Server alle 5 Minuten
//**************************************************************************
void maybeReportStatus() {
  if (millis() - lastReport < REPORT_INTERVAL) return;
  lastReport = millis();

  String currentStatus = getRelayBitmap();
  Serial.print(F("[SEND] Sende Status an Server: "));
  Serial.println(currentStatus);

  // JSON vorbereiten
  String payload = "{\"ip\":\"";
  payload += Ethernet.localIP().toString();
  payload += "\",\"status\":\"";
  payload += currentStatus;
  payload += "\"}";

  sendHttpPost(payload);
}

//**************************************************************************
// HTTP POST mit JSON an Server senden
//**************************************************************************
void sendHttpPost(const String& json) {
  if (client.connect(server, 80)) {
    client.println(F("POST /api/arduino-relay-status.php HTTP/1.1"));
    client.print(F("Host: "));
    client.println(server);
    client.println(F("Content-Type: application/json"));
    client.print(F("Content-Length: "));
    client.println(json.length());
    client.println();
    client.print(json);

    unsigned long timeout = millis();
    while (client.connected() && millis() - timeout < 3000) {
      if (client.available()) {
        String line = client.readStringUntil('\n');
        if (line.indexOf("{\"desired\"") != -1) {
          int start = line.indexOf(":\"") + 2;
          int end = line.indexOf("\"", start);
          String desired = line.substring(start, end);

          Serial.print(F("[RECV] Server-Sollwert: "));
          Serial.println(desired);

          if (desired != getRelayBitmap()) {
            Serial.println(F("[SYNC] Sollzustand weicht ab → Relais anpassen"));
            applyDesiredBitmap(desired.c_str());
          } else {
            Serial.println(F("[INFO] Soll/Ist identisch, keine Aktion"));
          }
          break;
        }
      }
    }
  } else {
    Serial.println(F("[ERROR] Verbindung zum Server fehlgeschlagen."));
  }
  client.stop();
}

//**************************************************************************
// Relaisstatus als Bitmap lesen ("1010")
//**************************************************************************
String getRelayBitmap() {
  String bits = "";
  bits += digitalRead(RELAY1) ? '1' : '0';
  bits += digitalRead(RELAY2) ? '1' : '0';
  bits += digitalRead(RELAY3) ? '1' : '0';
  bits += digitalRead(RELAY4) ? '1' : '0';
  return bits;
}

//**************************************************************************
// Sollzustand anwenden
//**************************************************************************
void applyDesiredBitmap(const char* bitmap) {
  Serial.print(F("[APPLY] Wende Sollzustand an: "));
  Serial.println(bitmap);

  if (strlen(bitmap) < 4) {
    Serial.println(F("[ERROR] Ungültige Bitmap!"));
    return;
  }

  digitalWrite(RELAY1, bitmap[0] == '1' ? HIGH : LOW);
  digitalWrite(RELAY2, bitmap[1] == '1' ? HIGH : LOW);
  digitalWrite(RELAY3, bitmap[2] == '1' ? HIGH : LOW);
  digitalWrite(RELAY4, bitmap[3] == '1' ? HIGH : LOW);
}
