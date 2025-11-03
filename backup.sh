#!/bin/bash
# ---------------------------------------------------------
# GIMA Rolltor – Backup Script für Docker & MariaDB
# (c) 2025 Tobias Guggenberger
# ---------------------------------------------------------
# Dieses Script erstellt:
#   1. ein SQL-Dump der rolltor-Datenbank
#   2. ein Archiv der Projektdateien (docker-compose.yml, www/, .env, etc.)
# ---------------------------------------------------------

# ==== EINSTELLUNGEN =====================================
PROJECT_DIR="/srv/rolltor"          # Hauptverzeichnis deines Projekts
BACKUP_DIR="/srv/rolltor_backups"   # Zielordner für Backups
DB_CONTAINER="rolltor_db"         # Name deines DB-Containers (docker ps)
DB_NAME="rolltor"                   # Datenbankname
DB_USER="root"                      # DB Benutzername
DB_PASS="dein_root_passwort"        # DB Passwort (oder aus .env lesen)
# ==========================================================

# Datum
DATE=$(date +%Y-%m-%d_%H-%M)

# Sicherstellen, dass Zielordner existiert
mkdir -p "$BACKUP_DIR"

echo "-------------------------------------------"
echo " Starte Backup für GIMA Rolltor ($DATE)"
echo "-------------------------------------------"

# 1️⃣ Datenbank-Dump erstellen
echo "[1/2] Erstelle Datenbank-Dump..."
docker exec -t $DB_CONTAINER mariadb-dump -u$DB_USER -p$DB_PASS $DB_NAME > "$BACKUP_DIR/${DB_NAME}_backup_${DATE}.sql"
if [ $? -eq 0 ]; then
    echo "✅ Datenbank-Dump erstellt: $BACKUP_DIR/${DB_NAME}_backup_${DATE}.sql"
else
    echo "❌ Fehler beim Erstellen des DB-Dumps!"
    exit 1
fi

# 2️⃣ Projektdateien sichern
echo "[2/2] Erstelle Projekt-Archiv..."
cd "$PROJECT_DIR" || exit 1
tar -czf "$BACKUP_DIR/rolltor_project_${DATE}.tar.gz" docker-compose.yml .env www/ 2>/dev/null
if [ $? -eq 0 ]; then
    echo "✅ Projekt-Archiv erstellt: $BACKUP_DIR/rolltor_project_${DATE}.tar.gz"
else
    echo "❌ Fehler beim Erstellen des Projekt-Archivs!"
    exit 1
fi

# Optional: alte Backups nach 14 Tagen löschen
find "$BACKUP_DIR" -type f -mtime +14 -delete

echo "-------------------------------------------"
echo " 🎉 Backup abgeschlossen!"
echo " Speicherort: $BACKUP_DIR"
echo "-------------------------------------------"
