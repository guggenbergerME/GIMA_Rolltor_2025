/*
 * Copyright (c) 2024/2025 Tobias Guggenberger
 *
 * Automatischer Hardware-Reset alle 2 Stunden über den internen Watchdog.
 * Funktioniert auf Arduino UNO, Nano, Mega (AVR).
 *
 * Erweiterung:
 * - Alle 5 Minuten wird der aktuelle Relaistatus per HTTP-POST an ein PHP-Backend gemeldet.
 * - Als eindeutige Kennung dient die feste IP des Arduino.
 * - Antwort des Servers kann den Soll-Status liefern (Bitmap 4 Zeichen "1010"
 *   oder JSON wie {"desired":"1010"}), woraufhin die Relais synchronisiert werden.
 */

#include <SPI.h>
#include <Ethernet.h>
#include <EthernetUdp.h>
#include <avr/wdt.h>

//**************************************************************************
// Funktionsprototypen
//**************************************************************************
void loop();
void setup();
void sendResponse(const char* msg);
void checkAutoReset();
void maybeReportStatus();
void applyDesiredBitmap(const char* bitmap);
void sendStatusAndSync();

// Hilfsfunktionen fürs Logging
void buildCurrentBitmap(char* out4);
void formatUptime(char* out, size_t outLen);

//**************************************************************************
// Relais-Konfiguration
//**************************************************************************
const int relayPins[4] = {7, 6, 5, 4};  // Relais 1–4 (aktiv LOW)

//**************************************************************************
// Sicherheit (nur für UDP-Kommandos)
//**************************************************************************
const char* PASSWORD = "1234";   // <-- ggf. anpassen

//**************************************************************************
// Buffer für eingehende UDP-Nachrichten
//**************************************************************************
char packetBuffer[100];

//**************************************************************************
// Netzwerk-Konfiguration (feste IP des Arduino = Geräte-ID)
//**************************************************************************
byte mac[] = { 0xDE, 0xAD, 0xBE, 0xEF, 0x00, 0x09 };
IPAddress ip(10, 140, 1, 10);
IPAddress gateway(10, 140, 0, 1);
IPAddress subnet(255, 255, 0, 0);

//************ Backend / PHP-Server-Ziel (ANPASSEN!) ************************
IPAddress SERVER_IP(10, 140, 0, 40);        // <-- PHP-Server-IP
const char* SERVER_HOST = "example.local";  // Host-Header (optional, falls vHost)
const int   SERVER_PORT = 80;               // 80 oder 443 (UNO: i. d. R. kein TLS)
const char* SERVER_PATH = "/api/arduino-relay-status.php"; // PHP-Endpunkt

//**************************************************************************
// UDP-Server-Port (bestehende Logik bleibt erhalten)
//**************************************************************************
const unsigned int localPort = 8888;
EthernetUDP Udp;

//**************************************************************************
// HTTP-Client
//**************************************************************************
EthernetClient httpClient;

//**************************************************************************
// Zeitsteuerung: Auto-Reset + Status-Report
//**************************************************************************
unsigned long lastResetMillis  = 0;
unsigned long lastReportMillis = 0;

const unsigned long RESET_INTERVAL  = 7200000UL;   // 2 Stunden in ms
const unsigned long REPORT_INTERVAL = 60000UL;    // 5 Minuten in ms

//**************************************************************************
// Hilfsfunktionen Relais
//**************************************************************************
inline uint8_t relayIsOn(int idx) {
  // aktiv LOW: LOW = EIN (1), HIGH = AUS (0)
  return (digitalRead(relayPins[idx]) == LOW) ? 1 : 0;
}

inline void relaySetOn(int idx, bool on) {
  digitalWrite(relayPins[idx], on ? LOW : HIGH);
}

//**************************************************************************
// Antwort an UDP-Sender
//**************************************************************************
void sendResponse(const char* msg) {
  Udp.beginPacket(Udp.remoteIP(), Udp.remotePort());
  Udp.write(msg);
  Udp.endPacket();
  Serial.print("Antwort: ");
  Serial.println(msg);
}

//**************************************************************************
// Setup
//**************************************************************************
void setup() {
  // Watchdog sicher deaktivieren bis zur gezielten Verwendung
  wdt_disable();

  Serial.begin(115200);
  Serial.println("Starte UDP Relaissteuerung + HTTP-Status-Reporter...");

  Ethernet.begin(mac, ip, gateway, gateway, subnet);
  delay(1000);

  Udp.begin(localPort);
  Serial.print("UDP Server gestartet auf IP: ");
  Serial.println(Ethernet.localIP());
  Serial.print("Port: ");
  Serial.println(localPort);

  for (int i = 0; i < 4; i++) {
    pinMode(relayPins[i], OUTPUT);
    digitalWrite(relayPins[i], HIGH); // Relais AUS (aktiv LOW)
  }

  lastResetMillis  = millis();
  lastReportMillis = millis() - REPORT_INTERVAL; // sofort nach Start berichten
}

