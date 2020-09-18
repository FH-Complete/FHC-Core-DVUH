# FHC-Core-DVUH
Extension to exchange Data between FHComplete and Datenverbund

# Matrikelnummervergabe

GET  0.5/fullstudent.xml			working prototype
POST 0.5/matrikelkorrektur.xml		not implemented
POST 0.5/matrikelmeldung.xml		deprecated
GET  0.5/matrikelpruefung.xml		working prototype
POST 0.5/matrikelreservierung.xml	working prototype
GET 0.5/pruefebpk.xml				not implemented (important)

# Vollteilnehmer Kern

GET  0.5/feed.xml					not implemented (important)
GET  0.5/fehler.xml					working prototype
GET  0.5/kontostaende.xml			working prototype
GET  0.5/stammdaten.xml				working prototype
POST 0.5/stammdaten.xml				working prototype
GET  0.5/studium.xml				working prototype
POST 0.5/studium.xml				failure prototype
POST 0.5/zahlung.xml				working prototype

# Vollteilnehmer erweitert

GET  0.5/abschluesse.xml			not implemented (check if needed)
POST 0.5/abschluesse.xml			not implemented (check if needed)
GET  0.5/bpk.xml					not implemented (important)
POST 0.5/ekzanfordern.xml			not implemented (important)
HEAD 0.5/feed.xml					not implemented (important)
GET  0.5/feed.xml/{be}				experimental
GET  0.5/nachweise.xml				not implemented (check if needed)
POST 0.5/nachweise.xml				not implemented (check if needed)
GET  0.5/pruefungsaktivitaeten.xml	not implemented (check if needed)
POST 0.5/pruefungsaktivitaeten.xml	not implemented (check if needed)
GET  0.5/refundierung.xml			not implemented (check if needed)
POST 0.5/refundierung.xml			not implemented (check if needed)
GET  0.5/stammdatenblock.xml		MOCK-Status 	(not used)
POST 0.5/stammdatenblock.xml		not implemented (not used)
GET  0.5/studentmitbelegung.xml		not implemented (not used)
POST 0.5/studienberechtigung.xml	not implemented (not used)

# Kennzahlen

GET  0.5/kennzahlen.xml				not implemented (nice to have)
GET  0.5/rohdaten.xml				not implemented (nice to have)

# Codex

GET  0.5/exportBecodes				implemented
GET  0.5/exportLaenderCodes			implemented
GET  0.5/fehlerliste				implemented
