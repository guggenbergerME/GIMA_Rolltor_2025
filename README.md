# GIMA_Rolltor_2025




## Backup Projekt
Projektpfad

/opt/torsteuerung/
```
tar -czvf rolltor_docker_backup_$(date +%Y%m%d).tar.gz docker-compose.yml www/
```
Datenbank Backup aus Docker
```
docker exec -t rolltor_db mariadb-dump -u root -p rolltor > rolltor_db_backup_$(date +%Y%m%d).sql
```

## Wiederherstellung auf neuem System
🔁 Schritt 1: Projekt entpacken
tar -xzvf rolltor_docker_backup_YYYYMMDD.tar.gz -C /ziel/pfad/

🔁 Schritt 2: Datenbank importieren

Starte zuerst MariaDB:
```
docker compose up -d db
```


Dann importiere dein Backup:
```
docker exec -i rolltor_db mariadb -u root -p rolltor < rolltor_db_backup_YYYYMMDD.sql
```


(Wieder DB-Name und Containername anpassen.)