//**************************************************************************
// Loop
//**************************************************************************
void loop() {
  // ---------------- UDP-Befehle (bestehende Logik) ----------------
  int packetSize = Udp.parsePacket();
  if (packetSize) {
    int len = Udp.read(packetBuffer, sizeof(packetBuffer) - 1);
    if (len > 0) packetBuffer[len] = '\0';

    Serial.print("Empfangen: ");
    Serial.println(packetBuffer);

    // Passwort-Prüfung
    char expectedPrefix[32];
    snprintf(expectedPrefix, sizeof(expectedPrefix), "PASS=%s;", PASSWORD);

    if (strncmp(packetBuffer, expectedPrefix, strlen(expectedPrefix)) != 0) {
      Serial.println("Falsches Passwort!");
      sendResponse("Fehler: Falsches Passwort!");
      return;
    }

    // Nur bei korrektem Passwort:
    char* commandPart = packetBuffer + strlen(expectedPrefix);

    if (strstr(commandPart, "ALL=OFF")) {
      for (int i = 0; i < 4; i++) relaySetOn(i, false);
      sendResponse("OK: all off");
    } else {
      for (int i = 0; i < 4; i++) {
        char cmdOn[10];
        char cmdOff[10];
        snprintf(cmdOn,  sizeof(cmdOn),  "R%d=ON",  i + 1);
        snprintf(cmdOff, sizeof(cmdOff), "R%d=OFF", i + 1);

        if (strstr(commandPart, cmdOn)) {
          relaySetOn(i, true);
          String ok = String("OK: R") + (i + 1) + " on";
          sendResponse(ok.c_str());
        } else if (strstr(commandPart, cmdOff)) {
          relaySetOn(i, false);
          String ok = String("OK: R") + (i + 1) + " off";
          sendResponse(ok.c_str());
        }
      }
    }
  }

  // ---------------- Regelmäßig an Server melden & synchronisieren -----
  maybeReportStatus();

  // ---------------- Automatischer Reset via Watchdog -------------------
  checkAutoReset();
}

//**************************************************************************
// Alle 5 Minuten: Status melden + evtl. synchronisieren
//**************************************************************************
void maybeReportStatus() {
  unsigned long now = millis();
  if (now - lastReportMillis >= REPORT_INTERVAL) {
    lastReportMillis = now;
    sendStatusAndSync();
  }
}

