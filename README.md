# Progetto Backend Gestione Ordini (Symfony)

Questo è il backend per l'applicazione di gestione degli ordini, sviluppato con Symfony. Fornisce un'API REST per la gestione dei dati degli ordini e dei prodotti ed è pensato per essere eseguito come microservizio Docker.

## Prerequisiti

Prima di iniziare, assicurati di avere installato sul tuo sistema:

* **Git:** Per clonare il repository.
* **Docker:** Per eseguire l'applicazione in container.
* **Docker Compose:** Per orchestrare i container (solitamente incluso con Docker Desktop).

## Installazione e Avvio

Segui questi passaggi per installare ed eseguire il backend:

**1. Clonare il Repository del Backend**

```
git clone https://github.com/devisgiordano/technical_test_be backend
```
entra nella cartella
```
cd backend
```
**2. Costruire e Avviare i Container del Backend**

Dalla directory backend:
```
docker compose up --build -d
```
**3. Installare le Dipendenze PHP (Composer)**

Una volta che i container sono in esecuzione, installa le dipendenze di Composer all'interno del container backend

Entra nel container:
```
docker compose exec php sh
```
installa le dipendenze di Symfony:

```
composer install --prefer-dist --no-progress --no-interaction
```
**4. Eseguire le Migrazioni del Database**

Crea lo schema del database eseguendo le migrazioni:
```
php bin/console doctrine:migrations:migrate --no-interaction
```

A questo punto il backens sarà a disposizione per le chiamate api del frontend, per verificare il corretto funzionamento andare al link http://localhost

## Endpoint API Principali
Il backend espone i seguenti endpoint principali tramite OrderController.php:

 - ```GET /api/orders```: Lista tutti gli ordini (supporta i parametri di query ?orderDate=YYYY-MM-DD e ?q=searchTerm).

 - ```POST /api/orders```: Crea un nuovo ordine.

 - ```GET /api/orders/{id}```: Mostra i dettagli di un ordine specifico.

 - ```PUT /api/orders/{id}```: Aggiorna un ordine esistente.

 - ```DELETE /api/orders/{id}```: Elimina un ordine.