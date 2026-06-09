<div align="center">

# 🔐 Secure Access

**Un sistema gestionale web per il controllo degli accessi fisici tramite badge e gate di sicurezza.**

![GitHub repo size](https://img.shields.io/github/repo-size/Naotino-bit/Secure_Access?style=for-the-badge&color=blue)
![GitHub last commit](https://img.shields.io/github/last-commit/Naotino-bit/Secure_Access?style=for-the-badge&color=orange)
</div>

---

## 📖 Descrizione del Progetto

**Secure Access** è un'applicazione web sviluppata per gestire, monitorare e tracciare gli accessi fisici all'interno di una struttura aziendale o di un laboratorio.

Il sistema simula e gestisce l'interazione tra utenti (dipendenti e visitatori), badge di vario livello e varchi di sicurezza (Gates) che collegano diversi settori (Sectors). L'obiettivo è fornire un pannello di controllo per registrare le presenze, bloccare accessi non autorizzati o con badge scaduti e gestire le autorizzazioni di ingresso.

### ✨ Caratteristiche Principali
* **Gestione Badge e Sicurezza:** Assegnazione di badge temporanei (per i visitatori) o permanenti, con controlli in tempo reale sui permessi di transito tra settori.
* **Sistema di Autenticazione e Verifica:** Registrazione e Login con hashing delle password e convalida dell'account tramite token inviato via email automatica (tramite **PHPMailer**).
* **Tracciamento Accessi (Log):** Registrazione dettagliata di ogni transito (Es. *GRANTED* o *EXPIRED*) nel database per permettere un auditing completo della struttura.
* **Deploy Containerizzato:** L'ambiente è pronto all'uso grazie a **Docker** e `docker-compose`, che isolano il server web PHP e il database MySQL.

---

## 🛠️ Tecnologie Utilizzate

Il progetto sfrutta le seguenti tecnologie per il backend, la persistenza dei dati e l'infrastruttura:

<div align="center">
  <img src="https://img.shields.io/badge/php-%23777BB4.svg?style=for-the-badge&logo=php&logoColor=white" alt="PHP" />
  <img src="https://img.shields.io/badge/mysql-%2300000F.svg?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL" />
  <img src="https://img.shields.io/badge/docker-%230db7ed.svg?style=for-the-badge&logo=docker&logoColor=white" alt="Docker" />
  <img src="https://img.shields.io/badge/html5-%23E34F26.svg?style=for-the-badge&logo=html5&logoColor=white" alt="HTML5" />
  <img src="https://img.shields.io/badge/css3-%231572B6.svg?style=for-the-badge&logo=css3&logoColor=white" alt="CSS3" />
</div>

---

## 🚀 Guida all'Installazione

Il progetto è strutturato per essere avviato rapidamente tramite Docker, fornendo sia il server web che il database in pochi passaggi, senza complesse configurazioni manuali.

### Prerequisiti
Prima di avviare l'applicazione, verifica di avere sul tuo sistema:
* **Docker** e **Docker Compose** installati.

### Avvio rapido

1. **Clona la repository**:
   ```bash
   git clone [https://github.com/Naotino-bit/Secure_Access.git](https://github.com/Naotino-bit/Secure_Access.git)
   cd Secure_Access