//**************************************************************************
// HTTP-POST an PHP-Server: aktuellen Status melden
// und gewünschte Bitmap entgegennehmen (z. B. "1010" oder JSON {"desired":"1010"})
// -> Mit klarer Seriell-Monitor-Meldung vor dem Senden
//**************************************************************************
void sendStatusAndSync() {
  // Aktuelle IP als String
  IPAddress myIP = Ethernet.localIP();
  char ipStr[24];
  snprintf(ipStr, sizeof(ipStr), "%u.%u.%u.%u", myIP[0], myIP[1], myIP[2], myIP[3]);

  // Aktuelle Relais-Bitmap bilden
  char currentBitmap[5] = {0};
  buildCurrentBitmap(currentBitmap);

  // Uptime formatiert
  char uptimeStr[32];
  formatUptime(uptimeStr, sizeof(uptimeStr));

  // >>>>>>>>>>>> Serielle Hinweiszeile vorm Senden <<<<<<<<<<<<<<
  Serial.print(F("[REPORT] Sende Relais-Status: IP="));
  Serial.print(ipStr);
  Serial.print(F(" Bitmap="));
  Serial.print(currentBitmap);
  Serial.print(F(" (Uptime "));
  Serial.print(uptimeStr);
  Serial.println(F(")"));

  // JSON-Payload
  char payload[160];
  int r1 = (currentBitmap[0] == '1') ? 1 : 0;
  int r2 = (currentBitmap[1] == '1') ? 1 : 0;
  int r3 = (currentBitmap[2] == '1') ? 1 : 0;
  int r4 = (currentBitmap[3] == '1') ? 1 : 0;

  snprintf(payload, sizeof(payload),
           "{\"device_ip\":\"%s\",\"r1\":%d,\"r2\":%d,\"r3\":%d,\"r4\":%d}",
           ipStr, r1, r2, r3, r4);

  // HTTP-Anfrage zusammenstellen
  char header[256];
  snprintf(header, sizeof(header),
           "POST %s HTTP/1.1\r\n"
           "Host: %s\r\n"
           "Connection: close\r\n"
           "Content-Type: application/json\r\n"
           "Content-Length: %d\r\n"
           "\r\n",
           SERVER_PATH, SERVER_HOST, (int)strlen(payload));

  Serial.println(F("[HTTP] Verbinde zum Server..."));

  if (!httpClient.connect(SERVER_IP, SERVER_PORT)) {
    Serial.println(F("[HTTP] Verbindung fehlgeschlagen (Status wurde nicht gesendet)"));
    return;
  }

  // Senden
  httpClient.print(header);
  httpClient.print(payload);

  Serial.println(F("[HTTP] Request gesendet"));
  // Serial.println(header);   // bei Bedarf einkommentieren (mehr Daten)
  // Serial.println(payload);

  // Antwort lesen: erst Header bis \r\n\r\n, dann Body
  bool headerDone = false;
  char bodyLine[64];
  int idx = 0;
  unsigned long t0 = millis();

  while (httpClient.connected() || httpClient.available()) {
    if (millis() - t0 > 5000) { // 5s Timeout
      Serial.println(F("[HTTP] Timeout beim Lesen"));
      break;
    }

    if (httpClient.available()) {
      char c = httpClient.read();

      if (!headerDone) {
        // auf Header-Ende warten
        static char last4[4] = {0,0,0,0};
        last4[0] = last4[1]; last4[1] = last4[2]; last4[2] = last4[3]; last4[3] = c;
        if (last4[0]=='\r' && last4[1]=='\n' && last4[2]=='\r' && last4[3]=='\n') {
          headerDone = true;
          idx = 0;
          memset(bodyLine, 0, sizeof(bodyLine));
        }
      } else {
        // Body: wir sammeln maximal die erste „Zeile“ bzw. 63 Zeichen
        if (c == '\n' || c == '\r') {
          // Zeile komplett -> auswerten
          if (idx > 0) {
            bodyLine[idx] = '\0';
            Serial.print(F("[HTTP] Body-Zeile: "));
            Serial.println(bodyLine);

            // 1) Reine Bitmap "1010"
            if (strlen(bodyLine) == 4 &&
                (bodyLine[0]=='0'||bodyLine[0]=='1') &&
                (bodyLine[1]=='0'||bodyLine[1]=='1') &&
                (bodyLine[2]=='0'||bodyLine[2]=='1') &&
                (bodyLine[3]=='0'||bodyLine[3]=='1')) {
              applyDesiredBitmap(bodyLine);
              break; // fertig
            }

            // 2) JSON {"desired":"1010"} – sehr einfache Extraktion ohne Parser
            char* p = strstr(bodyLine, "\"desired\"");
            if (p) {
              p = strchr(p, ':');          // bis zum Doppelpunkt
              if (p) {
                p++;
                while (*p == ' ' || *p == '\t') p++;
                if (*p == '\"') {
                  p++;
                  // jetzt sollten die 4 Zeichen folgen
                  if (p[0] && p[1] && p[2] && p[3] && p[4]=='\"') {
                    char bm[5];
                    bm[0]=p[0]; bm[1]=p[1]; bm[2]=p[2]; bm[3]=p[3]; bm[4]='\0';
                    if ((bm[0]=='0'||bm[0]=='1') &&
                        (bm[1]=='0'||bm[1]=='1') &&
                        (bm[2]=='0'||bm[2]=='1') &&
                        (bm[3]=='0'||bm[3]=='1')) {
                      applyDesiredBitmap(bm);
                      break; // fertig
                    }
                  }
                }
              }
            }
          }
          // Zeilenpuffer zurücksetzen
          idx = 0;
          memset(bodyLine, 0, sizeof(bodyLine));
        } else {
          if (idx < (int)sizeof(bodyLine) - 1) {
            bodyLine[idx++] = c;
          }
        }
      }
    }
  }

  httpClient.stop();
  Serial.println(F("[HTTP] Verbindung geschlossen"));
}

//**************************************************************************
// Bitmap anwenden: '1' = EIN, '0' = AUS (Reihenfolge = R1..R4)
//**************************************************************************
void applyDesiredBitmap(const char* bitmap) {
  Serial.print(F("[SYNC] Soll-Bitmap empfangen: "));
  Serial.println(bitmap);

  for (int i = 0; i < 4; i++) {
    if (bitmap[i] == '1') {
      relaySetOn(i, true);
    } else if (bitmap[i] == '0') {
      relaySetOn(i, false);
    }
  }
}

//**************************************************************************
// Automatischer Watchdog-Reset alle 2 Stunden
//**************************************************************************
void checkAutoReset() {
  if (millis() - lastResetMillis >= RESET_INTERVAL) {
    Serial.println("Automatischer Watchdog-Reset (2h Intervall)...");
    delay(100);
    // Watchdog aktivieren – Timeout 8 Sekunden -> erzwungener Reset
    wdt_enable(WDTO_8S);
    while (true) { /* warten bis Reset */ }
  }
}

//**************************************************************************
// Hilfsfunktionen fürs Logging
//**************************************************************************
void buildCurrentBitmap(char* out4) {
  // out4 muss mind. 5 Bytes groß sein
  out4[0] = relayIsOn(0) ? '1' : '0';
  out4[1] = relayIsOn(1) ? '1' : '0';
  out4[2] = relayIsOn(2) ? '1' : '0';
  out4[3] = relayIsOn(3) ? '1' : '0';
  out4[4] = '\0';
}

void formatUptime(char* out, size_t outLen) {
  unsigned long ms = millis();
  unsigned long s  = ms / 1000UL;
  unsigned int  hh = (unsigned int)(s / 3600UL);
  unsigned int  mm = (unsigned int)((s % 3600UL) / 60UL);
  unsigned int  ss = (unsigned int)(s % 60UL);
  snprintf(out, outLen, "%02u:%02u:%02u", hh, mm, ss);
}
