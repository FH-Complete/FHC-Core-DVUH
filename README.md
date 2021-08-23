# FHC-Core-DVUH
Extension to exchange Data between FHComplete and Datenverbund

# Matrikelnummervergabe

GET  fullstudent.xml			implemented
POST matrikelkorrektur.xml		implemented
POST matrikelmeldung.xml		deprecated
GET  matrikelpruefung.xml		implemented
POST matrikelreservierung.xml	implemented
GET pruefebpk.xml				implemented

# Vollteilnehmer Kern

GET  feed.xml					implemented
GET  fehler.xml					working prototype
GET  kontostaende.xml			implemented
GET  stammdaten.xml				implemented
POST stammdaten.xml				implemented
GET  studium.xml				implemented
POST studium.xml				implemented
POST zahlung.xml				implemented

# Vollteilnehmer erweitert

GET  abschluesse.xml			not implemented (check if needed)
POST abschluesse.xml			not implemented (check if needed)
GET  bpk.xml					not implemented (important)
POST ekzanfordern.xml			implemented (important)
HEAD feed.xml					not implemented (important)
GET  feed.xml/{be}				experimental
GET  nachweise.xml				not implemented (check if needed)
POST nachweise.xml				not implemented (check if needed)
GET  pruefungsaktivitaeten.xml	not implemented (check if needed)
POST pruefungsaktivitaeten.xml	not implemented (check if needed)
GET  refundierung.xml			not implemented (check if needed)
POST refundierung.xml			not implemented (check if needed)
GET  stammdatenblock.xml		MOCK-Status 	(not used)
POST stammdatenblock.xml		not implemented (not used)
GET  studentmitbelegung.xml		not implemented (not used)
POST studienberechtigung.xml	not implemented (not used)

# Kennzahlen

GET  kennzahlen.xml				not implemented (nice to have)
GET  rohdaten.xml				not implemented (nice to have)

# Codex

GET  exportBecodes				implemented
GET  exportLaenderCodes			implemented
GET  fehlerliste				implemented
